<?php

use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ModuleLibraryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WorkspaceSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('projects', ProjectController::class)->except('destroy');
    Route::patch('projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');

    Route::get('modules', [ModuleLibraryController::class, 'index'])->name('modules.index');
    Route::get('modules/{module}', [ModuleLibraryController::class, 'show'])->name('modules.show');

    Route::get('projects/{project}/assessments/create', [AssessmentController::class, 'create'])->name('assessments.create');
    Route::post('projects/{project}/assessments', [AssessmentController::class, 'store'])->name('assessments.store');
    Route::get('assessments/{assessment}/run', [AssessmentController::class, 'run'])->name('assessments.run');
    Route::post('assessments/{assessment}/submit', [AssessmentController::class, 'submit'])->name('assessments.submit');
    Route::get('assessments/{assessment}/results', [AssessmentController::class, 'results'])->name('assessments.results');
    Route::get('assessments/{assessment}/export/pdf', [ExportController::class, 'assessmentPdf'])->name('assessments.export.pdf');
    Route::post('assessments/{assessment}/share', [ExportController::class, 'createShareLink'])->name('assessments.share');
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
});

// Public invitation show (no auth required — shows invite details before accepting)
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');

// Public shared report (signed URL, no auth required, expires in 30 days)
Route::get('/reports/{assessment}', [ExportController::class, 'sharedReport'])
    ->middleware('signed')
    ->name('reports.shared');

require __DIR__.'/auth.php';
