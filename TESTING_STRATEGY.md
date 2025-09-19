# Testing Strategy for mailbox-for-laravel

## Overview

This document outlines the comprehensive testing strategy for the mailbox-for-laravel package, including naming conventions, test organization, and best practices to ensure high-quality, maintainable tests that follow modern testing standards.

## Testing Framework Stack

- **Primary Framework**: Pest PHP v3+ with describe/it syntax
- **Laravel Testing**: Orchestra Testbench for package testing
- **Mocking**: Mockery for test doubles and mocks
- **Architecture Testing**: Pest Arch plugin for architectural constraints
- **Static Analysis**: PHPStan for type safety
- **Code Style**: Laravel Pint for consistent formatting

## Test Directory Structure

```
tests/
├── Architecture/           # Architectural rules and constraints
│   └── ArchitectureTest.php
├── Feature/               # End-to-end feature tests
│   ├── Http/             # HTTP layer tests
│   └── Integration/      # Integration tests
├── Unit/                 # Isolated unit tests
│   ├── Commands/         # Console command tests
│   ├── Contracts/        # Interface/contract tests
│   ├── Http/            # HTTP component tests
│   ├── Storage/         # Storage implementation tests
│   ├── Support/         # Support utility tests
│   └── Transport/       # Mail transport tests
├── Fixtures/            # Test data and fixtures
├── Helpers/             # Test helper classes
├── Pest.php            # Pest configuration
└── TestCase.php        # Base test case
```

## Naming Conventions

### Test File Names

- **Pattern**: `{ClassName}Test.php`
- **Examples**: 
  - `CaptureServiceTest.php`
  - `FileStorageTest.php`  
  - `InboxControllerTest.php`
  - `MessageNormalizerTest.php`

### Test Method Names (describe/it structure)

```php
describe(ClassName::class, function () {
    it('describes what the test validates in present tense', function () {
        // Test implementation
    });
    
    it('handles edge case or error condition', function () {
        // Test implementation
    });
});
```

### Helper Function Names

- **Pattern**: `camelCase` descriptive names
- **Examples**: 
  - `createTestMessage()`
  - `mockCaptureService()`
  - `setupFileStorage()`

## Test Categories and Organization

### 1. Unit Tests (`tests/Unit/`)

**Purpose**: Test individual classes and methods in isolation

**Characteristics**:
- Fast execution (< 100ms per test)
- No external dependencies (database, filesystem, network)
- Use mocks/stubs for dependencies
- Focus on single responsibility

**Structure Example**:
```php
<?php

use Redberry\MailboxForLaravel\Support\MessageNormalizer;
use Symfony\Component\Mime\Email;

describe(MessageNormalizer::class, function () {
    it('normalizes a simple text-only email', function () {
        $email = (new Email)
            ->from('alice@example.com')
            ->to('bob@example.com')
            ->subject('Test')
            ->text('body');

        $result = MessageNormalizer::normalize($email);

        expect($result['text'])->toBe('body')
            ->and($result['from'])->toContain(['address' => 'alice@example.com']);
    });
    
    it('handles missing subject gracefully', function () {
        $email = (new Email)
            ->from('test@example.com')
            ->to('user@example.com')
            ->text('content');

        $result = MessageNormalizer::normalize($email);

        expect($result['subject'])->toBeNull();
    });
});
```

### 2. Feature Tests (`tests/Feature/`)

**Purpose**: Test complete features and user workflows

**Characteristics**:
- Test HTTP endpoints end-to-end
- Include middleware, controllers, and services
- Use real Laravel application context
- May use temporary storage/database

**Structure Example**:
```php
<?php

use Illuminate\Support\Facades\Route;
use Redberry\MailboxForLaravel\Http\Controllers\InboxController;

describe(InboxController::class, function () {
    beforeEach(function () {
        Route::get('/mailbox', InboxController::class);
    });

    it('displays inbox with paginated messages', function () {
        // Setup test data
        $service = app(CaptureService::class);
        $service->store(['subject' => 'Test 1', 'text' => 'Body 1']);
        $service->store(['subject' => 'Test 2', 'text' => 'Body 2']);

        $response = $this->get('/mailbox');

        $response->assertOk()
            ->assertSee('Test 1')
            ->assertSee('Test 2');
    });
});
```

### 3. Architecture Tests (`tests/Architecture/`)

**Purpose**: Enforce architectural rules and design constraints

**Characteristics**:
- Validate dependency direction
- Ensure separation of concerns
- Check naming conventions
- Verify interface implementations

