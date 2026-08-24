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
     * Every column a payload may write to, mirroring the package migration.
     *
     * Declared as a constant so both mass assignment and the storage drivers
     * work from one list. `tests/Unit/Models/MailboxMessageTest.php` fails if
     * it ever drifts from the actual table.
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
     * Mass assignment is limited to the columns the table actually has.
     */
    protected $fillable = self::PERSISTABLE_COLUMNS;

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
