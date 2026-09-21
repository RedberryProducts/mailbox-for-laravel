<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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
        $result = $this->search->applyToQuery($query, '100%_done!');
        $sql = $result->toRawSql();

        expect($sql)->toContain("'%100!%!_done!!%' escape '!'");
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

describe('applyToQuery() wildcard and case semantics', function () {
    beforeEach(function () {
        config(['mailbox.store.database.connection' => 'testing']);
        $this->artisan('migrate', ['--database' => 'testing'])->run();

        MailboxMessage::query()->create([
            'id' => '01HTESTWILD000000000000001',
            'subject' => 'Report 100% complete',
            'from' => json_encode([['email' => 'john_doe@example.com', 'name' => 'John Doe']]),
            'to' => json_encode([['email' => 'Sales-Team@Example.com']]),
            'text' => 'Hello there',
            'timestamp' => time(),
        ]);

        MailboxMessage::query()->create([
            'id' => '01HTESTWILD000000000000002',
            'subject' => 'Report 10 of 20 complete',
            'from' => json_encode([['email' => 'johnxdoe@example.com']]),
            'to' => json_encode([['email' => 'other@example.com']]),
            'text' => 'Nothing here',
            'timestamp' => time(),
        ]);
    });

    it('matches the same records on the database driver as matches() does in memory', function (string $needle, array $expectedIds) {
        $ids = $this->search->applyToQuery(MailboxMessage::query(), $needle)->orderBy('id')->pluck('id')->all();

        expect($ids)->toBe($expectedIds);

        $payloads = MailboxMessage::query()->orderBy('id')->get()->map(fn ($m) => $m->toArray());
        $inMemoryIds = $payloads->filter(fn ($p) => $this->search->matches($p, $needle))->pluck('id')->values()->all();

        expect($inMemoryIds)->toBe($expectedIds);
    })->with([
        'underscore is literal' => ['john_doe', ['01HTESTWILD000000000000001']],
        'percent is literal' => ['100%', ['01HTESTWILD000000000000001']],
        'mixed case matches case-insensitively' => ['sales-team@example', ['01HTESTWILD000000000000001']],
        'plain term matches both' => ['complete', ['01HTESTWILD000000000000001', '01HTESTWILD000000000000002']],
    ]);
});

describe('applyToQuery() SQL per driver', function () {
    /**
     * Builds the query on a connection of the given driver without opening it
     * or touching the mailbox config: toSql() and getBindings() never use PDO.
     */
    function searchSqlOn(string $driver, string $needle = 'a_b'): array
    {
        config(["database.connections.grammar_{$driver}" => ['driver' => $driver, 'database' => ':memory:', 'host' => '127.0.0.1', 'username' => 'x', 'password' => 'x']]);

        $builder = (new Builder(DB::connection("grammar_{$driver}")->query()))->setModel(new MailboxMessage);
        $query = (new DefaultMessageSearch)->applyToQuery($builder, $needle);

        return [$query->toSql(), $query->getBindings()];
    }

    it('declares the escape character explicitly on sqlite, which has no default', function () {
        [$sql, $bindings] = searchSqlOn('sqlite');

        expect($sql)->toContain('"subject" like ? escape \'!\'')
            ->and($sql)->toContain('"from" like ? escape \'!\'')
            ->and($bindings)->toBe(array_fill(0, 5, '%a!_b%'));
    });

    it('casts columns to text and uses ilike on pgsql', function () {
        [$sql, $bindings] = searchSqlOn('pgsql');

        expect($sql)->toContain('"from"::text ilike ? escape \'!\'')
            ->and($sql)->toContain('"to"::text ilike ? escape \'!\'')
            ->and($sql)->toContain('"subject"::text ilike ? escape \'!\'')
            ->and($sql)->not->toContain(' like ')
            ->and($bindings)->toBe(array_fill(0, 5, '%a!_b%'));
    });

    it('also escapes the bracket wildcard on sqlsrv, where [ opens a character class', function () {
        [$sql, $bindings] = searchSqlOn('sqlsrv', 'a_[b]');

        expect($sql)->toContain("[subject] like ? escape '!'")
            ->and($bindings)->toBe(array_fill(0, 5, '%a!_![b]%'));
    });

    it('leaves brackets alone on drivers where they are literal', function () {
        [, $bindings] = searchSqlOn('sqlite', 'a_[b]');

        expect($bindings)->toBe(array_fill(0, 5, '%a!_[b]%'));
    });

    it('declares the escape character on mysql using its identifier quoting', function () {
        [$sql, $bindings] = searchSqlOn('mysql');

        expect($sql)->toContain('`subject` like ? escape \'!\'')
            ->and($sql)->toContain('`from` like ? escape \'!\'')
            ->and($bindings)->toBe(array_fill(0, 5, '%a!_b%'));
    });
});

describe('SEARCHABLE_FIELDS constant', function () {
    it('contains the canonical set of searchable fields', function () {
        expect(DefaultMessageSearch::SEARCHABLE_FIELDS)->toBe([
            'subject', 'from', 'to', 'html', 'text',
        ]);
    });
});
