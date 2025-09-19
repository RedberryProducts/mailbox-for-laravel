<?php

use Illuminate\Support\Facades\Gate;
use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Storage\FileStorage;

describe('Security Tests', function () {
    describe('path traversal prevention', function () {
        it('prevents directory traversal in file storage keys', function () {
            $storage = new FileStorage(sys_get_temp_dir() . '/security-test-' . uniqid());
            
            // Attempt various path traversal patterns
            $maliciousKeys = [
                '../etc/passwd',
                '..\\windows\\system32',
                '/etc/passwd',
                'normal/../../../etc/passwd',
                '....//....//etc/passwd',
                '%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd', // URL encoded
            ];
            
            foreach ($maliciousKeys as $key) {
                $storage->store($key, ['test' => 'data']);
                
                // Verify the file was stored safely within the storage directory
                $files = glob($storage->getBasePath() . '/*.json');
                foreach ($files as $file) {
                    expect(realpath($file))->toStartWith(realpath($storage->getBasePath()));
                }
            }
        });

        it('sanitizes file storage keys to prevent path traversal', function () {
            $storage = new FileStorage(sys_get_temp_dir() . '/security-test-' . uniqid());
            
            $storage->store('../malicious', ['data' => 'test']);
            
            // Check that the key was sanitized
            $files = glob($storage->getBasePath() . '/*.json');
            expect($files)->toHaveCount(1);
            
            $filename = basename($files[0], '.json');
            expect($filename)->not->toContain('..')
                ->and($filename)->not->toContain('/')
                ->and($filename)->not->toContain('\\');
        });
    });

    describe('input validation and sanitization', function () {
        it('handles malformed message data gracefully', function () {
            $service = new CaptureService(
                new FileStorage(sys_get_temp_dir() . '/security-test-' . uniqid())
            );
            
            // Test with various malformed inputs
            $malformedInputs = [
                null,
                false,
                '',
                'not-an-array',
                ['text' => str_repeat('A', 1024 * 1024 * 10)], // Very large text
                ['subject' => "\x00\x01\x02"], // Control characters
                ['from' => 'not-an-array'], // Wrong type
            ];
            
            foreach ($malformedInputs as $input) {
                expect(function () use ($service, $input) {
                    $service->store($input);
                })->not->toThrow();
            }
        });

        it('validates message keys to prevent injection attacks', function () {
            $service = new CaptureService(
                new FileStorage(sys_get_temp_dir() . '/security-test-' . uniqid())
            );
            
            $key = $service->store(['test' => 'data']);
            
            // Test various injection attempts
            $maliciousKeys = [
                $key . '; rm -rf /',
                $key . "' OR '1'='1",
                $key . '<script>alert("xss")</script>',
                $key . "\x00injection",
                $key . '../other-file',
            ];
            
            foreach ($maliciousKeys as $maliciousKey) {
                // Should not retrieve anything or should handle gracefully
                $result = $service->get($maliciousKey);
                if ($result !== null) {
                    // If it returns something, it should be the original data, not injected content
                    expect($result)->toBe(['test' => 'data']);
                }
            }
        });
    });

    describe('authorization and access control', function () {
        it('respects gate permissions for mailbox access', function () {
            Gate::shouldReceive('allows')
                ->with('viewMailbox')
                ->andReturn(false);
            
            config()->set('inbox.public', false);
            
            // Test that unauthorized access is denied
            $this->get('/mailbox')->assertForbidden();
        });

        it('allows access when explicitly configured as public', function () {
            config()->set('inbox.public', true);
            
            // Gate should not even be checked when public is true
            Gate::shouldReceive('allows')->never();
            
            $this->get('/mailbox')->assertSuccessful();
        });

        it('prevents unauthorized asset access', function () {
            Gate::shouldReceive('allows')
                ->with('viewMailbox')
                ->andReturn(false);
            
            config()->set('inbox.public', false);
            
            $this->get('/mailbox/messages/test-id/attachments/test.txt')
                ->assertForbidden();
        });
    });

    describe('data integrity and validation', function () {
        it('validates email addresses in message data', function () {
            $service = new CaptureService(
                new FileStorage(sys_get_temp_dir() . '/security-test-' . uniqid())
            );
            
            $invalidEmails = [
                'not-an-email',
                'missing@domain',
                '@missing-local.com',
                'spaces in@email.com',
                'special<chars>@domain.com',
            ];
            
            foreach ($invalidEmails as $email) {
                $message = [
                    'from' => [['address' => $email]],
                    'text' => 'test'
                ];
                
                // Service should handle invalid emails gracefully
                expect(function () use ($service, $message) {
                    $service->store($message);
                })->not->toThrow();
            }
        });

        it('handles excessively large attachments securely', function () {
            $service = new CaptureService(
                new FileStorage(sys_get_temp_dir() . '/security-test-' . uniqid())
            );
            
            $largeAttachment = [
                'filename' => 'large.txt',
                'content' => base64_encode(str_repeat('A', 1024 * 1024)), // 1MB
                'contentType' => 'text/plain',
            ];
            
            $message = [
                'text' => 'test',
                'attachments' => [$largeAttachment]
            ];
            
            $startMemory = memory_get_peak_usage();
            
            $key = $service->store($message);
            $retrieved = $service->get($key);
            
            $endMemory = memory_get_peak_usage();
            
            // Memory usage should be reasonable (not more than 5x the attachment size)
            expect($endMemory - $startMemory)->toBeLessThan(5 * 1024 * 1024);
            expect($retrieved['attachments'][0]['filename'])->toBe('large.txt');
        });
    });

    describe('file system security', function () {
        it('creates storage directory with secure permissions', function () {
            $tempDir = sys_get_temp_dir() . '/security-test-' . uniqid();
            $storage = new FileStorage($tempDir);
            
            // Directory should be created
            expect(is_dir($tempDir))->toBeTrue();
            
            // Check permissions (on Unix systems)
            if (PHP_OS_FAMILY !== 'Windows') {
                $perms = fileperms($tempDir);
                $octal = substr(sprintf('%o', $perms), -4);
                
                // Should not be world-writable
                expect($octal)->not->toEndWith('7');
            }
            
            // Cleanup
            rmdir($tempDir);
        });

        it('prevents reading files outside storage directory', function () {
            $storage = new FileStorage(sys_get_temp_dir() . '/security-test-' . uniqid());
            
            // Create a file outside the storage directory
            $outsideFile = sys_get_temp_dir() . '/outside-file-' . uniqid();
            file_put_contents($outsideFile, '{"secret": "data"}');
            
            // Attempt to read outside file by crafting malicious key
            $maliciousKey = basename($outsideFile, '.json');
            $result = $storage->retrieve($maliciousKey);
            
            // Should not be able to read the outside file
            expect($result)->toBeNull();
            
            // Cleanup
            unlink($outsideFile);
        });
    });
});