Scaffold a new controller for the mailbox package. For the given name:

1. **Create the controller** at `src/Http/Controllers/{Name}Controller.php`
   - Namespace: `Redberry\MailboxForLaravel\Http\Controllers`
   - Add `declare(strict_types=1)`
   - Use constructor injection for dependencies
   - Return `Inertia::render()` for page responses or `JsonResponse` for API endpoints
   - Keep the controller thin — delegate logic to `CaptureService` or other services

2. **Add route** in `routes/mailbox.php` inside the existing middleware group
   - Use RESTful HTTP methods (GET for reads, POST for creates, PUT/PATCH for updates, DELETE for deletes)
   - Add a named route with `mailbox.` prefix

3. **Create feature test** at `tests/Feature/{Name}ControllerTest.php`
   - Use Pest `describe()` and `it()` syntax
   - Test happy path, validation errors, and authorization
   - Use named routes: `route('mailbox.{name}')`
   - Use `assertInertia()` for Inertia responses

4. **Run QA**: `composer format && composer analyse && composer test`
