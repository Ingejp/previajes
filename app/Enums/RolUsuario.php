<?php

namespace App\Enums;

/**
 * Roles del sistema (§4). El orden de `nivel()` define la jerarquía: un rol
 * superior puede hacer todo lo del inferior, salvo donde una regla lo niegue
 * explícitamente (ver RN-09 sobre la visibilidad de la auditoría).
 */
enum RolUsuario: string
{
    case Mecanico = 'mecanico';
    case Supervisor = 'supervisor';
    case Administrador = 'administrador';
    case SuperAdministrador = 'super_administrador';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Mecanico => 'Mecánico',
            self::Supervisor => 'Supervisor de flota',
            self::Administrador => 'Administrador',
            self::SuperAdministrador => 'Super Administrador',
        };
    }

    public function nivel(): int
    {
        return match ($this) {
            self::Mecanico => 1,
            self::Supervisor => 2,
            self::Administrador => 3,
            self::SuperAdministrador => 4,
        };
    }

    public function alMenos(self $minimo): bool
    {
        return $this->nivel() >= $minimo->nivel();
    }

    /**
     * El super administrador opera a nivel global; los demás roles trabajan
     * acotados a la(s) flota(s) que tengan asignada(s) (RF-01, RF-02).
     */
    public function esGlobal(): bool
    {
        return $this === self::SuperAdministrador;
    }

    /** @return array<int, array{valor: string, etiqueta: string}> */
    public static function opciones(): array
    {
        return array_map(
            fn (self $rol) => ['valor' => $rol->value, 'etiqueta' => $rol->etiqueta()],
            self::cases(),
        );
    }
}
