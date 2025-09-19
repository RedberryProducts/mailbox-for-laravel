# Testing Guide for mailbox-for-laravel

This guide provides practical instructions for writing effective tests in the mailbox-for-laravel package.

## Quick Start

### Running Tests

```bash
# Run all tests
composer test

# Run specific test suites
vendor/bin/pest --testsuite=Unit
vendor/bin/pest --testsuite=Feature
vendor/bin/pest --testsuite=Architecture

# Run tests with coverage
composer test-coverage

# Run specific test file
vendor/bin/pest tests/Unit/CaptureServiceTest.php

# Run specific test
vendor/bin/pest --filter="stores message and returns key"
```

### Writing Your First Test

1. **Create the test file** in the appropriate directory:
   - `tests/Unit/` for isolated unit tests
   - `tests/Feature/` for integration/feature tests
   - `tests/Architecture/` for architectural rules

2. **Use the standard structure**:

```php
<?php

use YourClassNamespace\ClassName;

describe(ClassName::class, function () {
    it('describes what the test validates', function () {
        // Arrange
        $input = 'test data';
        
        // Act
        $result = ClassName::method($input);
        
        // Assert
        expect($result)->toBe('expected output');
    });
});
```

## Test Organization Patterns

### Group Related Tests

```php
describe(CaptureService::class, function () {
    describe('message storage', function () {
        it('stores simple text messages', function () {
            // Test implementation
        });
        
        it('stores messages with attachments', function () {
            // Test implementation
        });
    });
    
    describe('message retrieval', function () {
        it('retrieves existing messages', function () {
            // Test implementation
        });
        
        it('returns null for non-existent messages', function () {
            // Test implementation
        });
    });
});
```

### Use Helper Functions for Setup

```php
describe(FileStorage::class, function () {
    function createTempStorage(): FileStorage {
        $path = sys_get_temp_dir() . '/test-' . uniqid();
        @mkdir($path, 0777, true);
        return new FileStorage($path);
    }
    
    function createTestMessage(array $overrides = []): array {
        return array_merge([
            'subject' => 'Test Subject',
            'text' => 'Test body',
            'from' => [['address' => 'test@example.com']],
        ], $overrides);
    }
    
    it('stores and retrieves messages', function () {
        $storage = createTempStorage();
        $message = createTestMessage(['subject' => 'Custom']);
        
        $storage->store('key1', $message);
        $result = $storage->retrieve('key1');
        
        expect($result['subject'])->toBe('Custom');
    });
});
```

### Use beforeEach and afterEach for State Management

```php
describe(CaptureService::class, function () {
    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/test-' . uniqid();
        $this->storage = new FileStorage($this->tempDir);
        $this->service = new CaptureService($this->storage);
    });
    
    afterEach(function () {
        // Clean up
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*'));
            rmdir($this->tempDir);
        }
    });
    
    it('stores messages correctly', function () {
        $key = $this->service->store(['text' => 'test']);
        expect($key)->not->toBeEmpty();
    });
});
```

## Common Testing Patterns

### 1. Testing with Mock Objects

```php
it('notifies listeners when message is stored', function () {
    $mockNotifier = Mockery::mock(NotificationService::class);
    $mockNotifier->shouldReceive('notify')
        ->once()
        ->with(Mockery::type('array'));
    
    $service = new CaptureService($storage, $mockNotifier);
    $service->store(['text' => 'test']);
    
    // Mockery automatically verifies expectations
});
```

### 2. Testing Exceptions

```php
it('throws exception for invalid storage path', function () {
    expect(fn() => new FileStorage('/invalid/path'))
        ->toThrow(StorageException::class, 'Cannot create storage directory');
});

it('handles storage failures gracefully', function () {
    $mockStorage = Mockery::mock(MessageStore::class);
    $mockStorage->shouldReceive('store')->andThrow(new Exception('Storage failed'));
    
    $service = new CaptureService($mockStorage);
    
    expect(fn() => $service->store(['text' => 'test']))
        ->toThrow(Exception::class, 'Storage failed');
});
```

### 3. Testing HTTP Endpoints

