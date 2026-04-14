Run feature tests for the mailbox package.

If an argument is provided (e.g., `MailboxController`), run the matching feature test:

```bash
cd packages/redberry/mailbox-for-laravel
vendor/bin/pest tests/Feature/{argument}Test.php
```

If no argument is provided, run all feature tests:

```bash
cd packages/redberry/mailbox-for-laravel
vendor/bin/pest tests/Feature/
```

Report test results including pass/fail counts and any failure details.
