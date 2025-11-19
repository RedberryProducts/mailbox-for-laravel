<?php

namespace Redberry\MailboxForLaravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Redberry\MailboxForLaravel\Database\Factories\MailboxMessageFactory;

class MailboxMessage extends Model
{
    use HasFactory;

    protected $table = 'mailbox_messages';

    /**
     * The primary key is a string, not an auto-incrementing integer
     */
    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Allow mass assignment
     */
    protected $guarded = [];

    /**
     * Cast fields into correct PHP types
     */
    protected $casts = [
        'timestamp' => 'integer',
        'seen_at' => 'datetime',
        'version' => 'integer',
        'saved_at' => 'datetime',
        'date' => 'datetime',

        'from' => 'array',
        'sender' => 'array',
        'to' => 'array',
        'cc' => 'array',
        'bcc' => 'array',
        'reply_to' => 'array',
        'headers' => 'array',
        'attachments' => 'array',
    ];

    /**
     * Use the configured connection instead of a hardcoded one.
     */
    public function getConnectionName()
    {
        return config('mailbox.store.database.connection', parent::getConnectionName());
    }

    /**
     * Also resolve the table name from config so it's consistent everywhere.
     */
    public function getTable()
    {
        return config('mailbox.store.database.table', parent::getTable());
    }

    protected static function newFactory(): MailboxMessageFactory
    {
        return MailboxMessageFactory::new();
    }
}
