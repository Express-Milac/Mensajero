<?php

namespace Tests\Integration;

use App\Models\MonitoredChat;
use App\Models\WhatsappConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SqlServerCaptureSchemaTest extends TestCase
{
    public function test_capture_tables_are_isolated_and_allowlist_defaults_to_deny(): void
    {
        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.sqlsrv.database'), 'Use a disposable database ending in _test.');
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        foreach (['mensajero_whatsapp_connections', 'mensajero_monitored_chats', 'mensajero_whatsapp_contacts', 'mensajero_whatsapp_raw_events', 'mensajero_outbox', 'mensajero_whatsapp_messages'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table.' was not created.');
        }

        DB::beginTransaction();
        try {
            $connection = WhatsappConnection::create([
                'connection_key' => 'integration-primary',
                'provider' => 'self_hosted_web',
                'status' => 'disconnected',
            ]);
            $chat = MonitoredChat::create([
                'connection_id' => $connection->id,
                'provider_chat_id' => 'test-group@g.us',
                'chat_type' => 'group',
            ]);
            $chat->refresh();
            $this->assertFalse($chat->is_monitored);
            $this->assertTrue($chat->is_active);
        } finally {
            DB::rollBack();
        }
    }
}
