<?php

declare(strict_types=1);

use App\Http\Controllers\Api\WhatsApp\WhatsAppCannedReplyController;
use App\Http\Controllers\Api\WhatsApp\WhatsAppInboxController;
use App\Http\Controllers\Api\WhatsApp\WhatsAppNoteController;
use App\Http\Controllers\Api\WhatsApp\WhatsAppTagController;
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
        Route::get('canned-replies', [WhatsAppCannedReplyController::class, 'index'])
            ->name('canned.index');
        Route::post('canned-replies', [WhatsAppCannedReplyController::class, 'store'])
            ->middleware('permission:whatsapp.inbox.reply')->name('canned.store');
        Route::put('canned-replies/{id}', [WhatsAppCannedReplyController::class, 'update'])
            ->whereNumber('id')->middleware('permission:whatsapp.inbox.reply')->name('canned.update');
        Route::delete('canned-replies/{id}', [WhatsAppCannedReplyController::class, 'destroy'])
            ->whereNumber('id')->middleware('permission:whatsapp.inbox.reply')->name('canned.destroy');

        Route::get('tags', [WhatsAppTagController::class, 'index'])->name('tags.index');
        Route::post('tags', [WhatsAppTagController::class, 'store'])
            ->middleware('permission:whatsapp.inbox.reply')->name('tags.store');
        // Tag DELETION is intentionally not exposed — tags are a fixed, managed
        // set (changed only via migration). No destroy route, by design.

        Route::get('health', [WhatsAppInboxController::class, 'health'])->name('health');
        Route::get('number-quality', [WhatsAppInboxController::class, 'numberQuality'])->name('number_quality');

        Route::get('conversations', [WhatsAppInboxController::class, 'index'])
            ->name('conversations.index');
        Route::get('conversations/unread-count', [WhatsAppInboxController::class, 'unreadCount'])
            ->name('conversations.unread_count');
        Route::get('conversations/{id}', [WhatsAppInboxController::class, 'show'])
            ->whereNumber('id')->name('conversations.show');
        Route::get('conversations/{id}/older', [WhatsAppInboxController::class, 'olderMessages'])
            ->whereNumber('id')->name('conversations.older');
        Route::get('conversations/{id}/media/{messageId}', [WhatsAppInboxController::class, 'media'])
            ->whereNumber('id')->whereNumber('messageId')->name('conversations.media');
        Route::post('conversations/{id}/read', [WhatsAppInboxController::class, 'markRead'])
            ->whereNumber('id')->name('conversations.read');
        Route::post('conversations/{id}/unread', [WhatsAppInboxController::class, 'markUnread'])
            ->whereNumber('id')->name('conversations.unread');
        Route::post('conversations/{id}/typing', [WhatsAppInboxController::class, 'typing'])
            ->whereNumber('id')->middleware(['permission:whatsapp.inbox.reply', 'throttle:30,1'])->name('conversations.typing');
        Route::post('conversations/{id}/react', [WhatsAppInboxController::class, 'react'])
            ->whereNumber('id')->middleware(['permission:whatsapp.inbox.reply', 'throttle:30,1'])->name('conversations.react');
        Route::post('conversations/{id}/assign', [WhatsAppInboxController::class, 'assign'])
            ->whereNumber('id')->middleware('throttle:60,1')->name('conversations.assign');
        Route::post('conversations/{id}/resolve', [WhatsAppInboxController::class, 'resolve'])
            ->whereNumber('id')->middleware('throttle:60,1')->name('conversations.resolve');
        Route::post('conversations/{id}/tags', [WhatsAppInboxController::class, 'tag'])
            ->whereNumber('id')->middleware('throttle:60,1')->name('conversations.tag');
        Route::delete('conversations/{id}/tags/{tagId}', [WhatsAppInboxController::class, 'untag'])
            ->whereNumber('id')->whereNumber('tagId')->name('conversations.untag');
        Route::get('conversations/{id}/notes', [WhatsAppNoteController::class, 'index'])
            ->whereNumber('id')->name('conversations.notes.index');
        Route::post('conversations/{id}/notes', [WhatsAppNoteController::class, 'store'])
            ->whereNumber('id')->middleware('throttle:60,1')->name('conversations.notes.store');
        Route::delete('conversations/{id}/notes/{noteId}', [WhatsAppNoteController::class, 'destroy'])
            ->whereNumber('id')->whereNumber('noteId')->name('conversations.notes.destroy');
        Route::post('conversations/{id}/reply', [WhatsAppInboxController::class, 'reply'])
            ->whereNumber('id')
            ->middleware(['permission:whatsapp.inbox.reply', 'throttle:60,1'])
            ->name('conversations.reply');
        Route::post('conversations/{id}/reply-media', [WhatsAppInboxController::class, 'replyMedia'])
            ->whereNumber('id')
            ->middleware(['permission:whatsapp.inbox.reply', 'throttle:30,1'])
            ->name('conversations.reply_media');
        Route::post('conversations/{id}/messages/{messageId}/retry', [WhatsAppInboxController::class, 'retry'])
            ->whereNumber('id')->whereNumber('messageId')
            ->middleware(['permission:whatsapp.inbox.reply', 'throttle:30,1'])
            ->name('conversations.retry');
    });
