<?php

use App\Http\Controllers\Api\MandarinWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

Route::post('/webhooks/mandarin', [MandarinWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('api.webhooks.mandarin');
