<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\DTO;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

use function array_filter;
use function array_map;
use function array_values;

class MailboxMessageData extends Data
{
    public function __construct(
        public int $timestamp,
        public string $id,
        public ?string $seen_at,
        public int $version,
        public ?string $saved_at,
        public ?string $message_id,
        public ?string $subject,
        public ?string $date,
        /** @var array<int, array{email: string, name?: string|null}> */
        public array $from,
        /** @var array{email: string, name?: string|null}|null */
        public ?array $sender,
        /** @var array<int, array{email: string, name?: string|null}> */
        public array $to,
        public array $cc,
        public array $bcc,
        public array $reply_to,
        public string $text,
        public string $html,
        public array $headers,
        public array $attachments,
        public string $raw,
    ) {}

    /**
     * Compatible with Spatie\LaravelData\Data::from(mixed ...$payloads): static
     */
    public static function from(mixed ...$payloads): static
    {
        $message = $payloads[0] ?? [];

        if ($message instanceof static) {
            return $message;
        }

        if (! is_array($message)) {
            $message = (array) $message;
        }

        $timestamp = (int) ($message['timestamp'] ?? time());
        $id = (string) ($message['id'] ?? ('email_'.md5($message['raw'] ?? '').'_'.microtime(true)));

        return new static(
            timestamp: $timestamp,
            id: $id,
            seen_at: $message['seen_at'] ?? null,
            version: (int) ($message['version'] ?? 1),
            saved_at: $message['saved_at'] ?? Carbon::createFromTimestamp($timestamp)->toIso8601String(),
            message_id: $message['message_id'] ?? null,
            subject: $message['subject'] ?? null,
            date: $message['date'] ?? null,
            from: $message['from'] ?? [],
            sender: $message['sender'] ?? null,
            to: $message['to'] ?? [],
            cc: $message['cc'] ?? [],
            bcc: $message['bcc'] ?? [],
            reply_to: $message['reply_to'] ?? [],
            text: $message['text'] ?? '',
            html: $message['html'] ?? '',
            headers: $message['headers'] ?? [],
            attachments: $message['attachments'] ?? [],
            raw: $message['raw'] ?? '',
        );
    }

    /**
     * Helper: the exact shape your Vue components expect.
     */
    public function toFrontendArray(): array
    {
        $toEmails = array_values(array_filter(array_map(
            static fn (array $r) => $r['email'] ?? null,
            $this->to,
        )));

        return [
            'id' => $this->id,
            'subject' => $this->subject ?? '(No subject)',
            'from' => $this->sender['email'] ?? ($this->from[0]['email'] ?? ''),
            'to' => $toEmails,
            'created_at' => $this->saved_at ?? Carbon::createFromTimestamp($this->timestamp)->toIso8601String(),
            'html_body' => $this->html,
            'text_body' => $this->text,
            'raw_body' => $this->raw,
            'seen_at' => $this->seen_at,
        ];
    }
}
