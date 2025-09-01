<?php

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Redberry\MailboxForLaravel\CaptureService;

class SendTestMailController extends Controller
{
    public function __invoke(Request $request, CaptureService $service)
    {
        $now = now()->toRfc3339String();

        $payload = [
            'version' => 1,
            'saved_at' => $now,
            'message_id' => null,
            'subject' => 'Test Mailbox for Laravel',
            'date' => null,
            'from' => [
                [
                    'email' => 'hello@example.com',
                    'name' => 'Laravel',
                ],
            ],
            'sender' => [
                'name' => 'Laravel',
                'email' => 'hello@example.com',
            ],
            'to' => [
                [
                    'email' => 'recipient@example.com',
                ],
            ],
            'cc' => [],
            'bcc' => [],
            'reply_to' => [],
            'text' => null,
            'html' => '<h1>Hello from Mailbox for Laravel</h1><p>This is a test email.</p>',
            'headers' => [
                'MIME-Version' => '1.0',
                'Content-Type' => 'text/html; charset=utf-8',
                'Content-Transfer-Encoding' => 'quoted-printable',
            ],
            'attachments' => [],
            'raw' => <<<EOT
                From: Laravel <hello@example.com>
                To: recipient@example.com
                Subject: Test Mailbox for Laravel
                Message-ID: <9b1aef48dd4860ce51ee13539eb29205@example.com>
                MIME-Version: 1.0
                Date: {$now}
                Content-Type: text/html; charset=utf-8
                Content-Transfer-Encoding: quoted-printable

                <h1>Hello from Mailbox for Laravel</h1><p>This is a test email.</p>
                EOT,
        ];

        $key = $service->store($payload);

        return response()->json([
            'status' => 'stored',
            'key' => $key,
        ]);
    }

}
