<?php

namespace Tests\Feature\Auth;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    /**
     * RF-17: quien tiene acceso al dashboard (supervisor y superiores) entra
     * ahí; el mecánico, que no puede verlo, va directo a su historial de
     * previajes en vez de recibir un 403 al abrir /dashboard.
     */
    public function test_users_with_dashboard_access_are_redirected_there_after_login()
    {
        $user = User::factory()->create(['rol' => RolUsuario::Supervisor]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_mecanico_is_redirected_to_previajes_after_login_not_dashboard()
    {
        $user = User::factory()->create(['rol' => RolUsuario::Mecanico]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('previajes.index', absolute: false));
    }

    /** La raíz del sitio sigue la misma regla que el login: dashboard si se puede verlo. */
    public function test_home_route_redirects_to_dashboard_for_users_with_access()
    {
        $user = User::factory()->create(['rol' => RolUsuario::Administrador]);

        $this->actingAs($user)->get('/')->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_home_route_redirects_mecanico_to_previajes()
    {
        $user = User::factory()->create(['rol' => RolUsuario::Mecanico]);

        $this->actingAs($user)->get('/')->assertRedirect(route('previajes.index', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
