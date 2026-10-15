<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajero_whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('connection_id');
            $table->bigInteger('monitored_chat_id');
            $table->bigInteger('sender_contact_id')->nullable();
            $table->bigInteger('raw_event_id');
            $table->string('provider_message_id', 191)->collation('Latin1_General_100_BIN2');
            $table->string('message_type', 32);
            $table->longText('text_content')->nullable();
            $table->longText('caption')->nullable();
            $table->dateTime('sent_at', 6)->nullable();
            $table->dateTime('received_at', 6);
            $table->dateTime('processed_at', 6)->nullable();
            $table->string('quoted_provider_message_id', 191)->nullable()->collation('Latin1_General_100_BIN2');
            $table->bigInteger('quoted_message_id')->nullable();
            $table->string('capture_status', 24)->default('normalized');
            $table->string('media_status', 24)->default('not_required');
            $table->string('ai_status', 24)->default('not_eligible');
            $table->timestamps();
            $table->unique(['connection_id', 'monitored_chat_id', 'provider_message_id'], 'mensajero_messages_provider_uidx');
            $table->unique('raw_event_id', 'mensajero_messages_raw_uidx');
            $table->index(['monitored_chat_id', 'sent_at', 'id'], 'mensajero_messages_chat_sent_idx');
            $table->index(['sender_contact_id', 'sent_at'], 'mensajero_messages_sender_sent_idx');
            $table->foreign('connection_id', 'mensajero_messages_connection_fk')->references('id')->on('mensajero_whatsapp_connections')->onDelete('no action');
            $table->foreign('monitored_chat_id', 'mensajero_messages_chat_fk')->references('id')->on('mensajero_monitored_chats')->onDelete('no action');
            $table->foreign('sender_contact_id', 'mensajero_messages_sender_fk')->references('id')->on('mensajero_whatsapp_contacts')->onDelete('no action');
            $table->foreign('raw_event_id', 'mensajero_messages_raw_fk')->references('id')->on('mensajero_whatsapp_raw_events')->onDelete('no action');
            $table->foreign('quoted_message_id', 'mensajero_messages_quote_fk')->references('id')->on('mensajero_whatsapp_messages')->onDelete('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_whatsapp_messages');
    }
};
