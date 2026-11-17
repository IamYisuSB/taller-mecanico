<?php
/** @var string $titulo */

// Qué enlace del menú se marca como activo
$accionActual = $_GET['accion'] ?? 'listado';
$seccion = match (true) {
    $accionActual === 'nueva', $accionActual === 'guardar'    => 'nueva',
    str_starts_with($accionActual, 'gestion')                 => $_GET['tipo'] ?? $_POST['tipo'] ?? '',
    str_starts_with($accionActual, 'factura')                 => 'facturas',
    str_starts_with($accionActual, 'estadisticas')            => 'estadisticas',
    default                                                   => 'reparaciones',
};
$menu = [
    'reparaciones' => ['index.php', 'Reparaciones'],
    'nueva'        => ['index.php?accion=nueva', 'Nueva reparación'],
    'clientes'     => ['index.php?accion=gestion&tipo=clientes', 'Clientes'],
    'vehiculos'    => ['index.php?accion=gestion&tipo=vehiculos', 'Vehículos'],
    'mecanicos'    => ['index.php?accion=gestion&tipo=mecanicos', 'Mecánicos'],
    'piezas'       => ['index.php?accion=gestion&tipo=piezas', 'Piezas'],
    'facturas'     => ['index.php?accion=facturas', 'Facturas'],
    'estadisticas' => ['index.php?accion=estadisticas', 'Estadísticas'],
];
$usuarioActual = Sesion::usuario();
$avisoSesion = Sesion::sacarAviso();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo) ?> · Taller Genil</title>
  <link rel="stylesheet" href="estilos.css">
</head>
<body<?= Sesion::esDemo() ? ' data-demo' : '' ?>>
  <header class="cabecera">
    <div class="contenedor cabecera-interior">
      <a class="marca" href="index.php">
        <span class="marca-icono" aria-hidden="true">⚙</span>
        Taller Mecánico Genil
      </a>
      <?php if ($usuarioActual): ?>
        <div class="usuario-sesion">
          <span class="usuario-nombre">
            <?= e($usuarioActual['nombre']) ?>
            <span class="insignia rol-<?= e($usuarioActual['rol']) ?>"><?= $usuarioActual['rol'] === 'demo' ? 'Demo' : 'Admin' ?></span>
          </span>
          <form method="post" action="index.php?accion=salir" class="permitido-demo">
            <button class="boton pequeno salir">Salir</button>
          </form>
        </div>
      <?php endif; ?>
      <nav>
        <?php foreach ($menu as $clave => [$url, $texto]): ?>
          <a href="<?= e($url) ?>" <?= $clave === $seccion ? 'class="activo" aria-current="page"' : '' ?>><?= e($texto) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
  </header>
  <?php if (Sesion::esDemo()): ?>
    <div class="franja-demo" role="note">
      <div class="contenedor">
        <strong>Modo demo:</strong> solo lectura. Puedes verlo todo y descargar facturas y estadísticas; guardar, editar y borrar están desactivados.
      </div>
    </div>
  <?php endif; ?>
  <main class="contenedor">
    <h1><?= e($titulo) ?></h1>
    <?php if ($avisoSesion): ?>
      <p class="<?= $avisoSesion['tipo'] ?>" role="status"><?= e($avisoSesion['texto']) ?></p>
    <?php endif; ?>
