<?php

namespace Redberry\MailboxForLaravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Redberry\MailboxForLaravel\Database\Factories\MailboxMessageFactory;

class MailboxMessage extends Model
{
    use HasFactory;

    protected $connection = 'mailbox';
    protected $table = 'mailbox_messages';

    /**
     * Allow mass assignment
     */
    protected $guarded = [];

    /**
     * Cast fields into correct PHP types
     */
    protected $casts = [
        'timestamp'   => 'integer',     // stored as BIGINT
        'seen_at'     => 'datetime',
        'version'     => 'integer',
        'saved_at'    => 'datetime',
        'date'        => 'datetime',

        'from'        => 'array',
        'sender'      => 'array',
        'to'          => 'array',
        'cc'          => 'array',
        'bcc'         => 'array',
        'reply_to'    => 'array',
        'headers'     => 'array',
        'attachments' => 'array',
    ];

    protected static function newFactory(): MailboxMessageFactory
    {
        return MailboxMessageFactory::new();
    }
}
