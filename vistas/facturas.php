<?php
// Listado de facturas
/** @var string $titulo */
/** @var array  $facturas */
/** @var string $busqueda */
/** @var float  $total */
require __DIR__ . '/cabecera.php';
?>

<div class="barra">
  <form method="get" action="index.php" class="buscador">
    <input type="hidden" name="accion" value="facturas">
    <input type="search" name="q" value="<?= e($busqueda) ?>" aria-label="Buscar"
           placeholder="Número, cliente, vehículo o matrícula…">
    <button class="boton secundario">Buscar</button>
    <?php if ($busqueda !== ''): ?>
      <a class="boton secundario" href="index.php?accion=facturas">Limpiar</a>
    <?php endif; ?>
  </form>
</div>

<p class="contador">
  <?= count($facturas) ?> factura<?= count($facturas) === 1 ? '' : 's' ?> · Total facturado: <strong><?= euros($total) ?></strong>
</p>

<?php if (!$facturas): ?>
  <p class="vacio">
    <?= $busqueda !== '' ? 'No hay facturas para «' . e($busqueda) . '».' : 'Todavía no hay facturas. Se generan desde la ficha de una reparación cerrada.' ?>
  </p>
<?php else: ?>
  <div class="tabla-envoltorio tarjeta">
    <table>
      <thead>
        <tr>
          <th>Número</th>
          <th>Fecha</th>
          <th>Cliente</th>
          <th>Vehículo</th>
          <th class="num">Base</th>
          <th class="num">IVA</th>
          <th class="num">Total</th>
          <th class="acciones-col"><span class="oculto">Acciones</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($facturas as $f): ?>
          <tr>
            <td><strong><?= e($f['numero']) ?></strong>
              <small class="secundario"><a href="index.php?accion=ver&amp;id=<?= $f['id_reparacion'] ?>">Reparación nº <?= $f['id_reparacion'] ?></a></small>
            </td>
            <td class="fecha"><?= fecha($f['fecha']) ?></td>
            <td><?= e($f['cliente']) ?></td>
            <td>
              <div class="vehiculo">
                <span><?= e($f['vehiculo']) ?></span>
                <span class="matricula"><?= e($f['matricula']) ?></span>
              </div>
            </td>
            <td class="num"><?= euros($f['base']) ?></td>
            <td class="num"><?= euros($f['iva']) ?></td>
            <td class="num"><strong><?= euros($f['total']) ?></strong></td>
            <td class="acciones">
              <a class="boton pequeno secundario" href="index.php?accion=factura-pdf&amp;id=<?= $f['id'] ?>" target="_blank" rel="noopener">Ver</a>
              <a class="boton pequeno" href="index.php?accion=factura-pdf&amp;id=<?= $f['id'] ?>&amp;descargar=1">Descargar</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/pie.php'; ?>
