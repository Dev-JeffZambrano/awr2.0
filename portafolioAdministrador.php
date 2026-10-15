<?php

$script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$directorio = str_replace('\\', '/', dirname($script));
$rutaCookie = rtrim($directorio, '/');
$rutaCookie = ($rutaCookie === '' || $rutaCookie === '.') ? '/' : $rutaCookie . '/';
$login = $rutaCookie . 'login.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => $rutaCookie,
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

$rolEsperado = 'Administrador';
$sesionValida = false;

if (isset($_COOKIE[session_name()])) {
    session_start();
    $sesionValida = isset($_SESSION['id_usuario'], $_SESSION['username'], $_SESSION['id_rol'], $_SESSION['rol'])
        && $_SESSION['rol'] === $rolEsperado;
}

if (!$sesionValida) {
    header('Location: ' . $login, true, 302);
    exit;
}

$nombreCompleto = trim(
    ($_SESSION['nombres'] ?? '') . ' ' .
    ($_SESSION['apellidos'] ?? '')
);

if ($nombreCompleto === '') {
    $nombreCompleto = $_SESSION['username'] ?? 'Usuario';
}

$nombreVisible = htmlspecialchars((string) $nombreCompleto, ENT_QUOTES, 'UTF-8');
$rol = htmlspecialchars((string) $_SESSION['rol'], ENT_QUOTES, 'UTF-8');
$inicialFuente = (string) $nombreCompleto;
$inicialBruta = function_exists('mb_substr')
    ? mb_substr($inicialFuente, 0, 1, 'UTF-8')
    : substr($inicialFuente, 0, 1);
$inicial = htmlspecialchars($inicialBruta, ENT_QUOTES, 'UTF-8');

$icono = static function (string $contenido): string {
    return '<svg viewBox="0 0 24 24" aria-hidden="true">' . $contenido . '</svg>';
};

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portafolio Digital | AWR</title>
    <link rel="stylesheet" href="public/css/variables.css">
    <link rel="stylesheet" href="public/css/dashboardAdministrador.css?v=4">
    <link rel="stylesheet" href="public/css/portafolioAdministrador.css?v=1">
