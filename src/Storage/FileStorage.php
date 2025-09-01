<?php

namespace Redberry\MailboxForLaravel\Storage;

use Redberry\MailboxForLaravel\Contracts\MessageStore;

class FileStorage implements MessageStore
{
    protected string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?: storage_path('app/mail-inbox');
        if (!is_dir($this->basePath)) {
            @mkdir($this->basePath, 0775, true);
        }
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function store(string $key, array $value): void
    {
        $path = $this->pathFor($key);
        $value['timestamp'] ??= time();

        file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function retrieve(string $key): ?array
    {
        $path = $this->pathFor($key);
        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    public function keys(?int $since = null): iterable
    {
        if (!is_dir($this->basePath)) {
            return [];
        }

        $files = glob($this->basePath.DIRECTORY_SEPARATOR.'*.json') ?: [];
        foreach ($files as $file) {
            $key = basename($file, '.json');

            if ($since) {
                $payload = $this->retrieve($key);
                if (!$payload || ($payload['timestamp'] ?? 0) < $since) {
                    continue;
                }
            }

            yield $key;
        }
    }

    public function delete(string $key): void
    {
        $path = $this->pathFor($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function purgeOlderThan(int $seconds): void
    {
        $cut = time() - $seconds;

        foreach ($this->keys() as $key) {
            $payload = $this->retrieve($key);
            if (!$payload) {
                continue;
            }
            if (($payload['timestamp'] ?? 0) < $cut) {
                $this->delete($key);
            }
        }
    }

    protected function pathFor(string $key): string
    {
        $key = preg_replace('/[^A-Za-z0-9_\-]/', '_', $key);

        return $this->basePath.DIRECTORY_SEPARATOR.$key.'.json';
    }

    public function update(string $key, array $value): ?array
    {
        $existing = $this->retrieve($key);
        if (!$existing) {
            return null;
        }

        $updated = array_merge($existing, $value);
        $this->store($key, $updated);

        return $updated;
    }

    public function clear(): bool
    {
        foreach ($this->keys() as $key) {
            $this->delete($key);
        }

        return true;
    }
}
