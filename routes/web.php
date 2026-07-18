<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\GeographicUsageController as AdminGeographicUsageController;
use App\Http\Controllers\Admin\ModuleController as AdminModuleController;
use App\Http\Controllers\Admin\ModuleDomainController as AdminModuleDomainController;
use App\Http\Controllers\Admin\ModuleImportController;
use App\Http\Controllers\Admin\ModuleTranslationController;
use App\Http\Controllers\Admin\PlanFeatureController;
use App\Http\Controllers\Admin\PlatformSettingController;
use App\Http\Controllers\Admin\QuestionController as AdminQuestionController;
use App\Http\Controllers\Admin\WorkspaceController as AdminWorkspaceController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FlutterwaveWebhookController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ModuleLibraryController;
use App\Http\Controllers\MultiRespondentAssessmentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectProgressController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\RespondentLinkController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserPreferenceController;
use App\Http\Controllers\WorkspaceSettingsController;
use App\Http\Middleware\EnsurePlatformAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('projects', ProjectController::class)->except('destroy');
    Route::patch('projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
    Route::get('projects/{project}/progress', [ProjectProgressController::class, 'index'])->name('projects.progress');
    Route::get('projects/{project}/compare', [ProjectProgressController::class, 'compare'])->name('projects.compare');

    Route::get('modules', [ModuleLibraryController::class, 'index'])->name('modules.index');
    Route::get('modules/{module}', [ModuleLibraryController::class, 'show'])->name('modules.show');

    Route::get('assessments', [AssessmentController::class, 'index'])->name('assessments.index');
    Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('projects/{project}/assessments/create', [AssessmentController::class, 'create'])->name('assessments.create');
    Route::post('projects/{project}/assessments', [AssessmentController::class, 'store'])->name('assessments.store');
    Route::get('assessments/{assessment}/run', [AssessmentController::class, 'run'])->name('assessments.run');
    Route::post('assessments/{assessment}/submit', [AssessmentController::class, 'submit'])->name('assessments.submit');
    Route::get('assessments/{assessment}/results', [AssessmentController::class, 'results'])->name('assessments.results');
    Route::get('assessments/{assessment}/respondent-collection', [MultiRespondentAssessmentController::class, 'show'])->name('assessments.respondent-collection');
    Route::patch('assessments/{assessment}/respondent-sessions/{responseSession}', [MultiRespondentAssessmentController::class, 'classify'])->name('assessments.respondent-sessions.classify');
    Route::post('assessments/{assessment}/respondent-collection/finalize', [MultiRespondentAssessmentController::class, 'finalize'])->name('assessments.respondent-collection.finalize');
    Route::get('assessments/{assessment}/export/pdf', [ExportController::class, 'assessmentPdf'])->name('assessments.export.pdf');
    Route::post('assessments/{assessment}/share', [ExportController::class, 'createShareLink'])->name('assessments.share');
    Route::delete('assessments/{assessment}/share-links/{shareLink}', [ExportController::class, 'revokeShareLink'])->name('assessments.share.revoke');
    Route::post('assessments/{assessment}/respondent-link', [RespondentLinkController::class, 'store'])->name('assessments.respondent-link');
    Route::delete('assessments/{assessment}/respondent-links/{respondentToken}', [RespondentLinkController::class, 'destroy'])->name('assessments.respondent-link.destroy');
    Route::get('projects/{project}/export/csv', [ExportController::class, 'projectCsv'])->name('projects.export.csv');

    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
    Route::post('/team/invite', [TeamController::class, 'store'])->name('team.invite');
    Route::patch('/team/members/{user}/role', [TeamController::class, 'updateRole'])->name('team.role');
    Route::delete('/team/members/{user}', [TeamController::class, 'destroy'])->name('team.destroy');
    Route::delete('/team/invitations/{invitation}', [TeamController::class, 'cancelInvite'])->name('team.invite.cancel');

    Route::get('/invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::patch('/settings/workspace', [WorkspaceSettingsController::class, 'update'])->name('settings.workspace.update');
    Route::delete('/settings/workspace', [WorkspaceSettingsController::class, 'destroy'])->name('settings.workspace.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');

    Route::post('/preferences/theme', [UserPreferenceController::class, 'setTheme'])->name('preferences.theme');
    Route::post('/locale', [LocaleController::class, 'store'])->name('locale.store');
});

// Platform admin routes
Route::middleware(['auth', EnsurePlatformAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('geographic-usage', [AdminGeographicUsageController::class, 'index'])->name('geographic-usage.index');

    Route::get('plan-features', [PlanFeatureController::class, 'index'])->name('plan-features.index');
    Route::put('plan-features', [PlanFeatureController::class, 'update'])->name('plan-features.update');

    Route::get('workspaces', [AdminWorkspaceController::class, 'index'])->name('workspaces.index');
    Route::get('workspaces/{workspace}', [AdminWorkspaceController::class, 'show'])->name('workspaces.show');

    Route::get('settings', [PlatformSettingController::class, 'index'])->name('settings.index');
    Route::put('settings', [PlatformSettingController::class, 'update'])->name('settings.update');

    Route::get('modules', [AdminModuleController::class, 'index'])->name('modules.index');
    Route::get('modules/import', [ModuleImportController::class, 'create'])->name('modules.import');
    Route::post('modules/import', [ModuleImportController::class, 'store'])->name('modules.import.store');
    Route::get('modules/{module}', [AdminModuleController::class, 'show'])->name('modules.show');
    Route::get('modules/{module}/edit', [AdminModuleController::class, 'edit'])->name('modules.edit');
    Route::put('modules/{module}', [AdminModuleController::class, 'update'])->name('modules.update');
    Route::patch('modules/{module}/toggle', [AdminModuleController::class, 'toggleActive'])->name('modules.toggle');

    Route::put('domains/{domain}', [AdminModuleDomainController::class, 'update'])->name('domains.update');

    Route::put('questions/{question}', [AdminQuestionController::class, 'update'])->name('questions.update');
    Route::patch('questions/{question}/toggle', [AdminQuestionController::class, 'toggleActive'])->name('questions.toggle');

    Route::get('modules/{module}/translations/{locale?}', [ModuleTranslationController::class, 'edit'])->name('modules.translations.edit');
    Route::post('modules/{module}/translations/{locale?}', [ModuleTranslationController::class, 'update'])->name('modules.translations.update');
});

// Payment webhooks (no auth, no CSRF — signatures validated in controllers)
Route::post('/billing/webhook/paystack', [PaystackWebhookController::class, 'handle'])
    ->name('billing.webhook.paystack');

Route::post('/billing/webhook/flutterwave', [FlutterwaveWebhookController::class, 'handle'])
    ->name('billing.webhook.flutterwave');

// Public invitation show (no auth required — shows invite details before accepting)
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');

// Public shared report (signed URL, no auth required, expires in 30 days)
Route::get('/reports/{assessment}', [ExportController::class, 'sharedReport'])
    ->middleware('signed')
    ->name('reports.shared');
Route::get('/shared-reports/{token}', [ExportController::class, 'sharedReportByToken'])
    ->name('reports.shared.token');

// Public respondent runner (token-based, no auth required)
Route::get('/respond/{token}', function (string $token) {
    return view('respondent.run', compact('token'));
})->name('respondent.show');

require __DIR__.'/auth.php';
