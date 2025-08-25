<?php

namespace Redberry\MailboxForLaravel\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class InboxController
{
    public function __invoke()
    {
        return view('inbox::index');
    }

}
