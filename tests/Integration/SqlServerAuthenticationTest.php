<?php

namespace Tests\Integration;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SqlServerAuthenticationTest extends TestCase
{
    public function test_migrations_login_and_logout_on_sql_server(): void
    {
        $this->assertSame('sqlsrv', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.sqlsrv.database'), 'Use a disposable database ending in _test.');
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        DB::beginTransaction();
        try {
            $user = new User(['name' => 'Integration', 'email' => 'integration-'.bin2hex(random_bytes(6)).'@example.test', 'password' => 'Test-password-1234!']);
            $user->role = Role::Viewer;
            $user->save();
            $this->post('/login', ['email' => $user->email, 'password' => 'Test-password-1234!'])->assertRedirect('/dashboard');
            $this->assertAuthenticatedAs($user);
            $this->get('/health')->assertForbidden();
            $this->post('/logout')->assertRedirect('/login');
            $this->assertGuest();
        } finally {
            DB::rollBack();
        }
    }
}
