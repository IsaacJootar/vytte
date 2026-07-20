<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PlatformHealthService;
use Illuminate\Contracts\View\View;

class MonitoringController extends Controller
{
    public function index(PlatformHealthService $health): View
    {
        $checks = $health->checks();

        return view('admin.monitoring.index', [
            'checks' => $checks,
            'needsAttention' => array_values(array_filter($checks, fn ($c) => in_array($c['status'], ['warn', 'down'], true))),
            'recentFailures' => $health->recentFailures(),
        ]);
    }
}
