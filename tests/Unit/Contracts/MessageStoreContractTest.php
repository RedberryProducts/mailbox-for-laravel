<?php

use ReflectionClass;
use Redberry\MailboxForLaravel\Contracts\MessageStore;

describe(MessageStore::class, function () {
    it('defines required methods: store, retrieve, keys, delete, purgeOlderThan', function () {
        $methods = array_map(
            fn ($m) => $m->getName(),
            (new ReflectionClass(MessageStore::class))->getMethods()
        );

        expect($methods)->toBe([
            'store',
            'retrieve',
            'keys',
            'delete',
            'purgeOlderThan',
        ]);
    });

    it('describes return types and error behavior', function () {
        $ref = new ReflectionClass(MessageStore::class);
        $store = $ref->getMethod('store')->getReturnType();
        expect($store)->not->toBeNull();
        expect($store->getName())->toBe('void');

        $retrieve = $ref->getMethod('retrieve')->getReturnType();
        expect($retrieve?->getName())->toBe('array');
        expect($retrieve?->allowsNull())->toBeTrue();

        expect($ref->getMethod('keys')->getReturnType()?->getName())->toBe('iterable');
    });
});
