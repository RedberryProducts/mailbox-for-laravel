<?php

use Illuminate\Support\Facades\Route;
use Redberry\MailboxForLaravel\Http\Controllers\AttachmentController;
use Redberry\MailboxForLaravel\Http\Controllers\ClearMailboxController;
use Redberry\MailboxForLaravel\Http\Controllers\DeleteMailboxMessageController;
use Redberry\MailboxForLaravel\Http\Controllers\MailboxController;
use Redberry\MailboxForLaravel\Http\Controllers\SeenController;
use Redberry\MailboxForLaravel\Http\Controllers\SendTestMailController;

if (! config('mailbox.enabled')) {
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

        Route::delete('/messages', ClearMailboxController::class)
            ->name('messages.clear');

        Route::delete('/messages/{id}', DeleteMailboxMessageController::class)
            ->name('messages.destroy');

        Route::post('/test-email', SendTestMailController::class)
            ->name('test-email');

        Route::post('/messages/{id}/seen', SeenController::class)->name('messages.seen');

        // Attachment routes
        Route::get('/messages/{messageId}/attachments', [AttachmentController::class, 'list'])
            ->name('messages.attachments');

        Route::get('/attachments/{id}/download', [AttachmentController::class, 'download'])
            ->name('attachments.download');

        Route::get('/attachments/{id}/inline', [AttachmentController::class, 'inline'])
            ->name('attachments.inline');
    });
