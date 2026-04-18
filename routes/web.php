<?php

use App\Http\Controllers\ConnectionTestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectRulesController;
use App\Http\Controllers\ProjectsController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/setup')->name('home');

Route::get('/setup', [SetupController::class, 'index'])->name('setup');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/projects', [ProjectsController::class, 'index'])->name('projects.index');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');

    Route::prefix('connection-test')->name('connection-test.')->group(function () {
        Route::post('github', [ConnectionTestController::class, 'github'])->name('github');
        Route::post('slack', [ConnectionTestController::class, 'slack'])->name('slack');
        Route::post('jira', [ConnectionTestController::class, 'jira'])->name('jira');
        Route::post('all', [ConnectionTestController::class, 'all'])->name('all');
    });

    Route::patch('/projects/{project}/rules', [ProjectRulesController::class, 'update'])->name('projects.rules.update');
});
