<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'app' => true,
            'database' => $this->databaseIsReady(),
            'queue' => config('queue.default') !== null,
            'mail' => config('mail.default') !== null,
        ];

        $healthy = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    private function databaseIsReady(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
