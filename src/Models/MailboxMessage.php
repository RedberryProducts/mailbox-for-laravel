<?php

namespace Redberry\MailboxForLaravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Redberry\MailboxForLaravel\Database\Factories\MailboxMessageFactory;

/**
 * The table name is resolved from config at runtime, so static analysis cannot
 * read the columns off the migration. They are declared here instead.
 *
 * @property string $id
 * @property int $timestamp
 * @property Carbon|null $seen_at
 * @property int $version
 * @property Carbon|null $saved_at
 * @property string|null $message_id
 * @property string|null $subject
 * @property Carbon|null $date
 * @property array<string, mixed>|null $from
 * @property array<string, mixed>|null $sender
 * @property array<int, mixed>|null $to
 * @property array<int, mixed>|null $cc
 * @property array<int, mixed>|null $bcc
 * @property array<int, mixed>|null $reply_to
 * @property string|null $text
 * @property string|null $html
 * @property array<string, mixed>|null $headers
 * @property array<int, mixed>|null $attachments
 * @property string|null $raw
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MailboxMessage extends Model
{
    use HasFactory;

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
