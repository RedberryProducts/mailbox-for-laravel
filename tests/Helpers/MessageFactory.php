<?php

namespace Redberry\MailboxForLaravel\Tests\Helpers;

/**
 * Factory class for creating test message data
 */
class MessageFactory
{
    public static function create(array $overrides = []): array
    {
        return array_merge([
            'version' => 1,
            'saved_at' => now()->toISOString(),
            'subject' => 'Test Subject',
            'text' => 'Test message body',
            'html' => '<p>Test message body</p>',
            'from' => [['address' => 'sender@example.com', 'name' => 'Test Sender']],
            'to' => [['address' => 'recipient@example.com', 'name' => 'Test Recipient']],
            'cc' => [],
            'bcc' => [],
            'attachments' => [],
        ], $overrides);
    }

    public static function withAttachment(array $attachmentOverrides = []): array
    {
        $attachment = array_merge([
            'filename' => 'test.txt',
            'contentType' => 'text/plain',
            'content' => base64_encode('test content'),
            'size' => strlen('test content'),
            'inline' => false,
            'disposition' => 'attachment; filename="test.txt"',
        ], $attachmentOverrides);

        return self::create(['attachments' => [$attachment]]);
    }

    public static function withInlineImage(array $imageOverrides = []): array
    {
        $image = array_merge([
            'filename' => 'image.png',
            'contentType' => 'image/png',
            'content' => base64_encode('fake-png-data'),
            'size' => strlen('fake-png-data'),
            'inline' => true,
            'disposition' => 'inline; filename="image.png"',
            'contentId' => 'image1@example.com',
        ], $imageOverrides);

        return self::create(['attachments' => [$image]]);
    }

    public static function textOnly(array $overrides = []): array
    {
        return self::create(array_merge([
            'html' => null,
        ], $overrides));
    }

    public static function htmlOnly(array $overrides = []): array
    {
        return self::create(array_merge([
            'text' => null,
        ], $overrides));
    }

    public static function withMultipleRecipients(): array
    {
        return self::create([
            'to' => [
                ['address' => 'user1@example.com', 'name' => 'User One'],
                ['address' => 'user2@example.com', 'name' => 'User Two'],
            ],
            'cc' => [
                ['address' => 'cc@example.com', 'name' => 'CC User'],
            ],
            'bcc' => [
                ['address' => 'bcc@example.com', 'name' => 'BCC User'],
            ],
        ]);
    }

    public static function large(): array
    {
        return self::create([
            'subject' => 'Large Message ' . str_repeat('X', 1000),
            'text' => str_repeat('This is a large message body. ', 1000),
            'html' => '<p>' . str_repeat('This is a large HTML message body. ', 1000) . '</p>',
        ]);
    }
}