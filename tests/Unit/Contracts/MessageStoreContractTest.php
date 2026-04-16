<?php

use Redberry\MailboxForLaravel\Contracts\MessageStore;

describe(MessageStore::class, function () {
    it('defines the full contract surface', function () {
        $methods = array_map(
            fn ($m) => $m->getName(),
            (new ReflectionClass(MessageStore::class))->getMethods()
        );

        expect($methods)->toBe([
            'store',
            'find',
            'findIdByMessageId',
            'paginate',
            'count',
            'update',
            'delete',
            'purgeOlderThan',
            'idsOlderThan',
            'clear',
        ]);
    });

    it('describes return types and error behavior', function () {
        $ref = new ReflectionClass(MessageStore::class);

        $storeReturnType = $ref->getMethod('store')->getReturnType();
        expect($storeReturnType)->not->toBeNull();
        // store returns the canonical id (string ULID) supplied by the caller
        expect($storeReturnType->__toString())->toBe('string');

        $findReturnType = $ref->getMethod('find')->getReturnType();
        // find returns ?array
        expect($findReturnType->allowsNull())->toBeTrue();

        $paginateReturnType = $ref->getMethod('paginate')->getReturnType();
        expect($paginateReturnType?->getName())->toBe('array');

        $findByMsgIdReturnType = $ref->getMethod('findIdByMessageId')->getReturnType();
        expect($findByMsgIdReturnType->allowsNull())->toBeTrue()
            ->and($findByMsgIdReturnType->getName())->toBe('string');
    });
});
