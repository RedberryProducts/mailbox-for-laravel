<?php

use Illuminate\Support\Facades\Route;
use Redberry\MailboxForLaravel\Http\Controllers\AssetController;
use Redberry\MailboxForLaravel\Http\Controllers\ClearMailboxController;
use Redberry\MailboxForLaravel\Http\Controllers\MailboxController;
use Redberry\MailboxForLaravel\Http\Controllers\SeenController;
use Redberry\MailboxForLaravel\Http\Controllers\SendTestMailController;

if (! config('mailbox.enabled', true)) {
    return;
}

Route::middleware(array_merge(
    config('mailbox.middleware', ['web']),
    ['mailbox.inertia', 'mailbox.authorize']
))
    ->prefix(config('mailbox.route', 'mailbox'))
    ->name('mailbox.')
    ->group(function () {
        Route::get('/', MailboxController::class)
            ->name('index');

        Route::post('/clear', ClearMailboxController::class)
            ->name('clear');

        Route::post('/test-email', SendTestMailController::class)
            ->name('test-email');

        Route::get('/messages/{message}/attachments/{asset}', AssetController::class)
            ->name('asset');
        Route::post('/messages/{id}/seen', SeenController::class)->name('messages.seen');
    });
