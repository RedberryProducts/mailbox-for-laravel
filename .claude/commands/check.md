Run the full QA pipeline for mailbox-for-laravel:

1. `cd` to `packages/redberry/mailbox-for-laravel`
2. Run: `composer format` (Laravel Pint — fixes code style)
3. Run: `composer analyse` (PHPStan level 5 — static analysis)
4. Run: `composer test` (Pest — test suite)

Report results from each step. If any step fails, stop and report the failure with details.
