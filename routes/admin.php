<?php

use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\QuizQuestionController;
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

        Route::patch('/users/{user}/reset-quiz', [UserController::class, 'resetQuizProgress'])->name('users.reset-quiz');

        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
    });
});
