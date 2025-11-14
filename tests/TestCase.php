<?php

namespace Redberry\MailboxForLaravel\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Redberry\MailboxForLaravel\MailboxServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName
            ) => 'Redberry\\MailboxForLaravel\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
        
        // Create fake Vite manifest for tests to avoid build requirements
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

    protected function getPackageProviders($app)
    {
        return [
            MailboxServiceProvider::class,
            \Inertia\ServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        
        // Set application key for encryption (required by Inertia)
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $app['config']->set('mail.mailers.mailbox', ['transport' => 'mailbox']);
        $app['config']->set('mail.default', 'mailbox');
        
        // Configure Inertia for testing - disable SSR and use testing mode
        $app['config']->set('inertia.testing.ensure_pages_exist', false);
        $app['config']->set('inertia.testing.page_paths', []);

    }
}
