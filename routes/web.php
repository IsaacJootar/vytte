<?php

use App\Http\Controllers\ModuleLibraryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('projects', ProjectController::class)->except('destroy');
    Route::patch('projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');

    Route::get('modules', [ModuleLibraryController::class, 'index'])->name('modules.index');
    Route::get('modules/{module}', [ModuleLibraryController::class, 'show'])->name('modules.show');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
