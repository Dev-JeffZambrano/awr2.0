<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Usuario extends Model
{
    protected $table = 'usuario';

    protected $primaryKey = 'id_usuario';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'ultimo_acceso' => 'datetime',
            'fecha_creacion' => 'datetime',
            'fecha_actualizacion' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Rol::class,
            'usuario_rol',
            'id_usuario',
            'id_rol',
            'id_usuario',
            'id_rol'
        )->withPivot('fecha_asignacion', 'estado');
    }
}
