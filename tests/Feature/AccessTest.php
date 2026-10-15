<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Services\InfrastructureHealth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AccessTest extends TestCase
{
    private function user(Role $role): User
    {
        $user = new User(['name' => 'Operación', 'email' => 'test@example.test']);
        $user->id = 1;
        $user->role = $role;

        return $user;
    }

    public function test_disabled_auth_allows_internal_dashboard_without_login(): void
    {
        config(['mensajero.auth.mode' => 'disabled']);
        $this->get('/dashboard')->assertOk()->assertSee('Sin conectar');
        $this->get('/login')->assertRedirect('/dashboard');
    }

    public function test_corporate_auth_fails_closed_until_adapter_exists(): void
    {
        config(['mensajero.auth.mode' => 'corporate']);
        $this->get('/dashboard')->assertStatus(503);
    }

    public function test_local_mode_guests_cannot_read_dashboard_or_diagnostics(): void
    {
        config(['mensajero.auth.mode' => 'local']);
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/health')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Entrar al sistema');
    }

    public function test_local_viewers_can_read_dashboard_but_not_diagnostics(): void
    {
        config(['mensajero.auth.mode' => 'local']);
        $this->actingAs($this->user(Role::Viewer));
        $this->get('/dashboard')->assertOk()->assertSee('Sin conectar');
        $this->get('/health')->assertForbidden();
        $this->assertFalse(Gate::allows('review-events'));
    }

    public function test_local_supervisor_can_review_but_not_administer(): void
    {
        config(['mensajero.auth.mode' => 'local']);
        $this->actingAs($this->user(Role::Supervisor));
        $this->assertTrue(Gate::allows('review-events'));
        $this->assertFalse(Gate::allows('administer'));
    }

    public function test_local_administrator_sees_degraded_health(): void
    {
        config(['mensajero.auth.mode' => 'local']);
        $this->mock(InfrastructureHealth::class)->shouldReceive('check')->once()->andReturn(['storage' => 'failed']);
        $this->actingAs($this->user(Role::Administrator))->get('/health')->assertStatus(503)->assertJsonPath('checks.storage', 'failed');
    }

    public function test_local_invalid_login_is_generic_and_throttled(): void
    {
        config(['mensajero.auth.mode' => 'local']);
        Auth::shouldReceive('guard')->andReturnSelf();
        Auth::shouldReceive('check')->andReturn(false);
        Auth::shouldReceive('attempt')->times(5)->andReturn(false);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'unknown@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'unknown@example.test', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_local_logout_removes_authentication(): void
    {
        config(['mensajero.auth.mode' => 'local']);
        $this->actingAs($this->user(Role::Viewer))->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_role_cannot_be_mass_assigned(): void
    {
        $user = $this->user(Role::Viewer);
        $user->fill(['role' => 'administrator']);
        $this->assertSame(Role::Viewer, $user->role);
    }
}
