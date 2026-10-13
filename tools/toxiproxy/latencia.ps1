# Herramienta local de pruebas de AWR. No forma parte de la aplicación.
# Cambia la latencia artificial de Toxiproxy durante las pruebas de la tesis.
#
# 1. Cambia $LATENCIA_MS.
# 2. Guarda este archivo.
# 3. Ejecuta: powershell -ExecutionPolicy Bypass -File .\tools\toxiproxy\latencia.ps1
# 4. 0 = conexión normal, sin latencia artificial.
# 5. Un valor mayor que 0 aplica esa latencia en milisegundos.

$LATENCIA_MS = 200

$Api = 'http://localhost:8474'
$NombreProxy = 'awr_postgres_proxy'
$NombreToxic = 'awr_latency'
$Listen = '0.0.0.0:15433'
$Upstream = 'host.docker.internal:5433'
$PuertoAwr = 'localhost:15433'

function Invoke-Toxiproxy {
    param(
        [Parameter(Mandatory = $true)]
        [ValidateSet('GET', 'POST', 'DELETE')]
        [string]$Metodo,

        [Parameter(Mandatory = $true)]
        [string]$Ruta,

        [hashtable]$Cuerpo
    )

    $parametros = @{
        Uri         = $Api + $Ruta
        Method      = $Metodo
        Headers     = @{ 'User-Agent' = 'toxiproxy-cli' }
        ErrorAction = 'Stop'
    }

    if ($null -ne $Cuerpo) {
        $parametros.ContentType = 'application/json'
        $parametros.Body = ($Cuerpo | ConvertTo-Json -Compress -Depth 5)
    }

    try {
        $datos = Invoke-RestMethod @parametros
        return @{ Ok = $true; Codigo = 200; Datos = $datos }
    }
    catch {
        $codigo = $null

        if ($_.Exception.Response) {
            try {
                $codigo = [int]$_.Exception.Response.StatusCode
            }
            catch {
                $codigo = $null
            }
        }

        return @{ Ok = $false; Codigo = $codigo; Datos = $null }
    }
}

function Convertir-Lista {
    param($Valor)

    if ($null -eq $Valor) {
        return @()
    }

    return @($Valor)
}

function Detener {
    param([string]$Mensaje)

    Write-Host $Mensaje
    exit 1
}

if ($null -eq $LATENCIA_MS -or ("$LATENCIA_MS" -notmatch '^\d+$')) {
    Detener 'AWR Toxiproxy: LATENCIA_MS no es válido. Usa un número entero mayor o igual que 0 (0, 100, 200, 300, 500, 800, 1000).'
}

$Latencia = [int]$LATENCIA_MS

$version = Invoke-Toxiproxy -Metodo GET -Ruta '/version'

if (-not $version.Ok) {
    if ($null -eq $version.Codigo) {
        Detener 'AWR Toxiproxy: awr_toxiproxy no está disponible en http://localhost:8474. Comprueba que el contenedor esté en ejecución.'
    }

    Detener 'AWR Toxiproxy: la API respondió, pero no se pudo consultar su versión.'
}

$respuestaProxy = Invoke-Toxiproxy -Metodo GET -Ruta "/proxies/$NombreProxy"

if (-not $respuestaProxy.Ok -and $respuestaProxy.Codigo -eq 404) {
    $respuestaProxy = Invoke-Toxiproxy -Metodo POST -Ruta '/proxies' -Cuerpo @{
        name     = $NombreProxy
        listen   = $Listen
        upstream = $Upstream
        enabled  = $true
    }

    if (-not $respuestaProxy.Ok) {
        Detener 'AWR Toxiproxy: no se pudo crear awr_postgres_proxy.'
    }

    Write-Host 'AWR Toxiproxy: proxy awr_postgres_proxy creado.'
}
elseif (-not $respuestaProxy.Ok) {
    if ($null -eq $respuestaProxy.Codigo) {
        Detener 'AWR Toxiproxy: awr_toxiproxy no está disponible en http://localhost:8474. Comprueba que el contenedor esté en ejecución.'
    }

    Detener 'AWR Toxiproxy: no se pudo consultar awr_postgres_proxy.'
}

$toxicos = Invoke-Toxiproxy -Metodo GET -Ruta "/proxies/$NombreProxy/toxics"

if (-not $toxicos.Ok) {
    Detener 'AWR Toxiproxy: no se pudo consultar la latencia actual.'
}

$existe = $false

foreach ($item in (Convertir-Lista $toxicos.Datos)) {
    if ($item.name -eq $NombreToxic) {
        $existe = $true
    }
}

if ($Latencia -eq 0) {
    if ($existe) {
        $borrado = Invoke-Toxiproxy -Metodo DELETE -Ruta "/proxies/$NombreProxy/toxics/$NombreToxic"

        if (-not $borrado.Ok -and $borrado.Codigo -ne 404) {
            Detener 'AWR Toxiproxy: no se pudo desactivar la latencia artificial.'
        }
    }

    $textoLatencia = '0 ms'
    $estado = 'DESACTIVADA'
    Write-Host 'AWR Toxiproxy: latencia artificial desactivada (0 ms)'
}
else {
    $cuerpoAtributos = @{
        attributes = @{
            latency = $Latencia
            jitter  = 0
        }
    }

    if ($existe) {
        $aplicado = Invoke-Toxiproxy -Metodo POST -Ruta "/proxies/$NombreProxy/toxics/$NombreToxic" -Cuerpo $cuerpoAtributos
    }
    else {
        $aplicado = Invoke-Toxiproxy -Metodo POST -Ruta "/proxies/$NombreProxy/toxics" -Cuerpo @{
            name       = $NombreToxic
            type       = 'latency'
            stream     = 'downstream'
            toxicity   = 1
            attributes = @{
                latency = $Latencia
                jitter  = 0
            }
        }
    }

    if (-not $aplicado.Ok) {
        Detener 'AWR Toxiproxy: no se pudo aplicar la latencia artificial.'
    }

    $textoLatencia = "$Latencia ms"
    $estado = 'ACTIVA'
    Write-Host "AWR Toxiproxy: latencia configurada en $Latencia ms"
}

$destino = $Upstream

if ($respuestaProxy.Datos -and $respuestaProxy.Datos.upstream) {
    $destino = [string]$respuestaProxy.Datos.upstream
}

Write-Host ''
Write-Host 'Proxy:'
Write-Host $NombreProxy
Write-Host ''
Write-Host 'PostgreSQL destino:'
Write-Host $destino
Write-Host ''
Write-Host 'Puerto de AWR:'
Write-Host $PuertoAwr
Write-Host ''
Write-Host 'Latencia artificial:'
Write-Host $textoLatencia
Write-Host ''
Write-Host 'Estado:'
Write-Host $estado
