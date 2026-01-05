<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    /**
     * Health check endpoint for monitoring
     */
    public function check()
    {
        $checks = [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'queue' => $this->checkQueue(),
                'storage' => $this->checkStorage(),
            ],
        ];

        // Overall health status
        $allHealthy = collect($checks['checks'])->every(fn($check) => $check['status'] === 'ok');
        $checks['status'] = $allHealthy ? 'ok' : 'degraded';

        $statusCode = $allHealthy ? 200 : 503;

        return response()->json($checks, $statusCode);
    }

    /**
     * Detailed system status (admin only)
     */
    public function status()
    {
        return response()->json([
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
                'debug' => config('app.debug'),
                'url' => config('app.url'),
            ],
            'database' => [
                'connection' => config('database.default'),
                'status' => $this->checkDatabase()['status'],
            ],
            'cache' => [
                'driver' => config('cache.default'),
                'status' => $this->checkCache()['status'],
            ],
            'queue' => [
                'connection' => config('queue.default'),
                'pending_jobs' => Queue::size(),
                'failed_jobs' => DB::table('failed_jobs')->count(),
            ],
            'storage' => [
                'disk' => config('filesystems.default'),
                'status' => $this->checkStorage()['status'],
            ],
            'integrations' => [
                'total' => \App\Models\SocialIntegration::count(),
                'active' => \App\Models\SocialIntegration::where('status', 'active')->count(),
                'errors' => \App\Models\SocialIntegration::where('status', 'error')->count(),
            ],
            'publications' => [
                'queued' => \App\Models\PhotoPublication::where('status', 'queued')->count(),
                'published_today' => \App\Models\PhotoPublication::where('status', 'published')
                    ->whereDate('published_at', today())->count(),
            ],
        ]);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            DB::connection()->getDatabaseName();

            return [
                'status' => 'ok',
                'message' => 'Database connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'health_check_' . time();
            Cache::put($key, 'test', 10);
            $value = Cache::get($key);
            Cache::forget($key);

            if ($value === 'test') {
                return [
                    'status' => 'ok',
                    'message' => 'Cache working correctly',
                ];
            }

            return [
                'status' => 'error',
                'message' => 'Cache read/write failed',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Cache check failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkQueue(): array
    {
        try {
            $size = Queue::size();
            $failedCount = DB::table('failed_jobs')->count();

            // Warn if queue is backing up
            if ($size > 100) {
                return [
                    'status' => 'warning',
                    'message' => 'Queue size is high',
                    'pending' => $size,
                    'failed' => $failedCount,
                ];
            }

            return [
                'status' => 'ok',
                'message' => 'Queue healthy',
                'pending' => $size,
                'failed' => $failedCount,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Queue check failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkStorage(): array
    {
        try {
            $disk = Storage::disk(config('filesystems.default'));

            // Try to write a test file
            $testFile = 'health-check-' . time() . '.txt';
            $disk->put($testFile, 'health check');

            // Try to read it
            $content = $disk->get($testFile);

            // Delete it
            $disk->delete($testFile);

            if ($content === 'health check') {
                return [
                    'status' => 'ok',
                    'message' => 'Storage working correctly',
                    'disk' => config('filesystems.default'),
                ];
            }

            return [
                'status' => 'error',
                'message' => 'Storage read/write failed',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Storage check failed',
                'error' => $e->getMessage(),
            ];
        }
    }
}
