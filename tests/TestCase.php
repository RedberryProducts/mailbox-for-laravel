<?php

namespace Redberry\MailboxForLaravel\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Redberry\MailboxForLaravel\InboxServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn(string $modelName
            ) => 'Redberry\\MailboxForLaravel\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            InboxServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        $app['config']->set('mail.mailers.inbox', ['transport' => 'inbox']);
        $app['config']->set('mail.default', 'inbox');
        
    }
}