**Structure Example**:
```php
<?php

describe('Architecture Rules', function () {
    it('ensures controllers do not depend on storage implementations directly', function () {
        expect('Redberry\MailboxForLaravel\Http\Controllers')
            ->not->toUse('Redberry\MailboxForLaravel\Storage\FileStorage');
    });
    
    it('ensures all contracts end with interface or reside in Contracts namespace', function () {
        expect('Redberry\MailboxForLaravel\Contracts')
            ->toBeInterfaces();
    });
});
```

## Test Case Scenarios

### Core Component Tests

#### 1. CaptureService Tests
- ✅ Store message and return unique key
- ✅ Retrieve stored message by key
- ✅ List all messages with pagination
- ✅ Delete message by key
- ✅ Handle invalid keys gracefully
- ⚠️  **Missing**: Bulk operations, filtering, search
- ⚠️  **Missing**: Performance with large datasets
- ⚠️  **Missing**: Concurrent access scenarios

#### 2. Storage Layer Tests
- ✅ FileStorage CRUD operations
- ✅ Key sanitization for security
- ✅ Purge old messages functionality
- ⚠️  **Missing**: Database storage implementation
- ⚠️  **Missing**: Storage driver switching
- ⚠️  **Missing**: Storage failure scenarios

#### 3. Message Processing Tests
- ✅ MessageNormalizer for various email formats
- ✅ Attachment handling (inline and attachments)
- ✅ Content-ID preservation
- ⚠️  **Missing**: Large message handling
- ⚠️  **Missing**: Malformed email handling
- ⚠️  **Missing**: Character encoding edge cases

#### 4. HTTP Layer Tests
- ✅ AssetController serving attachments
- ✅ Authorization middleware
- ⚠️  **Missing**: InboxController comprehensive tests
- ⚠️  **Missing**: API endpoint tests
- ⚠️  **Missing**: Error handling scenarios

#### 5. Transport Layer Tests
- ⚠️  **Missing**: InboxTransport comprehensive tests
- ⚠️  **Missing**: Mail capture scenarios
- ⚠️  **Missing**: Integration with Laravel Mail

### Test Scenarios by Category

#### Happy Path Tests
- Valid inputs with expected outputs
- Standard workflow completion
- Successful data persistence and retrieval

#### Edge Case Tests
- Empty/null inputs
- Boundary value testing
- Large data sets
- Unicode and special characters

#### Error Handling Tests
- Invalid inputs and malformed data
- Resource unavailability (disk space, permissions)
- Network failures and timeouts
- Dependency injection failures

#### Security Tests
- Path traversal prevention
- Input sanitization
- Authorization bypass attempts
- XSS and injection prevention

#### Performance Tests
- Large message handling
- Bulk operations
- Memory usage optimization
- Concurrent access patterns

## Best Practices Guide

### 1. Test Structure and Organization

#### Use Descriptive Test Names
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

#### Group Related Tests
```php
describe(CaptureService::class, function () {
    describe('message storage', function () {
        it('stores message and returns unique key', function () {
            // Test implementation
        });
        
        it('prevents duplicate keys when storing simultaneously', function () {
            // Test implementation
        });
    });
    
    describe('message retrieval', function () {
        it('retrieves stored message by exact key match', function () {
            // Test implementation
        });
        
        it('returns null for non-existent keys', function () {
            // Test implementation
        });
    });
});
```

#### Use Helper Functions for Setup
```php
describe(FileStorage::class, function () {
    function createTestStorage(): FileStorage 
    {
        $tmpDir = sys_get_temp_dir() . '/mailbox-test-' . uniqid();
        @mkdir($tmpDir, 0777, true);
        return new FileStorage($tmpDir);
    }
    
    function createTestMessage(array $overrides = []): array
    {
        return array_merge([
            'subject' => 'Test Subject',
            'text' => 'Test body',
            'from' => [['address' => 'test@example.com']],
            'timestamp' => time(),
        ], $overrides);
    }
    
    it('stores and retrieves messages correctly', function () {
        $storage = createTestStorage();
        $message = createTestMessage(['subject' => 'Custom Subject']);
        
        $storage->store('key1', $message);
        
        expect($storage->retrieve('key1')['subject'])->toBe('Custom Subject');
    });
});
```

### 2. Mocking and Test Doubles

