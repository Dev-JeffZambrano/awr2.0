<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;

if (PHP_SAPI !== 'cli') {
    atenderSolicitudUsuariosAccesos();
}

function atenderSolicitudUsuariosAccesos(): void
{
    header('Content-Type: application/json; charset=utf-8');

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $directorio = str_replace('\\', '/', dirname($script, 3));
    $rutaCookie = rtrim($directorio, '/');
    $rutaCookie = ($rutaCookie === '' || $rutaCookie === '.') ? '/' : $rutaCookie . '/';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $rutaCookie,
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        responder(405, [
            'exito' => false,
            'mensaje' => 'Método no permitido.',
        ]);
    }

    $longitud = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

    if ($longitud < 0 || $longitud > 8192) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    $crudo = file_get_contents('php://input');
    $entrada = json_decode(is_string($crudo) ? $crudo : '', true);

    if (!is_array($entrada)) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    $op = $entrada['op'] ?? null;

    switch ($op) {
        case 'proponerUsername':
            proponerUsername($entrada);
            break;
        case 'listarUsuarios':
            listarUsuarios($entrada);
            break;
        case 'obtenerDetalleUsuario':
            obtenerDetalleUsuario($entrada);
            break;
        case 'crearUsuario':
            crearUsuario($entrada);
            break;
        case 'actualizarUsuario':
            actualizarUsuario($entrada);
            break;
        case 'cambiarEstadoUsuario':
            cambiarEstadoUsuario($entrada);
            break;
        case 'restablecerPassword':
            restablecerPassword($entrada);
            break;
        case 'obtenerRolesPermisos':
            obtenerRolesPermisos();
            break;
        default:
            responder(400, [
                'exito' => false,
                'mensaje' => 'Operación no reconocida.',
            ]);
    }
}

