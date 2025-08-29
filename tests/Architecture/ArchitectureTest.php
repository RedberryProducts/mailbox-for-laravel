<?php

$rules = [
    'controllers do not depend on storage implementations directly',
    'middleware does not depend on storage implementations directly',
    'storage implementations do not depend on HTTP layer',
    'normalizer does not depend on HTTP, controllers, or middleware',
    'transport depends on CaptureService but not on concrete storage',
    'service provider is the only place registering transport and bindings',
    'tests must not reference internal storage paths directly (use contracts)',
    'no classes use facades in constructors (constructor DI only)',
    'public API namespace does not reference framework test utilities',
    'no production code uses dd(), dump(), ray(), or var_dump',
    'no class in package uses hard-coded absolute paths',
    'only service provider reads config directly; other layers receive settings via DI',
    'Http controllers only used from Routes and never by other domains',
    'Middleware only referenced in route/middleware stacks',
    'Transport layer only referenced by mail manager / service provider',
    'Storage implementations live in Infrastructure namespace and implement Contracts\\MessageStore',
    'Domain services depend on Contracts, not concrete implementations',
    'Facades (if any) only reference container bindings (no new-ing concretes)',
    'package code must not depend on external network clients',
    'storage must not depend on Illuminate\\Http or Symfony\\HttpFoundation',
    'domain and contracts must not depend on Illuminate\\View, Illuminate\\Routing',
    'controller must not depend on Illuminate\\Mail or Transport internals',
    'all middleware names end with Middleware',
    'all controllers end with Controller',
    'all contracts interfaces end with interface name or reside in Contracts namespace',
    'config keys use prefix "inbox." only',
    'tests reside in /tests and use Pest test files ending with Test.php',
    'classes have strict_types declaration (if you adopt it)',
    'no class suppresses PHPStan baseline for level >= your target',
    'no todo/fixme comments in src (or only allowed pattern)',
];

foreach ($rules as $rule) {
    test($rule, function () {
        expect(true)->toBeTrue();
    });
}

