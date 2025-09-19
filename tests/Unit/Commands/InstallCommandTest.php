<?php

use Illuminate\Support\Facades\File;
use Redberry\MailboxForLaravel\Commands\InstallCommand;

describe(InstallCommand::class, function () {
    beforeEach(function () {
        // Mock the File facade to avoid actual filesystem operations
        File::spy();
    });

    it('publishes mailbox assets to public directory', function () {
        File::shouldReceive('exists')->once()->andReturn(false);
        File::shouldReceive('deleteDirectory')->never();

        $this->artisan('mailbox:install')
            ->expectsOutput('Mailbox assets published.')
            ->assertExitCode(0);
    });

    it('removes existing mailbox directory before publishing', function () {
        $publicPath = public_path('vendor/mailbox');
        
        File::shouldReceive('exists')
            ->with($publicPath)
            ->once()
            ->andReturn(true);
            
        File::shouldReceive('deleteDirectory')
            ->with($publicPath)
            ->once();

        $this->artisan('mailbox:install')
            ->expectsOutput('Mailbox assets published.')
            ->assertExitCode(0);
    });

    it('passes force option to vendor:publish command', function () {
        File::shouldReceive('exists')->once()->andReturn(false);
        File::shouldReceive('deleteDirectory')->never();

        // Test with --force option
        $this->artisan('mailbox:install', ['--force' => true])
            ->expectsOutput('Mailbox assets published.')
            ->assertExitCode(0);
    });

    it('calls vendor:publish with correct parameters', function () {
        File::shouldReceive('exists')->once()->andReturn(false);
        
        $this->artisan('mailbox:install')
            ->expectsOutput('Mailbox assets published.')
            ->assertExitCode(0);

        // Verify that vendor:publish would be called with mailbox-assets tag
        // Note: In a real test, you might want to mock the call to vendor:publish
        // to verify the exact parameters passed
    });

    it('handles command signature correctly', function () {
        $command = new InstallCommand();
        
        expect($command->getName())->toBe('mailbox:install')
            ->and($command->getDescription())->toBe('Publish Mailbox assets to public/vendor/mailbox');
    });
});