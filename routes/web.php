<?php

use App\Http\Controllers\Idol\ApplicationController as IdolApplicationController;
use App\Http\Controllers\Idol\QuizController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceController;

use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\AboutController;
use App\Http\Controllers\ContentPackController;
use App\Http\Controllers\ContentPackPurchaseController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NewsPublicController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewDisputeController;
use App\Http\Controllers\UserSearchController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('/locale', [LocaleController::class, 'switch'])->name('locale.switch');

Route::get('/about', AboutController::class)->name('about');
Route::get('/news',           [NewsPublicController::class, 'index'])->name('news');
Route::get('/news/feed',      [NewsPublicController::class, 'feed'])->name('news.feed');
Route::get('/news/{news}',    [NewsPublicController::class, 'show'])->name('news.show');

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('profile.show', ['user' => auth()->id()]);
    }

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Public profile page
Route::get('/users/{user}', [UserProfileController::class, 'show'])->name('profile.show');

// Public post endpoints (no auth required)
Route::get('/users/{user}/posts',    [UserProfileController::class, 'getPosts'])->name('profile.posts.feed');
Route::get('/users/{user}/category/{category}/idols', [UserProfileController::class, 'categoryIdols'])->name('profile.category-idols');
Route::get('/posts/{post}/comments',    [UserProfileController::class, 'getComments'])->name('posts.comments.index');
Route::get('/comments/{comment}/replies', [UserProfileController::class, 'getReplies'])->name('comments.replies');

// /profile redirects to own profile
Route::get('/profile', function () {
    return redirect()->route('profile.show', ['user' => auth()->id()]);
})->middleware('auth')->name('profile');

// Profile editing endpoints (owner only)
Route::middleware(['auth', 'verified', 'not_banned'])->group(function () {
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
    Route::post('/posts/{post}/like',            [UserProfileController::class, 'toggleLike'])->name('posts.like');
    Route::post('/posts/{post}/comments',        [UserProfileController::class, 'storeComment'])->name('posts.comments.store');
    Route::delete('/comments/{comment}',         [UserProfileController::class, 'destroyComment'])->name('posts.comments.destroy');
    Route::post('/profile/trait-suggestions',    [UserProfileController::class, 'storeTraitSuggestion'])->name('profile.trait-suggestions.store');
    Route::post('/profile/interest-suggestions', [UserProfileController::class, 'storeInterestSuggestion'])->name('profile.interest-suggestions.store');
});

// User search
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/search', [UserSearchController::class, 'index'])->name('users.search');
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
    Route::get('/profile/services/for-offer',       [ServiceController::class, 'forOffer'])->name('profile.services.for-offer');
    Route::post('/profile/services',                [ServiceController::class, 'store'])->name('profile.services.store');
    // Specific route before wildcard
    Route::patch('/profile/services/categories/{category}/description',
        [UserProfileController::class, 'updateCategoryDescription']
    )->name('profile.services.category.description');
    Route::patch('/profile/services/{service}',     [ServiceController::class, 'update'])->name('profile.services.update');
    Route::delete('/profile/services/{service}',    [ServiceController::class, 'destroy'])->name('profile.services.destroy');
});

// Content packs (idol management)
Route::middleware(['auth', 'verified', 'not_banned'])->group(function () {
    Route::post('/content-packs',                     [ContentPackController::class, 'store'])->name('content-packs.store');
    Route::post('/content-packs/purchase',            [ContentPackPurchaseController::class, 'store'])->name('content-packs.purchase');
    Route::patch('/content-packs/{pack}',             [ContentPackController::class, 'update'])->name('content-packs.update');
    Route::post('/content-packs/{pack}/publish',             [ContentPackController::class, 'publish'])->name('content-packs.publish');
    Route::post('/content-packs/{pack}/toggle-visibility',  [ContentPackController::class, 'toggleVisibility'])->name('content-packs.toggle-visibility');
    Route::post('/content-packs/{pack}/fix-change-request', [ContentPackController::class, 'fixChangeRequest'])->name('content-packs.fix-change-request');
    Route::patch('/content-packs/{pack}/cover',       [ContentPackController::class, 'updateCover'])->name('content-packs.cover');
    Route::patch('/content-packs/{pack}/title',       [ContentPackController::class, 'updateTitle'])->name('content-packs.title');
    Route::patch('/content-packs/{pack}/description', [ContentPackController::class, 'updateDescription'])->name('content-packs.description');
    Route::patch('/content-packs/{pack}/price',       [ContentPackController::class, 'updatePrice'])->name('content-packs.price');
    Route::delete('/content-packs/{pack}',            [ContentPackController::class, 'destroy'])->name('content-packs.destroy');
});

