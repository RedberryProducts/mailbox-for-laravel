<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Contracts\AttachmentStore;
use Redberry\MailboxForLaravel\Storage\FileStorage;
use Redberry\MailboxForLaravel\Transport\MailboxTransport;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

describe(MailboxTransport::class, function () {
    function transport(?CaptureService $svc = null, ?AttachmentStore $attachmentStore = null, ?TransportInterface $decorated = null, bool $enabled = true): MailboxTransport
    {
        $svc ??= new CaptureService(new FileStorage(sys_get_temp_dir().'/mailbox-transport-'.uniqid()));
        $attachmentStore ??= Mockery::mock(AttachmentStore::class)->shouldIgnoreMissing();
        if (! $decorated) {
            $decorated = Mockery::mock(TransportInterface::class);
            $decorated->shouldReceive('send')->andReturnNull();
        }

        return new MailboxTransport($svc, $attachmentStore, $decorated, $enabled);
    }

    it('sends messages through Symfony Transport while capturing raw', function () {
        $svc = Mockery::mock(CaptureService::class);
        $svc->shouldReceive('store')->once()->andReturn('key1');
        $decorated = Mockery::mock(TransportInterface::class);
        $decorated->shouldReceive('send')->once();

        $t = transport($svc, decorated: $decorated);
        $t->send((new Email)->from('a@example.com')->to('b@example.com')->text('hi'));
        expect($t->getStoredKey())->toBe('key1');
    });

    it('captures raw RFC822 content before delegating', function () {
        $svc = Mockery::mock(CaptureService::class);
        $decorated = Mockery::mock(TransportInterface::class);
        $svc->shouldReceive('store')->once()->ordered()->andReturn('key');
        $decorated->shouldReceive('send')->once()->ordered();
        $t = transport($svc, decorated: $decorated);
        $t->send((new Email)->from('a@example.com')->to('b@example.com')->text('hi'));
    });

    it('uses CaptureService->storeRaw and returns storage key', function () {
        $svc = new CaptureService(new FileStorage(sys_get_temp_dir().'/mailbox-transport-'.uniqid()));
        $t = transport($svc);
        $t->send((new Email)->from('a@example.com')->to('b@example.com')->text('hi'));
        expect($t->getStoredKey())->not->toBeNull();
    });

    it('does not call CaptureService when disabled via config', function () {
        $svc = Mockery::mock(CaptureService::class);
        $svc->shouldReceive('store')->never();
        $decorated = Mockery::mock(TransportInterface::class);
        $decorated->shouldReceive('send')->once();
        $t = transport($svc, decorated: $decorated, enabled: false);
        $t->send((new Email)->from('a@example.com')->to('b@example.com')->text('hi'));
        expect($t->getStoredKey())->toBeNull();
    });

    it('handles message with only text part', function () {
        $svc = new CaptureService(new FileStorage(sys_get_temp_dir().'/mailbox-transport-'.uniqid()));
        $t = transport($svc);
        $email = (new Email)->from('a@example.com')->to('b@example.com')->text('hello');
        $t->send($email);
        $payload = $svc->find($t->getStoredKey());
        expect($payload->text)->toBe('hello')
            ->and($payload->html)->toBeNull();
    });

    it('handles message with only html part', function () {
        $svc = new CaptureService(new FileStorage(sys_get_temp_dir().'/mailbox-transport-'.uniqid()));
        $t = transport($svc);
        $email = (new Email)->from('a@example.com')->to('b@example.com')->html('<p>hi</p>');
        $t->send($email);
        $payload = $svc->find($t->getStoredKey());
        expect($payload->html)->toBe('<p>hi</p>')
            ->and($payload->text)->toBeNull();
    });

    it('handles message with attachments', function () {
        $svc = new CaptureService(new FileStorage(sys_get_temp_dir().'/mailbox-transport-'.uniqid()));
        $t = transport($svc);
        $email = (new Email)->from('a@example.com')->to('b@example.com')->text('body');
        $email->attach('file-content', 'doc.txt', 'text/plain');
        $t->send($email);
        $payload = $svc->find($t->getStoredKey());
        expect($payload->attachments)->toHaveCount(1)
            ->and($payload->attachments[0]['filename'])->toBe('doc.txt');
    });

    it('handles inline cid images and preserves references', function () {
        $svc = new CaptureService(new FileStorage(sys_get_temp_dir().'/mailbox-transport-'.uniqid()));
        $t = transport($svc);
        $email = (new Email)->from('a@example.com')->to('b@example.com')->text('body');
        $part = (new DataPart('img', 'img.txt', 'text/plain'))->asInline();
        $part->setContentId('cid1@example.com');
        $email->addPart($part);
        $t->send($email);
        $payload = $svc->find($t->getStoredKey());
        expect($payload->attachments[0]['contentId'])->toBe('cid1@example.com');
    });

    it('throws a TransportException on underlying send failure and still does not corrupt store', function () {
        $svc = new CaptureService(new FileStorage(sys_get_temp_dir().'/mailbox-transport-'.uniqid()));
        $decorated = Mockery::mock(TransportInterface::class);
        $decorated->shouldReceive('send')->andThrow(new TransportException('fail'));
        $t = transport($svc, decorated: $decorated);
        $email = (new Email)->from('a@example.com')->to('b@example.com')->text('body');
        expect(fn () => $t->send($email))->toThrow(TransportException::class);
        // message stored despite failure
        expect($svc->find($t->getStoredKey())->text)->toBe('body');
    });

    describe('capture failures in decorate mode', function () {
        it('still forwards to the decorated transport when storing the message fails', function () {
            $failure = new RuntimeException('database is locked');
            $svc = Mockery::mock(CaptureService::class);
            $svc->shouldReceive('store')->once()->andThrow($failure);
            $decorated = Mockery::mock(TransportInterface::class);
            $decorated->shouldReceive('send')->once();
            $handler = Mockery::mock(ExceptionHandler::class);
            $handler->shouldReceive('report')->once()->with($failure);
            app()->instance(ExceptionHandler::class, $handler);

            $t = transport($svc, decorated: $decorated);
            $t->send((new Email)->from('a@example.com')->to('b@example.com')->text('hi'));

            expect($t->getStoredKey())->toBeNull();
        });

        it('still forwards to the decorated transport when storing an attachment fails', function () {
            $failure = new RuntimeException('disk is not writable');
            $svc = Mockery::mock(CaptureService::class);
            $svc->shouldReceive('store')->once()->andReturn('key1');
            $attachmentStore = Mockery::mock(AttachmentStore::class);
            $attachmentStore->shouldReceive('store')->once()->andThrow($failure);
            $decorated = Mockery::mock(TransportInterface::class);
            $decorated->shouldReceive('send')->once();
            $handler = Mockery::mock(ExceptionHandler::class);
            $handler->shouldReceive('report')->once()->with($failure);
            app()->instance(ExceptionHandler::class, $handler);

            $t = transport($svc, $attachmentStore, $decorated);
            $email = (new Email)->from('a@example.com')->to('b@example.com')->text('hi')
                ->addPart(new DataPart('data', 'file.txt', 'text/plain'));
            $t->send($email);

            expect($t->getStoredKey())->toBe('key1');
        });

        it('does not expose the previous message key when a later capture fails', function () {
            $svc = Mockery::mock(CaptureService::class);
            $svc->shouldReceive('store')->once()->ordered()->andReturn('first-key');
            $svc->shouldReceive('store')->once()->ordered()->andThrow(new RuntimeException('database is locked'));
            $decorated = Mockery::mock(TransportInterface::class);
            $decorated->shouldReceive('send')->twice();
            app()->instance(ExceptionHandler::class, Mockery::mock(ExceptionHandler::class)->shouldIgnoreMissing());

            $t = transport($svc, decorated: $decorated);
            $email = (new Email)->from('a@example.com')->to('b@example.com')->text('hi');

            $t->send($email);
            expect($t->getStoredKey())->toBe('first-key');

            $t->send($email);
            expect($t->getStoredKey())->toBeNull();
        });

        it('rethrows a capture failure in capture-only mode since nothing else delivers the message', function () {
            $failure = new RuntimeException('database is locked');
            $svc = Mockery::mock(CaptureService::class);
            $svc->shouldReceive('store')->once()->andThrow($failure);
            $handler = Mockery::mock(ExceptionHandler::class);
            $handler->shouldReceive('report')->never();
            app()->instance(ExceptionHandler::class, $handler);

            $attachmentStore = Mockery::mock(AttachmentStore::class)->shouldIgnoreMissing();
            $t = new MailboxTransport($svc, $attachmentStore, null, true);

            expect(fn () => $t->send((new Email)->from('a@example.com')->to('b@example.com')->text('hi')))
                ->toThrow(RuntimeException::class, 'database is locked');
        });
    });
});
