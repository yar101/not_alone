<?php

use App\Http\Controllers\ProfileController;
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
    Route::post('/profile/pinned-card',          [UserProfileController::class, 'updatePinnedCard'])->name('profile.pinned-card.update');
    Route::delete('/profile/pinned-card/photo',  [UserProfileController::class, 'deletePinnedPhoto'])->name('profile.pinned-card.delete-photo');
    Route::post('/profile/posts',                [UserProfileController::class, 'storePost'])->name('profile.posts.store');
    Route::delete('/profile/posts/{post}',       [UserProfileController::class, 'destroyPost'])->name('profile.posts.destroy');
});

// Account settings (email, password, delete)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/settings',    [ProfileController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings',  [ProfileController::class, 'update'])->name('settings.update');
    Route::delete('/settings', [ProfileController::class, 'destroy'])->name('settings.destroy');
});

require __DIR__.'/auth.php';
