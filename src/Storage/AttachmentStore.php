<?php

declare(strict_types=1);

namespace Redberry\MailboxForLaravel\Storage;

/**
 * @deprecated since v2.0.0 — use {@see \Redberry\MailboxForLaravel\Contracts\AttachmentStore}
 *             for type-hints and {@see DatabaseAttachmentStore} when a concrete
 *             database-backed implementation is required. Scheduled for removal in v2.1.
 */
final class AttachmentStore extends DatabaseAttachmentStore {}
