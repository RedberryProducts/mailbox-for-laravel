<?php

use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Storage\FileStorage;
use Redberry\MailboxForLaravel\Tests\Helpers\MessageFactory;

describe('Performance Tests', function () {
    describe('message storage performance', function () {
        it('stores messages efficiently under load', function () {
            $storage = new FileStorage(sys_get_temp_dir() . '/perf-test-' . uniqid());
            $service = new CaptureService($storage);
            
            $messageCount = 100;
            $startTime = microtime(true);
            $startMemory = memory_get_peak_usage();
            
            // Store multiple messages
            $keys = [];
            for ($i = 0; $i < $messageCount; $i++) {
                $message = MessageFactory::create([
                    'subject' => "Test Message $i",
                    'text' => "This is test message number $i with some content.",
                ]);
                $keys[] = $service->store($message);
            }
            
            $endTime = microtime(true);
            $endMemory = memory_get_peak_usage();
            
            $executionTime = $endTime - $startTime;
            $memoryUsed = $endMemory - $startMemory;
            
            // Performance assertions
            expect($executionTime)->toBeLessThan(5.0) // Should complete in under 5 seconds
                ->and($memoryUsed)->toBeLessThan(50 * 1024 * 1024) // Should use less than 50MB
                ->and($keys)->toHaveCount($messageCount)
                ->and($executionTime / $messageCount)->toBeLessThan(0.05); // Less than 50ms per message
        });

        it('retrieves messages efficiently', function () {
            $storage = new FileStorage(sys_get_temp_dir() . '/perf-test-' . uniqid());
            $service = new CaptureService($storage);
            
            // Pre-populate with test messages
            $keys = [];
            for ($i = 0; $i < 50; $i++) {
                $keys[] = $service->store(MessageFactory::create(['subject' => "Message $i"]));
            }
            
            $startTime = microtime(true);
            $startMemory = memory_get_peak_usage();
            
            // Retrieve all messages
            $retrieved = [];
            foreach ($keys as $key) {
                $retrieved[] = $service->get($key);
            }
            
            $endTime = microtime(true);
            $endMemory = memory_get_peak_usage();
            
            $executionTime = $endTime - $startTime;
            $memoryUsed = $endMemory - $startMemory;
            
            expect($executionTime)->toBeLessThan(2.0) // Should complete in under 2 seconds
                ->and($memoryUsed)->toBeLessThan(20 * 1024 * 1024) // Should use less than 20MB
                ->and($retrieved)->toHaveCount(50)
                ->and(array_filter($retrieved, fn($msg) => $msg !== null))->toHaveCount(50);
        });
    });

    describe('large message handling', function () {
        it('handles large text messages without memory issues', function () {
            $storage = new FileStorage(sys_get_temp_dir() . '/perf-test-' . uniqid());
            $service = new CaptureService($storage);
            
            $largeText = str_repeat('This is a large message body. ', 10000); // ~300KB
            $message = MessageFactory::create([
                'text' => $largeText,
                'html' => "<p>$largeText</p>",
            ]);
            
            $startTime = microtime(true);
            $startMemory = memory_get_peak_usage();
            
            $key = $service->store($message);
            $retrieved = $service->get($key);
            
            $endTime = microtime(true);
            $endMemory = memory_get_peak_usage();
            
            $executionTime = $endTime - $startTime;
            $memoryUsed = $endMemory - $startMemory;
            
            expect($executionTime)->toBeLessThan(1.0) // Should complete in under 1 second
                ->and($memoryUsed)->toBeLessThan(5 * 1024 * 1024) // Should use less than 5MB extra
                ->and($retrieved['text'])->toBe($largeText)
                ->and(strlen($retrieved['text']))->toBe(strlen($largeText));
        });

        it('handles messages with multiple large attachments', function () {
            $storage = new FileStorage(sys_get_temp_dir() . '/perf-test-' . uniqid());
            $service = new CaptureService($storage);
            
            // Create message with multiple 1MB attachments
            $attachments = [];
            for ($i = 0; $i < 3; $i++) {
                $attachments[] = [
                    'filename' => "large-file-$i.txt",
                    'content' => base64_encode(str_repeat("Content $i ", 100000)), // ~1MB each
                    'contentType' => 'text/plain',
                    'size' => 100000,
                    'inline' => false,
                ];
            }
            
            $message = MessageFactory::create(['attachments' => $attachments]);
            
            $startTime = microtime(true);
            $startMemory = memory_get_peak_usage();
            
            $key = $service->store($message);
            $retrieved = $service->get($key);
            
            $endTime = microtime(true);
            $endMemory = memory_get_peak_usage();
            
            $executionTime = $endTime - $startTime;
            $memoryUsed = $endMemory - $startMemory;
            
            expect($executionTime)->toBeLessThan(3.0) // Should complete in under 3 seconds
                ->and($memoryUsed)->toBeLessThan(20 * 1024 * 1024) // Should use less than 20MB extra
                ->and($retrieved['attachments'])->toHaveCount(3)
                ->and($retrieved['attachments'][0]['filename'])->toBe('large-file-0.txt');
        });
    });

    describe('concurrent access patterns', function () {
        it('handles simultaneous storage operations without corruption', function () {
            $storage = new FileStorage(sys_get_temp_dir() . '/perf-test-' . uniqid());
            $service = new CaptureService($storage);
            
            $processes = [];
            $keys = [];
            
            // Simulate concurrent writes (simplified since we can't do true concurrency in tests)
            for ($i = 0; $i < 20; $i++) {
                $message = MessageFactory::create(['subject' => "Concurrent Message $i"]);
                $keys[] = $service->store($message);
            }
            
            // Verify all messages were stored correctly
            foreach ($keys as $index => $key) {
                $retrieved = $service->get($key);
                expect($retrieved)->not->toBeNull()
                    ->and($retrieved['subject'])->toBe("Concurrent Message $index");
            }
            
            // Verify no duplicate keys were generated
            expect(array_unique($keys))->toHaveCount(count($keys));
        });
    });

    describe('pagination performance', function () {
        it('paginates large message lists efficiently', function () {
            $storage = new FileStorage(sys_get_temp_dir() . '/perf-test-' . uniqid());
            $service = new CaptureService($storage);
            
            // Pre-populate with many messages
            for ($i = 0; $i < 200; $i++) {
                $service->store(MessageFactory::create(['subject' => "Message $i"]));
            }
            
            $startTime = microtime(true);
            
            // Test pagination performance
            $page1 = $service->list(page: 1, perPage: 20);
            $page5 = $service->list(page: 5, perPage: 20);
            $page10 = $service->list(page: 10, perPage: 20);
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            expect($executionTime)->toBeLessThan(1.0) // Should complete in under 1 second
                ->and($page1['data'])->toHaveCount(20)
                ->and($page5['data'])->toHaveCount(20)
                ->and($page10['data'])->toHaveCount(20)
                ->and($page1['total'])->toBe(200);
        });
    });

    describe('memory leak detection', function () {
        it('does not leak memory during repeated operations', function () {
            $storage = new FileStorage(sys_get_temp_dir() . '/perf-test-' . uniqid());
            $service = new CaptureService($storage);
            
            $initialMemory = memory_get_usage();
            $peakMemoryUsage = [];
            
            // Perform repeated store/retrieve cycles
            for ($cycle = 0; $cycle < 10; $cycle++) {
                // Store some messages
                $keys = [];
                for ($i = 0; $i < 10; $i++) {
                    $keys[] = $service->store(MessageFactory::create(['subject' => "Cycle $cycle Message $i"]));
                }
                
                // Retrieve and delete them
                foreach ($keys as $key) {
                    $service->get($key);
                    $service->delete($key);
                }
                
                // Force garbage collection
                gc_collect_cycles();
                
                $peakMemoryUsage[] = memory_get_peak_usage();
            }
            
            $finalMemory = memory_get_usage();
            $memoryGrowth = $finalMemory - $initialMemory;
            
            // Memory growth should be minimal (less than 1MB)
            expect($memoryGrowth)->toBeLessThan(1024 * 1024);
            
            // Peak memory usage should not show a consistent upward trend
            $firstHalf = array_slice($peakMemoryUsage, 0, 5);
            $secondHalf = array_slice($peakMemoryUsage, 5);
            $avgFirstHalf = array_sum($firstHalf) / count($firstHalf);
            $avgSecondHalf = array_sum($secondHalf) / count($secondHalf);
            
            // Second half should not use significantly more memory than first half
            expect($avgSecondHalf - $avgFirstHalf)->toBeLessThan(2 * 1024 * 1024); // Less than 2MB difference
        });
    });
});