#### Mock External Dependencies
```php
it('sends notification when message is captured', function () {
    $mockNotifier = Mockery::mock(NotificationService::class);
    $mockNotifier->shouldReceive('notify')
        ->once()
        ->with(Mockery::type('array'));
    
    $service = new CaptureService($storage, $mockNotifier);
    $service->store(['subject' => 'Test']);
    
    // Assertion is in the mock expectation
});
```

#### Use Dependency Injection
```php
// ✅ Good - testable
class CaptureService 
{
    public function __construct(
        private MessageStore $storage,
        private NotificationService $notifier
    ) {}
}

// ❌ Bad - hard to test
class CaptureService 
{
    public function store($message) 
    {
        $storage = new FileStorage('/var/mailbox'); // Hard-coded dependency
        // ...
    }
}
```

### 3. Assertion Patterns

#### Use Fluent Assertions
```php
// ✅ Good
expect($result)
    ->toBeArray()
    ->toHaveKey('subject')
    ->and($result['subject'])->toBe('Expected Subject')
    ->and($result['attachments'])->toHaveCount(2);

// ✅ Also good for complex validations
expect($payload)
    ->toMatchArray([
        'version' => 1,
        'text' => 'Expected text',
        'html' => null,
    ])
    ->and($payload['saved_at'])->toMatch('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/')
    ->and($payload['attachments'])->each->toHaveKeys(['filename', 'contentType']);
```

#### Test Both Success and Failure Cases
```php
describe('message validation', function () {
    it('accepts valid message format', function () {
        $message = ['subject' => 'Test', 'text' => 'Body'];
        
        expect(fn() => MessageValidator::validate($message))
            ->not->toThrow();
    });
    
    it('rejects message without required fields', function () {
        $message = ['text' => 'Body']; // Missing subject
        
        expect(fn() => MessageValidator::validate($message))
            ->toThrow(ValidationException::class, 'Subject is required');
    });
});
```

### 4. Environment and State Management

#### Isolate Tests with Fresh State
```php
describe(CaptureService::class, function () {
    beforeEach(function () {
        // Reset state before each test
        $this->tempDir = sys_get_temp_dir() . '/test-' . uniqid();
        @mkdir($this->tempDir, 0777, true);
        $this->storage = new FileStorage($this->tempDir);
        $this->service = new CaptureService($this->storage);
    });
    
    afterEach(function () {
        // Clean up after each test
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*'));
            rmdir($this->tempDir);
        }
    });
});
```

#### Use Configuration for Test Variations
```php
it('respects storage configuration', function () {
    config()->set('inbox.store.driver', 'file');
    config()->set('inbox.store.path', '/tmp/test-inbox');
    
    $manager = app(StoreManager::class);
    $store = $manager->create();
    
    expect($store)->toBeInstanceOf(FileStorage::class);
});
```

### 5. Performance Testing Guidelines

#### Measure Resource Usage
```php
it('handles large messages efficiently', function () {
    $largeMessage = [
        'text' => str_repeat('A', 1024 * 1024), // 1MB text
        'attachments' => [
            ['content' => base64_encode(str_repeat('B', 1024 * 1024))] // 1MB attachment
        ]
    ];
    
    $startMemory = memory_get_peak_usage();
    $startTime = microtime(true);
    
    $service = createTestService();
    $key = $service->store($largeMessage);
    $retrieved = $service->retrieve($key);
    
    $endTime = microtime(true);
    $endMemory = memory_get_peak_usage();
    
    expect($retrieved['text'])->toBe($largeMessage['text'])
        ->and($endTime - $startTime)->toBeLessThan(1.0) // Max 1 second
        ->and($endMemory - $startMemory)->toBeLessThan(10 * 1024 * 1024); // Max 10MB extra
});
```

### 6. Error Testing Patterns

#### Test Exception Handling
```php
it('throws appropriate exception for invalid storage path', function () {
    expect(fn() => new FileStorage('/invalid/readonly/path'))
        ->toThrow(StorageException::class, 'Cannot write to storage path');
});

it('gracefully handles corrupted message files', function () {
    $storage = createTestStorage();
    
    // Manually corrupt a file
    file_put_contents($storage->getPath() . '/test.json', 'invalid json');
    
    expect($storage->retrieve('test'))->toBeNull();
});
```

## Testing Workflow

### Development Workflow
1. **Red**: Write a failing test first (TDD approach)
2. **Green**: Write minimal code to make the test pass
3. **Refactor**: Improve code while keeping tests green
4. **Repeat**: Continue with next feature/requirement

