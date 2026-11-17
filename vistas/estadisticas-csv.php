<?php
// Exporta las estadísticas del año en CSV, listo para abrir con Excel.
// Formato español: separador «;», coma decimal y UTF-8 con BOM (para que
// Excel muestre bien las tildes y el símbolo €)
/** @var int   $anio */
/** @var array $meses */
/** @var array $resumen */
/** @var array $mecanicos */
/** @var array $piezas */
/** @var array $clientes */
/** @var array $facturas */
/** @var array $pendiente */
/** @var array $enTaller */

$numero = fn ($n) => number_format((float) $n, 2, ',', '');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="estadisticas-' . $anio . '.csv"');

$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF");
$fila = fn (array $campos) => fputcsv($salida, $campos, ';', '"', '');

$fila(["Estadísticas del taller · $anio"]);
$fila(['Generado el ' . date('d/m/Y H:i')]);
$fila([]);

$fila(['RESUMEN']);
$fila(['Concepto', 'Valor']);
$fila(['Ingresos sin IVA (reparaciones cerradas)', $numero($resumen['ingresos'])]);
$fila(['  Mano de obra', $numero($resumen['mano_obra'])]);
$fila(['  Piezas', $numero($resumen['piezas'])]);
$fila(['Horas de mano de obra', $numero($resumen['horas'])]);
$fila(['Reparaciones cerradas', $resumen['reparaciones']]);
$fila(['Ticket medio', $numero($resumen['ticket_medio'])]);
$fila(['Facturas emitidas', $resumen['facturas']]);
$fila(['Base imponible facturada', $numero($resumen['base'])]);
$fila(['IVA repercutido', $numero($resumen['iva'])]);
$fila(['Total facturado con IVA', $numero($resumen['facturado'])]);
$fila(['Pendiente de facturar (todos los años)', $numero($pendiente['importe']), $pendiente['reparaciones'] . ' reparaciones']);
$fila(['En el taller ahora', $numero($enTaller['importe']), $enTaller['reparaciones'] . ' reparaciones']);
$fila([]);

$fila(['POR MES']);
$fila(['Mes', 'Reparaciones', 'Horas', 'Mano de obra', 'Piezas', 'Ingresos sin IVA', 'Facturas', 'Base facturada', 'IVA', 'Total facturado']);
foreach ($meses as $m) {
    $fila([$m['mes_largo'], $m['reparaciones'], $numero($m['horas']), $numero($m['mano_obra']), $numero($m['piezas']),
           $numero($m['ingresos']), $m['facturas'], $numero($m['base']), $numero($m['iva']), $numero($m['total'])]);
}
$fila(['TOTAL', $resumen['reparaciones'], $numero($resumen['horas']), $numero($resumen['mano_obra']), $numero($resumen['piezas']),
       $numero($resumen['ingresos']), $resumen['facturas'], $numero($resumen['base']), $numero($resumen['iva']), $numero($resumen['facturado'])]);
$fila([]);

$fila(['POR MECÁNICO']);
$fila(['Mecánico', 'Reparaciones', 'Horas', 'Mano de obra', 'Piezas', 'Ingresos sin IVA']);
foreach ($mecanicos as $m) {
    $fila([$m['nombre'], $m['reparaciones'], $numero($m['horas']), $numero($m['mano_obra']), $numero($m['piezas']), $numero($m['ingresos'])]);
}
$fila([]);

$fila(['PIEZAS QUE MÁS INGRESAN']);
$fila(['Pieza', 'Referencia', 'Unidades', 'Importe']);
foreach ($piezas as $p) {
    $fila([$p['nombre'], $p['referencia'], (int) $p['unidades'], $numero($p['importe'])]);
}
$fila([]);

$fila(['CLIENTES QUE MÁS VIENEN (reparaciones que han entrado en el año)']);
$fila(['Cliente', 'Teléfono', 'Visitas', 'Vehículos', 'Gastado sin IVA', 'Última visita']);
foreach ($clientes as $c) {
    $fila([$c['nombre'] . ' ' . $c['apellidos'], $c['telefono'], (int) $c['visitas'], (int) $c['vehiculos'], $numero($c['importe']), fecha($c['ultima'])]);
}
$fila([]);

$fila(['FACTURAS DEL AÑO']);
$fila(['Número', 'Fecha', 'Reparación', 'Cliente', 'Vehículo', 'Matrícula', 'Base', 'IVA', 'Total']);
foreach ($facturas as $f) {
    $fila([$f['numero'], fecha($f['fecha']), $f['id_reparacion'], $f['cliente'], $f['vehiculo'], $f['matricula'],
           $numero($f['base']), $numero($f['iva']), $numero($f['total'])]);
}

fclose($salida);
