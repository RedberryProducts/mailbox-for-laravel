<?php

declare(strict_types=1);

use Redberry\MailboxForLaravel\Models\MailboxMessage;
use Redberry\MailboxForLaravel\Search\DefaultMessageSearch;

beforeEach(function () {
    $this->search = new DefaultMessageSearch;
});

describe('matches()', function () {
    it('returns true when the needle is empty', function () {
        expect($this->search->matches([], ''))->toBeTrue();
    });

    it('returns true when the needle is whitespace-only', function () {
        expect($this->search->matches([], '   '))->toBeTrue();
    });

    it('matches against the subject field case-insensitively', function () {
        $payload = ['subject' => 'Invoice for March'];

        expect($this->search->matches($payload, 'invoice'))->toBeTrue()
            ->and($this->search->matches($payload, 'MARCH'))->toBeTrue();
    });

    it('matches against the from field when it is a string', function () {
        $payload = ['from' => 'billing@acme.test'];

        expect($this->search->matches($payload, 'billing@acme'))->toBeTrue();
    });

    it('matches against the from field when it is an array', function () {
        $payload = ['from' => [['email' => 'billing@acme.test', 'name' => 'Billing']]];

        expect($this->search->matches($payload, 'billing@acme'))->toBeTrue()
            ->and($this->search->matches($payload, 'Billing'))->toBeTrue();
    });

    it('matches against the to field when it is an array', function () {
        $payload = ['to' => [['email' => 'user@example.com', 'name' => 'John']]];

        expect($this->search->matches($payload, 'user@example'))->toBeTrue();
    });

    it('matches against the text body', function () {
        $payload = ['text' => 'Please reset your password using the link below.'];

        expect($this->search->matches($payload, 'reset your password'))->toBeTrue();
    });

    it('returns false when no field contains the needle', function () {
        $payload = [
            'subject' => 'Hello',
            'from' => 'sender@test.com',
            'to' => [['email' => 'recipient@test.com']],
            'text' => 'Some body text',
        ];

        expect($this->search->matches($payload, 'nonexistent'))->toBeFalse();
    });

    it('handles missing and null fields gracefully', function () {
        expect($this->search->matches([], 'anything'))->toBeFalse()
            ->and($this->search->matches(['subject' => null], 'anything'))->toBeFalse();
    });
});

describe('applyToQuery()', function () {
    beforeEach(function () {
        config(['mailbox.store.database.connection' => 'testing']);
        $this->artisan('migrate', ['--database' => 'testing'])->run();
    });

    it('returns the query unchanged when the needle is empty', function () {
        $query = MailboxMessage::query();
        $result = $this->search->applyToQuery($query, '');

        expect($result->toRawSql())->toBe($query->toRawSql());
    });

    it('returns the query unchanged when the needle is whitespace-only', function () {
        $query = MailboxMessage::query();
        $result = $this->search->applyToQuery($query, '   ');

        expect($result->toRawSql())->toBe($query->toRawSql());
    });

    it('adds LIKE clauses for all searchable fields', function () {
        $query = MailboxMessage::query();
        $result = $this->search->applyToQuery($query, 'test');
        $sql = $result->toRawSql();

        expect($sql)->toContain("'%test%'")
            ->and($sql)->toContain('subject')
            ->and($sql)->toContain('from')
            ->and($sql)->toContain('to')
            ->and($sql)->toContain('text');
    });

    it('escapes percent and underscore wildcards in the needle', function () {
        $query = MailboxMessage::query();
        $result = $this->search->applyToQuery($query, '100%_done');
        $sql = $result->toRawSql();

        expect($sql)->toContain('100\\%\\_done');
    });

    it('filters matching records from the database', function () {
        MailboxMessage::query()->create([
            'id' => '01HTESTMATCH00000000000001',
            'subject' => 'Invoice for March',
            'from' => json_encode([['email' => 'billing@acme.test']]),
            'to' => json_encode([['email' => 'user@test.com']]),
            'text' => 'Please pay the invoice.',
            'timestamp' => time(),
        ]);

        MailboxMessage::query()->create([
            'id' => '01HTESTMATCH00000000000002',
            'subject' => 'Welcome aboard',
            'from' => json_encode([['email' => 'hr@acme.test']]),
            'to' => json_encode([['email' => 'newbie@test.com']]),
            'text' => 'Welcome to the team.',
            'timestamp' => time(),
        ]);

        $query = MailboxMessage::query();
        $results = $this->search->applyToQuery($query, 'invoice')->get();

        expect($results)->toHaveCount(1)
            ->and($results->first()->id)->toBe('01HTESTMATCH00000000000001');
    });
});

describe('SEARCHABLE_FIELDS constant', function () {
    it('contains the canonical set of searchable fields', function () {
        expect(DefaultMessageSearch::SEARCHABLE_FIELDS)->toBe([
            'subject', 'from', 'to', 'html', 'text',
        ]);
    });
});
