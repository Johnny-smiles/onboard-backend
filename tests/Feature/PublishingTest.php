<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Photo;
use App\Models\PhotoPublication;
use App\Models\User;
use App\Notifications\PublishingFailedNotification;
use App\Services\PublishService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PublishingTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_creates_publications_for_existing_photos(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->forClient($client)->create();

        $photoOne = Photo::factory()->state([
            'client_id' => $client->id,
            'user_id' => $user->id,
        ])->create();
        $photoTwo = Photo::factory()->state([
            'client_id' => $client->id,
            'user_id' => $user->id,
        ])->create();

        $service = app(PublishService::class);
        $scheduled = now()->addHour()->setMicrosecond(0);

        $ids = $service->queue([$photoOne->id, $photoTwo->id, 999999], 'meta', $scheduled->toIso8601String(), $client->id);

        $this->assertCount(2, $ids);
        $this->assertDatabaseCount('photo_publications', 2);

        $publication = PhotoPublication::first();
        $this->assertSame('queued', $publication->status);
        $this->assertSame('meta', $publication->service);
        $this->assertSame($client->id, $publication->payload['client_id']);
        $this->assertTrue($publication->scheduled_at->equalTo($scheduled));
    }

    public function test_dispatch_due_reschedules_after_failed_publish(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->forClient($client)->create();

        $photo = Photo::factory()->state([
            'client_id' => $client->id,
            'user_id' => $user->id,
        ])->create();

        $publication = PhotoPublication::create([
            'photo_id' => $photo->id,
            'service' => 'meta',
            'status' => 'queued',
            'scheduled_at' => now()->subMinute(),
            'payload' => [
                'client_id' => $client->id,
                'retry_count' => 0,
            ],
        ]);

        $before = now();

        $service = app(PublishService::class);
        $publishedCount = $service->dispatchDue();

        $publication->refresh();

        $this->assertSame(0, $publishedCount);
        $this->assertSame('queued', $publication->status);
        $this->assertSame(1, $publication->payload['retry_count']);
        $this->assertArrayHasKey('last_error', $publication->payload);
        $this->assertTrue($publication->scheduled_at->greaterThan($before));
    }

    public function test_dispatch_due_marks_failed_and_notifies_after_max_retries(): void
    {
        Notification::fake();

        $client = Client::factory()->create();
        $user = User::factory()->forClient($client)->create();

        $photo = Photo::factory()->state([
            'client_id' => $client->id,
            'user_id' => $user->id,
        ])->create();

        $publication = PhotoPublication::create([
            'photo_id' => $photo->id,
            'service' => 'meta',
            'status' => 'queued',
            'scheduled_at' => now()->subMinute(),
            'payload' => [
                'client_id' => $client->id,
                'retry_count' => 4,
            ],
        ]);

        $service = app(PublishService::class);
        $publishedCount = $service->dispatchDue();

        $publication->refresh();

        $this->assertSame(0, $publishedCount);
        $this->assertSame('failed', $publication->status);
        $this->assertSame(5, $publication->payload['retry_count']);
        $this->assertNotEmpty($publication->payload['error'] ?? null);
        $this->assertNotEmpty($publication->error);

        Notification::assertSentTo($user, PublishingFailedNotification::class);
    }
}
