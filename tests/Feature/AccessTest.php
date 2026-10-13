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

    public function test_guests_cannot_read_dashboard_or_diagnostics(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/health')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Entrar al sistema');
    }

    public function test_viewers_can_read_dashboard_but_not_diagnostics(): void
    {
        $this->actingAs($this->user(Role::Viewer));
        $this->get('/dashboard')->assertOk()->assertSee('Sin conectar');
        $this->get('/health')->assertForbidden();
        $this->assertFalse(Gate::allows('review-events'));
    }

    public function test_supervisor_can_review_but_not_administer(): void
    {
        $this->actingAs($this->user(Role::Supervisor));
        $this->assertTrue(Gate::allows('review-events'));
        $this->assertFalse(Gate::allows('administer'));
    }

    public function test_administrator_sees_degraded_health(): void
    {
        $this->mock(InfrastructureHealth::class)->shouldReceive('check')->once()->andReturn(['storage' => 'failed']);
        $this->actingAs($this->user(Role::Administrator))->get('/health')->assertStatus(503)->assertJsonPath('checks.storage', 'failed');
    }

    public function test_invalid_login_is_generic_and_throttled(): void
    {
        Auth::shouldReceive('guard')->andReturnSelf();
        Auth::shouldReceive('check')->andReturn(false);
        Auth::shouldReceive('attempt')->times(5)->andReturn(false);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'unknown@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'unknown@example.test', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_logout_removes_authentication(): void
    {
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
