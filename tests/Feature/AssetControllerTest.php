<?php

use Redberry\MailboxForLaravel\Http\Controllers\AssetController;

describe(AssetController::class, function () {
    it('serves an inline asset with correct content-type and cache headers')->todo();
    it('returns 404 for non-existing message')->todo();
    it('returns 404 for non-existing asset in existing message')->todo();
    it('rejects unauthorized access when middleware denies')->todo();
    it('streams large assets without loading entire file into memory')->todo();
});
