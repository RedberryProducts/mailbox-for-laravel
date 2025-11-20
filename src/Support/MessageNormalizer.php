<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Support;

use DateTimeInterface;
use Redberry\MailboxForLaravel\DTO\AttachmentData;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\RawMessage;

final class MessageNormalizer
{
    /**
     * Build a structured array to store.
     *
     *
     * @return array<string,mixed>
     */
    /** @return array<string,mixed> */
    public static function normalize(
        Email|RawMessage $message,
        ?Envelope $envelope = null,
        ?string $raw = null,
        bool $storeAttachmentsInline = false
    ): array {
        if ($message instanceof Email) {

            return self::normalizeEmail($message, $envelope, $raw, $storeAttachmentsInline);
        }

        return self::normalizeRaw($message, $envelope, $raw);
    }

    /**
     * Extract attachment DTOs from an email for separate storage.
     *
     * @return array<int, AttachmentData>
     */
    public static function extractAttachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $part) {
            /** @var DataPart $part */
            $filename = $part->getFilename() ?? 'unnamed';

            // Extract Content-ID for inline images
            $contentId = $part->getPreparedHeaders()->has('Content-ID')
                ? trim($part->getPreparedHeaders()->get('Content-ID')->getBodyAsString(), '<>')
                : null;

            // Determine if inline based on disposition or Content-ID presence
            $disposition = $part->getPreparedHeaders()->has('Content-Disposition')
                ? $part->getPreparedHeaders()->get('Content-Disposition')->getBodyAsString()
                : null;
            $isInline = $contentId !== null || str_contains((string) $disposition, 'inline');

            // Get mime type
            $mimeType = 'application/octet-stream';
            if ($part->getPreparedHeaders()->has('Content-Type')) {
                $contentType = $part->getPreparedHeaders()->get('Content-Type')->getBodyAsString();
                // Extract just the mime type part (before semicolon)
                $mimeType = explode(';', $contentType)[0];
            }

            // Extract body content
            /** @var string|resource $body */
            $body = $part->getBody();

            if (is_resource($body)) {
                $body = stream_get_contents($body) ?: '';
            }

            $size = strlen($body);
            $content = base64_encode($body);

            $attachments[] = new AttachmentData(
                filename: $filename,
                mimeType: $mimeType,
                size: $size,
                content: $content,
                cid: $contentId,
                isInline: $isInline,
            );
        }

        return $attachments;
    }

    /** @return array<string,mixed> */
    private static function normalizeRaw(
        RawMessage $rawMessage,
        ?Envelope $envelope,
        ?string $raw
    ): array {
        return [
            'version' => 1,
            'saved_at' => (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            'subject' => null,
            'from' => [],
            'to' => [],
            'cc' => [],
            'bcc' => [],
            'reply_to' => [],
            'text' => null,
            'html' => null,
            'headers' => [],
            'attachments' => [],
            'raw' => $rawMessage,
        ];
    }

    /** @return array<string,mixed> */
    private static function normalizeEmail(
        Email $email,
        ?Envelope $envelope,
        ?string $raw,
        bool $storeAttachmentsInline
    ): array {
        $headers = [];
        foreach ($email->getHeaders()->all() as $header) {
            $headers[$header->getName()][] = trim($header->getBodyAsString());
        }

        $attachments = [];
        foreach ($email->getAttachments() as $part) {
            /** @var DataPart $part */
            $filename = $part->getFilename();
            $contentId = $part->getPreparedHeaders()->has('Content-ID')
                ? trim($part->getPreparedHeaders()->get('Content-ID')->getBodyAsString(), '<>')
                : null;

            $disposition = $part->getPreparedHeaders()->has('Content-Disposition')
                ? $part->getPreparedHeaders()->get('Content-Disposition')->getBodyAsString()
                : null;

            $contentType = $part->getPreparedHeaders()->has('Content-Type')
                ? $part->getPreparedHeaders()->get('Content-Type')->getBodyAsString()
                : null;

            $bodyBase64 = null;
            $size = null;

            if ($storeAttachmentsInline) {
                /** @var string|resource $body */
                $body = $part->getBody();

                if (is_resource($body)) {
                    $body = stream_get_contents($body) ?: '';
                }

                $size = strlen($body);
                $bodyBase64 = base64_encode($body);
            }

            $attachments[] = array_filter([
                'filename' => $filename,
                'contentType' => $contentType,
                'disposition' => $disposition,
                'contentId' => $contentId,
                'inline' => $contentId !== null,
                'size' => $size,
                'content' => $bodyBase64,
            ], static fn ($v) => $v !== null);
        }

        $sender = $envelope?->getSender()
            ? self::addressToArray($envelope->getSender())
            : self::addressesToArray($email->getFrom())[0] ?? null;

        $recipients = $envelope?->getRecipients() ?? [];
        $to = $recipients ? self::addressesToArray($recipients) : self::addressesToArray($email->getTo());

        $payload = [
            'version' => 1,
            'saved_at' => (new \DateTimeImmutable)->format(DateTimeInterface::ATOM),

            'message_id' => self::firstHeader($email, 'Message-ID'),
            'subject' => $email->getSubject(),
            'date' => self::firstHeader($email, 'Date'),

            'from' => self::addressesToArray($email->getFrom()),
            'sender' => $sender,
            'to' => $to,
            'cc' => self::addressesToArray($email->getCc()),
            'bcc' => self::addressesToArray($email->getBcc()),
            'reply_to' => self::addressesToArray($email->getReplyTo()),

            'text' => $email->getTextBody(),
            'html' => $email->getHtmlBody(),

            'headers' => $headers,
            'attachments' => $attachments,

            'raw' => $raw,
        ];

        return $payload;
    }

    /**
     * @param  Address[]  $addresses
     * @return array<int,array{name?:string,email:string}>
     */
    private static function addressesToArray(iterable $addresses): array
    {
        $out = [];
        foreach ($addresses as $addr) {
            $row = ['email' => $addr->getAddress()];
            if ($addr->getName() !== '') {
                $row['name'] = $addr->getName();
            }
            $out[] = $row;
        }

        return $out;
    }

    private static function addressToArray(Address $address): array
    {
        return array_filter([
            'name' => $address->getName() ?: null,
            'email' => $address->getAddress(),
        ]);
    }

    private static function firstHeader(Email $email, string $name): ?string
    {
        $headers = $email->getHeaders();
        if (! $headers->has($name)) {
            return null;
        }

        return trim($headers->get($name)->getBodyAsString());
    }
}
