<?php

namespace App\Enums;

/**
 * RN-04: si cualquier ítem queda fuera del estado óptimo el previaje se marca
 * `con_hallazgos`, pero eso NO bloquea la operación del equipo — sólo alerta y
 * deja constancia (RF-14, decisión de negocio confirmada).
 */
enum EstatusPreviaje: string
{
    case SinHallazgos = 'sin_hallazgos';
    case ConHallazgos = 'con_hallazgos';
    case Anulado = 'anulado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SinHallazgos => 'Sin hallazgos',
            self::ConHallazgos => 'Con hallazgos',
            self::Anulado => 'Anulado',
        };
    }

    /** @return array<int, array{valor: string, etiqueta: string}> */
    public static function opciones(): array
    {
        return array_map(
            fn (self $estatus) => ['valor' => $estatus->value, 'etiqueta' => $estatus->etiqueta()],
            self::cases(),
        );
    }
}