```php
it('returns paginated inbox messages', function () {
    // Setup test data
    $service = app(CaptureService::class);
    $service->store(['subject' => 'Test 1']);
    $service->store(['subject' => 'Test 2']);
    
    $response = $this->get('/mailbox?page=1&per_page=10');
    
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['subject', 'text', 'from']
            ],
            'total',
            'per_page',
            'current_page'
        ])
        ->assertJsonCount(2, 'data');
});
```

### 4. Testing Authorization

```php
it('denies access when not authorized', function () {
    Gate::shouldReceive('allows')
        ->with('viewMailbox')
        ->andReturn(false);
    
    config()->set('inbox.public', false);
    
    $this->get('/mailbox')->assertForbidden();
});
```

### 5. Testing Configuration

```php
it('uses configured storage driver', function () {
    config()->set('inbox.store.driver', 'file');
    config()->set('inbox.store.file.path', '/tmp/test');
    
    $manager = app(StoreManager::class);
    $store = $manager->create();
    
    expect($store)->toBeInstanceOf(FileStorage::class);
});
```

## Using Test Helpers and Factories

### MessageFactory Usage

```php
use Redberry\MailboxForLaravel\Tests\Helpers\MessageFactory;

it('processes messages with attachments', function () {
    $message = MessageFactory::withAttachment([
        'filename' => 'document.pdf',
        'contentType' => 'application/pdf'
    ]);
    
    $service = createTestService();
    $key = $service->store($message);
    $retrieved = $service->get($key);
    
    expect($retrieved['attachments'][0]['filename'])->toBe('document.pdf');
});
```

### Custom Expectations

```php
it('validates email format in from field', function () {
    $message = MessageFactory::create([
        'from' => [['address' => 'valid@example.com']]
    ]);
    
    expect($message['from'][0]['address'])->toBeValidEmail();
});

it('returns valid message structure', function () {
    $service = createTestService();
    $key = $service->store(MessageFactory::create());
    $result = $service->get($key);
    
    expect($result)->toHaveValidMessageStructure();
});
```

## Testing Best Practices

### 1. Write Descriptive Test Names

```php
// ✅ Good
it('normalizes email with both text and html content preserving both formats', function () {
    // Test implementation
});

// ❌ Bad
it('tests email normalization', function () {
    // Test implementation
});
```

### 2. Follow the AAA Pattern

```php
it('stores message and generates unique key', function () {
    // Arrange
    $service = createTestService();
    $message = ['text' => 'test message'];
    
    // Act
    $key = $service->store($message);
    
    // Assert
    expect($key)->not->toBeEmpty()
        ->and($service->get($key)['text'])->toBe('test message');
});
```

### 3. Test Edge Cases

```php
describe('edge cases', function () {
    it('handles empty message gracefully', function () {
        $service = createTestService();
        expect(fn() => $service->store([]))->not->toThrow();
    });
    
    it('handles null values in message fields', function () {
        $message = ['subject' => null, 'text' => 'content'];
        $service = createTestService();
        $key = $service->store($message);
        
        expect($service->get($key)['subject'])->toBeNull();
    });
});
```

### 4. Use Data Providers for Multiple Scenarios

```php
it('handles various email formats', function (array $emailData, string $expectedFormat) {
    $normalizer = new MessageNormalizer();
    $result = $normalizer->normalize($emailData);
    
    expect($result['format'])->toBe($expectedFormat);
})->with([
    [['text' => 'content'], 'text'],
    [['html' => '<p>content</p>'], 'html'],
    [['text' => 'content', 'html' => '<p>content</p>'], 'multipart'],
]);
```

### 5. Isolate External Dependencies

```php
// ✅ Good - isolated test
it('processes message without external dependencies', function () {
    $mockStorage = Mockery::mock(MessageStore::class);
    $mockStorage->shouldReceive('store')->once()->andReturn('key123');
    
    $service = new CaptureService($mockStorage);
    $result = $service->store(['text' => 'test']);
    
    expect($result)->toBe('key123');
});

// ❌ Bad - depends on file system
it('processes message', function () {
    $service = new CaptureService(new FileStorage('/tmp/test'));
    // Test might fail if /tmp is not writable
});
```

## Testing Specific Components

### Testing Commands

```php
describe(InstallCommand::class, function () {
    it('publishes assets successfully', function () {
        $this->artisan('mailbox:install')
            ->expectsOutput('Mailbox assets published.')
            ->assertExitCode(0);
    });
    
    it('handles force option correctly', function () {
        $this->artisan('mailbox:install --force')
            ->assertExitCode(0);
    });
});
```

