<?php
// Variables que llegan desde el controlador con extract():
/** @var string $titulo */
/** @var array $reparacion */
/** @var array $piezas */
/** @var float $total */
/** @var string $aviso */
/** @var string $error */
/** @var array $catalogo */
/** @var array|null $factura */
require __DIR__ . '/cabecera.php';

$abierta = $reparacion["salida"] === null;
?>
<?php if ($aviso): ?>
  <p class="aviso"><?= e($aviso) ?></p>
<?php endif; ?>
<?php /* TODO 13: el error arriba con la clase «error» */ ?>
<?php if ($error): ?>
  <p class="error"><?= e($error) ?></p>
<?php endif; ?>

<div class="barra">
  <?php if ($abierta): ?>
    <strong class="insignia abierta">En el taller</strong>
  <?php else: ?>
    <span class="insignia cerrada">Cerrada</span>
  <?php endif; ?>
  <div class="barra-acciones">
    <?php if ($factura): ?>
      <span class="insignia facturada">Factura <?= e($factura['numero']) ?></span>
      <a class="boton secundario" href="index.php?accion=factura-pdf&amp;id=<?= $factura['id'] ?>" target="_blank" rel="noopener">Ver factura</a>
      <a class="boton" href="index.php?accion=factura-pdf&amp;id=<?= $factura['id'] ?>&amp;descargar=1">Descargar PDF</a>
    <?php else: ?>
      <a class="boton secundario" href="index.php?accion=editar&amp;id=<?= $reparacion['id'] ?>">Editar datos</a>
      <?php if (!$abierta): ?>
        <form method="post" action="index.php?accion=facturar"
              data-confirmar="¿Generar la factura? Se le dará el siguiente número y la reparación ya no se podrá modificar.">
          <input type="hidden" name="reparacion_id" value="<?= $reparacion['id'] ?>">
          <button class="boton">Generar factura</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
    <?php if ($abierta): ?>
      <form method="post" action="index.php?accion=cerrar"
            data-confirmar="¿Cerrar la reparación? Se pondrá la fecha de salida de ahora y ya no se podrán cambiar las piezas.">
        <input type="hidden" name="reparacion_id" value="<?= $reparacion['id'] ?>">
        <button class="boton exito">Cerrar reparación</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<dl class="tarjeta datos">
  <dt>Vehículo</dt>
  <dd class="vehiculo">
    <span><?= e($reparacion['marca'] . ' ' . $reparacion['modelo']) ?></span>
    <span class="matricula"><?= e($reparacion['matricula']) ?></span>
  </dd>
  <!-- TODO 7: con el mismo formato, añade: Cliente («Nombre Apellidos · teléfono»),
       Mecánico, Avería, Entrada y Salida («En el taller» si todavía no ha salido) -->
  <dt>Cliente</dt>
  <dd><?= e($reparacion["cliente_nombre"] . ' ' . $reparacion["cliente_apellidos"] . ' · ' . $reparacion["telefono"]) ?></dd>
  <dt>Mecánico</dt>
  <dd><?= e($reparacion["mecanico"]) ?></dd>
  <dt>Avería</dt>
  <dd><?= e($reparacion["descripcion"]) ?></dd>
  <dt>Entrada</dt>
  <dd><?= fecha($reparacion["entrada"]) ?></dd>
  <dt>Salida</dt>
  <dd>
    <?php if ($reparacion["salida"] === null): ?>
      <strong>En el taller</strong>
    <?php else: ?>
      <?= fecha($reparacion["salida"]) ?>
    <?php endif; ?>
  </dd>
</dl>

<h2>Piezas</h2>
<!-- TODO 8: si no hay piezas, «No se ha usado ninguna pieza.»
     Si hay, una tabla: Pieza, Unidades, Precio e Importe.
     Usa euros() para el dinero y la clase «num» en las columnas de números -->
<?php if (!$piezas): ?>
    <p class="vacio">No se ha usado ninguna pieza.</p>
<?php else: ?>
  <div class="tabla-envoltorio tarjeta">
    <table>
        <thead>
            <tr>
                <th>Pieza</th>
                <th class="num">Unidades</th>
                <th class="num">Precio</th>
                <th class="num">Importe</th>
                <?php if ($abierta): ?><th class="acciones-col"><span class="oculto">Acciones</span></th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach($piezas as $pieza): ?>
                <tr>
                    <td><?= e($pieza["nombre"]) ?> <small class="secundario"><?= e($pieza["referencia"]) ?></small></td>
                    <td class="num"><?= e($pieza["cantidad"]) ?></td>
                    <td class="num"><?= euros($pieza["precio"]) ?></td>
                    <td class="num"><?= euros($pieza["importe"]) ?></td>
                    <?php if ($abierta): ?>
                    <td class="acciones">
                        <form method="post" action="index.php?accion=quitar-pieza"
                              data-confirmar="¿Quitar «<?= e($pieza['nombre']) ?>» de la reparación?">
                            <input type="hidden" name="reparacion_id" value="<?= $reparacion['id'] ?>">
                            <input type="hidden" name="pieza_id" value="<?= $pieza['pieza_id'] ?>">
                            <button class="boton pequeno peligro">Quitar</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
  </div>
