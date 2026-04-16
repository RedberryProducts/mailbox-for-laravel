<?php

declare(strict_types=1);

use Redberry\MailboxForLaravel\Contracts\MessageSearch;

describe(MessageSearch::class, function () {
    it('defines the full contract surface', function () {
        $methods = array_map(
            fn ($m) => $m->getName(),
            (new ReflectionClass(MessageSearch::class))->getMethods()
        );

        expect($methods)->toBe([
            'matches',
            'applyToQuery',
        ]);
    });

    it('describes correct parameter and return types', function () {
        $ref = new ReflectionClass(MessageSearch::class);

        $matches = $ref->getMethod('matches');
        expect($matches->getReturnType()?->__toString())->toBe('bool')
            ->and($matches->getParameters())->toHaveCount(2)
            ->and($matches->getParameters()[0]->getType()?->__toString())->toBe('array')
            ->and($matches->getParameters()[1]->getType()?->__toString())->toBe('string');

        $applyToQuery = $ref->getMethod('applyToQuery');
        expect($applyToQuery->getParameters())->toHaveCount(2)
            ->and($applyToQuery->getParameters()[1]->getType()?->__toString())->toBe('string');
    });
});
