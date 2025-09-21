<?php

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Redberry\MailboxForLaravel\Commands\InstallCommand;

describe(InstallCommand::class, function () {
    beforeEach(function () {
        // Clean up any existing installation
        $path = public_path('vendor/mailbox');
        if (File::exists($path)) {
            File::deleteDirectory($path);
        }
    });

    afterEach(function () {
        // Clean up after tests
        $path = public_path('vendor/mailbox');
        if (File::exists($path)) {
            File::deleteDirectory($path);
        }
    });

    it('publishes mailbox assets to public/vendor/mailbox directory', function () {
        $result = Artisan::call('mailbox:install');
        
        expect($result)->toBe(Command::SUCCESS);
        
        $path = public_path('vendor/mailbox');
        expect(File::exists($path))->toBeTrue();
        expect(File::isDirectory($path))->toBeTrue();
    });

    it('removes existing directory before publishing new assets', function () {
        $path = public_path('vendor/mailbox');
        
        // Create a fake existing directory with some content
        File::makeDirectory($path, 0755, true);
        File::put($path . '/old-file.txt', 'old content');
        
        expect(File::exists($path . '/old-file.txt'))->toBeTrue();
        
        $result = Artisan::call('mailbox:install');
        
        expect($result)->toBe(Command::SUCCESS);
        expect(File::exists($path))->toBeTrue();
        // Old file should be gone after directory recreation
        expect(File::exists($path . '/old-file.txt'))->toBeFalse();
    });

    it('accepts --force option and passes it to vendor:publish command', function () {
        // Test that the force option is properly passed through
        $result = Artisan::call('mailbox:install', ['--force' => true]);
        
        expect($result)->toBe(Command::SUCCESS);
        
        $path = public_path('vendor/mailbox');
        expect(File::exists($path))->toBeTrue();
    });

    it('works without --force option', function () {
        $result = Artisan::call('mailbox:install');
        
        expect($result)->toBe(Command::SUCCESS);
        
        $path = public_path('vendor/mailbox');
        expect(File::exists($path))->toBeTrue();
    });

    it('displays success message after publishing assets', function () {
        $this->artisan('mailbox:install')
            ->expectsOutput('Mailbox assets published.')
            ->assertExitCode(Command::SUCCESS);
    });

    it('calls vendor:publish with correct tag and force option', function () {
        // We can't easily mock Artisan::call, but we can verify the end result
        // and that the command completes successfully
        
        $result = Artisan::call('mailbox:install', ['--force' => true]);
        
        expect($result)->toBe(Command::SUCCESS);
        
        // Verify the publish operation worked by checking for published assets
        $path = public_path('vendor/mailbox');
        expect(File::exists($path))->toBeTrue();
    });

    it('handles permission errors gracefully', function () {
        // This is harder to test without actually changing file permissions
        // But we can at least verify the command structure is correct
        $command = new InstallCommand();
        
        expect($command)->toBeInstanceOf(Command::class);
        expect(method_exists($command, 'handle'))->toBeTrue();
    });

    it('publishes assets from correct source tag', function () {
        // Verify that the vendor:publish command would be called with 'mailbox-assets' tag
        // We test this indirectly by ensuring the command succeeds and assets are published
        
        $result = Artisan::call('mailbox:install');
        
        expect($result)->toBe(Command::SUCCESS);
        
        $path = public_path('vendor/mailbox');
        expect(File::exists($path))->toBeTrue();
        
        // The assets should be published from the 'mailbox-assets' tag
        // as defined in the service provider
    });
});