<?php

use Illuminate\Support\Facades\Route;
use Redberry\MailboxForLaravel\Http\Controllers\AssetController;
use Redberry\MailboxForLaravel\Http\Controllers\InboxController;

Route::middleware(array_merge(
    config('inbox.middleware', ['web']),
    []
))
    ->prefix(config('inbox.route', 'mailbox'))
    ->name('inbox.')
    ->group(function () {
        abort_unless(config('inbox.enabled'), 404);

        Route::get('/', InboxController::class)
            ->name('index');
    });


Route::get('/mailbox/assets/{path}', AssetController::class)->where('path', '.*')->name('mailbox.asset');
