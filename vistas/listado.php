<?php
// Variables que llegan desde el controlador con extract():
/** @var string $titulo */
/** @var array $reparaciones */
/** @var array $ordenes */
/** @var string $orden */
/** @var array $estados */
/** @var array $filtros */
/** @var array $mecanicos */
/** @var string $aviso */
require __DIR__ . '/cabecera.php';

$hayFiltros = $filtros['estado'] !== '' || $filtros['mecanico'] || $filtros['busqueda'] !== '';
?>

<?php if ($aviso): ?>
  <p class="aviso"><?= e($aviso) ?></p>
<?php endif; ?>

<form method="get" action="index.php" class="filtros tarjeta">
  <input type="hidden" name="accion" value="listado">

  <label class="filtro-busqueda">Buscar
    <input type="search" name="q" value="<?= e($filtros['busqueda']) ?>"
           placeholder="Matrícula, marca, modelo, avería o cliente…">
  </label>

  <label>Estado
    <select name="estado" data-auto>
      <?php foreach ($estados as $clave => $texto): ?>
        <option value="<?= e($clave) ?>" <?= $clave === $filtros['estado'] ? 'selected' : '' ?>><?= e($texto) ?></option>
      <?php endforeach; ?>
    </select>
  </label>

  <label>Mecánico
    <select name="mecanico" data-auto>
      <option value="">Todos</option>
      <?php foreach ($mecanicos as $m): ?>
        <option value="<?= $m['id'] ?>" <?= $filtros['mecanico'] == $m['id'] ? 'selected' : '' ?>><?= e($m['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>

  <label>Ordenar
    <select name="orden" data-auto>
      <?php foreach ($ordenes as $clave => $texto): ?>
        <option value="<?= e($clave) ?>" <?= $clave === $orden ? 'selected' : '' ?>><?= e($texto) ?></option>
      <?php endforeach; ?>
    </select>
  </label>

  <div class="filtros-botones">
    <button class="boton">Filtrar</button>
    <?php if ($hayFiltros): ?>
      <a class="boton secundario" href="index.php?accion=listado&amp;orden=<?= e($orden) ?>">Limpiar</a>
    <?php endif; ?>
  </div>
</form>

<p class="contador">
  <?= count($reparaciones) ?> <?= count($reparaciones) === 1 ? 'reparación' : 'reparaciones' ?>
  <?= $hayFiltros ? 'con estos filtros' : '' ?>
</p>

<?php if (!$reparaciones): ?>
    <p class="vacio"><?= $hayFiltros ? 'Ninguna reparación coincide con los filtros.' : 'Todavía no hay ninguna reparación.' ?></p>
<?php else: ?>
  <div class="tabla-envoltorio tarjeta">
    <table>
        <thead>
            <tr>
                <th>Entrada</th>
                <th>Vehículo</th>
                <th>Avería</th>
                <th>Mecánico</th>
                <th>Estado</th>
                <th class="acciones-col"><span class="oculto">Acciones</span></th>
            </tr>
        </thead>
        <tbody>
            <!-- TODO 3: una fila por reparación. Usa las funciones de ayudas.php:
           fecha() para las fechas y e() para escapar los textos.
           Vehículo: «Marca Modelo (matrícula)».
           Estado: «En el taller» en negrita, o «Salió el 21/10/2026» -->
            <?php foreach($reparaciones as $re): ?>
                <tr>
                    <td class="fecha"><?=  fecha($re["entrada"]) ?></td>
                    <td>
                        <div class="vehiculo">
                            <span><?= e($re["marca"] . " " . $re["modelo"]) ?></span>
                            <span class="matricula"><?= e($re["matricula"]) ?></span>
                        </div>
                        <small class="secundario"><?= e($re["cliente"]) ?></small>
                    </td>
                    <td>
                        <a href="index.php?accion=ver&amp;id=<?= $re["id"] ?>">
                        <?= e($re["descripcion"]) ?>
                    </a>
                    </td>
                    <td><?= e($re["mecanico"]) ?></td>
                    <td>
                        <?php if($re["salida"] === null): ?>
                            <strong class="insignia abierta">En el taller</strong>
                        <?php else: ?>
                            <span class="insignia cerrada">Salió el <?= fecha($re["salida"]) ?></span>
                         <?php endif; ?>
                    </td>
                    <td class="acciones">
                        <a class="boton pequeno secundario" href="index.php?accion=ver&amp;id=<?= $re["id"] ?>">Ver</a>
                        <?php if ($re["salida"] === null): ?>
                            <form method="post" action="index.php?accion=cerrar"
                                  data-confirmar="¿Cerrar la reparación nº <?= $re['id'] ?>? Se pondrá la fecha de salida de ahora.">
                                <input type="hidden" name="reparacion_id" value="<?= $re['id'] ?>">
                                <input type="hidden" name="volver" value="listado">
                                <button class="boton pequeno exito">Cerrar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/pie.php'; ?>
