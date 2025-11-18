<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel;

use Illuminate\Support\Manager;
use InvalidArgumentException;
use Redberry\MailboxForLaravel\Contracts\MessageStore;
use Redberry\MailboxForLaravel\Storage\DatabaseMessageStore;
use Redberry\MailboxForLaravel\Storage\FileStorage;

/**
 * Central manager for mailbox storage drivers.
 *
 * Usage:
 *   $store = app(StoreManager::class)->driver();
 *   $store = app(StoreManager::class)->driver('file');
 */
class StoreManager extends Manager
{
    /**
     * Get the default driver name from config.
     */
    public function getDefaultDriver(): string
    {
        return (string) $this->container['config']->get('mailbox.store.driver', 'file');
    }

    /**
     * File-based driver (JSON files in storage).
     */
    protected function createFileDriver(): MessageStore
    {
        $config = (array) $this->container['config']->get('mailbox.store.file', []);

        $path = $config['path'] ?? storage_path('app/mailbox');

        return new FileStorage($path);
    }

    /**
     * Database-backed driver.
     *
     * Uses the MailboxMessage model, which should define connection/table
     * if you want to point it at a specific DB/connection.
     */
    protected function createDatabaseDriver(): MessageStore
    {
        return new DatabaseMessageStore;
    }

    /**
     * Fallback for any other driver name, resolved through config "resolvers".
     *
     * Example:
     *   'store' => [
     *       'driver' => 'redis',
     *       'resolvers' => [
     *           'redis' => fn ($app) => new \App\Mailbox\RedisMessageStore($app['redis']),
     *       ],
     *   ],
     */
    protected function createCustomDriver(): MessageStore
    {
        $driver = $this->getDefaultDriver();
        $resolvers = (array) $this->container['config']->get('mailbox.store.resolvers', []);

        if (! isset($resolvers[$driver]) || ! is_callable($resolvers[$driver])) {
            throw new InvalidArgumentException("Mailbox store driver [{$driver}] is not supported.");
        }

        $store = $resolvers[$driver]($this->container);

        if (! $store instanceof MessageStore) {
            throw new InvalidArgumentException("Resolver for mailbox driver [{$driver}] must return an instance of ".MessageStore::class.'.');
        }

        return $store;
    }

    /**
     * Override Manager::driver to transparently support "custom" driver names
     * that don't have an explicit createXDriver method.
     */
    public function driver($driver = null): MessageStore
    {
        $name = $driver ?? $this->getDefaultDriver();

        if (method_exists($this, 'create'.ucfirst($name).'Driver')) {
            /** @var MessageStore $store */
            $store = parent::driver($name);

            return $store;
        }

        return $this->createCustomDriver();
    }
}