### Pre-commit Checks
```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Static analysis
composer analyse

# Code formatting
composer format
```

### Continuous Integration Pipeline
1. **Install Dependencies**: `composer install --no-dev --prefer-dist`
2. **Code Style**: `composer format --test`
3. **Static Analysis**: `composer analyse`
4. **Unit Tests**: `vendor/bin/pest --testsuite=Unit`
5. **Feature Tests**: `vendor/bin/pest --testsuite=Feature`
6. **Architecture Tests**: `vendor/bin/pest --testsuite=Architecture`
7. **Coverage Report**: `vendor/bin/pest --coverage --min=90`

## Test Coverage Goals

### Coverage Targets
- **Overall Coverage**: ≥ 90%
- **Unit Tests**: ≥ 95%
- **Feature Tests**: ≥ 85%
- **Critical Paths**: 100% (security, data integrity)

### Coverage Exclusions
- Vendor dependencies
- Generated files (migrations, configs)
- Debug/development-only code

## Recommended Test Additions

### High Priority Missing Tests

1. **InboxTransport Integration Tests**
   ```php
   describe(InboxTransport::class, function () {
       it('captures sent mail messages', function () {
           // Test mail capture functionality
       });
       
       it('integrates with Laravel Mail facade', function () {
           // Test Laravel Mail integration
       });
   });
   ```

2. **StoreManager Driver Tests**
   ```php
   describe(StoreManager::class, function () {
       it('creates file storage from configuration', function () {
           // Test storage driver creation
       });
       
       it('handles invalid driver configuration', function () {
           // Test error handling
       });
   });
   ```

3. **Command Tests**
   ```php
   describe(InstallCommand::class, function () {
       it('publishes configuration files', function () {
           // Test command execution
       });
   });
   ```

4. **Security Tests**
   ```php
   describe('Security', function () {
       it('prevents path traversal in file storage', function () {
           // Test security measures
       });
       
       it('sanitizes user input in controllers', function () {
           // Test input sanitization
       });
   });
   ```

### Medium Priority Additions

5. **Performance Stress Tests**
6. **Concurrent Access Tests**
7. **Memory Leak Detection Tests**
8. **Browser/JavaScript Tests** (for UI components)

## Tools and Utilities

### Custom Test Assertions
```php
// tests/Helpers/CustomAssertions.php
expect()->extend('toBeValidEmail', function () {
    return $this->toMatch('/^[^\s@]+@[^\s@]+\.[^\s@]+$/');
});

expect()->extend('toHaveValidMessageStructure', function () {
    return $this->toMatchArray([
        'version' => expect()->toBe(1),
        'saved_at' => expect()->toBeString(),
        'subject' => expect()->toBeString()->or->toBeNull(),
        'text' => expect()->toBeString()->or->toBeNull(),
        'html' => expect()->toBeString()->or->toBeNull(),
        'attachments' => expect()->toBeArray(),
    ]);
});
```

### Test Data Factories
```php
// tests/Helpers/MessageFactory.php
class MessageFactory 
{
    public static function create(array $overrides = []): array
    {
        return array_merge([
            'version' => 1,
            'saved_at' => now()->toISOString(),
            'subject' => 'Test Subject',
            'text' => 'Test message body',
            'html' => '<p>Test message body</p>',
            'from' => [['address' => 'sender@example.com', 'name' => 'Test Sender']],
            'to' => [['address' => 'recipient@example.com', 'name' => 'Test Recipient']],
            'attachments' => [],
        ], $overrides);
    }
    
    public static function withAttachment(array $attachmentOverrides = []): array
    {
        $attachment = array_merge([
            'filename' => 'test.txt',
            'contentType' => 'text/plain',
            'content' => base64_encode('test content'),
            'size' => strlen('test content'),
            'inline' => false,
        ], $attachmentOverrides);
        
        return self::create(['attachments' => [$attachment]]);
    }
}
```

## Conclusion

This testing strategy provides a comprehensive framework for maintaining high-quality tests in the mailbox-for-laravel package. By following these conventions and best practices, the codebase will remain reliable, maintainable, and easy to extend.

Key principles to remember:
- **Test behavior, not implementation**
- **Make tests readable and maintainable**
- **Isolate tests for reliability**
- **Cover edge cases and error conditions**
- **Use meaningful assertions and descriptions**
- **Maintain fast test execution**
- **Follow consistent naming and organization patterns**

Regular review and updates to this strategy will ensure it continues to serve the project's needs as it evolves.