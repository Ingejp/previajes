<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora de accesos para la pantalla de auditoría (RF-20) y para el control
 * OWASP de "fallas de registro y monitoreo".
 */
class Acceso extends Model
{
    use HasFactory;

    protected $table = 'accesos';

    public const UPDATED_AT = null;

    protected $fillable = ['usuario_id', 'email_intentado', 'evento', 'exitoso', 'ip', 'user_agent'];

    protected function casts(): array
    {
        return ['exitoso' => 'boolean'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
