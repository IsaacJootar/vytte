<?php

namespace App\View\Components;

use App\Support\RoleNavigation;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    public function __construct(public readonly string $title = '') {}

    public function render(): View
    {
        return view('layouts.app', ['nav' => RoleNavigation::workspace()]);
    }
}
