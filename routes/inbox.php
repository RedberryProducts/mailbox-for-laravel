<?php

use Illuminate\Support\Facades\Route;
use Redberry\MailboxForLaravel\Http\Controllers\AssetController;
use Redberry\MailboxForLaravel\Http\Controllers\InboxController;
use Redberry\MailboxForLaravel\Http\Controllers\PublicAssetController;

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

        Route::get('/messages/{message}/attachments/{asset}', AssetController::class)
            ->name('asset');
    });

Route::get('/mailbox/assets/{path}', PublicAssetController::class)->where('path', '.*')->name('mailbox.asset');
