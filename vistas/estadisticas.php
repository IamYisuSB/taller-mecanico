<?php
// Estadísticas de ingresos y facturación de un año
/** @var string $titulo */
/** @var int    $anio */
/** @var int[]  $anios */
/** @var array  $meses      12 filas: mes, reparaciones, mano_obra, piezas, ingresos, horas, facturas, base, iva, total */
/** @var array  $resumen */
/** @var array  $mecanicos */
/** @var array  $piezas */
/** @var array  $clientes   nombre, apellidos, telefono, visitas, vehiculos, importe, ultima */
/** @var array  $pendiente  reparaciones, importe */
/** @var array  $enTaller   reparaciones, importe */
require __DIR__ . '/cabecera.php';

$horas = fn (float $h) => number_format($h, ($h == floor($h)) ? 0 : 1, ',', '.') . ' h';

// Gráfica de columnas por mes. $series: [[campo, nombre, clase CSS], …] (se apilan de abajo arriba)
$graficoColumnas = function (array $meses, array $series, string $etiquetaAccesible) use ($anio) {
    $totales = array_map(fn ($m) => array_sum(array_map(fn ($s) => $m[$s[0]], $series)), $meses);
    $escala = escala(max($totales));
    $mayor = max($totales) > 0 ? array_search(max($totales), $totales, true) : null;
    ?>
    <div class="columnas" role="img" aria-label="<?= e($etiquetaAccesible) ?>">
      <div class="trazado">
        <?php foreach ($escala['marcas'] as $marca): ?>
          <div class="rejilla" style="bottom: <?= porcentaje($marca, $escala['tope']) ?>%">
            <span><?= e(euros_corto($marca)) ?></span>
          </div>
        <?php endforeach; ?>
        <div class="barras">
          <?php foreach ($meses as $i => $m): ?>
            <?php
              $filas = array_map(fn ($s) => [$s[1], euros($m[$s[0]]), $s[2]], array_reverse($series));
              if (count($series) > 1) {
                  $filas[] = ['Total', euros($totales[$i]), ''];
              }
            ?>
            <div class="col" tabindex="0" <?= tooltip($m['mes_largo'] . ' ' . $anio, $filas) ?>>
              <?php if ($totales[$i] > 0): ?>
                <div class="pila" style="height: <?= porcentaje($totales[$i], $escala['tope']) ?>%">
                  <?php foreach (array_reverse($series) as [$campo, , $clase]): ?>
                    <?php if ($m[$campo] > 0): ?>
                      <span class="seg <?= $clase ?>" style="flex-grow: <?= round($m[$campo], 2) ?>"></span>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
                <?php if ($i === $mayor): ?>
                  <span class="valor-cima" style="bottom: <?= porcentaje($totales[$i], $escala['tope']) ?>%"><?= e(euros_corto($totales[$i])) ?></span>
                <?php endif; ?>
              <?php endif; ?>
              <span class="mes"><?= e($m['mes']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php
};

// Barras horizontales. $filas: [[nombre, detalle, [[valor, nombre serie, clase], …], filas extra del tooltip], …]
// $corto da el texto del final de la barra y $largo el del tooltip (por defecto, euros)
$graficoBarras = function (array $filas, ?callable $corto = null, ?callable $largo = null) {
    $corto ??= 'euros_corto';
    $largo ??= 'euros';
    $maximo = max(array_map(fn ($f) => array_sum(array_column($f[2], 0)), $filas) ?: [0]);
    ?>
    <div class="barras-h">
      <?php foreach ($filas as $fila): ?>
        <?php
          [$nombre, $detalle, $partes] = $fila;
          $total = array_sum(array_column($partes, 0));
          $tip = array_map(fn ($p) => [$p[1], $largo($p[0]), $p[2]], $partes);
          if (count($partes) > 1) {
              $tip[] = ['Total', $largo($total), ''];
          }
          $tip = array_merge($tip, $fila[3] ?? []);
        ?>
        <div class="fila-h" tabindex="0" <?= tooltip($nombre, $tip) ?>>
          <span class="nombre-h"><?= e($nombre) ?><?php if ($detalle): ?> <small class="secundario"><?= e($detalle) ?></small><?php endif; ?></span>
          <span class="pista">
            <span class="barra-h" style="width: calc((100% - 6.5rem) * <?= round(porcentaje($total, $maximo) / 100, 4) ?>)">
              <?php foreach ($partes as [$valor, , $clase]): ?>
                <?php if ($valor > 0): ?><span class="seg <?= $clase ?>" style="flex-grow: <?= round($valor, 2) ?>"></span><?php endif; ?>
              <?php endforeach; ?>
            </span>
            <span class="valor-h"><?= e($corto($total)) ?></span>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
};

$leyenda = '<ul class="leyenda"><li><span class="muestra serie-1"></span>Mano de obra</li><li><span class="muestra serie-2"></span>Piezas</li></ul>';
$hayIngresos = $resumen['ingresos'] > 0;
?>

<form method="get" action="index.php" class="barra filtros-estadisticas">
  <input type="hidden" name="accion" value="estadisticas">
  <label class="en-linea">Año
    <select name="anio" data-auto>
      <?php foreach ($anios as $a): ?>
        <option value="<?= $a ?>" <?= $a === $anio ? 'selected' : '' ?>><?= $a ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <noscript><button class="boton secundario">Ver</button></noscript>
  <div class="barra-acciones">
    <a class="boton secundario" href="index.php?accion=estadisticas-csv&amp;anio=<?= $anio ?>">Exportar a Excel (CSV)</a>
    <a class="boton" href="index.php?accion=estadisticas-pdf&amp;anio=<?= $anio ?>">Exportar resumen PDF</a>
  </div>
</form>

<!-- ---------- Cifras ---------- -->
<section class="cifras">
  <div class="cifra principal tarjeta">
    <span class="cifra-etiqueta">Ingresos de <?= $anio ?> (sin IVA)</span>
    <span class="cifra-valor"><?= euros($resumen['ingresos']) ?></span>
    <span class="cifra-detalle"><?= $resumen['reparaciones'] ?> reparaciones cerradas · ticket medio <?= euros($resumen['ticket_medio']) ?></span>
  </div>
  <div class="cifra tarjeta">
    <span class="cifra-etiqueta">Facturado (con IVA)</span>
    <span class="cifra-valor"><?= euros($resumen['facturado']) ?></span>
    <span class="cifra-detalle"><?= $resumen['facturas'] ?> factura<?= $resumen['facturas'] === 1 ? '' : 's' ?> · base <?= euros($resumen['base']) ?></span>
  </div>
  <div class="cifra tarjeta">
    <span class="cifra-etiqueta">IVA repercutido</span>
    <span class="cifra-valor"><?= euros($resumen['iva']) ?></span>
    <span class="cifra-detalle">De las facturas de <?= $anio ?></span>
  </div>
  <div class="cifra tarjeta">
    <span class="cifra-etiqueta">Mano de obra</span>
    <span class="cifra-valor"><?= euros($resumen['mano_obra']) ?></span>
    <span class="cifra-detalle"><?= $horas($resumen['horas']) ?> apuntadas por horas</span>
  </div>
  <div class="cifra tarjeta">
    <span class="cifra-etiqueta">Piezas</span>
    <span class="cifra-valor"><?= euros($resumen['piezas']) ?></span>
    <span class="cifra-detalle"><?= e(number_format(porcentaje($resumen['piezas'], $resumen['ingresos']), 0, ',', '.')) ?> % de los ingresos</span>
  </div>
  <a class="cifra tarjeta enlace-cifra" href="index.php?accion=listado&amp;estado=cerradas">
    <span class="cifra-etiqueta">Pendiente de facturar</span>
    <span class="cifra-valor"><?= euros($pendiente['importe']) ?></span>
    <span class="cifra-detalle"><?= (int) $pendiente['reparaciones'] ?> reparaciones cerradas sin factura (todos los años)</span>
  </a>
  <a class="cifra tarjeta enlace-cifra" href="index.php?accion=listado&amp;estado=abiertas">
    <span class="cifra-etiqueta">En el taller ahora</span>
    <span class="cifra-valor"><?= euros($enTaller['importe']) ?></span>
    <span class="cifra-detalle"><?= (int) $enTaller['reparaciones'] ?> reparaciones abiertas, importe acumulado</span>
  </a>
</section>

<!-- ---------- Ingresos por mes ---------- -->
<section class="tarjeta grafico">
  <div class="grafico-cabecera">
    <div>
      <h2>Ingresos por mes</h2>
      <p class="secundario">Reparaciones cerradas en <?= $anio ?>, por fecha de salida. Sin IVA.</p>
    </div>
    <?= $leyenda ?>
  </div>
  <?php if (!$hayIngresos): ?>
    <p class="vacio">No hay reparaciones cerradas en <?= $anio ?>.</p>
  <?php else: ?>
    <?php $graficoColumnas($meses, [['mano_obra', 'Mano de obra', 'serie-1'], ['piezas', 'Piezas', 'serie-2']], "Ingresos por mes de $anio") ?>
  <?php endif; ?>
  <!-- ---------- Reparto ---------- -->
  <?php if ($hayIngresos): ?>
  <div class="reparto">
    <h3>De dónde vienen los ingresos</h3>
      <?php
        $partes = [['Mano de obra', $resumen['mano_obra'], 'serie-1'], ['Piezas', $resumen['piezas'], 'serie-2']];
      ?>
      <div class="segmentado" role="img"
           aria-label="Mano de obra <?= e(number_format(porcentaje($resumen['mano_obra'], $resumen['ingresos']), 0)) ?> %, piezas <?= e(number_format(porcentaje($resumen['piezas'], $resumen['ingresos']), 0)) ?> %">
        <?php foreach ($partes as [$nombre, $valor, $clase]): ?>
          <?php $pct = porcentaje($valor, $resumen['ingresos']); ?>
          <?php if ($valor > 0): ?>
            <span class="bloque <?= $clase ?>" style="flex-grow: <?= round($valor, 2) ?>" tabindex="0"
                  <?= tooltip($nombre, [[$nombre, euros($valor) . ' · ' . number_format($pct, 1, ',', '.') . ' %', $clase]]) ?>>
              <?php if ($pct >= 12): ?><?= e(number_format($pct, 0, ',', '.')) ?> %<?php endif; ?>
            </span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <ul class="leyenda leyenda-valores">
        <?php foreach ($partes as [$nombre, $valor, $clase]): ?>
          <li><span class="muestra <?= $clase ?>"></span><?= e($nombre) ?> <strong><?= euros($valor) ?></strong></li>
        <?php endforeach; ?>
      </ul>
  </div>
  <?php endif; ?>

  <details>
    <summary>Ver los datos en tabla</summary>
    <div class="tabla-envoltorio">
      <table>
        <thead><tr><th>Mes</th><th class="num">Reparaciones</th><th class="num">Horas</th><th class="num">Mano de obra</th><th class="num">Piezas</th><th class="num">Ingresos</th></tr></thead>
        <tbody>
          <?php foreach ($meses as $m): ?>
            <tr><td><?= e($m['mes_largo']) ?></td><td class="num"><?= $m['reparaciones'] ?></td><td class="num"><?= $horas($m['horas']) ?></td>
                <td class="num"><?= euros($m['mano_obra']) ?></td><td class="num"><?= euros($m['piezas']) ?></td><td class="num"><strong><?= euros($m['ingresos']) ?></strong></td></tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th>Total</th><th class="num"><?= $resumen['reparaciones'] ?></th><th class="num"><?= $horas($resumen['horas']) ?></th>
          <th class="num"><?= euros($resumen['mano_obra']) ?></th><th class="num"><?= euros($resumen['piezas']) ?></th><th class="num"><?= euros($resumen['ingresos']) ?></th></tr></tfoot>
      </table>
    </div>
  </details>
</section>

  <!-- ---------- Facturación por mes ---------- -->
  <section class="tarjeta grafico">
    <h2>Facturación por mes</h2>
    <p class="secundario">Total de las facturas emitidas en <?= $anio ?>, con IVA.</p>
    <?php if ($resumen['facturado'] <= 0): ?>
      <p class="vacio">No hay facturas en <?= $anio ?>. Se generan desde la ficha de una reparación cerrada.</p>
    <?php else: ?>
      <?php $graficoColumnas($meses, [['total', 'Facturado', 'serie-1']], "Facturación por mes de $anio") ?>
    <?php endif; ?>
    <details>
      <summary>Ver los datos en tabla</summary>
      <div class="tabla-envoltorio">
        <table>
          <thead><tr><th>Mes</th><th class="num">Facturas</th><th class="num">Base</th><th class="num">IVA</th><th class="num">Total</th></tr></thead>
          <tbody>
            <?php foreach ($meses as $m): ?>
              <tr><td><?= e($m['mes_largo']) ?></td><td class="num"><?= $m['facturas'] ?></td><td class="num"><?= euros($m['base']) ?></td>
                  <td class="num"><?= euros($m['iva']) ?></td><td class="num"><strong><?= euros($m['total']) ?></strong></td></tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot><tr><th>Total</th><th class="num"><?= $resumen['facturas'] ?></th><th class="num"><?= euros($resumen['base']) ?></th>
            <th class="num"><?= euros($resumen['iva']) ?></th><th class="num"><?= euros($resumen['facturado']) ?></th></tr></tfoot>
        </table>
      </div>
    </details>
  </section>

<div class="rejilla-graficos">
  <!-- ---------- Por mecánico ---------- -->
  <section class="tarjeta grafico">
    <div class="grafico-cabecera">
      <div>
        <h2>Ingresos por mecánico</h2>
        <p class="secundario">Reparaciones cerradas en <?= $anio ?>.</p>
      </div>
      <?= $leyenda ?>
    </div>
    <?php if (!$mecanicos): ?>
      <p class="vacio">Sin datos.</p>
    <?php else: ?>
      <?php $graficoBarras(array_map(fn ($m) => [
          $m['nombre'],
          $m['reparaciones'] . ' rep.',
          [[(float) $m['mano_obra'], 'Mano de obra', 'serie-1'], [(float) $m['piezas'], 'Piezas', 'serie-2']],
      ], $mecanicos)) ?>
    <?php endif; ?>
  </section>

  <!-- ---------- Piezas ---------- -->
  <section class="tarjeta grafico">
    <h2>Piezas que más ingresan</h2>
    <p class="secundario">Las <?= count($piezas) ?> piezas con más importe en <?= $anio ?>.</p>
    <?php if (!$piezas): ?>
      <p class="vacio">Sin datos.</p>
    <?php else: ?>
      <?php $graficoBarras(array_map(fn ($p) => [
          $p['nombre'],
          (int) $p['unidades'] . ' ud',
          [[(float) $p['importe'], 'Importe', 'serie-1']],
      ], $piezas)) ?>
    <?php endif; ?>
  </section>
</div>

<!-- ---------- Clientes que más vienen ---------- -->
<section class="tarjeta grafico">
  <h2>Clientes que más vienen</h2>
  <p class="secundario">Visitas al taller en <?= $anio ?> (reparaciones que han entrado, abiertas o cerradas).</p>
  <?php if (!$clientes): ?>
    <p class="vacio">No ha entrado ninguna reparación en <?= $anio ?>.</p>
  <?php else: ?>
    <?php $visitas = fn ($n) => (int) $n . ((int) $n === 1 ? ' visita' : ' visitas'); ?>
    <?php $graficoBarras(array_map(fn ($c) => [
        $c['nombre'] . ' ' . $c['apellidos'],
        $c['vehiculos'] > 1 ? $c['vehiculos'] . ' vehículos' : '',
        [[(int) $c['visitas'], 'Visitas', 'serie-1']],
        [['Gastado (sin IVA)', euros($c['importe']), ''], ['Última visita', fecha($c['ultima']), '']],
    ], $clientes), $visitas, $visitas) ?>
    <details>
      <summary>Ver los datos en tabla</summary>
      <div class="tabla-envoltorio">
        <table>
          <thead><tr><th>Cliente</th><th>Teléfono</th><th class="num">Visitas</th><th class="num">Vehículos</th><th class="num">Gastado (sin IVA)</th><th>Última visita</th></tr></thead>
          <tbody>
            <?php foreach ($clientes as $c): ?>
              <tr><td><?= e($c['nombre'] . ' ' . $c['apellidos']) ?></td><td><?= e($c['telefono']) ?></td>
                  <td class="num"><strong><?= (int) $c['visitas'] ?></strong></td><td class="num"><?= (int) $c['vehiculos'] ?></td>
                  <td class="num"><?= euros($c['importe']) ?></td><td class="fecha"><?= fecha($c['ultima']) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>
  <?php endif; ?>
</section>

<p class="ayuda">
  Los ingresos se calculan con los precios actuales del catálogo. La facturación sale de las facturas
  emitidas, que guardan sus importes fijos. Para calcular el beneficio real haría falta el precio de coste de las piezas.
</p>

<?php require __DIR__ . '/pie.php'; ?>
