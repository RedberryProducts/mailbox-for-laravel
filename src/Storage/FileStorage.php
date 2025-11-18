<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Storage;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Redberry\MailboxForLaravel\Contracts\MessageStore;

use function array_slice;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function rtrim;
use function unlink;
use function usort;

use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;

/**
 * JSON file–based storage driver.
 *
 * This is ideal for local/dev environments: simple, no DB required.
 */
class FileStorage implements MessageStore
{
    protected string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?: storage_path('app/mail-inbox');

        if (!is_dir($this->basePath)) {
            @mkdir($this->basePath, 0o775, true);
        }
    }

    public function store(array $payload): string
    {
        $id = $payload['id'] ?? null;
        $payload['timestamp'] ??= time();
        $payload['saved_at'] ??= now()->toIso8601String();

        if (!is_string($id) || $id === '') {
            $id = $this->generateId($payload, (int) $payload['timestamp']);
            $payload['id'] = $id;
        }


        $path = $this->pathFor($id);
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

        return $id;
    }

    public function find(string $id): ?array
    {
        $path = $this->pathFor($id);

        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            return null;
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function paginate(int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $files = glob($this->basePath.'/*.json') ?: [];

        $messages = [];

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            if ($contents === false || $contents === '') {
                continue;
            }

            $decoded = json_decode($contents, true);

            if (!is_array($decoded)) {
                continue;
            }

            $messages[] = $decoded;
        }

        // Order by timestamp DESC by default
        usort(
            $messages,
            static fn(array $a, array $b): int => ((int) ($b['timestamp'] ?? 0)) <=> ((int) ($a['timestamp'] ?? 0)),
        );

        $offset = ($page - 1) * $perPage;

        return array_slice($messages, $offset, $perPage);
    }

    public function update(string $id, array $changes): ?array
    {
        $existing = $this->find($id);

        if ($existing === null) {
            return null;
        }

        $updated = array_merge($existing, $changes);

        $this->store($updated);

        return $updated;
    }

    public function delete(string $id): void
    {
        $path = $this->pathFor($id);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function purgeOlderThan(int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        $cutoff = time() - $seconds;

        $files = glob($this->basePath.'/*.json') ?: [];

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            if ($contents === false || $contents === '') {
                continue;
            }

            $decoded = json_decode($contents, true);

            if (!is_array($decoded)) {
                continue;
            }

            $timestamp = (int) ($decoded['timestamp'] ?? 0);

            if ($timestamp > 0 && $timestamp < $cutoff) {
                @unlink($file);
            }
        }
    }

    public function clear(): void
    {
        $files = glob($this->basePath.'/*.json') ?: [];

        foreach ($files as $file) {
            @unlink($file);
        }
    }

    protected function pathFor(string $id): string
    {
        return rtrim($this->basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$id.'.json';
    }


    /**
     * Generate a filesystem-safe, reasonably unique id.
     */
    protected function generateId(array $payload, int $timestamp): string
    {
        $payloadString = json_encode($payload);
        $hash = substr(sha1($payloadString.$timestamp.microtime(true).Str::random(8)), 0, 32);

        return "email_{$timestamp}_{$hash}";
    }
}