function proponerUsername(array $entrada): void
{
    if (!exigirAdministrador()) {
        return;
    }

    try {
        $nombres = palabras($entrada['nombres'] ?? null);
        $apellidos = palabras($entrada['apellidos'] ?? null);

        if ($nombres === []) {
            rechazar('Nombres obligatorios');
        }

        if ($apellidos === []) {
            rechazar('Apellidos obligatorios');
        }

        $base = substr($nombres[0], 0, 1) . $apellidos[0];
        responder(200, [
            'exito' => true,
            'username' => usernameDisponible($base),
        ]);
    } catch (ValidacionAcceso $e) {
        responder(200, [
            'exito' => false,
            'mensaje' => $e->getMessage(),
        ]);
    } catch (Throwable $e) {
        error_log('AWR proponerUsername: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function listarUsuarios(array $entrada): void
{
    if (!exigirAdministrador()) {
        return;
    }

    try {
        $pagina = paginaSolicitada($entrada);
        $estado = estadoFiltro($entrada);
        $idRol = filtroRol($entrada);
        $busqueda = busquedaSolicitada($entrada);
        $consulta = Usuario::query()->select([
            'id_usuario',
            'nombres',
            'apellidos',
            'username',
            'estado',
        ]);

        if ($busqueda !== null) {
            $patron = patron($busqueda);
            $consulta->where(function ($q) use ($patron) {
                $q->whereRaw("nombres ILIKE ? ESCAPE '|'", [$patron])
                    ->orWhereRaw("apellidos ILIKE ? ESCAPE '|'", [$patron])
                    ->orWhereRaw("username ILIKE ? ESCAPE '|'", [$patron]);
            });
        }

        if ($estado !== null) {
            $consulta->where('estado', $estado);
        }

        if ($idRol !== null) {
            $consulta->whereExists(function ($q) use ($idRol) {
                $q->selectRaw('1')
                    ->from('usuario_rol')
                    ->whereColumn('usuario_rol.id_usuario', 'usuario.id_usuario')
                    ->where('usuario_rol.id_rol', $idRol)
                    ->where('usuario_rol.estado', true);
            });
        }

        $total = (clone $consulta)->count();
        $filas = $consulta
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->orderBy('id_usuario')
            ->offset(($pagina - 1) * 20)
            ->limit(20)
            ->get();
        $ids = [];

        foreach ($filas as $fila) {
            $ids[] = (int) $fila->id_usuario;
        }

        $agrupados = agruparAsignaciones($ids);
        $usuarios = [];

        foreach ($filas as $fila) {
            $usuarios[] = presentarUsuario($fila, $agrupados[(int) $fila->id_usuario] ?? ['roles' => [], 'sucursales' => []]);
        }

        responder(200, [
            'exito' => true,
            'kpis' => kpisUsuarios(),
            'roles' => catalogoRoles(),
            'sucursales' => catalogoSucursales(),
            'usuarios' => $usuarios,
            'pagina' => $pagina,
            'por_pagina' => 20,
            'total' => $total,
            'paginas' => $total === 0 ? 0 : (int) ceil($total / 20),
        ]);
    } catch (ValidacionAcceso $e) {
        responder(200, [
            'exito' => false,
            'mensaje' => $e->getMessage(),
        ]);
    } catch (Throwable $e) {
        error_log('AWR listarUsuarios: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function obtenerDetalleUsuario(array $entrada): void
{
    if (!exigirAdministrador()) {
        return;
    }

    $idUsuario = enteroEntrada($entrada['id_usuario'] ?? null);

    if ($idUsuario === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Operación inválida.',
        ]);
    }

    try {
        $usuario = Usuario::query()
            ->select(['id_usuario', 'nombres', 'apellidos', 'username', 'estado'])
            ->where('id_usuario', $idUsuario)
            ->first();

        if ($usuario === null) {
            rechazar('No disponible');
        }

        $agrupados = agruparAsignaciones([$idUsuario]);
        responder(200, [
            'exito' => true,
            'usuario' => presentarUsuario($usuario, $agrupados[$idUsuario] ?? ['roles' => [], 'sucursales' => []]),
        ]);
    } catch (ValidacionAcceso $e) {
        responder(200, [
            'exito' => false,
            'mensaje' => $e->getMessage(),
        ]);
    } catch (Throwable $e) {
        error_log('AWR obtenerDetalleUsuario: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function crearUsuario(array $entrada): void
{
    if (!exigirAdministrador()) {
        return;
    }

    try {
        $datos = datosUsuario($entrada, true);
        $idUsuario = persistirUsuarioNuevo($datos);
        responder(201, [
            'exito' => true,
            'usuario' => detallePorId($idUsuario),
        ]);
    } catch (ValidacionAcceso $e) {
        responder(200, [
            'exito' => false,
            'mensaje' => $e->getMessage(),
        ]);
    } catch (QueryException $e) {
        if (esDuplicado($e)) {
            responder(200, [
                'exito' => false,
                'mensaje' => 'Username ya existe',
            ]);
        }

        error_log('AWR crearUsuario: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    } catch (Throwable $e) {
        error_log('AWR crearUsuario: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function actualizarUsuario(array $entrada): void
{
    if (!exigirAdministrador()) {
        return;
    }

    $idUsuario = enteroEntrada($entrada['id_usuario'] ?? null);

    if ($idUsuario === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Operación inválida.',
        ]);
    }

    try {
        $datos = datosUsuario($entrada, false);
        persistirUsuarioExistente($idUsuario, $datos);
        responder(200, [
            'exito' => true,
            'usuario' => detallePorId($idUsuario),
        ]);
    } catch (ValidacionAcceso $e) {
        responder(200, [
            'exito' => false,
            'mensaje' => $e->getMessage(),
        ]);
    } catch (Throwable $e) {
        error_log('AWR actualizarUsuario: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function cambiarEstadoUsuario(array $entrada): void
{
    if (!exigirAdministrador()) {
        return;
    }

    $idUsuario = enteroEntrada($entrada['id_usuario'] ?? null);
    $nuevoEstado = estadoObligatorio($entrada['nuevo_estado'] ?? null);

    if ($idUsuario === null || $nuevoEstado === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Operación inválida.',
        ]);
    }

    try {
        DB::transaction(function () use ($idUsuario, $nuevoEstado): void {
            $usuario = Usuario::query()->where('id_usuario', $idUsuario)->lockForUpdate()->first();

            if ($usuario === null) {
                rechazar('No disponible');
            }

            if ($nuevoEstado === false && esUltimoAdministradorActivo($idUsuario)) {
                rechazar('No puede dejar el sistema sin Administrador');
            }

            if ($nuevoEstado === false && $idUsuario === (int) $_SESSION['id_usuario']) {
                rechazar('No puede desactivar su propia cuenta');
            }

            Usuario::query()->where('id_usuario', $idUsuario)->update([
                'estado' => $nuevoEstado,
                'fecha_actualizacion' => DB::raw('CURRENT_TIMESTAMP'),
            ]);
        });
        responder(200, [
            'exito' => true,
            'usuario' => detallePorId($idUsuario),
        ]);
    } catch (ValidacionAcceso $e) {
        responder(200, [
            'exito' => false,
            'mensaje' => $e->getMessage(),
        ]);
    } catch (Throwable $e) {
        error_log('AWR cambiarEstadoUsuario: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function restablecerPassword(array $entrada): void
{
    if (!exigirAdministrador()) {
        return;
    }

    $idUsuario = enteroEntrada($entrada['id_usuario'] ?? null);

    if ($idUsuario === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Operación inválida.',
        ]);
    }

    try {
        $temporal = passwordTemporal();
        $actualizado = false;
        DB::transaction(function () use ($idUsuario, $temporal, &$actualizado): void {
            $usuario = Usuario::query()->where('id_usuario', $idUsuario)->lockForUpdate()->first();

            if ($usuario === null) {
                rechazar('No disponible');
            }

            Usuario::query()->where('id_usuario', $idUsuario)->update([
                'password_hash' => hashPassword($temporal),
                'fecha_actualizacion' => DB::raw('CURRENT_TIMESTAMP'),
            ]);
            $actualizado = true;
        });

        if ($actualizado !== true) {
            rechazar('No disponible');
        }

        responder(200, [
            'exito' => true,
            'id_usuario' => $idUsuario,
            'password_temporal' => $temporal,
        ]);
    } catch (ValidacionAcceso $e) {
        responder(200, [
            'exito' => false,
            'mensaje' => $e->getMessage(),
        ]);
    } catch (Throwable $e) {
        error_log('AWR restablecerPassword: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function obtenerRolesPermisos(): void
{
    if (!exigirAdministrador()) {
        return;
    }

    try {
        $filas = DB::table('rol')
            ->leftJoin('rol_permiso', 'rol_permiso.id_rol', '=', 'rol.id_rol')
            ->leftJoin('permiso', function ($join) {
                $join->on('permiso.id_permiso', '=', 'rol_permiso.id_permiso')
                    ->where('permiso.estado', true);
            })
            ->where('rol.estado', true)
            ->whereIn('rol.nombre', rolesLogin())
            ->orderByRaw("CASE rol.nombre WHEN 'Administrador' THEN 1 WHEN 'Administrativo' THEN 2 WHEN 'Operador' THEN 3 ELSE 4 END")
            ->orderBy('permiso.codigo')
            ->get([
                'rol.id_rol',
                'rol.nombre as rol',
                'rol.descripcion as descripcion_rol',
                'permiso.codigo',
                'permiso.nombre as permiso',
                'permiso.descripcion as descripcion_permiso',
            ]);
        $roles = [];

        foreach ($filas as $fila) {
            $idRol = (int) $fila->id_rol;

            if (!isset($roles[$idRol])) {
                $roles[$idRol] = [
                    'id_rol' => $idRol,
                    'nombre' => (string) $fila->rol,
                    'descripcion' => textoONull($fila->descripcion_rol ?? null),
                    'permisos' => [],
                ];
            }

            if (!is_string($fila->codigo ?? null) || $fila->codigo === '') {
                continue;
            }

            $roles[$idRol]['permisos'][] = [
                'codigo' => $fila->codigo,
                'nombre' => (string) $fila->permiso,
                'descripcion' => textoONull($fila->descripcion_permiso ?? null),
            ];
        }

        responder(200, [
            'exito' => true,
            'roles' => array_values($roles),
        ]);
    } catch (Throwable $e) {
        error_log('AWR obtenerRolesPermisos: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function persistirUsuarioNuevo(array $datos): int
{
    return DB::transaction(function () use ($datos): int {
        $ocupado = Usuario::query()->where('username', $datos['username'])->lockForUpdate()->first(['id_usuario']);

        if ($ocupado !== null) {
            rechazar('Username ya existe');
        }

        $usuario = new Usuario();
        $usuario->forceFill([
            'nombres' => $datos['nombres'],
            'apellidos' => $datos['apellidos'],
            'username' => $datos['username'],
            'password_hash' => hashPassword($datos['password']),
            'estado' => true,
        ]);
        $usuario->save();
        $idUsuario = (int) $usuario->getKey();
        dejarUnRol($idUsuario, $datos['id_rol']);
        dejarSucursales($idUsuario, $datos['id_sucursal']);

        return $idUsuario;
    });
}

function persistirUsuarioExistente(int $idUsuario, array $datos): void
{
    DB::transaction(function () use ($idUsuario, $datos): void {
        $usuario = Usuario::query()->where('id_usuario', $idUsuario)->lockForUpdate()->first();

        if ($usuario === null) {
            rechazar('No disponible');
        }

        if ($datos['nombre_rol'] !== 'Administrador' && esUltimoAdministradorActivo($idUsuario)) {
            rechazar('No puede dejar el sistema sin Administrador');
        }

        Usuario::query()->where('id_usuario', $idUsuario)->update([
            'nombres' => $datos['nombres'],
            'apellidos' => $datos['apellidos'],
            'fecha_actualizacion' => DB::raw('CURRENT_TIMESTAMP'),
        ]);
        dejarUnRol($idUsuario, $datos['id_rol']);
        dejarSucursales($idUsuario, $datos['id_sucursal']);
    });
}

function datosUsuario(array $entrada, bool $creacion): array
{
    $nombres = textoHumano($entrada['nombres'] ?? null, 150);
    $apellidos = textoHumano($entrada['apellidos'] ?? null, 150);

    if ($nombres === null) {
        rechazar('Nombres obligatorios');
    }

    if ($apellidos === null) {
        rechazar('Apellidos obligatorios');
    }

    $rol = rolPermitido(enteroEntrada($entrada['id_rol'] ?? null));

    if ($rol === null) {
        rechazar('Rol no disponible');
    }

    $idSucursal = null;

    if ($rol->nombre === 'Operador') {
        $idSucursal = enteroEntrada($entrada['id_sucursal'] ?? null);

        if ($idSucursal === null) {
            rechazar('El Operador requiere una sucursal');
        }

        $sucursal = DB::table('sucursal')
            ->where('id_sucursal', $idSucursal)
            ->where('estado', true)
            ->first(['id_sucursal']);

        if ($sucursal === null) {
            rechazar('Sucursal no disponible');
        }
    }

    $datos = [
        'nombres' => $nombres,
        'apellidos' => $apellidos,
        'id_rol' => (int) $rol->id_rol,
        'nombre_rol' => (string) $rol->nombre,
        'id_sucursal' => $idSucursal,
    ];

    if ($creacion) {
        $username = usernameNormalizado($entrada['username'] ?? null);
        $password = $entrada['password'] ?? null;

        if ($username === null) {
            rechazar('Username obligatorio');
        }

        if (!is_string($password) || $password === '' || strlen($password) > 255) {
            rechazar('Contraseña obligatoria');
        }

        $datos['username'] = $username;
        $datos['password'] = $password;
    }

    return $datos;
}

function dejarUnRol(int $idUsuario, int $idRol): void
{
    DB::table('usuario_rol')
        ->where('id_usuario', $idUsuario)
        ->where('estado', true)
        ->update(['estado' => false]);

    $existe = DB::table('usuario_rol')
        ->where('id_usuario', $idUsuario)
        ->where('id_rol', $idRol)
        ->exists();

    if ($existe) {
        DB::table('usuario_rol')
            ->where('id_usuario', $idUsuario)
            ->where('id_rol', $idRol)
            ->update([
                'estado' => true,
                'fecha_asignacion' => DB::raw('CURRENT_TIMESTAMP'),
            ]);

        return;
    }

    DB::table('usuario_rol')->insert([
        'id_usuario' => $idUsuario,
        'id_rol' => $idRol,
        'estado' => true,
    ]);
}

function dejarSucursales(int $idUsuario, ?int $idSucursal): void
{
    DB::table('usuario_sucursal')
        ->where('id_usuario', $idUsuario)
        ->where('estado', true)
        ->update(['estado' => false]);

    if ($idSucursal === null) {
        return;
    }

    $existe = DB::table('usuario_sucursal')
        ->where('id_usuario', $idUsuario)
        ->where('id_sucursal', $idSucursal)
        ->exists();

    if ($existe) {
        DB::table('usuario_sucursal')
            ->where('id_usuario', $idUsuario)
            ->where('id_sucursal', $idSucursal)
            ->update([
                'estado' => true,
                'fecha_asignacion' => DB::raw('CURRENT_TIMESTAMP'),
            ]);

        return;
    }

    DB::table('usuario_sucursal')->insert([
        'id_usuario' => $idUsuario,
        'id_sucursal' => $idSucursal,
        'estado' => true,
    ]);
}

function esUltimoAdministradorActivo(int $idUsuario): bool
{
    $ids = [];
    $filas = DB::select(
        "SELECT u.id_usuario
           FROM usuario u
           JOIN usuario_rol ur
             ON ur.id_usuario = u.id_usuario
            AND ur.estado = true
           JOIN rol r
             ON r.id_rol = ur.id_rol
            AND r.estado = true
          WHERE u.estado = true
            AND r.nombre = 'Administrador'
          FOR UPDATE OF u"
    );

    foreach ($filas as $fila) {
        $ids[] = (int) $fila->id_usuario;
    }

    return count($ids) === 1 && in_array($idUsuario, $ids, true);
}

function detallePorId(int $idUsuario): array
{
    $usuario = Usuario::query()
        ->select(['id_usuario', 'nombres', 'apellidos', 'username', 'estado'])
        ->where('id_usuario', $idUsuario)
        ->first();

    if ($usuario === null) {
        rechazar('No disponible');
    }

    $agrupados = agruparAsignaciones([$idUsuario]);

    return presentarUsuario($usuario, $agrupados[$idUsuario] ?? ['roles' => [], 'sucursales' => []]);
}

function presentarUsuario(object $usuario, array $asignacion): array
{
    $roles = $asignacion['roles'];
    $sucursales = $asignacion['sucursales'];
    $rol = count($roles) === 1 ? $roles[0] : null;
    $sucursal = count($sucursales) === 1 ? $sucursales[0] : null;
    $inconsistencia = count($roles) !== 1 || count($sucursales) > 1;

    if ($rol !== null && $rol['nombre'] === 'Operador' && count($sucursales) !== 1) {
        $inconsistencia = true;
    }

    if ($rol !== null && $rol['nombre'] !== 'Operador' && count($sucursales) > 0) {
        $inconsistencia = true;
    }

    return [
        'id_usuario' => (int) $usuario->id_usuario,
        'nombres' => (string) $usuario->nombres,
        'apellidos' => (string) $usuario->apellidos,
        'username' => (string) $usuario->username,
        'estado' => esVerdadero($usuario->estado),
        'rol' => $rol['nombre'] ?? null,
        'id_rol' => $rol['id_rol'] ?? null,
        'sucursal' => $sucursal['nombre'] ?? null,
        'id_sucursal' => $sucursal['id_sucursal'] ?? null,
        'inconsistencia' => $inconsistencia,
    ];
}

function agruparAsignaciones(array $ids): array
{
    $agrupados = [];

    foreach ($ids as $id) {
        $agrupados[$id] = ['roles' => [], 'sucursales' => []];
    }

    if ($ids === []) {
        return $agrupados;
    }

    $roles = DB::table('usuario_rol')
        ->join('rol', 'rol.id_rol', '=', 'usuario_rol.id_rol')
        ->whereIn('usuario_rol.id_usuario', $ids)
        ->where('usuario_rol.estado', true)
        ->where('rol.estado', true)
        ->get(['usuario_rol.id_usuario', 'rol.id_rol', 'rol.nombre']);

    foreach ($roles as $rol) {
        $agrupados[(int) $rol->id_usuario]['roles'][] = [
            'id_rol' => (int) $rol->id_rol,
            'nombre' => (string) $rol->nombre,
        ];
    }

    $sucursales = DB::table('usuario_sucursal')
        ->join('sucursal', 'sucursal.id_sucursal', '=', 'usuario_sucursal.id_sucursal')
        ->whereIn('usuario_sucursal.id_usuario', $ids)
        ->where('usuario_sucursal.estado', true)
        ->get([
            'usuario_sucursal.id_usuario',
            'sucursal.id_sucursal',
            'sucursal.nombre',
        ]);

    foreach ($sucursales as $sucursal) {
        $agrupados[(int) $sucursal->id_usuario]['sucursales'][] = [
            'id_sucursal' => (int) $sucursal->id_sucursal,
            'nombre' => (string) $sucursal->nombre,
        ];
    }

    return $agrupados;
}

function kpisUsuarios(): array
{
    return [
        'usuarios_activos' => (int) Usuario::query()->where('estado', true)->count(),
        'usuarios_inactivos' => (int) Usuario::query()->where('estado', false)->count(),
        'administradores_activos' => contarRolActivo('Administrador'),
        'administrativos_activos' => contarRolActivo('Administrativo'),
        'operadores_activos' => contarRolActivo('Operador'),
    ];
}

function contarRolActivo(string $nombre): int
{
    return (int) Usuario::query()
        ->where('estado', true)
        ->whereExists(function ($q) use ($nombre) {
            $q->selectRaw('1')
                ->from('usuario_rol')
                ->join('rol', 'rol.id_rol', '=', 'usuario_rol.id_rol')
                ->whereColumn('usuario_rol.id_usuario', 'usuario.id_usuario')
                ->where('usuario_rol.estado', true)
                ->where('rol.estado', true)
                ->where('rol.nombre', $nombre);
        })
        ->count();
}

function catalogoRoles(): array
{
    $filas = Rol::query()
        ->where('estado', true)
        ->whereIn('nombre', rolesLogin())
        ->get(['id_rol', 'nombre']);
    $orden = array_flip(rolesLogin());
    $roles = [];

    foreach ($filas as $fila) {
        $roles[] = [
            'id_rol' => (int) $fila->id_rol,
            'nombre' => (string) $fila->nombre,
        ];
    }

    usort($roles, static function (array $a, array $b) use ($orden): int {
        return ($orden[$a['nombre']] ?? 9) <=> ($orden[$b['nombre']] ?? 9);
    });

    return $roles;
}

function catalogoSucursales(): array
{
    $filas = DB::table('sucursal')
        ->where('estado', true)
        ->orderBy('nombre')
        ->get(['id_sucursal', 'nombre', 'ciudad']);
    $sucursales = [];

    foreach ($filas as $fila) {
        $sucursales[] = [
            'id_sucursal' => (int) $fila->id_sucursal,
            'nombre' => (string) $fila->nombre,
            'ciudad' => (string) $fila->ciudad,
        ];
    }

    return $sucursales;
}

function rolPermitido(?int $idRol): ?object
{
    if ($idRol === null) {
        return null;
    }

    $rol = Rol::query()->where('id_rol', $idRol)->where('estado', true)->first();

    if ($rol === null || !in_array((string) $rol->nombre, rolesLogin(), true)) {
        return null;
    }

    return $rol;
}

function usernameDisponible(string $base): string
{
    $filas = Usuario::query()
        ->where('username', $base)
        ->orWhereRaw('username ~ ?', ['^' . $base . '[0-9]+$'])
        ->pluck('username');
    $ocupados = [];

    foreach ($filas as $username) {
        $ocupados[(string) $username] = true;
    }

    if (!isset($ocupados[$base])) {
        return $base;
    }

    $sufijo = 1;

    while (isset($ocupados[$base . $sufijo])) {
        $sufijo++;
    }

    return $base . $sufijo;
}

function usernameNormalizado(mixed $valor): ?string
{
    if (!is_string($valor)) {
        return null;
    }

    $username = sinAcentos(mb_strtolower(trim($valor), 'UTF-8'));
    $username = preg_replace('/[^a-z0-9]/', '', $username) ?? '';

    if (preg_match('/^[a-z][a-z0-9]{1,29}$/', $username) !== 1) {
        return null;
    }

    return $username;
}

function palabras(mixed $valor): array
{
    if (!is_string($valor)) {
        return [];
    }

    $texto = sinAcentos(mb_strtolower(trim($valor), 'UTF-8'));
    $texto = preg_replace('/[^a-z\s]/', ' ', $texto) ?? '';
    $texto = trim(preg_replace('/\s+/', ' ', $texto) ?? '');

    if ($texto === '') {
        return [];
    }

    return explode(' ', $texto);
}

function sinAcentos(string $texto): string
{
    return strtr($texto, [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n',
    ]);
}

function textoHumano(mixed $valor, int $maximo): ?string
{
    if (!is_string($valor)) {
        return null;
    }

    $texto = trim($valor);

    if ($texto === '' || mb_strlen($texto) > $maximo) {
        return null;
    }

    return $texto;
}

function hashPassword(string $password): string
{
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    if (!is_string($hash)) {
        rechazar('No disponible');
    }

    return $hash;
}

function passwordTemporal(): string
{
    $grupos = [
        'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'abcdefghijkmnpqrstuvwxyz',
        'abcdefghijkmnpqrstuvwxyz',
        '23456789',
        '23456789',
        '23456789',
        '23456789',
        '#@',
        'abcdefghijkmnpqrstuvwxyz',
    ];
    $caracteres = [];

    foreach ($grupos as $grupo) {
        $caracteres[] = $grupo[random_int(0, strlen($grupo) - 1)];
    }

    for ($indice = count($caracteres) - 1; $indice > 0; $indice--) {
        $destino = random_int(0, $indice);
        [$caracteres[$indice], $caracteres[$destino]] = [$caracteres[$destino], $caracteres[$indice]];
    }

    return implode('', $caracteres);
}

function rolesLogin(): array
{
    return ['Administrador', 'Administrativo', 'Operador'];
}

function exigirAdministrador(): bool
{
    $valida = false;

    if (isset($_COOKIE[session_name()])) {
        session_start();
        $valida = isset($_SESSION['id_usuario'], $_SESSION['username'], $_SESSION['id_rol'], $_SESSION['rol'])
            && $_SESSION['rol'] === 'Administrador';
    }

    if ($valida) {
        return true;
    }

    responder(403, [
        'exito' => false,
        'mensaje' => 'No disponible',
    ]);
}

function paginaSolicitada(array $entrada): int
{
    if (!array_key_exists('pagina', $entrada) || $entrada['pagina'] === null || $entrada['pagina'] === '') {
        return 1;
    }

    $pagina = enteroEntrada($entrada['pagina']);

    if ($pagina === null) {
        rechazar('Operación inválida.');
    }

    return $pagina;
}

function estadoFiltro(array $entrada): ?bool
{
    if (!array_key_exists('estado', $entrada) || $entrada['estado'] === null || $entrada['estado'] === '') {
        return null;
    }

    $estado = estadoObligatorio($entrada['estado']);

    if ($estado === null) {
        rechazar('Operación inválida.');
    }

    return $estado;
}

function filtroRol(array $entrada): ?int
{
    if (!array_key_exists('id_rol', $entrada) || $entrada['id_rol'] === null || $entrada['id_rol'] === '') {
        return null;
    }

    $idRol = enteroEntrada($entrada['id_rol']);
    $rol = rolPermitido($idRol);

    if ($rol === null) {
        rechazar('Rol no disponible');
    }

    return (int) $rol->id_rol;
}

function busquedaSolicitada(array $entrada): ?string
{
    if (!array_key_exists('busqueda', $entrada) || $entrada['busqueda'] === null) {
        return null;
    }

    if (!is_string($entrada['busqueda'])) {
        rechazar('Operación inválida.');
    }

    $texto = trim($entrada['busqueda']);

    if ($texto === '') {
        return null;
    }

    if (mb_strlen($texto) > 100) {
        rechazar('Operación inválida.');
    }

    return $texto;
}

function estadoObligatorio(mixed $valor): ?bool
{
    if ($valor === true || $valor === 1 || $valor === '1' || $valor === 'true') {
        return true;
    }

    if ($valor === false || $valor === 0 || $valor === '0' || $valor === 'false') {
        return false;
    }

    return null;
}

function enteroEntrada(mixed $valor): ?int
{
    if (is_int($valor) && $valor > 0) {
        return $valor;
    }

    if (is_string($valor) && preg_match('/^[1-9][0-9]*$/', $valor) === 1) {
        return (int) $valor;
    }

    return null;
}

function patron(string $texto): string
{
    return '%' . str_replace(['|', '%', '_'], ['||', '|%', '|_'], $texto) . '%';
}

function esVerdadero(mixed $valor): bool
{
    return $valor === true || $valor === 1 || $valor === '1' || $valor === 't' || $valor === 'true';
}

function esDuplicado(QueryException $e): bool
{
    return (string) ($e->errorInfo[0] ?? $e->getCode()) === '23505';
}

function textoONull(mixed $valor): ?string
{
    if (!is_string($valor) || trim($valor) === '') {
        return null;
    }

    return $valor;
}

function rechazar(string $mensaje): void
{
    throw new ValidacionAcceso($mensaje);
}

class ValidacionAcceso extends RuntimeException
{
}

function responder(int $codigo, array $cuerpo): void
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}
