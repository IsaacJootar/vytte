<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\AssessmentOversightController as AdminAssessmentOversightController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\CatalogueReleaseController as AdminCatalogueReleaseController;
use App\Http\Controllers\Admin\DomainTaxonomyController as AdminDomainTaxonomyController;
use App\Http\Controllers\Admin\FacilityProfileController as AdminFacilityProfileController;
use App\Http\Controllers\Admin\FrameworkVersionController as AdminFrameworkVersionController;
use App\Http\Controllers\Admin\GeographicUsageController as AdminGeographicUsageController;
use App\Http\Controllers\Admin\ModuleController as AdminModuleController;
use App\Http\Controllers\Admin\OfficialContentController as AdminOfficialContentController;
use App\Http\Controllers\Admin\QuestionGroupController as AdminQuestionGroupController;
use App\Http\Controllers\Admin\ModuleImportController;
use App\Http\Controllers\Admin\ModuleTranslationController;
use App\Http\Controllers\Admin\PlanFeatureController;
use App\Http\Controllers\Admin\PlatformUserController as AdminPlatformUserController;
use App\Http\Controllers\Admin\PlatformSettingController;
use App\Http\Controllers\Admin\QuestionController as AdminQuestionController;
use App\Http\Controllers\Admin\QuestionIdentityController as AdminQuestionIdentityController;
use App\Http\Controllers\Admin\QuestionVersionController as AdminQuestionVersionController;
use App\Http\Controllers\Admin\ReportShareController as AdminReportShareController;
use App\Http\Controllers\Admin\ScoringPolicyController as AdminScoringPolicyController;
use App\Http\Controllers\Admin\WorkspaceController as AdminWorkspaceController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ModuleLibraryController;
use App\Http\Controllers\MultiRespondentAssessmentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectProgressController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\RespondentLinkController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserPreferenceController;
use App\Http\Controllers\WorkspaceCustomAssessmentController;
use App\Http\Controllers\WorkspaceSettingsController;
use App\Http\Middleware\EnsurePlatformAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', HealthController::class)->name('health');

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('projects', ProjectController::class)->except('destroy');
    Route::patch('projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
    Route::get('projects/{project}/progress', [ProjectProgressController::class, 'index'])->name('projects.progress');
    Route::get('projects/{project}/compare', [ProjectProgressController::class, 'compare'])->name('projects.compare');

    Route::get('modules', [ModuleLibraryController::class, 'index'])->name('modules.index');
    Route::get('modules/{module}', [ModuleLibraryController::class, 'show'])->name('modules.show');
    Route::get('custom-assessments', [WorkspaceCustomAssessmentController::class, 'index'])->name('custom-assessments.index');
    Route::get('custom-assessments/create', [WorkspaceCustomAssessmentController::class, 'create'])->name('custom-assessments.create');
    Route::post('custom-assessments', [WorkspaceCustomAssessmentController::class, 'store'])->name('custom-assessments.store');
    Route::get('custom-assessments/{customAssessment}', [WorkspaceCustomAssessmentController::class, 'show'])->name('custom-assessments.show');
    Route::patch('custom-assessments/{customAssessment}/status', [WorkspaceCustomAssessmentController::class, 'updateStatus'])->name('custom-assessments.status');

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

    Route::get('official-content', [AdminOfficialContentController::class, 'index'])->name('official-content.index');

    Route::get('geographic-usage', [AdminGeographicUsageController::class, 'index'])->name('geographic-usage.index');

    Route::get('plan-features', [PlanFeatureController::class, 'index'])->name('plan-features.index');
    Route::put('plan-features', [PlanFeatureController::class, 'update'])->name('plan-features.update');

    Route::get('workspaces', [AdminWorkspaceController::class, 'index'])->name('workspaces.index');
    Route::get('workspaces/{workspace}', [AdminWorkspaceController::class, 'show'])->name('workspaces.show');
    Route::patch('workspaces/{workspace}/status', [AdminWorkspaceController::class, 'updateStatus'])->name('workspaces.status');

    Route::get('platform-users', [AdminPlatformUserController::class, 'index'])->name('platform-users.index');
    Route::patch('platform-users/{user}/role', [AdminPlatformUserController::class, 'updateRole'])->name('platform-users.role');

    Route::get('assessment-oversight', [AdminAssessmentOversightController::class, 'index'])->name('assessment-oversight.index');
    Route::get('report-shares', [AdminReportShareController::class, 'index'])->name('report-shares.index');
    Route::patch('report-shares/{shareLink}/revoke', [AdminReportShareController::class, 'revoke'])->name('report-shares.revoke');
    Route::get('audit-logs', [AdminAuditLogController::class, 'index'])->name('audit-logs.index');

    Route::get('settings', [PlatformSettingController::class, 'index'])->name('settings.index');
    Route::put('settings', [PlatformSettingController::class, 'update'])->name('settings.update');

    Route::get('modules', [AdminModuleController::class, 'index'])->name('modules.index');
    Route::get('modules/import', [ModuleImportController::class, 'create'])->name('modules.import');
    Route::post('modules/import', [ModuleImportController::class, 'store'])->name('modules.import.store');
    Route::get('modules/{module}', [AdminModuleController::class, 'show'])->name('modules.show');
    Route::get('modules/{module}/edit', [AdminModuleController::class, 'edit'])->name('modules.edit');
    Route::put('modules/{module}', [AdminModuleController::class, 'update'])->name('modules.update');
    Route::patch('modules/{module}/toggle', [AdminModuleController::class, 'toggleActive'])->name('modules.toggle');

    Route::get('domain-taxonomies', [AdminDomainTaxonomyController::class, 'index'])->name('domain-taxonomies.index');
    Route::get('domain-taxonomy-versions/{version}', [AdminDomainTaxonomyController::class, 'show'])->name('domain-taxonomies.show');

    Route::get('question-groups', [AdminQuestionGroupController::class, 'index'])->name('question-groups.index');
    Route::get('question-groups/create', [AdminQuestionGroupController::class, 'create'])->name('question-groups.create');
    Route::post('question-groups', [AdminQuestionGroupController::class, 'store'])->name('question-groups.store');
    Route::get('question-groups/{group}', [AdminQuestionGroupController::class, 'show'])->name('question-groups.show');
    Route::get('question-groups/{group}/edit', [AdminQuestionGroupController::class, 'edit'])->name('question-groups.edit');
    Route::put('question-groups/{group}', [AdminQuestionGroupController::class, 'update'])->name('question-groups.update');
    Route::patch('question-groups/{group}/archive', [AdminQuestionGroupController::class, 'archive'])->name('question-groups.archive');

    Route::get('question-identities', [AdminQuestionIdentityController::class, 'index'])->name('question-identities.index');
    Route::get('question-identities/create', [AdminQuestionIdentityController::class, 'create'])->name('question-identities.create');
    Route::post('question-identities', [AdminQuestionIdentityController::class, 'store'])->name('question-identities.store');
    Route::get('question-identities/{question}', [AdminQuestionIdentityController::class, 'show'])->name('question-identities.show');

    Route::get('question-versions', [AdminQuestionVersionController::class, 'index'])->name('question-versions.index');
    Route::get('question-versions/{version}', [AdminQuestionVersionController::class, 'show'])->name('question-versions.show');
    Route::put('question-versions/{version}', [AdminQuestionVersionController::class, 'update'])->name('question-versions.update');
    Route::patch('question-versions/{version}/approve', [AdminQuestionVersionController::class, 'markApproved'])->name('question-versions.approve');
    Route::patch('question-versions/{version}/publish', [AdminQuestionVersionController::class, 'publish'])->name('question-versions.publish');
    Route::post('question-versions/{version}/supersede', [AdminQuestionVersionController::class, 'supersede'])->name('question-versions.supersede');
    Route::patch('question-versions/{version}/archive', [AdminQuestionVersionController::class, 'archive'])->name('question-versions.archive');

    Route::get('framework-versions', [AdminFrameworkVersionController::class, 'index'])->name('framework-versions.index');
    Route::get('framework-versions/create', [AdminFrameworkVersionController::class, 'create'])->name('framework-versions.create');
    Route::post('framework-versions', [AdminFrameworkVersionController::class, 'store'])->name('framework-versions.store');
    Route::get('framework-versions/{framework}', [AdminFrameworkVersionController::class, 'show'])->name('framework-versions.show');
    Route::put('framework-versions/{framework}', [AdminFrameworkVersionController::class, 'update'])->name('framework-versions.update');
    Route::patch('framework-versions/{framework}/publish', [AdminFrameworkVersionController::class, 'publish'])->name('framework-versions.publish');
    Route::post('framework-versions/{framework}/supersede', [AdminFrameworkVersionController::class, 'supersede'])->name('framework-versions.supersede');
    Route::patch('framework-versions/{framework}/archive', [AdminFrameworkVersionController::class, 'archive'])->name('framework-versions.archive');
    Route::post('framework-versions/{framework}/sections', [AdminFrameworkVersionController::class, 'storeSection'])->name('framework-versions.sections.store');
    Route::put('framework-versions/{framework}/sections/{section}', [AdminFrameworkVersionController::class, 'updateSection'])->name('framework-versions.sections.update');
    Route::delete('framework-versions/{framework}/sections/{section}', [AdminFrameworkVersionController::class, 'destroySection'])->name('framework-versions.sections.destroy');
    Route::post('framework-versions/{framework}/indicators', [AdminFrameworkVersionController::class, 'storeIndicator'])->name('framework-versions.indicators.store');
    Route::put('framework-versions/{framework}/indicators/{indicator}', [AdminFrameworkVersionController::class, 'updateIndicator'])->name('framework-versions.indicators.update');
    Route::delete('framework-versions/{framework}/indicators/{indicator}', [AdminFrameworkVersionController::class, 'destroyIndicator'])->name('framework-versions.indicators.destroy');
    Route::post('framework-versions/{framework}/placements', [AdminFrameworkVersionController::class, 'storePlacement'])->name('framework-versions.placements.store');
    Route::delete('framework-versions/{framework}/placements/{placement}', [AdminFrameworkVersionController::class, 'destroyPlacement'])->name('framework-versions.placements.destroy');

    Route::get('catalogue-releases', [AdminCatalogueReleaseController::class, 'index'])->name('catalogue-releases.index');
    Route::get('catalogue-releases/create', [AdminCatalogueReleaseController::class, 'create'])->name('catalogue-releases.create');
    Route::post('catalogue-releases', [AdminCatalogueReleaseController::class, 'store'])->name('catalogue-releases.store');
    Route::get('catalogue-releases/{release}', [AdminCatalogueReleaseController::class, 'show'])->name('catalogue-releases.show');
    Route::put('catalogue-releases/{release}', [AdminCatalogueReleaseController::class, 'update'])->name('catalogue-releases.update');
    Route::post('catalogue-releases/{release}/frameworks', [AdminCatalogueReleaseController::class, 'attachFramework'])->name('catalogue-releases.frameworks.attach');
    Route::delete('catalogue-releases/{release}/frameworks/{framework}', [AdminCatalogueReleaseController::class, 'detachFramework'])->name('catalogue-releases.frameworks.detach');
    Route::patch('catalogue-releases/{release}/publish', [AdminCatalogueReleaseController::class, 'publish'])->name('catalogue-releases.publish');
    Route::post('catalogue-releases/{release}/supersede', [AdminCatalogueReleaseController::class, 'supersede'])->name('catalogue-releases.supersede');
    Route::patch('catalogue-releases/{release}/archive', [AdminCatalogueReleaseController::class, 'archive'])->name('catalogue-releases.archive');

    Route::get('facility-profiles', [AdminFacilityProfileController::class, 'index'])->name('facility-profiles.index');
    Route::get('facility-profiles/{profile}', [AdminFacilityProfileController::class, 'show'])->name('facility-profiles.show');
    Route::put('facility-profiles/{profile}', [AdminFacilityProfileController::class, 'update'])->name('facility-profiles.update');

    Route::get('scoring-policies', [AdminScoringPolicyController::class, 'index'])->name('scoring-policies.index');

    Route::put('questions/{question}', [AdminQuestionController::class, 'update'])->name('questions.update');
    Route::patch('questions/{question}/toggle', [AdminQuestionController::class, 'toggleActive'])->name('questions.toggle');

    Route::get('modules/{module}/translations/{locale?}', [ModuleTranslationController::class, 'edit'])->name('modules.translations.edit');
    Route::post('modules/{module}/translations/{locale?}', [ModuleTranslationController::class, 'update'])->name('modules.translations.update');
});

// Public invitation show (no auth required — shows invite details before accepting)
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');

// Public shared report (signed URL, no auth required, expires in 30 days)
Route::get('/reports/{assessment}', [ExportController::class, 'sharedReport'])
    ->middleware('signed')
    ->name('reports.shared');
Route::get('/shared-reports/{token}', [ExportController::class, 'sharedReportByToken'])
    ->middleware('throttle:60,1')
    ->name('reports.shared.token');

// Public respondent runner (token-based, no auth required)
Route::get('/respond/{token}', function (string $token) {
    return view('respondent.run', compact('token'));
})->middleware('throttle:30,1')->name('respondent.show');

require __DIR__.'/auth.php';
