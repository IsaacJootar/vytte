<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformSettingController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.index', [
            'emailEnabled' => PlatformSetting::get('email.notifications_enabled', false),
            'linkExpiryDays' => (int) PlatformSetting::get('sharing.link_expiry_days', 30),
            'paystackEnabled' => PlatformSetting::get('payment.paystack_enabled', true),
            'flutterwaveEnabled' => PlatformSetting::get('payment.flutterwave_enabled', false),
            'freePlanProjects' => (int) PlatformSetting::get('plan.free_projects', 1),
            'freePlanAssessments' => (int) PlatformSetting::get('plan.free_assessments_per_project', 3),
            'proPlanProjects' => (int) PlatformSetting::get('plan.pro_projects', 10),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'link_expiry_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'free_plan_projects' => ['nullable', 'integer', 'min:1', 'max:100'],
            'free_plan_assessments' => ['nullable', 'integer', 'min:1', 'max:100'],
            'pro_plan_projects' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        PlatformSetting::set(
            'email.notifications_enabled',
            $request->boolean('email_notifications_enabled'),
            'boolean'
        );

        PlatformSetting::set(
            'sharing.link_expiry_days',
            (int) $request->input('link_expiry_days', 30),
            'integer'
        );

        PlatformSetting::set(
            'payment.paystack_enabled',
            $request->boolean('paystack_enabled'),
            'boolean'
        );

        PlatformSetting::set(
            'payment.flutterwave_enabled',
            $request->boolean('flutterwave_enabled'),
            'boolean'
        );

        PlatformSetting::set(
            'plan.free_projects',
            (int) $request->input('free_plan_projects', 1),
            'integer'
        );

        PlatformSetting::set(
            'plan.free_assessments_per_project',
            (int) $request->input('free_plan_assessments', 3),
            'integer'
        );

        PlatformSetting::set(
            'plan.pro_projects',
            (int) $request->input('pro_plan_projects', 10),
            'integer'
        );

        return back()->with('success', 'Platform settings saved.');
    }
}
