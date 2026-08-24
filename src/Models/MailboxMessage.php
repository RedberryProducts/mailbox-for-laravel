<?php

namespace Redberry\MailboxForLaravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Redberry\MailboxForLaravel\Database\Factories\MailboxMessageFactory;

class MailboxMessage extends Model
{
    use HasFactory;

    /**
     * Columns that may be written from a storage payload.
     *
     * @var list<string>
     */
    public const PERSISTABLE_COLUMNS = [
        'id',
        'timestamp',
        'seen_at',
        'version',
        'saved_at',
        'message_id',
        'subject',
        'date',
        'from',
        'sender',
        'to',
        'cc',
        'bcc',
        'reply_to',
        'text',
        'html',
        'headers',
        'attachments',
        'raw',
    ];

    protected $table = 'mailbox_messages';

    public $incrementing = false;

    protected $keyType = 'string';

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
        return config('mailbox.store.database.connection', 'mailbox');
    }

    /**
     * Also resolve the table name from config so it's consistent everywhere.
     */
    public function getTable()
    {
        return config('mailbox.store.database.table', 'mailbox_messages');
    }

    /**
     * Relationship to attachments.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(MailboxAttachment::class, 'message_id');
    }

    protected static function newFactory(): MailboxMessageFactory
    {
        return MailboxMessageFactory::new();
    }
}
