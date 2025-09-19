<?php

use Illuminate\Support\Facades\Route;
use Redberry\MailboxForLaravel\Http\Controllers\AssetController;
use Redberry\MailboxForLaravel\Http\Controllers\ClearInboxController;
use Redberry\MailboxForLaravel\Http\Controllers\InboxController;
use Redberry\MailboxForLaravel\Http\Controllers\SeenController;
use Redberry\MailboxForLaravel\Http\Controllers\SendTestMailController;

if (! config('inbox.enabled', true)) {
    return;
}

Route::middleware(array_merge(
    config('inbox.middleware', ['web']),
    ['mailbox.authorize']
))
    ->prefix(config('inbox.route', 'mailbox'))
    ->name('inbox.')
    ->group(function () {
        Route::get('/', InboxController::class)
            ->name('index');

        Route::post('/clear', ClearInboxController::class)
            ->name('clear');

        Route::post('/test-email', SendTestMailController::class)
            ->name('test-email');

        Route::get('/messages/{message}/attachments/{asset}', AssetController::class)
            ->name('asset');
        Route::post('/messages/{id}/seen', SeenController::class)->name('messages.seen');
    });
