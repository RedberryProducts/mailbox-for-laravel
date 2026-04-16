<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Storage;

use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;

use InvalidArgumentException;
use Redberry\MailboxForLaravel\Contracts\MessageSearch;
use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\Search\DefaultMessageSearch;

use function array_slice;
use function count;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function rtrim;
use function trim;
use function unlink;
use function usort;

/**
 * JSON file–based storage driver.
 *
 * This is ideal for local/dev environments: simple, no DB required.
 */
class FileStorage implements MessageStore
{
    protected string $basePath;

    public function __construct(
        ?string $basePath = null,
        private readonly MessageSearch $search = new DefaultMessageSearch,
    ) {
        $this->basePath = $basePath ?: storage_path('app/mail-inbox');

        if (! is_dir($this->basePath)) {
            @mkdir($this->basePath, 0775, true);
        }
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function store(array $payload): string
    {
        $id = $payload['id'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new InvalidArgumentException('Payload is missing a canonical "id". CaptureService::store() must be called upstream.');
        }

        $payload['timestamp'] ??= time();
        $payload['saved_at'] ??= now()->toIso8601String();

        $path = $this->pathFor($id);
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

        return $id;
    }

    public function find(string $id): ?array
    {
        $path = $this->pathFor($id);

        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            return null;
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function findIdByMessageId(string $messageId): ?string
    {
        foreach (glob($this->basePath.'/*.json') ?: [] as $file) {
            $contents = file_get_contents($file);

            if ($contents === false || $contents === '') {
                continue;
            }

            $decoded = json_decode($contents, true);

            if (! is_array($decoded)) {
                continue;
            }

            if (isset($decoded['message_id']) && $decoded['message_id'] === $messageId) {
                return isset($decoded['id']) && is_string($decoded['id']) ? $decoded['id'] : null;
            }
        }

        return null;
    }

    public function paginate(int $page, int $perPage, ?string $search = null): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $messages = $this->loadAll($search);

        usort(
            $messages,
            static fn (array $a, array $b): int => ((int) ($b['timestamp'] ?? 0)) <=> ((int) ($a['timestamp'] ?? 0)),
        );

        $offset = ($page - 1) * $perPage;

        return array_slice($messages, $offset, $perPage);
    }

    public function count(?string $search = null): int
    {
        if ($search === null || trim($search) === '') {
            $files = glob($this->basePath.'/*.json') ?: [];

            return count($files);
        }

        return count($this->loadAll($search));
    }

    /**
     * Load every stored payload from disk, optionally filtered by a search
     * needle that matches subject, from, to, or text body.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function loadAll(?string $search): array
    {
        $files = glob($this->basePath.'/*.json') ?: [];
        $needle = $search !== null ? trim($search) : '';

        $messages = [];

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            if ($contents === false || $contents === '') {
                continue;
            }

            $decoded = json_decode($contents, true);

            if (! is_array($decoded)) {
                continue;
            }

            if ($needle !== '' && ! $this->search->matches($decoded, $needle)) {
                continue;
            }

            $messages[] = $decoded;
        }

        return $messages;
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

            if (! is_array($decoded)) {
                continue;
            }

            $timestamp = (int) ($decoded['timestamp'] ?? 0);

            if ($timestamp > 0 && $timestamp < $cutoff) {
                @unlink($file);
            }
        }
    }

    public function idsOlderThan(int $seconds): array
    {
        if ($seconds <= 0) {
            return [];
        }

        $cutoff = time() - $seconds;
        $ids = [];

        foreach (glob($this->basePath.'/*.json') ?: [] as $file) {
            $contents = file_get_contents($file);

            if ($contents === false || $contents === '') {
                continue;
            }

            $decoded = json_decode($contents, true);

            if (! is_array($decoded)) {
                continue;
            }

            $timestamp = (int) ($decoded['timestamp'] ?? 0);

            if ($timestamp > 0 && $timestamp < $cutoff && isset($decoded['id'])) {
                $ids[] = (string) $decoded['id'];
            }
        }

        return $ids;
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
}
