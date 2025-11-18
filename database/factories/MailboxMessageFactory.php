<?php

namespace Redberry\MailboxForLaravel\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Redberry\MailboxForLaravel\Models\MailboxMessage;

class MailboxMessageFactory extends Factory
{
    protected $model = MailboxMessage::class;

    public function definition(): array
    {
        $rawText = $this->faker->sentence(12);
        $timestamp = $this->faker->unixTime();

        return [
            'id' => 'email_'.md5($rawText).'_'.microtime(true),
            'timestamp' => $timestamp,
            'seen_at' => null,
            'version' => 1,
            'saved_at' => now(),
            'message_id' => '<'.Str::uuid()->toString().'@example.com>',
            'subject' => $this->faker->sentence(5),
            'date' => $this->faker->dateTime(),

            'from' => [
                [
                    'email' => 'sender@example.com',
                    'name' => 'Sender Name',
                ],
            ],
            'sender' => [
                'email' => 'sender@example.com',
                'name' => 'Sender Name',
            ],
            'to' => [
                ['email' => 'recipient@example.com'],
            ],
            'cc' => [],
            'bcc' => [],
            'reply_to' => [],

            'text' => $rawText,
            'html' => null,
            'headers' => [
                'From' => ['Sender Name <sender@example.com>'],
                'To' => ['recipient@example.com'],
                'Subject' => [$this->faker->sentence(5)],
            ],
            'attachments' => [],
            'raw' => <<<EOT
From: Sender Name <sender@example.com>
To: recipient@example.com
Subject: {$this->faker->sentence(5)}
Date: {$this->faker->date('r')}

{$rawText}
EOT,
        ];
    }

    /**
     * Mark message as seen
     */
    public function seen(): static
    {
        return $this->state(fn () => [
            'seen_at' => now(),
        ]);
    }
}