<?php endif; ?>

<?php if ($abierta): ?>
<form method="post" action="index.php?accion=pieza" class="linea tarjeta separado">
  <?php /* TODO 10: campo oculto con el id de la reparación */ ?>
  <input type="hidden" name="reparacion_id" value="<?= $reparacion['id'] ?>">
  <label>Buscar pieza
    <input type="search" data-filtra="pieza_id" placeholder="Escribe para filtrar…">
  </label>
  <label>Pieza
    <select name="pieza_id" id="pieza_id" required>
      <option value="">Elige una pieza…</option>
      <?php /* TODO 11: una opción por pieza: «Nombre (precio)» */ ?>
      <?php foreach ($catalogo as $p): ?>
        <option value="<?= $p['id'] ?>"><?= e($p['nombre']) ?> (<?= euros($p['precio']) ?>)</option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Unidades
    <input type="number" name="cantidad" value="1" min="1" required class="corto">
  </label>
  <button class="boton">Añadir pieza</button>
</form>
<?php endif; ?>

<h2>Mano de obra</h2>
<?php if (!$manoObra && $reparacion["mano_obra"] == 0): ?>
    <p class="vacio">Todavía no se ha apuntado mano de obra.</p>
<?php else: ?>
  <div class="tabla-envoltorio tarjeta">
    <table>
        <thead>
            <tr>
                <th>Trabajo</th>
                <th class="num">Horas</th>
                <th class="num">Precio/hora</th>
                <th class="num">Importe</th>
                <?php if ($abierta): ?><th class="acciones-col"><span class="oculto">Acciones</span></th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if ($reparacion["mano_obra"] > 0): ?>
                <tr>
                    <td>Importe fijo<?php if (!$factura): ?> <small class="secundario">Se cambia en «Editar datos»</small><?php endif; ?></td>
                    <td class="num">—</td>
                    <td class="num">—</td>
                    <td class="num"><?= euros($reparacion["mano_obra"]) ?></td>
                    <?php if ($abierta): ?><td></td><?php endif; ?>
                </tr>
            <?php endif; ?>
            <?php foreach ($manoObra as $linea): ?>
                <tr>
                    <td><?= e($linea["descripcion"]) ?> <small class="secundario"><?= fecha($linea["fecha"]) ?></small></td>
                    <td class="num"><?= e(number_format((float) $linea["horas"], 2, ',', '.')) ?> h</td>
                    <td class="num"><?= euros($linea["precio_hora"]) ?></td>
                    <td class="num"><?= euros($linea["importe"]) ?></td>
                    <?php if ($abierta): ?>
                    <td class="acciones">
                        <form method="post" action="index.php?accion=quitar-mano-obra"
                              data-confirmar="¿Quitar «<?= e($linea['descripcion']) ?>»?">
                            <input type="hidden" name="reparacion_id" value="<?= $reparacion['id'] ?>">
                            <input type="hidden" name="linea_id" value="<?= $linea['id'] ?>">
                            <button class="boton pequeno peligro">Quitar</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
  </div>
<?php endif; ?>

<?php if ($abierta): ?>
<form method="post" action="index.php?accion=mano-obra" class="linea tarjeta separado">
  <input type="hidden" name="reparacion_id" value="<?= $reparacion['id'] ?>">
  <label>Trabajo realizado
    <input type="text" name="descripcion" maxlength="150" required placeholder="Ej.: Desmontaje de la caja de cambios">
  </label>
  <label>Horas
    <input type="number" name="horas" min="0.01" max="999.99" step="0.01" value="1" required class="corto">
  </label>
  <label>Precio/hora (€)
    <input type="number" name="precio_hora" min="0" step="0.01" required class="corto" value="<?= e((string) $precioHora) ?>">
  </label>
  <button class="boton">Añadir mano de obra</button>
</form>
<?php endif; ?>

<div class="totales tarjeta">
     <p>Piezas: <?= euros($totalPiezas) ?></p>
     <p>Mano de obra: <?= euros($totalManoObra) ?></p>
     <p class="total">Total: <?= euros($total) ?></p>
</div>

<p><a href="index.php">← Volver a las reparaciones</a></p>

<?php require __DIR__ . '/pie.php'; ?>
