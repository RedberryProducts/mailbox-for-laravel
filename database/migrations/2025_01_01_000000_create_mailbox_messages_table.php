<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection(config('mailbox.store.database.connection', 'mailbox'))
            ->create(config('mailbox.store.database.table'), function (Blueprint $table) {
                $table->string('id')->primary();

                $table->unsignedBigInteger('timestamp')->index();
                $table->timestampTz('seen_at')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestampTz('saved_at')->nullable();

                $table->string('message_id')->nullable();
                $table->string('subject')->nullable();
                $table->timestampTz('date')->nullable();

                $table->json('from')->nullable();
                $table->json('sender')->nullable();
                $table->json('to')->nullable();
                $table->json('cc')->nullable();
                $table->json('bcc')->nullable();
                $table->json('reply_to')->nullable();

                $table->longText('text')->nullable();
                $table->longText('html')->nullable();
                $table->json('headers')->nullable();
                $table->json('attachments')->nullable();
                $table->longText('raw')->nullable();

                $table->timestamps();
            });
    }

    public function down(): void
    {
        Schema::connection(config('mailbox.connection', 'mailbox'))
            ->dropIfExists('mailbox_messages');
    }
};
