<?php

use App\Http\Controllers\Idol\ApplicationController as IdolApplicationController;
use App\Http\Controllers\Idol\QuizController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServiceController;

use App\Http\Controllers\UserProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Public profile page
Route::get('/users/{user}', [UserProfileController::class, 'show'])->name('profile.show');

// /profile redirects to own profile
Route::get('/profile', function () {
    return redirect()->route('profile.show', ['user' => auth()->id()]);
})->middleware('auth')->name('profile');

// Profile editing endpoints (owner only)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::patch('/profile/about',      [UserProfileController::class, 'updateAbout'])->name('profile.update.about');
    Route::patch('/profile/traits',     [UserProfileController::class, 'updateTraits'])->name('profile.update.traits');
    Route::patch('/profile/interests',  [UserProfileController::class, 'updateInterests'])->name('profile.update.interests');
    Route::patch('/profile/languages',  [UserProfileController::class, 'updateLanguages'])->name('profile.update.languages');
    Route::patch('/profile/timezone',   [UserProfileController::class, 'updateTimezone'])->name('profile.update.timezone');
    Route::patch('/profile/gender',     [UserProfileController::class, 'updateGender'])->name('profile.update.gender');
    Route::patch('/profile/birth-date', [UserProfileController::class, 'updateBirthDate'])->name('profile.update.birth-date');
    Route::patch('/profile/header',     [UserProfileController::class, 'updateHeader'])->name('profile.update.header');
    Route::post('/profile/voice',                [UserProfileController::class, 'updateVoice'])->name('profile.update.voice');
    Route::delete('/profile/voice',              [UserProfileController::class, 'deleteVoice'])->name('profile.delete.voice');
    Route::patch('/profile/checklist',           [UserProfileController::class, 'updateChecklist'])->name('profile.update.checklist');
    Route::post('/profile/avatar',               [UserProfileController::class, 'updateAvatar'])->name('profile.update.avatar');
    Route::delete('/profile/avatar',             [UserProfileController::class, 'deleteAvatar'])->name('profile.delete.avatar');
    Route::post('/profile/posts',                [UserProfileController::class, 'storePost'])->name('profile.posts.store');
    Route::delete('/profile/posts/{post}',       [UserProfileController::class, 'destroyPost'])->name('profile.posts.destroy');
});

// Account settings (email, password, delete)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/settings',    [ProfileController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings',  [ProfileController::class, 'update'])->name('settings.update');
    Route::delete('/settings', [ProfileController::class, 'destroy'])->name('settings.destroy');
});

// Idol routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/idol/apply', [IdolApplicationController::class, 'show'])->name('idol.apply');
    Route::post('/idol/apply', [IdolApplicationController::class, 'store'])->name('idol.apply.store');
    Route::post('/idol/quiz/start', [QuizController::class, 'start'])->name('idol.quiz.start');
    Route::post('/idol/quiz/answer', [QuizController::class, 'answer'])->name('idol.quiz.answer');
});

// Services (idol owner)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/profile/services',                [ServiceController::class, 'store'])->name('profile.services.store');
    Route::patch('/profile/services/{service}',     [ServiceController::class, 'update'])->name('profile.services.update');
    Route::delete('/profile/services/{service}',    [ServiceController::class, 'destroy'])->name('profile.services.destroy');
});

// Notification routes
Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('/notifications/service', [NotificationController::class, 'service'])->name('notifications.service');
    Route::patch('/notifications/service/read-all', [NotificationController::class, 'markAllServiceRead'])->name('notifications.service.read-all');
    Route::patch('/broadcasts/{id}/read', [NotificationController::class, 'markBroadcastRead'])->name('broadcasts.read');
});

require __DIR__.'/auth.php';
