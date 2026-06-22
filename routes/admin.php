<?php

use App\Http\Controllers\Admin\AdminLogController;
use App\Http\Controllers\Admin\ContentPackChangeRequestController;
use App\Http\Controllers\Admin\ContentPackModerationController;
use App\Http\Controllers\Admin\RatingLogController;
use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\BanReasonController;
use App\Http\Controllers\Admin\ChatBlockController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\IdolRatingController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\PlatformSettingsController;
use App\Http\Controllers\Admin\QuizQuestionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\InterestCategoryController;
use App\Http\Controllers\Admin\InterestController;
use App\Http\Controllers\Admin\InterestSuggestionController;
use App\Http\Controllers\Admin\PersonalityTraitController;
use App\Http\Controllers\Admin\TraitSuggestionController;
use App\Http\Controllers\Admin\ServiceCategoryController;
use App\Http\Controllers\Admin\ServiceChangeRequestController;
use App\Http\Controllers\Admin\ServiceModerationController;
use App\Http\Controllers\Admin\ServicePriceLimitController;
use App\Http\Controllers\Admin\ServiceTimeUnitController;
use App\Http\Controllers\Admin\NewsController;
use App\Http\Controllers\Admin\ConversationController;
use App\Http\Controllers\Admin\DisputeController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ReviewDisputeController;
use App\Http\Controllers\Admin\ReviewEpithetController;
use App\Http\Controllers\Admin\SupportChatController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\HelpCategoryController;
use App\Http\Controllers\Admin\HelpArticleController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    // Auth (guest admin)
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Protected admin routes
    Route::middleware('admin')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

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
            Route::get('/questions/export', [QuizQuestionController::class, 'exportQuestions'])->name('questions.export');
            Route::get('/questions/template', [QuizQuestionController::class, 'downloadTemplate'])->name('questions.template');
            Route::post('/questions/import', [QuizQuestionController::class, 'importQuestions'])->name('questions.import');

            // Article versioning
            Route::get('/article', [QuizQuestionController::class, 'showArticle'])->name('article.index');
            Route::post('/article/versions', [QuizQuestionController::class, 'storeVersion'])->name('article.versions.store');
            Route::get('/article/versions/{version}', [QuizQuestionController::class, 'getVersion'])->name('article.versions.show');
            Route::patch('/article/versions/{version}/activate', [QuizQuestionController::class, 'activateVersion'])->name('article.versions.activate');
            Route::delete('/article/versions/{version}', [QuizQuestionController::class, 'destroyVersion'])->name('article.versions.destroy');
            Route::get('/article/versions/{version}/diff/{other}', [QuizQuestionController::class, 'diffVersions'])->name('article.versions.diff');
            Route::get('/article/export', [QuizQuestionController::class, 'exportArticle'])->name('article.export');
            Route::patch('/article/active', [QuizQuestionController::class, 'updateActiveVersion'])->name('article.active.update');
            Route::post('/article/import', [QuizQuestionController::class, 'importArticle'])->name('article.import');
        });

        // Users — static routes before parameterized
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/search', [UserController::class, 'search'])->name('users.search');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::patch('/users/{user}/cooldown', [UserController::class, 'updateCooldown'])->name('users.cooldown.update');
        Route::delete('/users/{user}/cooldown', [UserController::class, 'clearCooldown'])->name('users.cooldown.clear');
        Route::patch('/users/{user}/reset-quiz', [UserController::class, 'resetQuizProgress'])->name('users.reset-quiz');
        Route::post('/users/{user}/ban', [UserController::class, 'ban'])->name('users.ban');
        Route::delete('/users/{user}/ban', [UserController::class, 'unban'])->name('users.unban');

        // Idol rating (manual adjustment)
        Route::patch('/users/{user}/rating', [IdolRatingController::class, 'update'])->name('users.rating.update');

        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');

        // Content pack moderation
        Route::prefix('content-packs')->name('content-packs.')->group(function () {
            Route::get('/', [ContentPackModerationController::class, 'index'])->name('index');
            // Change requests (static prefix must come before /{pack})
            Route::prefix('change-requests')->name('change-requests.')->group(function () {
                Route::get('/',                              [ContentPackChangeRequestController::class, 'index'])->name('index');
                Route::get('/{changeRequest}',               [ContentPackChangeRequestController::class, 'show'])->name('show');
                Route::post('/{changeRequest}/decide',       [ContentPackChangeRequestController::class, 'decide'])->name('decide');
            });
            Route::get('/{pack}', [ContentPackModerationController::class, 'show'])->name('show');
            Route::post('/{pack}/decide', [ContentPackModerationController::class, 'decide'])->name('decide');
        });

        // Services management
        Route::prefix('services')->name('services.')->group(function () {
            // Static routes first
            Route::get('/moderation', [ServiceModerationController::class, 'index'])->name('moderation.index');
            Route::get('/moderation/{service}', [ServiceModerationController::class, 'show'])->name('moderation.show');
            Route::post('/moderation/{service}/decide', [ServiceModerationController::class, 'decide'])->name('moderation.decide');
            
            // Change requests
            Route::prefix('change-requests')->name('change-requests.')->group(function () {
                Route::get('/', [ServiceChangeRequestController::class, 'index'])->name('index');
                Route::get('/{changeRequest}', [ServiceChangeRequestController::class, 'show'])->name('show');
                Route::post('/{changeRequest}/decide', [ServiceChangeRequestController::class, 'decide'])->name('decide');
            });

            Route::patch('/{service}/approve', [ServiceModerationController::class, 'approve'])->name('moderation.approve');
            Route::patch('/{service}/reject', [ServiceModerationController::class, 'reject'])->name('moderation.reject');
            Route::post('/bulk-approve', [ServiceModerationController::class, 'bulkApprove'])->name('moderation.bulk-approve');
            Route::post('/bulk-reject', [ServiceModerationController::class, 'bulkReject'])->name('moderation.bulk-reject');

            Route::get('/categories', [ServiceCategoryController::class, 'index'])->name('categories.index');
            Route::post('/categories', [ServiceCategoryController::class, 'store'])->name('categories.store');
            Route::post('/categories/reorder', [ServiceCategoryController::class, 'reorder'])->name('categories.reorder');
            Route::patch('/categories/{category}', [ServiceCategoryController::class, 'update'])->name('categories.update');
            Route::delete('/categories/{category}', [ServiceCategoryController::class, 'destroy'])->name('categories.destroy');

            Route::get('/time-units', [ServiceTimeUnitController::class, 'index'])->name('time-units.index');
            Route::post('/time-units', [ServiceTimeUnitController::class, 'store'])->name('time-units.store');
            Route::patch('/time-units/{timeUnit}', [ServiceTimeUnitController::class, 'update'])->name('time-units.update');
            Route::delete('/time-units/{timeUnit}', [ServiceTimeUnitController::class, 'destroy'])->name('time-units.destroy');

            Route::get('/price-limits', [ServicePriceLimitController::class, 'index'])->name('price-limits.index');
            Route::post('/price-limits', [ServicePriceLimitController::class, 'store'])->name('price-limits.store');
            Route::patch('/price-limits/{priceLimit}', [ServicePriceLimitController::class, 'update'])->name('price-limits.update');
            Route::delete('/price-limits/{priceLimit}', [ServicePriceLimitController::class, 'destroy'])->name('price-limits.destroy');
        });

        // Platform settings
        Route::get('/settings', [PlatformSettingsController::class, 'index'])->name('settings.index');
        Route::patch('/settings', [PlatformSettingsController::class, 'update'])->name('settings.update');

        // Logs
        Route::get('/logs', [AdminLogController::class, 'index'])->name('logs.index');
        Route::get('/rating-logs', [RatingLogController::class, 'index'])->name('rating-logs.index');

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('/{report}', [ReportController::class, 'show'])->name('show');
            Route::patch('/{report}/review', [ReportController::class, 'review'])->name('review');
            Route::patch('/{report}/dismiss', [ReportController::class, 'dismiss'])->name('dismiss');
        });

        // Traits (CRUD + suggestions)
        Route::prefix('traits')->name('traits.')->group(function () {
            Route::get('/', [PersonalityTraitController::class, 'index'])->name('index');
            Route::post('/', [PersonalityTraitController::class, 'store'])->name('store');
Route::patch('/{trait}', [PersonalityTraitController::class, 'update'])->name('update');
            Route::delete('/{trait}', [PersonalityTraitController::class, 'destroy'])->name('destroy');

            Route::prefix('suggestions')->name('suggestions.')->group(function () {
                Route::get('/', [TraitSuggestionController::class, 'index'])->name('index');
                Route::patch('/{traitSuggestion}/approve', [TraitSuggestionController::class, 'approve'])->name('approve');
                Route::patch('/{traitSuggestion}/reject', [TraitSuggestionController::class, 'reject'])->name('reject');
                Route::patch('/{traitSuggestion}/implement', [TraitSuggestionController::class, 'implement'])->name('implement');
                Route::patch('/{traitSuggestion}/reopen', [TraitSuggestionController::class, 'reopen'])->name('reopen');
                Route::delete('/{traitSuggestion}', [TraitSuggestionController::class, 'destroy'])->name('destroy');
            });
        });

        // Interests (CRUD + suggestions)
        Route::prefix('interests')->name('interests.')->group(function () {
            Route::get('/', [InterestController::class, 'index'])->name('index');
            Route::post('/', [InterestController::class, 'store'])->name('store');
            Route::patch('/{interest}', [InterestController::class, 'update'])->name('update');
            Route::delete('/{interest}', [InterestController::class, 'destroy'])->name('destroy');

            Route::post('/categories', [InterestCategoryController::class, 'store'])->name('categories.store');
            Route::patch('/categories/{interestCategory}', [InterestCategoryController::class, 'update'])->name('categories.update');
            Route::delete('/categories/{interestCategory}', [InterestCategoryController::class, 'destroy'])->name('categories.destroy');

            Route::prefix('suggestions')->name('suggestions.')->group(function () {
                Route::get('/', [InterestSuggestionController::class, 'index'])->name('index');
                Route::patch('/{interestSuggestion}/approve', [InterestSuggestionController::class, 'approve'])->name('approve');
                Route::patch('/{interestSuggestion}/reject', [InterestSuggestionController::class, 'reject'])->name('reject');
                Route::patch('/{interestSuggestion}/implement', [InterestSuggestionController::class, 'implement'])->name('implement');
                Route::patch('/{interestSuggestion}/reopen', [InterestSuggestionController::class, 'reopen'])->name('reopen');
                Route::delete('/{interestSuggestion}', [InterestSuggestionController::class, 'destroy'])->name('destroy');
            });
        });

        // Chat blocks
        Route::get('/chat-blocks', [ChatBlockController::class, 'index'])->name('chat-blocks.index');

        // Ban reasons
        Route::prefix('ban-reasons')->name('ban-reasons.')->group(function () {
            Route::get('/',             [BanReasonController::class, 'index'])->name('index');
            Route::post('/',            [BanReasonController::class, 'store'])->name('store');
            Route::patch('/{banReason}', [BanReasonController::class, 'update'])->name('update');
            Route::delete('/{banReason}', [BanReasonController::class, 'destroy'])->name('destroy');
            Route::post('/reorder',     [BanReasonController::class, 'reorder'])->name('reorder');
        });

        // Review epithets
        Route::prefix('review-epithets')->name('review-epithets.')->group(function () {
            Route::get('/',              [ReviewEpithetController::class, 'index'])->name('index');
            Route::post('/',             [ReviewEpithetController::class, 'store'])->name('store');
            Route::patch('/{epithet}',   [ReviewEpithetController::class, 'update'])->name('update');
            Route::delete('/{epithet}',  [ReviewEpithetController::class, 'destroy'])->name('destroy');
            Route::post('/reorder',      [ReviewEpithetController::class, 'reorder'])->name('reorder');
        });

        // News (О проекте)
        Route::prefix('news')->name('news.')->group(function () {
            Route::get('/',          [NewsController::class, 'index'])->name('index');
            Route::post('/',         [NewsController::class, 'store'])->name('store');
            Route::patch('/{news}',  [NewsController::class, 'update'])->name('update');
            Route::delete('/{news}', [NewsController::class, 'destroy'])->name('destroy');
        });

        // Help / FAQ CRUD
        Route::prefix('help-categories')->name('help-categories.')->group(function () {
            Route::get('/',             [HelpCategoryController::class, 'index'])->name('index');
            Route::post('/',            [HelpCategoryController::class, 'store'])->name('store');
            Route::patch('/{category}', [HelpCategoryController::class, 'update'])->name('update');
            Route::delete('/{category}', [HelpCategoryController::class, 'destroy'])->name('destroy');
            Route::post('/reorder',     [HelpCategoryController::class, 'reorder'])->name('reorder');
        });

        Route::prefix('help-articles')->name('help-articles.')->group(function () {
            Route::post('/',            [HelpArticleController::class, 'store'])->name('store');
            Route::patch('/{article}',  [HelpArticleController::class, 'update'])->name('update');
            Route::delete('/{article}', [HelpArticleController::class, 'destroy'])->name('destroy');
            Route::post('/reorder',     [HelpArticleController::class, 'reorder'])->name('reorder');
        });

        // Orders
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [OrderController::class, 'index'])->name('index');
            Route::patch('/{order}/status', [OrderController::class, 'updateStatus'])->name('status');
            Route::get('/{order}/history', [OrderController::class, 'history'])->name('history');
        });

        // Disputes
        Route::get('/disputes', [DisputeController::class, 'index'])->name('disputes.index');
        Route::patch('/disputes/{dispute}/resolve', [DisputeController::class, 'resolve'])->name('disputes.resolve');

        // Review disputes
        Route::prefix('review-disputes')->name('review-disputes.')->group(function () {
            Route::get('/', [ReviewDisputeController::class, 'index'])->name('index');
            Route::patch('/{reviewDispute}/resolve', [ReviewDisputeController::class, 'resolve'])->name('resolve');
        });

        // Conversations (read-only for admin)
        Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
        Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages'])->name('conversations.messages');

        // Support chat
        Route::prefix('support')->name('support.')->group(function () {
            Route::get('/', [SupportChatController::class, 'index'])->name('index');
            Route::get('/more', [SupportChatController::class, 'moreConversations'])->name('more');
            Route::post('/', [SupportChatController::class, 'store'])->name('store');
            Route::get('/{conversation}/messages', [SupportChatController::class, 'messages'])->name('messages');
            Route::post('/{conversation}/messages', [SupportChatController::class, 'send'])->name('send');
            Route::post('/{conversation}/upload', [SupportChatController::class, 'upload'])->name('upload');
            Route::post('/{conversation}/image', [SupportChatController::class, 'sendImage'])->name('image');
            Route::post('/{conversation}/close', [SupportChatController::class, 'close'])->name('close');
            Route::post('/{conversation}/open', [SupportChatController::class, 'open'])->name('open');
        });

        // Export
        Route::prefix('export')->name('export.')->group(function () {
            Route::get('/users', [ExportController::class, 'users'])->name('users');
            Route::get('/applications', [ExportController::class, 'applications'])->name('applications');
        });
    });
});
