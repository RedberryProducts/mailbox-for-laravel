<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Support;

use DateTimeInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

final class MessageNormalizer
{
    /**
     * Build a structured array to store.
     *
     *
     * @return array<string,mixed>
     */
    public static function normalize(
        Email $email,
        ?Envelope $envelope = null,
        ?string $raw = null,
        bool $storeAttachmentsInline = false
    ): array {
        $keepRaw = true;
        $headers = [];
        foreach ($email->getHeaders()->all() as $header) {
            // flatten to "Name" => ["value1", "value2"...]
            $headers[$header->getName()][] = trim($header->getBodyAsString());
        }

        $attachments = [];
        foreach ($email->getAttachments() as $part) {
            /** @var DataPart $part */
            $contentId = $part->getPreparedHeaders()->has('Content-ID')
                ? trim($part->getPreparedHeaders()->get('Content-ID')->getBodyAsString(), '<>')
                : null;

            $disposition = $part->getPreparedHeaders()->has('Content-Disposition')
                ? $part->getPreparedHeaders()->get('Content-Disposition')->getBodyAsString()
                : null;

            $filename = method_exists($part, 'getFilename') ? $part->getFilename() : null;

            $contentType = $part->getPreparedHeaders()->has('Content-Type')
                ? $part->getPreparedHeaders()->get('Content-Type')->getBodyAsString()
                : null;

            $bodyBase64 = null;
            $size = null;

            if ($storeAttachmentsInline) {
                // getBody() can be string|resource
                $body = $part->getBody();
                if (is_resource($body)) {
                    $body = stream_get_contents($body);
                }
                if (is_string($body)) {
                    $size = strlen($body);
                    $bodyBase64 = base64_encode($body);
                }
            }

            $attachments[] = array_filter([
                'filename' => $filename,
                'contentType' => $contentType,
                'disposition' => $disposition, // e.g. attachment/inline; filename=...
                'contentId' => $contentId,   // for cid: images
                'inline' => $contentId !== null,
                'size' => $size,
                'content' => $bodyBase64,  // base64 or null
            ], static fn ($v) => $v !== null);
        }

        // Prefer explicitly set envelope sender/recipients, fallback to headers
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
            'date' => self::firstHeader($email, 'Date'), // raw date header for fidelity

            'from' => self::addressesToArray($email->getFrom()),
            'sender' => $sender,
            'to' => $to,
            'cc' => self::addressesToArray($email->getCc()),
            'bcc' => self::addressesToArray($email->getBcc()),
            'reply_to' => self::addressesToArray($email->getReplyTo()),

            'text' => $email->getTextBody(),
            'html' => $email->getHtmlBody(),

            'headers' => $headers,       // full header map
            'attachments' => $attachments,   // metadata (+ content if enabled)
        ];

        if ($keepRaw && $raw !== null) {
            $payload['raw'] = $raw;
        }

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
            if ($addr instanceof Address) {
                $row = ['email' => $addr->getAddress()];
                if ($addr->getName() !== '') {
                    $row['name'] = $addr->getName();
                }
                $out[] = $row;
            }
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
