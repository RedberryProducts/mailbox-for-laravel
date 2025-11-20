<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('mailbox.store.database.connection', 'mailbox'))
            ->create('mailbox_attachments', function (Blueprint $table) {
                $table->string('id', 26)->primary(); // ULID
                $table->unsignedBigInteger('message_id')->index();
                $table->string('filename');
                $table->string('mime_type');
                $table->unsignedBigInteger('size');
                $table->string('disk')->default('mailbox');
                $table->string('path');
                $table->string('cid')->nullable()->index();
                $table->boolean('is_inline')->default(false);
                $table->timestamps();

                $table->foreign('message_id')
                    ->references('id')
                    ->on(config('mailbox.store.database.table', 'mailbox_messages'))
                    ->onDelete('cascade');
            });
    }

    public function down(): void
    {
        Schema::connection(config('mailbox.store.database.connection', 'mailbox'))
            ->dropIfExists('mailbox_attachments');
    }
};
