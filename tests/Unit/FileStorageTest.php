<?php

use Redberry\MailboxForLaravel\Storage\FileStorage;

beforeEach(function () {
    $this->basePath = sys_get_temp_dir().'/inbox-fs-'.uniqid();
    @mkdir($this->basePath, 0777, true);
    $this->fs = new FileStorage($this->basePath);
});

afterEach(function () {
    if (is_dir($this->basePath)) {
        $files = glob($this->basePath.'/*');
        if ($files) {
            foreach ($files as $f) {
                @unlink($f);
            }
        }
        @rmdir($this->basePath);
    }
});

it('stores and retrieves payloads', function () {
    $key = 'k1';
    $payload = ['raw' => 'hello', 'timestamp' => time()];

    $this->fs->store($key, $payload);

    $got = $this->fs->retrieve($key);

    expect($got)->toBeArray()
        ->and($got['raw'])->toBe('hello')
        ->and($got['timestamp'])->toBeGreaterThan(0);
});

it('lists keys with optional since filter', function () {
    $now = time();
    $this->fs->store('a', ['raw' => 'A', 'timestamp' => $now - 100]);
    $this->fs->store('b', ['raw' => 'B', 'timestamp' => $now - 10]);

    $all = iterator_to_array($this->fs->keys());
    expect($all)->toContain('a', 'b');

    $since = $now - 60;
    $recent = iterator_to_array($this->fs->keys($since));
    expect($recent)->not()->toContain('a')
        ->toContain('b');
});

it('deletes payloads', function () {
    $this->fs->store('x', ['raw' => 'X', 'timestamp' => time()]);
    expect($this->fs->retrieve('x'))->not->toBeNull();

    $this->fs->delete('x');
    expect($this->fs->retrieve('x'))->toBeNull();
});

it('purges old payloads', function () {
    $now = time();
    $this->fs->store('old', ['raw' => 'O', 'timestamp' => $now - 3600]);
    $this->fs->store('new', ['raw' => 'N', 'timestamp' => $now]);

    $this->fs->purgeOlderThan(1800); // keep only last 30 minutes

    $keys = iterator_to_array($this->fs->keys());
    expect($keys)->not()->toContain('old')
        ->toContain('new');
});
