<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajero_whatsapp_connections', function (Blueprint $table) {
            $table->id();
            $table->string('connection_key', 64)->unique();
            $table->string('provider', 32);
            $table->string('external_account_id', 191)->nullable();
            $table->string('display_name')->nullable();
            $table->string('status', 32)->default('unconfigured');
            $table->longText('metadata')->nullable();
            $table->dateTime('connected_at', 6)->nullable();
            $table->dateTime('last_heartbeat_at', 6)->nullable();
            $table->dateTime('last_disconnected_at', 6)->nullable();
            $table->timestamps();
            $table->index(['provider', 'status']);
        });

        Schema::create('mensajero_monitored_chats', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('connection_id');
            $table->string('provider_chat_id', 191)->collation('Latin1_General_100_BIN2');
            $table->string('chat_type', 32);
            $table->string('group_name')->nullable();
            $table->string('internal_name')->nullable();
            $table->boolean('is_monitored')->default(false);
            $table->boolean('is_active')->default(true);
            $table->bigInteger('authorization_version')->default(0);
            $table->dateTime('first_seen_at', 6)->nullable();
            $table->dateTime('last_seen_at', 6)->nullable();
            $table->timestamps();
            $table->unique(['connection_id', 'provider_chat_id'], 'mensajero_chats_connection_provider_uidx');
            $table->index(['is_monitored', 'is_active'], 'mensajero_chats_monitoring_idx');
            $table->foreign('connection_id', 'mensajero_chats_connection_fk')->references('id')->on('mensajero_whatsapp_connections')->onDelete('no action');
        });

        Schema::create('mensajero_whatsapp_contacts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('connection_id');
            $table->string('provider_contact_id', 191)->collation('Latin1_General_100_BIN2');
            $table->string('phone', 32)->nullable();
            $table->string('display_name')->nullable();
            $table->string('push_name')->nullable();
            $table->longText('metadata')->nullable();
            $table->dateTime('first_seen_at', 6)->nullable();
            $table->dateTime('last_seen_at', 6)->nullable();
            $table->timestamps();
            $table->unique(['connection_id', 'provider_contact_id'], 'mensajero_contacts_connection_provider_uidx');
            $table->index('phone', 'mensajero_contacts_phone_idx');
            $table->foreign('connection_id', 'mensajero_contacts_connection_fk')->references('id')->on('mensajero_whatsapp_connections')->onDelete('no action');
        });

        Schema::create('mensajero_whatsapp_raw_events', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('connection_id');
            $table->bigInteger('monitored_chat_id');
            $table->string('external_event_id', 191)->nullable()->collation('Latin1_General_100_BIN2');
            $table->char('deduplication_key', 64);
            $table->string('event_type', 64);
            $table->longText('payload');
            $table->char('payload_sha256', 64);
            $table->string('contract_version', 32)->default('v1');
            $table->string('processing_status', 24)->default('captured');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->dateTime('received_at', 6);
            $table->dateTime('processed_at', 6)->nullable();
            $table->longText('error_message')->nullable();
            $table->timestamps();
            $table->unique(['connection_id', 'deduplication_key'], 'mensajero_raw_connection_dedup_uidx');
            $table->index(['monitored_chat_id', 'received_at', 'id'], 'mensajero_raw_chat_received_idx');
            $table->index(['processing_status', 'received_at'], 'mensajero_raw_status_received_idx');
            $table->foreign('connection_id', 'mensajero_raw_connection_fk')->references('id')->on('mensajero_whatsapp_connections')->onDelete('no action');
            $table->foreign('monitored_chat_id', 'mensajero_raw_chat_fk')->references('id')->on('mensajero_monitored_chats')->onDelete('no action');
        });

        Schema::create('mensajero_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('topic', 128);
            $table->string('aggregate_type', 64);
            $table->bigInteger('aggregate_id')->nullable();
            $table->char('deduplication_key', 64);
            $table->longText('payload');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->dateTime('available_at', 6);
            $table->dateTime('lease_until', 6)->nullable();
            $table->dateTime('dispatched_at', 6)->nullable();
            $table->longText('last_error')->nullable();
            $table->dateTime('created_at', 6)->useCurrent();
            $table->unique(['topic', 'deduplication_key'], 'mensajero_outbox_topic_dedup_uidx');
            $table->index(['dispatched_at', 'available_at', 'id'], 'mensajero_outbox_dispatch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajero_outbox');
        Schema::dropIfExists('mensajero_whatsapp_raw_events');
        Schema::dropIfExists('mensajero_whatsapp_contacts');
        Schema::dropIfExists('mensajero_monitored_chats');
        Schema::dropIfExists('mensajero_whatsapp_connections');
    }
};
