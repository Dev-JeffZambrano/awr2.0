<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Rol extends Model
{
    protected $table = 'rol';

    protected $primaryKey = 'id_rol';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'fecha_creacion' => 'datetime',
            'fecha_actualizacion' => 'datetime',
        ];
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(
            Usuario::class,
            'usuario_rol',
            'id_rol',
            'id_usuario',
            'id_rol',
            'id_usuario'
        )->withPivot('fecha_asignacion', 'estado');
    }
}
