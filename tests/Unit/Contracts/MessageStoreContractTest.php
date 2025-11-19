<?php

use Redberry\MailboxForLaravel\Contracts\MessageStore;

describe(MessageStore::class, function () {
    it('defines required methods: store, find, paginate, count, update, delete, purgeOlderThan, clear', function () {
        $methods = array_map(
            fn ($m) => $m->getName(),
            (new ReflectionClass(MessageStore::class))->getMethods()
        );

        expect($methods)->toBe([
            'store',
            'find',
            'paginate',
            'count',
            'update',
            'delete',
            'purgeOlderThan',
            'clear',
        ]);
    });

    it('describes return types and error behavior', function () {
        $ref = new ReflectionClass(MessageStore::class);

        $storeReturnType = $ref->getMethod('store')->getReturnType();
        expect($storeReturnType)->not->toBeNull();
        // store returns string|int
        expect($storeReturnType->__toString())->toBe('string|int');

        $findReturnType = $ref->getMethod('find')->getReturnType();
        // find returns ?array
        expect($findReturnType->allowsNull())->toBeTrue();

        $paginateReturnType = $ref->getMethod('paginate')->getReturnType();
        expect($paginateReturnType?->getName())->toBe('array');
    });
});
