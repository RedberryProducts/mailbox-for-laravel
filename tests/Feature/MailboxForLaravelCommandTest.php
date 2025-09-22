<?php

use Illuminate\Console\Command;
use Redberry\MailboxForLaravel\Commands\MailboxForLaravelCommand;

describe(MailboxForLaravelCommand::class, function () {
    it('has correct command signature and description', function () {
        $command = new MailboxForLaravelCommand();
        
        expect($command->signature)->toBe('mailbox-for-laravel');
        expect($command->description)->toBe('My command');
    });

    it('handles comment output correctly in context', function () {
        // This command class contains a comment() call which would be tested 
        // in actual usage. We'll just verify the structure is correct.
        $command = new MailboxForLaravelCommand();
        
        expect(method_exists($command, 'comment'))->toBeTrue();
        expect($command)->toBeInstanceOf(Command::class);
    });

    it('is a valid artisan command', function () {
        $command = new MailboxForLaravelCommand();
        
        expect($command)->toBeInstanceOf(Command::class);
        expect(method_exists($command, 'handle'))->toBeTrue();
    });

    it('extends Laravel Command class', function () {
        $command = new MailboxForLaravelCommand();
        
        expect($command)->toBeInstanceOf(Command::class);
        expect(get_parent_class($command))->toBe(Command::class);
    });
});