<?php

use Illuminate\Support\Facades\Schema;
use Redberry\MailboxForLaravel\Models\MailboxMessage;

describe(MailboxMessage::class, function () {
    it('lists every payload column the messages table has', function () {
        $model = new MailboxMessage;

        $columns = Schema::connection($model->getConnectionName())
            ->getColumnListing($model->getTable());

        // created_at/updated_at are written by Eloquent, never by a payload.
        $payloadColumns = array_values(array_diff($columns, ['created_at', 'updated_at']));

        expect(MailboxMessage::PERSISTABLE_COLUMNS)->toEqualCanonicalizing($payloadColumns);
    });

    it('only allows mass assignment of payload columns', function () {
        $model = new MailboxMessage;

        expect($model->getFillable())->toBe(MailboxMessage::PERSISTABLE_COLUMNS)
            ->and($model->isFillable('not_a_column'))->toBeFalse();
    });
});