</head>
<body>
    <div class="mando">
        <aside class="barra" aria-label="Navegación del administrador">
            <div class="barra__marca">
                <div class="barra__logo">
                    <img src="public/img/branding/awr-logo.png" alt="AWR">
                </div>
                <p>Aplicativo Web Resiliente</p>
            </div>
            <details class="barra__menu" open>
                <summary>Secciones</summary>
                <nav class="barra__nav">
                    <p class="barra__grupo">Operación</p>
                    <a class="barra__item" href="dashboardAdministrador.php">
                        <?= $icono('<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>') ?>
                        <span>Dashboard Principal</span>
                    </a>
                    <a class="barra__item" href="monitoreoAdministrador.php">
                        <?= $icono('<path d="M4 13h4l2-6 4 12 2-6h4"/>') ?>
                        <span>Monitoreo en Tiempo Real</span>
                    </a>
                    <a class="barra__item" href="serviciosAdministrador.php">
                        <?= $icono('<path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 16 9 5 9-5"/>') ?>
                        <span>Servicios</span>
                    </a>
                    <a class="barra__item" href="basesDatosAdministrador.php">
                        <?= $icono('<ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6"/><path d="M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/>') ?>
                        <span>Bases de Datos</span>
                    </a>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<path d="M4 19V5M4 19h16"/><path d="M8 16v-4M12 16V8M16 16v-6"/>') ?>
                        <span>Reportes</span>
                    </button>

                    <p class="barra__grupo">Analítica</p>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<path d="M4 16 9 11l3 3 8-8"/><path d="M15 6h5v5"/>') ?>
                        <span>Métricas de Rendimiento</span>
                    </button>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<path d="M8 6h12M8 12h12M8 18h12"/><path d="M4 6h.01M4 12h.01M4 18h.01"/>') ?>
                        <span>Logs y Trazas</span>
                    </button>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<path d="M6 9a6 6 0 0 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9"/><path d="M10 21a2 2 0 0 0 4 0"/>') ?>
                        <span>Alertas</span>
                    </button>

                    <p class="barra__grupo">Administración</p>
                    <a class="barra__item" href="usuariosAccesosAdministrador.php">
                        <?= $icono('<path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z"/><path d="M5 20a7 7 0 0 1 14 0"/>') ?>
                        <span>Usuarios y Accesos</span>
                    </a>
                    <a class="barra__item" href="clientesAdministrador.php">
                        <?= $icono('<path d="M8 11a3 3 0 1 0-3-3 3 3 0 0 0 3 3Z"/><path d="M16 11a3 3 0 1 0-3-3"/><path d="M2 19a6 6 0 0 1 10.5-3.8"/><path d="M22 19a6 6 0 0 0-4-5.6"/>') ?>
                        <span>Clientes</span>
                    </a>
                    <a class="barra__item" href="configuracionAdministrador.php">
                        <?= $icono('<circle cx="12" cy="12" r="3"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/>') ?>
                        <span>Configuración</span>
                    </a>
                    <button class="barra__item barra__item--actual" type="button" aria-current="page">
                        <?= $icono('<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/>') ?>
                        <span>Portafolio Digital</span>
                    </button>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<path d="M8 4h8v16H8z"/><path d="M8 8H6M8 12H6M8 16H6M12 8h2M12 12h2"/>') ?>
                        <span>Auditoría</span>
                    </button>
                </nav>
            </details>
            <p class="barra__nota">Portafolio personal</p>
        </aside>

        <div class="zona">
            <header class="tope">
                <label class="buscar">
                    <span class="solo-lectura">Búsqueda visual</span>
                    <?= $icono('<circle cx="11" cy="11" r="6"/><path d="m20 20-3.5-3.5"/>') ?>
                    <input type="search" placeholder="Buscar" readonly>
                </label>
                <ul class="tope__senales" aria-label="Indicadores del sistema">
                    <li><span>Disponibilidad</span><strong id="senal-disponibilidad">…</strong></li>
                    <li><span>Latencia</span><strong id="senal-latencia">…</strong></li>
                    <li><span>Sincronización</span><strong id="senal-sincronizacion">…</strong></li>
                </ul>
                <div class="tope__cuenta">
                    <div class="tope__identidad">
                        <span class="tope__avatar" aria-hidden="true"><?= $inicial ?></span>
                        <div>
                            <p class="tope__usuario"><?= $nombreVisible ?></p>
                            <p class="tope__rol"><?= $rol ?></p>
                        </div>
                    </div>
                    <button class="cerrar" id="cerrar-sesion" type="button">Cerrar sesión</button>
                </div>
            </header>
            <p class="tope__aviso" id="mensaje-sesion" role="alert" hidden></p>

            <main class="principal portafolio">
                <header class="encabezado">
                    <div>
                        <h1>Portafolio Digital</h1>
                        <p>Perfil profesional integrado en el panel Administrador de AWR.</p>
                    </div>
                </header>

                <nav class="portafolio-anclas" aria-label="Secciones del portafolio">
                    <a href="#inicio">Inicio</a>
                    <a href="#sobre-mi">Sobre mí</a>
                    <a href="#experiencia">Experiencia</a>
                    <a href="#skills">Skills</a>
                    <a href="#proyectos">Proyectos</a>
                    <a href="#contacto">Contacto</a>
                </nav>

                <section class="portafolio-seccion portafolio-hero" id="inicio">
                    <div class="portafolio-hero__texto">
                        <p class="portafolio-ceja">Hola, soy</p>
                        <h2 class="portafolio-hero__titulo">Jefferson Antonio <span>Zambrano Jama</span></h2>
                        <p class="portafolio-hero__rol">Estudiante de Ingeniería de Software <span aria-hidden="true">•</span> Desarrollador Web</p>
                        <p>Desarrollo soluciones web y participo en el mantenimiento de aplicaciones empresariales, con interés en arquitectura de software, bases de datos y procesamiento de datos.</p>
                        <div class="portafolio-hero__acciones">
                            <a class="portafolio-boton portafolio-boton--principal" href="#proyectos">Ver proyectos</a>
                            <a class="portafolio-boton" href="#sobre-mi">Conocer más</a>
                        </div>
                        <ul class="portafolio-resumen" aria-label="Resumen profesional">
                            <li><strong>Ingeniería de Software</strong><span>Universidad Estatal de Milagro</span></li>
                            <li><strong>Desarrollo Web</strong><span>Experiencia empresarial</span></li>
                            <li><strong>Datos y arquitectura</strong><span>Área de crecimiento</span></li>
                        </ul>
                    </div>
                    <figure class="portafolio-foto">
                        <img src="public/img/portafolio/perfil-jefferson.png" alt="Jefferson Zambrano" width="280" height="280">
                        <figcaption>Jefferson Zambrano <span>Software &amp; Web Development</span></figcaption>
                    </figure>
                </section>

                <section class="portafolio-seccion" id="sobre-mi">
                    <header class="portafolio-seccion__cabeza">
                        <p class="portafolio-ceja">Perfil</p>
                        <h2>Sobre mí</h2>
                        <p>Formación, experiencia e intereses que definen mi perfil profesional como desarrollador.</p>
                    </header>
                    <div class="portafolio-sobre">
                        <article class="portafolio-card">
                            <h3>Mi perfil profesional</h3>
                            <p>Soy estudiante de Ingeniería de Software en la Universidad Estatal de Milagro y desarrollador web con experiencia en desarrollo, mantenimiento y soporte de aplicaciones empresariales.</p>
                            <p>He participado en el desarrollo y mantenimiento de aplicaciones empresariales orientadas a la gestión ambiental, gestión de información, generación de reportes y seguimiento de procesos, además de brindar soporte e inducción a usuarios.</p>
                            <p>Actualmente continúo fortaleciendo mis conocimientos en arquitectura de software, procesamiento de grandes volúmenes de datos, inteligencia artificial y nuevas tecnologías.</p>
                            <p>El proyecto de titulación en el que trabajo es AWR, un aplicativo de facturación y monitoreo preparado para seguir operando cuando la comunicación se degrada.</p>
                            <div class="portafolio-formacion">
                                <span>Formación actual</span>
                                <strong>Ingeniería de Software</strong>
                                <span>Universidad Estatal de Milagro</span>
                            </div>
                        </article>
                        <aside class="portafolio-card" aria-label="Áreas de interés">
                            <h3>Áreas de interés</h3>
                            <p>Áreas en las que actualmente desarrollo y fortalezco mis conocimientos.</p>
                            <ul class="portafolio-etiquetas">
                                <li>Desarrollo Web</li>
                                <li>Backend</li>
                                <li>Bases de Datos</li>
                                <li>Arquitectura de Software</li>
                                <li>Inteligencia Artificial</li>
                                <li>Ingeniería y análisis de datos</li>
                            </ul>
                        </aside>
                    </div>
                </section>

                <section class="portafolio-seccion" id="experiencia">
                    <header class="portafolio-seccion__cabeza">
                        <p class="portafolio-ceja">Trayectoria</p>
                        <h2>Experiencia</h2>
                        <p>Experiencia profesional y prácticas desarrolladas durante mi formación en Ingeniería de Software.</p>
                    </header>
                    <div class="portafolio-timeline">
                        <article class="portafolio-hito">
                            <div class="portafolio-hito__cabeza">
                                <div>
                                    <p class="portafolio-hito__fecha">Agosto 2026 — Actualidad</p>
                                    <h3>Desarrollador Web</h3>
                                </div>
                                <span class="portafolio-insignia">Actual</span>
                            </div>
                            <p class="portafolio-hito__empresa">Soluciones Ambientales Totales Sambito S.A.</p>
                            <p class="portafolio-hito__area">Departamento de TIC</p>
                            <p>Formo parte del Departamento de TIC de Soluciones Ambientales Totales Sambito S.A., donde participo en el desarrollo y mantenimiento de soluciones tecnológicas, administración de bases de datos, gestión de servidores y desarrollo web con JavaScript.</p>
                            <p>Entre los sistemas en los que colaboro se encuentra Nikola, plataforma para la gestión integral del cumplimiento de obligaciones ambientales. También brindo soporte y gestión tecnológica dentro del Departamento de TIC, incluyendo mejoras funcionales, corrección de inconsistencias e inducciones a usuarios.</p>
                            <ul class="portafolio-etiquetas" aria-label="Tecnologías utilizadas">
                                <li>HTML5</li>
                                <li>CSS3</li>
                                <li>JavaScript</li>
                                <li>PHP</li>
                                <li>PostgreSQL</li>
                                <li>Eloquent ORM</li>
                            </ul>
                        </article>
                        <article class="portafolio-hito">
                            <p class="portafolio-hito__fecha">Mayo 2026 — Julio 2026</p>
                            <h3>Pasante de Desarrollo de Software</h3>
                            <p class="portafolio-hito__empresa">Soluciones Ambientales Totales Sambito S.A.</p>
                            <p class="portafolio-hito__area">Departamento de TIC</p>
                            <p>Desarrollo y mantenimiento de módulos empresariales, con participación en funcionalidades relacionadas con la generación y presentación de reportes, documentos PDF y envío de reportes por correo electrónico.</p>
                            <ul class="portafolio-etiquetas" aria-label="Tecnologías utilizadas">
                                <li>HTML5</li>
                                <li>CSS3</li>
                                <li>PHP</li>
                                <li>PostgreSQL</li>
                                <li>Eloquent ORM</li>
                            </ul>
                        </article>
                        <article class="portafolio-hito">
                            <p class="portafolio-hito__fecha">Septiembre 2025 — Diciembre 2025</p>
                            <h3>Pasante de Desarrollo y Mantenimiento de Software</h3>
                            <p class="portafolio-hito__empresa">Comisariato El Constructor</p>
                            <p>Participación en actividades de mantenimiento y mejora de aplicaciones, colaborando en interfaces, funcionalidades y gestión de información.</p>
                            <ul class="portafolio-etiquetas" aria-label="Tecnologías utilizadas">
                                <li>HTML5</li>
                                <li>CSS3</li>
                                <li>React</li>
                                <li>MongoDB</li>
                            </ul>
                        </article>
                    </div>
                </section>

                <section class="portafolio-seccion" id="skills">
                    <header class="portafolio-seccion__cabeza">
                        <p class="portafolio-ceja">Conocimientos</p>
                        <h2>Habilidades / Skills</h2>
                        <p>Tecnologías y herramientas con las que he trabajado durante mi formación académica y experiencia profesional.</p>
                    </header>
                    <div class="portafolio-skills">
                        <article class="portafolio-card">
                            <h3>Frontend</h3>
                            <ul class="portafolio-etiquetas">
                                <li>HTML5 <span class="portafolio-marca">En AWR</span></li>
                                <li>CSS3 <span class="portafolio-marca">En AWR</span></li>
                                <li>JavaScript <span class="portafolio-marca">En AWR</span></li>
                            </ul>
                        </article>
                        <article class="portafolio-card">
                            <h3>Backend</h3>
                            <ul class="portafolio-etiquetas">
                                <li>PHP <span class="portafolio-marca">En AWR</span></li>
                                <li>Python</li>
                                <li>Node.js</li>
                                <li>Express</li>
                                <li>Eloquent ORM</li>
                            </ul>
                        </article>
                        <article class="portafolio-card">
                            <h3>Bases de datos</h3>
                            <ul class="portafolio-etiquetas">
                                <li>PostgreSQL <span class="portafolio-marca">En AWR</span></li>
                                <li>MongoDB</li>
                            </ul>
                        </article>
                        <article class="portafolio-card">
                            <h3>Herramientas</h3>
                            <ul class="portafolio-etiquetas">
                                <li>Git <span class="portafolio-marca">En AWR</span></li>
                                <li>GitHub <span class="portafolio-marca">En AWR</span></li>
                                <li>Docker <span class="portafolio-marca">En AWR</span></li>
                                <li>Navicat</li>
                                <li>FileZilla</li>
                                <li>Visual Studio Code</li>
                            </ul>
                        </article>
                        <article class="portafolio-card">
                            <h3>Frameworks</h3>
                            <ul class="portafolio-etiquetas">
                                <li>Django</li>
                                <li>React</li>
                                <li>Streamlit</li>
                            </ul>
                        </article>
                        <article class="portafolio-card">
                            <h3>Cloud</h3>
                            <ul class="portafolio-etiquetas">
                                <li>AWS — Fundamentos</li>
                            </ul>
                        </article>
                        <article class="portafolio-card">
                            <h3>En aprendizaje</h3>
                            <ul class="portafolio-etiquetas">
                                <li>ClickHouse</li>
                                <li>Redpanda</li>
                                <li>Apache Spark</li>
                                <li>Power BI</li>
                            </ul>
                        </article>
                    </div>
                </section>

                <section class="portafolio-seccion" id="proyectos">
                    <header class="portafolio-seccion__cabeza">
                        <p class="portafolio-ceja">Portafolio</p>
                        <h2>Proyectos destacados</h2>
                        <p>Proyectos académicos y empresariales en los que he participado durante mi formación y experiencia profesional.</p>
                    </header>
                    <div class="portafolio-proyectos">
                        <article class="portafolio-proyecto portafolio-proyecto--destacado">
                            <h3>AWR — Aplicativo Web Resiliente</h3>
                            <p class="portafolio-proyecto__completo">Desarrollo de un aplicativo web resiliente para procesamiento transaccional y análisis de grandes volúmenes de datos</p>
                            <span class="portafolio-insignia">Actualmente en desarrollo</span>
                            <p class="portafolio-proyecto__texto">Aplicativo web de procesamiento transaccional resiliente cuando la comunicación se degrada, con arquitectura preparada para análisis de grandes volúmenes.</p>
                            <div>
                                <p class="portafolio-etiqueta-titulo">Tecnologías implementadas</p>
                                <ul class="portafolio-etiquetas" aria-label="Tecnologías implementadas en AWR">
                                    <li>PHP</li>
                                    <li>JavaScript</li>
                                    <li>Axios</li>
                                    <li>PostgreSQL</li>
                                    <li>Docker</li>
                                    <li>Toxiproxy</li>
                                    <li>Git</li>
                                    <li>GitHub</li>
                                </ul>
                            </div>
                            <div class="portafolio-funciones" hidden>
                                <p class="portafolio-etiqueta-titulo">Funcionalidad</p>
                                <ul>
                                    <li>Facturación simulada</li>
                                    <li>PostgreSQL como motor transaccional</li>
                                    <li>Degradación controlada mediante Toxiproxy</li>
                                    <li>Monitoreo de latencia</li>
                                    <li>Módulos por roles</li>
                                    <li>Panel Administrador</li>
                                    <li>Preparación para arquitectura resiliente</li>
                                </ul>
                            </div>
                            <div>
                                <p class="portafolio-etiqueta-titulo">Próximas integraciones</p>
                                <ul class="portafolio-pendientes">
                                    <li><strong>Redpanda</strong><span>En integración</span><small>Mensajería para resiliencia</small></li>
                                    <li><strong>ClickHouse</strong><span>Pendiente</span><small>Analítica OLAP</small></li>
                                    <li><strong>Power BI</strong><span>Planificado</span><small>Visualización analítica</small></li>
                                </ul>
                            </div>
                            <button class="portafolio-boton" type="button" data-detalle>Ver detalles</button>
                        </article>

                        <article class="portafolio-proyecto">
                            <h3>SIP NEW</h3>
                            <p class="portafolio-proyecto__estado">Proyecto empresarial privado</p>
                            <p class="portafolio-proyecto__texto">Sistema empresarial orientado a la gestión y seguimiento de proyectos ambientales.</p>
                            <div>
                                <p class="portafolio-etiqueta-titulo">Mi participación</p>
                                <p>Desarrollo y mejora de funcionalidades relacionadas con reportes, generación de documentos PDF y envío de reportes mediante correo electrónico.</p>
                            </div>
                            <div>
                                <p class="portafolio-etiqueta-titulo">Tecnologías</p>
                                <ul class="portafolio-etiquetas" aria-label="Tecnologías utilizadas">
                                    <li>HTML5</li>
                                    <li>CSS3</li>
                                    <li>PHP</li>
                                    <li>PostgreSQL</li>
                                    <li>Eloquent ORM</li>
                                    <li>PDFdiller</li>
                                    <li>Mailer</li>
                                </ul>
                            </div>
                            <button class="portafolio-boton" type="button" data-detalle>Ver detalles</button>
                        </article>

                        <article class="portafolio-proyecto">
                            <h3>Nikola — Plataforma de Gestión Ambiental</h3>
                            <p class="portafolio-proyecto__estado">Proyecto empresarial privado</p>
                            <p class="portafolio-proyecto__texto">Plataforma empresarial para la gestión integral del cumplimiento ambiental, permitiendo realizar seguimiento de obligaciones, planes de manejo, indicadores de desempeño, no conformidades, documentación y comunicación con asesores ambientales.</p>
                            <div>
                                <p class="portafolio-etiqueta-titulo">Mi participación</p>
                                <p>Desarrollo y mantenimiento de mejoras en los sistemas Nikola Técnico y Nikola Cliente, corrección de inconsistencias, soporte a usuarios e inducciones para el correcto uso de la plataforma.</p>
                            </div>
                            <div>
                                <p class="portafolio-etiqueta-titulo">Tecnologías</p>
                                <ul class="portafolio-etiquetas" aria-label="Tecnologías utilizadas">
                                    <li>HTML5</li>
                                    <li>CSS3</li>
                                    <li>JavaScript</li>
                                    <li>PHP</li>
                                    <li>PostgreSQL</li>
                                    <li>Eloquent ORM</li>
                                </ul>
                            </div>
                            <button class="portafolio-boton" type="button" data-detalle>Ver detalles</button>
                        </article>

                        <article class="portafolio-proyecto">
                            <h3>Clasificación corporal mediante Machine Learning</h3>
                            <p class="portafolio-proyecto__texto">Proyecto académico orientado a la clasificación de tipos corporales mediante técnicas de inteligencia artificial y Machine Learning.</p>
                            <div>
                                <p class="portafolio-etiqueta-titulo">Resultado</p>
                                <p>Clasificación entre endomorfo, mesomorfo y ectomorfo.</p>
                            </div>
                            <div>
                                <p class="portafolio-etiqueta-titulo">Tecnologías</p>
                                <ul class="portafolio-etiquetas" aria-label="Tecnologías utilizadas">
                                    <li>Python</li>
                                    <li>Streamlit</li>
                                    <li>Machine Learning</li>
                                </ul>
                            </div>
                            <button class="portafolio-boton" type="button" data-detalle>Ver detalles</button>
                        </article>
                    </div>
                </section>

                <section class="portafolio-seccion" id="contacto">
                    <header class="portafolio-seccion__cabeza">
                        <p class="portafolio-ceja">Comunicación</p>
                        <h2>Contacto</h2>
                        <p>Puedes contactarme para conversar sobre desarrollo, proyectos o colaboración profesional.</p>
                    </header>
                    <div class="portafolio-contactos">
                        <a class="portafolio-contacto" href="https://github.com/Ing-Jeff-Zambrano" target="_blank" rel="noopener noreferrer">
                            <strong>GitHub</strong>
                            <span>github.com/Ing-Jeff-Zambrano</span>
                        </a>
                        <a class="portafolio-contacto" href="https://www.linkedin.com/in/jefferson-zambrano-10174a2bb/" target="_blank" rel="noopener noreferrer">
                            <strong>LinkedIn</strong>
                            <span>linkedin.com/in/jefferson-zambrano-10174a2bb</span>
                        </a>
                        <a class="portafolio-contacto" href="mailto:zambrano.jeff94@gmail.com">
                            <strong>Correo</strong>
                            <span>zambrano.jeff94@gmail.com</span>
                        </a>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <dialog class="portafolio-modal" id="portafolio-modal" aria-labelledby="portafolio-modal-titulo">
        <div class="portafolio-modal__cuerpo">
            <div class="portafolio-modal__cabeza">
                <h2 id="portafolio-modal-titulo"></h2>
                <button class="portafolio-boton" id="portafolio-cerrar" type="button">Cerrar</button>
            </div>
            <p class="portafolio-insignia" id="portafolio-modal-estado" hidden></p>
            <div id="portafolio-modal-detalle"></div>
        </div>
    </dialog>

    <script src="public/js/axios.min.js"></script>
    <script src="public/js/senalesAdministrador.js?v=2"></script>
    <script src="public/js/portafolioAdministrador.js?v=2"></script>
</body>
</html>
