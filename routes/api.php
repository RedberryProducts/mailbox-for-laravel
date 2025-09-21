<?php

use Illuminate\Support\Facades\Route;
use Redberry\MailboxForLaravel\Http\Controllers\Api\InboxController;
use Redberry\MailboxForLaravel\Http\Controllers\Api\MessagesController;
use Redberry\MailboxForLaravel\Http\Controllers\AssetController;
use Redberry\MailboxForLaravel\Http\Controllers\SendTestMailController;

if (! config('inbox.enabled', true)) {
    return;
}

Route::middleware(array_merge(
    config('inbox.middleware', ['web']),
    ['mailbox.authorize']
))
    ->prefix(config('inbox.route', 'mailbox').'/api')
    ->name('inbox.api.')
    ->group(function () {
        // Messages endpoints
        Route::get('/messages', [MessagesController::class, 'index'])
            ->name('messages.index');

        Route::get('/messages/{id}', [MessagesController::class, 'show'])
            ->name('messages.show')
            ->where('id', '[A-Za-z0-9_.\\-]+');

        Route::post('/messages/{id}/seen', [MessagesController::class, 'markAsSeen'])
            ->name('messages.seen')
            ->where('id', '[A-Za-z0-9_.\\-]+');

        Route::delete('/messages/{id}', [MessagesController::class, 'destroy'])
            ->name('messages.destroy')
            ->where('id', '[A-Za-z0-9_.\\-]+');

        // Inbox operations
        Route::post('/clear', [InboxController::class, 'clear'])
            ->name('clear');

        Route::get('/stats', [InboxController::class, 'stats'])
            ->name('stats');

        Route::post('/test-email', SendTestMailController::class)
            ->name('test-email');

        // Assets (keeping the same pattern)
        Route::get('/messages/{message}/attachments/{asset}', AssetController::class)
            ->name('asset');
    });
