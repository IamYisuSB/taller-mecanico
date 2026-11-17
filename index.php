<?php
/** @var string $titulo */
/** @var array $reparacion */
// Controlador frontal: todas las peticiones de la aplicación entran por aquí
require __DIR__ . '/config/conexion.php';
require __DIR__ . '/vistas/ayudas.php';
require __DIR__ . '/model/ReparacionModelo.php';
require __DIR__ . '/model/CatalogoModelo.php';
require __DIR__ . '/model/GestionModelo.php';
require __DIR__ . '/model/FacturaModelo.php';
require __DIR__ . '/controladores/ReparacionesControlador.php';
require __DIR__ . '/controladores/GestionControlador.php';
require __DIR__ . '/controladores/FacturasControlador.php';
require __DIR__ . '/model/EstadisticasModelo.php';
require __DIR__ . '/controladores/EstadisticasControlador.php';
require __DIR__ . '/lib/Sesion.php';
require __DIR__ . '/model/UsuarioModelo.php';
require __DIR__ . '/controladores/AuthControlador.php';

Sesion::iniciar();
$pdo = conectar();
$auth = new AuthControlador(new UsuarioModelo($pdo));
$accion = $_GET["accion"] ?? "listado";

// ---------- Control de acceso ----------
// Sin sesión, solo se puede ver el login y entrar
if (!Sesion::usuario() && !in_array($accion, ['login', 'entrar'], true)) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion !== 'salir') {
        Sesion::recordarDestino('index.php' . ($_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''));
    }
    header('Location: index.php?accion=login');
    exit;
}

// La cuenta demo es de solo lectura: cualquier envío de formulario (POST)
// que no sea salir se rechaza y se vuelve a la página de la que venía
if (Sesion::esDemo() && $_SERVER['REQUEST_METHOD'] === 'POST' && $accion !== 'salir') {
    Sesion::avisar('La cuenta demo es de solo lectura: este cambio no se ha guardado.', 'error');
    $volver = 'index.php';
    $origen = parse_url($_SERVER['HTTP_REFERER'] ?? '');
    if (($origen['host'] ?? '') === parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)
        && str_ends_with($origen['path'] ?? '', '/index.php')) {
        $volver = 'index.php' . (isset($origen['query']) ? '?' . $origen['query'] : '');
    }
    header('Location: ' . $volver);
    exit;
}

$reparacionModelo = new ReparacionModelo($pdo);
$facturaModelo = new FacturaModelo($pdo);
$controlador = new ReparacionesControlador($reparacionModelo, new CatalogoModelo($pdo), $facturaModelo);
$gestion = new GestionControlador(new GestionModelo($pdo));
$empresa = require __DIR__ . '/config/empresa.php';
$facturas = new FacturasControlador($facturaModelo, $reparacionModelo, $empresa);
$estadisticas = new EstadisticasControlador(new EstadisticasModelo($pdo), $empresa);
// echo "Se ha conectado correctamente";


match ($accion){
    // Sesión
    'login'  => $auth->formulario(),
    'entrar' => $auth->entrar(),
    'salir'  => $auth->salir(),

    'listado'      => $controlador->listado(),
    'ver'          => $controlador->ver(),
    'nueva'        => $controlador->nueva(),
    'guardar'      => $controlador->guardar(),
    'editar'       => $controlador->editar(),
    'actualizar'   => $controlador->actualizar(),
    'cerrar'       => $controlador->cerrar(),
    'pieza'        => $controlador->anadirPieza(),
    'quitar-pieza' => $controlador->quitarPieza(),
    'mano-obra'        => $controlador->anadirManoObra(),
    'quitar-mano-obra' => $controlador->quitarManoObra(),

    // Clientes, vehículos, mecánicos y piezas: ?tipo=clientes|vehiculos|mecanicos|piezas
    'gestion'         => $gestion->listado(),
    'gestion-editar'  => $gestion->editar(),
    'gestion-guardar' => $gestion->guardar(),
    'gestion-borrar'  => $gestion->borrar(),

    // Facturas
    'facturas'    => $facturas->listado(),
    'facturar'    => $facturas->generar(),
    'factura-pdf' => $facturas->pdf(),

    // Estadísticas de ingresos y facturación, y sus exportaciones
    'estadisticas'     => $estadisticas->ver(),
    'estadisticas-csv' => $estadisticas->csv(),
    'estadisticas-pdf' => $estadisticas->pdf(),

    default   => $controlador->noEncontrada(),
};


// TODO 2: con match, escribe «Aquí irá el listado de reparaciones» si la acción
//         es 'listado' y «No existe la acción …» (con la acción escapada)
//         en cualquier otro caso