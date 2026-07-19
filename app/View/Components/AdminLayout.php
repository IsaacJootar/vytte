<?php

namespace App\View\Components;

use App\Support\RoleNavigation;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Platform Admin renders the same shell as every other role.
 *
 * This component exists only to supply the platform navigation, so the existing admin views
 * keep working unchanged. There is no separate admin chrome: spacing, mobile behaviour,
 * theme handling and focus states all come from layouts.app.
 */
class AdminLayout extends Component
{
    public function __construct(public ?string $title = null) {}

    public function render(): View
    {
        return view('layouts.app', ['nav' => RoleNavigation::platform()]);
    }
}
