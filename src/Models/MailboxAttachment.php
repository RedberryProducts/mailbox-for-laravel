<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $message_id
 * @property string $filename
 * @property string $mime_type
 * @property int $size
 * @property string $disk
 * @property string $path
 * @property string|null $cid
 * @property bool $is_inline
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MailboxAttachment extends Model
{
    protected $table = 'mailbox_attachments';

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
        'size' => 'integer',
        'is_inline' => 'boolean',
    ];

    /**
     * Use the configured connection instead of a hardcoded one.
     */
    public function getConnectionName()
    {
        return config('mailbox.store.database.connection', parent::getConnectionName());
    }

    /**
     * Relationship to parent message.
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(MailboxMessage::class, 'message_id');
    }
}