### Testing Middleware

```php
describe(AuthorizeInboxMiddleware::class, function () {
    beforeEach(function () {
        Route::get('/test', fn() => 'ok')
            ->middleware(AuthorizeInboxMiddleware::class);
    });
    
    it('allows authorized users', function () {
        Gate::shouldReceive('allows')
            ->with('viewMailbox')
            ->andReturn(true);
        
        $this->get('/test')->assertOk();
    });
});
```

### Testing Service Providers

```php
describe(InboxServiceProvider::class, function () {
    it('registers required services', function () {
        expect(app()->bound(CaptureService::class))->toBeTrue()
            ->and(app()->bound(MessageStore::class))->toBeTrue();
    });
    
    it('publishes configuration', function () {
        expect(config('inbox.store.driver'))->toBe('file');
    });
});
```

## Performance Testing Guidelines

### Basic Performance Tests

```php
it('processes messages within time limit', function () {
    $service = createTestService();
    $message = MessageFactory::large();
    
    $startTime = microtime(true);
    $key = $service->store($message);
    $endTime = microtime(true);
    
    expect($endTime - $startTime)->toBeLessThan(1.0); // Max 1 second
    expect($key)->not->toBeEmpty();
});
```

### Memory Usage Tests

```php
it('maintains reasonable memory usage', function () {
    $service = createTestService();
    $startMemory = memory_get_peak_usage();
    
    // Perform operations
    for ($i = 0; $i < 100; $i++) {
        $service->store(MessageFactory::create());
    }
    
    $endMemory = memory_get_peak_usage();
    $memoryUsed = $endMemory - $startMemory;
    
    expect($memoryUsed)->toBeLessThan(10 * 1024 * 1024); // Less than 10MB
});
```

## Debugging Tests

### Using Debug Output

```php
it('debugs message processing', function () {
    $message = MessageFactory::create();
    
    // Add debug output
    dump($message); // Shows in test output
    
    $service = createTestService();
    $key = $service->store($message);
    
    expect($key)->not->toBeEmpty();
});
```

### Using dd() for Test Development

```php
it('explores data structure', function () {
    $service = createTestService();
    $key = $service->store(MessageFactory::create());
    $result = $service->get($key);
    
    dd($result); // Stops execution and shows data
    
    expect($result)->toHaveValidMessageStructure();
});
```

## Common Pitfalls and Solutions

### 1. Tests Affecting Each Other

```php
// ❌ Problem: Tests share state
static $sharedService;

// ✅ Solution: Use beforeEach for fresh state
beforeEach(function () {
    $this->service = createTestService();
});
```

### 2. Hard-coded Paths

```php
// ❌ Problem: Hard-coded paths
$storage = new FileStorage('/tmp/mailbox');

// ✅ Solution: Use temporary directories
$storage = new FileStorage(sys_get_temp_dir() . '/test-' . uniqid());
```

### 3. Not Cleaning Up Resources

```php
// ✅ Solution: Always clean up
afterEach(function () {
    if (isset($this->tempDir) && is_dir($this->tempDir)) {
        array_map('unlink', glob($this->tempDir . '/*'));
        rmdir($this->tempDir);
    }
});
```

### 4. Over-mocking

```php
// ❌ Problem: Mocking everything
$mockEverything = Mockery::mock('everything');

// ✅ Solution: Mock only external dependencies
$mockStorage = Mockery::mock(MessageStore::class);
$realService = new CaptureService($mockStorage);
```

## Continuous Integration

### Test Pipeline Configuration

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.3'
        
    - name: Install dependencies
      run: composer install --prefer-dist --no-progress
      
    - name: Run tests
      run: composer test
      
    - name: Run static analysis
      run: composer analyse
      
    - name: Check code style
      run: composer format --test
```

### Coverage Requirements

```php
// phpunit.xml.dist
<coverage includeUncoveredFiles="true">
    <include>
        <directory suffix=".php">./src</directory>
    </include>
    <report>
        <clover outputFile="build/coverage.xml"/>
        <html outputDirectory="build/coverage"/>
    </report>
</coverage>
```

This guide provides the foundation for writing maintainable, reliable tests that follow modern best practices. Remember to update tests when requirements change and always consider the balance between test coverage and maintainability.