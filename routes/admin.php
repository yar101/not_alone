<?php

use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\IdolRatingController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\PlatformSettingsController;
use App\Http\Controllers\Admin\QuizQuestionController;
use App\Http\Controllers\Admin\ServiceCategoryController;
use App\Http\Controllers\Admin\ServicePriceLimitController;
use App\Http\Controllers\Admin\ServiceTimeUnitController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    // Auth (guest admin)
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Protected admin routes
    Route::middleware('admin')->group(function () {
        Route::get('/', fn() => redirect()->route('admin.applications.index'));

        Route::prefix('applications')->name('applications.')->group(function () {
            Route::get('/', [ApplicationController::class, 'index'])->name('index');
            Route::get('/{application}', [ApplicationController::class, 'show'])->name('show');
            Route::patch('/{application}/approve', [ApplicationController::class, 'approve'])->name('approve');
            Route::patch('/{application}/reject', [ApplicationController::class, 'reject'])->name('reject');
        });

        Route::prefix('quiz')->name('quiz.')->group(function () {
            Route::get('/questions', [QuizQuestionController::class, 'index'])->name('questions.index');
            Route::post('/questions', [QuizQuestionController::class, 'store'])->name('questions.store');
            Route::patch('/questions/{question}', [QuizQuestionController::class, 'update'])->name('questions.update');
            Route::delete('/questions/{question}', [QuizQuestionController::class, 'destroy'])->name('questions.destroy');
        });

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/search', [UserController::class, 'search'])->name('users.search');
        Route::get('/idols', [UserController::class, 'idols'])->name('idols.index');
        Route::patch('/users/{user}/cooldown', [UserController::class, 'updateCooldown'])->name('users.cooldown.update');
        Route::delete('/users/{user}/cooldown', [UserController::class, 'clearCooldown'])->name('users.cooldown.clear');
        Route::patch('/users/{user}/reset-quiz', [UserController::class, 'resetQuizProgress'])->name('users.reset-quiz');

        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');

        // Services management
        Route::prefix('services')->name('services.')->group(function () {
            Route::get('/categories',                            [ServiceCategoryController::class, 'index'])->name('categories.index');
            Route::post('/categories',                           [ServiceCategoryController::class, 'store'])->name('categories.store');
            Route::patch('/categories/{category}',              [ServiceCategoryController::class, 'update'])->name('categories.update');
            Route::delete('/categories/{category}',             [ServiceCategoryController::class, 'destroy'])->name('categories.destroy');

            Route::get('/time-units',                            [ServiceTimeUnitController::class, 'index'])->name('time-units.index');
            Route::post('/time-units',                           [ServiceTimeUnitController::class, 'store'])->name('time-units.store');
            Route::patch('/time-units/{timeUnit}',              [ServiceTimeUnitController::class, 'update'])->name('time-units.update');
            Route::delete('/time-units/{timeUnit}',             [ServiceTimeUnitController::class, 'destroy'])->name('time-units.destroy');

            Route::get('/price-limits',                          [ServicePriceLimitController::class, 'index'])->name('price-limits.index');
            Route::post('/price-limits',                         [ServicePriceLimitController::class, 'store'])->name('price-limits.store');
            Route::patch('/price-limits/{priceLimit}',          [ServicePriceLimitController::class, 'update'])->name('price-limits.update');
            Route::delete('/price-limits/{priceLimit}',         [ServicePriceLimitController::class, 'destroy'])->name('price-limits.destroy');
        });

        // Platform settings
        Route::get('/settings',  [PlatformSettingsController::class, 'index'])->name('settings.index');
        Route::patch('/settings', [PlatformSettingsController::class, 'update'])->name('settings.update');

        // Idol rating (manual adjustment)
        Route::patch('/users/{user}/rating', [IdolRatingController::class, 'update'])->name('users.rating.update');
    });
});
