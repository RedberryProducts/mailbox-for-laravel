<?php

use Redberry\MailboxForLaravel\CaptureService;
use Redberry\MailboxForLaravel\Contracts\MessageStore;

class InMemoryStore implements MessageStore
{
    public array $data = [];

    public function store(string $key, array $value): void { $this->data[$key] = $value; }
    public function retrieve(string $key): ?array { return $this->data[$key] ?? null; }
    public function keys(?int $since = null): iterable {
        foreach (array_keys($this->data) as $k) {
            if ($since) {
                $ts = $this->data[$k]['timestamp'] ?? 0;
                if ($ts < $since) { continue; }
            }
            yield $k;
        }
    }
    public function delete(string $key): void { unset($this->data[$key]); }
    public function purgeOlderThan(int $seconds): void {
        $cut = time() - $seconds;
        foreach ($this->data as $k => $v) {
            if (($v['timestamp'] ?? 0) < $cut) unset($this->data[$k]);
        }
    }
}

it('stores raw message with timestamp and returns a key', function () {
    $store = new InMemoryStore();
    $svc = new CaptureService($store);

    $key = $svc->storeRaw("Subject: Hi\r\n\r\nBody");

    expect($key)->toBeString()->not->toBe('');
    $payload = $store->retrieve($key);

    expect($payload)->toBeArray()
        ->and($payload['raw'])->toContain('Subject: Hi')
        ->and($payload['timestamp'])->toBeGreaterThan(0);
});
