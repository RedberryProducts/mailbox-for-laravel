<?php

namespace Redberry\MailboxForLaravel\Tests\Helpers;

/**
 * Custom Pest expectations for mailbox testing
 */

// Register custom expectation to validate email address format
expect()->extend('toBeValidEmail', function () {
    return $this->toMatch('/^[^\s@]+@[^\s@]+\.[^\s@]+$/');
});

// Register custom expectation to validate message structure
expect()->extend('toHaveValidMessageStructure', function () {
    return $this->toMatchArray([
        'version' => expect()->toBe(1),
        'saved_at' => expect()->toBeString(),
        'subject' => expect()->toBeString()->or->toBeNull(),
        'text' => expect()->toBeString()->or->toBeNull(),
        'html' => expect()->toBeString()->or->toBeNull(),
        'from' => expect()->toBeArray(),
        'to' => expect()->toBeArray(),
        'cc' => expect()->toBeArray(),
        'bcc' => expect()->toBeArray(),
        'attachments' => expect()->toBeArray(),
    ]);
});

// Register custom expectation to validate attachment structure
expect()->extend('toHaveValidAttachmentStructure', function () {
    return $this->toHaveKeys([
        'filename',
        'contentType', 
        'size',
        'inline',
        'disposition',
    ]);
});

// Register custom expectation for ISO date strings
expect()->extend('toBeISODate', function () {
    return $this->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?([+-]\d{2}:\d{2}|Z)$/');
});

// Register custom expectation for base64 content
expect()->extend('toBeBase64', function () {
    return $this->toMatch('/^[A-Za-z0-9+\/]*={0,2}$/');
});