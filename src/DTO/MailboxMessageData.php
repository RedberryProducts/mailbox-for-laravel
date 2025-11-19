<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\DTO;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

use function array_filter;
use function array_map;
use function array_values;

/**
 * Canonical representation of a stored mailbox message.
 *
 * This is the shape the frontend consumes.
 */
class MailboxMessageData extends Data
{
    public function __construct(
        public string $id,
        public ?int $timestamp = null,
        public ?string $message_id = null,
        public ?string $subject = null,
        public ?string $date = null,
        /** @var array<int, array{email:string,name?:string}>|null */
        public ?array $from = null,
        /** @var array{email?:string,name?:string}|null */
        public ?array $sender = null,
        /** @var array<int, array{email:string,name?:string}>|null */
        public ?array $to = null,
        /** @var array<int, array{email:string,name?:string}>|null */
        public ?array $cc = null,
        /** @var array<int, array{email:string,name?:string}>|null */
        public ?array $bcc = null,
        /** @var array<int, array{email:string,name?:string}>|null */
        public ?array $reply_to = null,
        public ?string $text = null,
        public ?string $html = null,
        /** @var array<string, mixed>|null */
        public ?array $headers = null,
        /** @var array<int, mixed>|null */
        public ?array $attachments = null,
        public ?string $raw = null,
        public ?string $saved_at = null,
        public ?string $seen_at = null,
    ) {}

    /**
     * Convert into a compact frontend-friendly array.
     *
     * @return array{
     *   id: string,
     *   subject: string,
     *   from: string,
     *   to: array<int, string>,
     *   created_at: string,
     *   html_body: ?string,
     *   text_body: ?string,
     *   raw_body: ?string,
     *   seen_at: ?string,
     * }
     */
    public function toFrontendArray(): array
    {
        $toEmails = array_values(array_filter(array_map(
            static function (array $recipient): string {
                return $recipient['email'];
            },
            $this->to ?? [],
        )));

        $createdAt = $this->saved_at;

        if ($createdAt === null && $this->timestamp !== null) {
            $createdAt = Carbon::createFromTimestamp($this->timestamp)->toIso8601String();
        }

        return [
            'id' => $this->id,
            'subject' => ($this->subject ?: null) ?? '(No subject)',
            'from' => $this->sender['email'] ?? ($this->from[0]['email'] ?? ''),
            'to' => $toEmails,
            'created_at' => $createdAt ?? Carbon::now()->toIso8601String(),
            'html_body' => $this->html,
            'text_body' => $this->text,
            'raw_body' => $this->raw,
            'seen_at' => $this->seen_at,
        ];
    }
}