// Public content pack profile feed
Route::get('/users/{user}/content-packs', [ContentPackController::class, 'indexForProfile'])->name('profile.content-packs.index');

// Gallery
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/gallery',        [GalleryController::class, 'index'])->name('gallery.index');
    Route::get('/gallery/photos', [GalleryController::class, 'photos'])->name('gallery.photos');
    Route::get('/gallery/packs',  [GalleryController::class, 'packs'])->name('gallery.packs');
});

// Reports
Route::middleware(['auth', 'not_banned'])->group(function () {
    Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
});

// Push subscriptions
Route::middleware('auth')->group(function () {
    Route::post('/push/subscribe',   [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::delete('/push/subscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
});

// Notification routes
Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('/notifications/service', [NotificationController::class, 'service'])->name('notifications.service');
    Route::patch('/notifications/service/read-all', [NotificationController::class, 'markAllServiceRead'])->name('notifications.service.read-all');
    Route::get('/notifications/orders', [NotificationController::class, 'orders'])->name('notifications.orders');
    Route::patch('/notifications/orders/read-all', [NotificationController::class, 'markAllOrdersRead'])->name('notifications.orders.read-all');
    Route::get('/notifications/combined', [NotificationController::class, 'combined'])->name('notifications.combined');
    Route::patch('/broadcasts/{id}/read', [NotificationController::class, 'markBroadcastRead'])->name('broadcasts.read');
});

// Chat / Conversations
Route::middleware('auth')->group(function () {
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
});
Route::middleware(['auth', 'not_banned'])->group(function () {
    Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'message'])->name('conversations.message');
    Route::post('/conversations/{conversation}/upload', [ConversationController::class, 'upload'])->name('conversations.upload');
    Route::post('/conversations/{conversation}/offer-services', [ConversationController::class, 'offerServices'])->name('conversations.offer-services');
    Route::post('/conversations/{conversation}/block', [ConversationController::class, 'block'])->name('conversations.block');
    Route::delete('/conversations/{conversation}/block', [ConversationController::class, 'unblock'])->name('conversations.unblock');
});

// Orders
Route::middleware('auth')->group(function () {
    Route::get('/orders',                      [OrderController::class, 'index'])->name('orders.index');
    // Static routes before {order} wildcard
    Route::get('/orders/disputable',           [OrderController::class, 'disputable'])->name('orders.disputable');
    Route::patch('/orders/{order}/accept',     [OrderController::class, 'accept'])->name('orders.accept');
    Route::patch('/orders/{order}/pay',        [OrderController::class, 'pay'])->name('orders.pay');
    Route::patch('/orders/{order}/cancel',              [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::patch('/orders/{order}/confirm-completion', [OrderController::class, 'confirmCompletion'])->name('orders.confirm-completion');
    Route::post('/orders/{order}/dispute',     [OrderController::class, 'dispute'])->name('orders.dispute');
    Route::post('/orders/{order}/items',       [OrderController::class, 'addItem'])->name('orders.items.add');
    Route::post('/reviews/{review}/dispute',   [ReviewDisputeController::class, 'store'])->name('reviews.dispute.store');
});
Route::middleware(['auth', 'not_banned'])->group(function () {
    Route::post('/orders',                     [OrderController::class, 'store'])->name('orders.store');
    Route::post('/orders/{order}/review',      [ReviewController::class, 'store'])->name('orders.review.store');
});

Route::get('/reviews/epithets', [ReviewController::class, 'epithets'])->name('reviews.epithets');
Route::get('/users/{user}/reviews', [ReviewController::class, 'index'])->name('users.reviews');

require __DIR__.'/auth.php';
