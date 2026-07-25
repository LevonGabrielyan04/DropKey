<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\IdentityKeyController;
use App\Http\Controllers\MarkChatMessageAsViewedController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SendController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')
    ->withoutMiddleware(['auth'])
    ->name('home');

Route::middleware(['throttle:60,1', 'verified'])->group(function () {
    Route::resource('sends', SendController::class)->except(['index']);

    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/to/{name}', [ChatController::class, 'openByName'])->name('chat.open');
    Route::get('/chat/{user}', [ChatController::class, 'show'])->name('chat.show');

    Route::prefix('api')->group(function () {
        Route::post('/identity/public-key', [IdentityKeyController::class, 'store'])
            ->middleware('throttle:chat-identity')
            ->name('api.identity.public-key.store');
        Route::get('/identity/public-key', [IdentityKeyController::class, 'mine'])
            ->middleware('throttle:chat-poll')
            ->name('api.identity.public-key.mine');
        Route::get('/users/{user}/public-key', [IdentityKeyController::class, 'show'])
            ->middleware(['throttle:chat-poll', 'not-self'])
            ->name('api.users.public-key.show');
        Route::get('/messages/{user}', [MessageController::class, 'index'])
            ->middleware(['throttle:chat-poll', 'not-self'])
            ->name('messages.index');
        Route::post('/messages', [MessageController::class, 'store'])
            ->middleware('throttle:chat-write')
            ->name('messages.store');
        Route::post('/messages/{message}/viewed', MarkChatMessageAsViewedController::class)
            ->middleware(['throttle:chat-write', 'can:markChatMessageAsViewed,message'])
            ->name('messages.viewed');
        Route::get('/conversations', [ConversationController::class, 'index'])
            ->middleware('throttle:chat-poll')
            ->name('conversations.index');
        Route::patch('/conversations/{user}/auto-delete', [ConversationController::class, 'updateAutoDelete'])
            ->middleware(['throttle:chat-write', 'not-self'])
            ->name('conversations.auto-delete.update');

        Route::get('/push/vapid-public-key', [PushSubscriptionController::class, 'vapidPublicKey'])
            ->middleware('throttle:chat-poll')
            ->name('api.push.vapid-public-key');
        Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])
            ->middleware('throttle:chat-write')
            ->name('api.push-subscriptions.store');
        Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])
            ->middleware('throttle:chat-write')
            ->name('api.push-subscriptions.destroy');

        Route::post('/uploads', [FileUploadController::class, 'store'])
            ->middleware('throttle:uploads')
            ->name('api.uploads.store');
    });
});

Route::get('/dashboard', [SendController::class, 'index'])
    ->middleware(['verified'])
    ->name('dashboard');

require __DIR__.'/settings.php';
