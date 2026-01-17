<?php

namespace Tests\Feature;

use App\Models\CaptureReminder;
use App\Models\Client;
use App\Services\CaptureReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CaptureReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_due_reminders_updates_schedule_and_status(): void
    {
        $now = Carbon::parse('2025-01-01 10:00:00');
        Carbon::setTestNow($now);

        $client = Client::factory()->create();

        $sendAt = $now->copy()->subHour();
        $expectedNext = $sendAt->copy()->addDay();

        $dailyReminder = CaptureReminder::create([
            'client_id' => $client->id,
            'title' => 'Daily reminder',
            'message' => 'Time to capture',
            'channel' => 'email',
            'target' => 'ops@example.test',
            'send_at' => $sendAt,
            'repeat_interval' => 'daily',
            'is_active' => true,
        ]);

        $oneOffReminder = CaptureReminder::create([
            'client_id' => $client->id,
            'title' => 'One-off reminder',
            'message' => 'Single send',
            'channel' => 'email',
            'target' => 'ops@example.test',
            'send_at' => $now->copy()->subMinutes(30),
            'repeat_interval' => null,
            'is_active' => true,
        ]);

        $service = app(CaptureReminderService::class);
        $processed = $service->processDueReminders();

        $dailyReminder->refresh();
        $oneOffReminder->refresh();

        $this->assertSame(2, $processed);
        $this->assertTrue($dailyReminder->last_sent_at->equalTo($now));
        $this->assertTrue($dailyReminder->send_at->equalTo($expectedNext));
        $this->assertTrue($oneOffReminder->last_sent_at->equalTo($now));
        $this->assertFalse($oneOffReminder->is_active);

        Carbon::setTestNow();
    }
}
