<?php

namespace Redberry\MailboxForLaravel\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Testing\TestResponse;
use Orchestra\Testbench\TestCase as Orchestra;
use Redberry\MailboxForLaravel\MailboxServiceProvider;

class TestCase extends Orchestra
{
    /**
     * The latest test response (if any).
     */
    protected static ?TestResponse $latestResponse = null;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Redberry\\MailboxForLaravel\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        $manifestPath = base_path('public/vendor/mailbox');
        if (! file_exists($manifestPath)) {
            mkdir($manifestPath, 0755, true);
        }

        file_put_contents($manifestPath.'/manifest.json', json_encode([
            'resources/js/dashboard.js' => [
                'file' => 'assets/dashboard.js',
                'src' => 'resources/js/dashboard.js',
                'isEntry' => true,
                'css' => ['assets/dashboard.css'],
            ],
        ]));
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app)
    {
        return [
            MailboxServiceProvider::class,
            \Inertia\ServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('mailbox.store.database.connection', 'testing');

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $app['config']->set('mail.mailers.mailbox', ['transport' => 'mailbox']);
        $app['config']->set('mail.default', 'mailbox');

        $app['config']->set('inertia.testing.ensure_pages_exist', false);
        $app['config']->set('inertia.testing.page_paths', []);

        $dataConfig = require __DIR__.'/../config/data.php';
        foreach ($dataConfig as $key => $value) {
            $app['config']->set("data.{$key}", $value);
        }

        $app['config']->set('view.paths', [
            base_path('resources/views'),
        ]);
    }
}
