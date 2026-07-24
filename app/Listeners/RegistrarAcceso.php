<?php

namespace App\Listeners;

use App\Models\Acceso;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;

/**
 * Persiste los eventos de autenticación en `accesos`, que junto con
 * `activity_log` alimenta la pantalla de auditoría (RF-20) y cubre el control
 * OWASP de "fallas de registro y monitoreo".
 *
 * Los intentos fallidos se guardan aunque el correo no exista en el sistema,
 * para poder detectar fuerza bruta contra cuentas inventadas.
 */
class RegistrarAcceso
{
    public function __construct(private readonly Request $request) {}

    public function alIniciarSesion(Login $evento): void
    {
        $this->registrar('login', true, $evento->user->getAuthIdentifier(), $evento->user->email);
    }

    public function alCerrarSesion(Logout $evento): void
    {
        $this->registrar('logout', true, $evento->user?->getAuthIdentifier(), $evento->user?->email);
    }

    public function alFallar(Failed $evento): void
    {
        $this->registrar(
            'fallido',
            false,
            $evento->user?->getAuthIdentifier(),
            $evento->credentials['email'] ?? null,
        );
    }

    /**
     * El bloqueo por demasiados intentos se registra aparte del intento
     * fallido: es la señal que de verdad interesa revisar en auditoría.
     */
    public function alBloquear(Lockout $evento): void
    {
        $this->registrar('fallido', false, null, $evento->request->input('email'));
    }

    public function subscribe(Dispatcher $eventos): array
    {
        return [
            Login::class => 'alIniciarSesion',
            Logout::class => 'alCerrarSesion',
            Failed::class => 'alFallar',
            Lockout::class => 'alBloquear',
        ];
    }

    private function registrar(string $evento, bool $exitoso, mixed $usuarioId, ?string $email): void
    {
        Acceso::create([
            'usuario_id' => is_numeric($usuarioId) ? (int) $usuarioId : null,
            'email_intentado' => $email,
            'evento' => $evento,
            'exitoso' => $exitoso,
            'ip' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 1000),
        ]);
    }
}
