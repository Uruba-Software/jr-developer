<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ConnectionTestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectRulesController;
use App\Http\Controllers\ProjectsController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Setup (public — needs to be accessible before first login)
Route::get('/setup', [SetupController::class, 'index'])->name('setup');

// Connection tests (public during setup flow)
Route::prefix('connection-test')->name('connection-test.')->group(function () {
    Route::post('github', [ConnectionTestController::class, 'github'])->name('github');
    Route::post('slack', [ConnectionTestController::class, 'slack'])->name('slack');
    Route::post('jira', [ConnectionTestController::class, 'jira'])->name('jira');
    Route::post('all', [ConnectionTestController::class, 'all'])->name('all');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/projects', [ProjectsController::class, 'index'])->name('projects.index');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::patch('/projects/{project}/rules', [ProjectRulesController::class, 'update'])->name('projects.rules.update');
});
