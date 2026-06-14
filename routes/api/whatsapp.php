<?php

declare(strict_types=1);

use App\Http\Controllers\Api\WhatsApp\WhatsAppInboxController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WhatsApp inbox (team-facing — Phase 2)
|--------------------------------------------------------------------------
| Loaded inside the auth.api.dual group (routes/api.php). The public Meta
| webhook is NOT here — it lives unauthenticated in routes/api.php.
*/

Route::prefix('whatsapp')->name('whatsapp.')
    ->middleware('permission:whatsapp.inbox.view')
    ->group(function (): void {
        Route::get('conversations', [WhatsAppInboxController::class, 'index'])
            ->name('conversations.index');
        Route::get('conversations/unread-count', [WhatsAppInboxController::class, 'unreadCount'])
            ->name('conversations.unread_count');
        Route::get('conversations/{id}', [WhatsAppInboxController::class, 'show'])
            ->whereNumber('id')->name('conversations.show');
        Route::get('conversations/{id}/media/{messageId}', [WhatsAppInboxController::class, 'media'])
            ->whereNumber('id')->whereNumber('messageId')->name('conversations.media');
        Route::post('conversations/{id}/read', [WhatsAppInboxController::class, 'markRead'])
            ->whereNumber('id')->name('conversations.read');
        Route::post('conversations/{id}/reply', [WhatsAppInboxController::class, 'reply'])
            ->whereNumber('id')
            ->middleware(['permission:whatsapp.inbox.reply', 'throttle:60,1'])
            ->name('conversations.reply');
        Route::post('conversations/{id}/reply-media', [WhatsAppInboxController::class, 'replyMedia'])
            ->whereNumber('id')
            ->middleware(['permission:whatsapp.inbox.reply', 'throttle:30,1'])
            ->name('conversations.reply_media');
    });
