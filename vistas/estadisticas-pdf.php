<?php
// Resumen de ventas del año en PDF: cifras, gráfica de ingresos por mes y tablas
/** @var int   $anio */
/** @var array $meses */
/** @var array $resumen */
/** @var array $mecanicos */
/** @var array $piezas */
/** @var array $clientes */
/** @var array $pendiente */
/** @var array $enTaller */
/** @var array $empresa */
require_once __DIR__ . '/../lib/Pdf.php';

$pdf = new Pdf("Resumen de ventas $anio");

$oscuro = [24, 32, 43];
$gris = [91, 102, 117];
$grisClaro = [201, 210, 222];
$fondo = [243, 245, 248];
$borde = [221, 226, 232];
$rejilla = [225, 224, 217];
$serie1 = [42, 120, 214];  // mano de obra (azul)
$serie2 = [235, 104, 52];  // piezas (naranja)
$blanco = [255, 255, 255];
$negro = [28, 36, 48];

$izquierda = 40;
$derecha = Pdf::ANCHO - 40;
$ancho = $derecha - $izquierda;
$limiteInferior = 770;

$pie = function () use ($pdf, $empresa, $anio, $izquierda, $derecha, $borde, $gris) {
    $pdf->linea($izquierda, 805, $derecha, 805, 0.5, $borde);
    $pdf->texto($izquierda, 820, $empresa['nombre'] . " · Resumen de ventas $anio · " . date('d/m/Y'), 8, false, 'L', $gris);
    $pdf->texto($derecha, 820, 'Página ' . $pdf->numeroPaginas() . ' de {paginas}', 8, false, 'R', $gris);
};
$nuevaPagina = function () use ($pdf, $pie, $empresa, $anio, $izquierda, $derecha, $oscuro, $gris): float {
    $pie();
    $pdf->nuevaPagina();
    $pdf->texto($izquierda, 50, $empresa['nombre'], 11, true, 'L', $oscuro);
    $pdf->texto($derecha, 50, "Resumen de ventas $anio (continuación)", 9, false, 'R', $gris);
    return 80;
};

// Tabla sencilla. $columnas: [[título, ancho, alineación], …]; devuelve la y final
$tabla = function (float $y, string $titulo, array $columnas, array $filas, ?array $total = null)
    use ($pdf, $nuevaPagina, $izquierda, $ancho, $limiteInferior, $fondo, $borde, $gris, $negro): float {
    if ($y + 60 > $limiteInferior) {
        $y = $nuevaPagina();
    }
    $pdf->texto($izquierda, $y, $titulo, 12, true, 'L', $negro);
    $y += 10;
    $cabecera = function (float $y) use ($pdf, $columnas, $izquierda, $ancho, $fondo, $gris): float {
        $pdf->rectangulo($izquierda, $y, $ancho, 20, $fondo);
        $x = $izquierda;
        foreach ($columnas as [$texto, $anchoCol, $alinear]) {
            $pdf->texto($alinear === 'R' ? $x + $anchoCol - 6 : $x + 6, $y + 13.5, mb_strtoupper($texto), 7.5, true, $alinear, $gris);
            $x += $anchoCol;
        }
        return $y + 20;
    };
    $y = $cabecera($y);
    foreach (array_merge($filas, $total ? [$total] : []) as $i => $fila) {
        if ($y + 18 > $limiteInferior) {
            $y = $cabecera($nuevaPagina());
        }
        $esTotal = $total && $i === count($filas);
        $x = $izquierda;
        foreach ($columnas as $c => [, $anchoCol, $alinear]) {
            $pdf->texto($alinear === 'R' ? $x + $anchoCol - 6 : $x + 6, $y + 12.5, (string) $fila[$c], 9, $esTotal, $alinear, $negro);
            $x += $anchoCol;
        }
        $y += 18;
        $pdf->linea($izquierda, $y, $izquierda + $ancho, $y, $esTotal ? 1 : 0.5, $esTotal ? $gris : $borde);
    }
    return $y + 22;
};

$horas = fn (float $h) => number_format($h, 1, ',', '.') . ' h';

$pdf->nuevaPagina();

// ---------- Cabecera ----------
$pdf->rectangulo(0, 0, Pdf::ANCHO, 95, $oscuro);
$pdf->rectangulo(0, 95, Pdf::ANCHO, 4, $serie1);
$pdf->texto($izquierda, 48, $empresa['nombre'], 18, true, 'L', $blanco);
$pdf->texto($izquierda, 68, 'Generado el ' . date('d/m/Y \a \l\a\s H:i'), 9, false, 'L', $grisClaro);
$pdf->texto($derecha, 48, 'RESUMEN DE VENTAS', 16, true, 'R', $blanco);
$pdf->texto($derecha, 70, "Año $anio", 12, true, 'R', $grisClaro);

// ---------- Cifras (2 filas de 3) ----------
$cifras = [
    ['Ingresos sin IVA', euros($resumen['ingresos']), $resumen['reparaciones'] . ' reparaciones cerradas'],
    ['Facturado con IVA', euros($resumen['facturado']), $resumen['facturas'] . ($resumen['facturas'] === 1 ? ' factura' : ' facturas')],
    ['IVA repercutido', euros($resumen['iva']), 'Base ' . euros($resumen['base'])],
    ['Mano de obra', euros($resumen['mano_obra']), $horas($resumen['horas']) . ' por horas'],
    ['Piezas', euros($resumen['piezas']), number_format(porcentaje($resumen['piezas'], $resumen['ingresos']), 0, ',', '.') . ' % de los ingresos'],
    ['Ticket medio', euros($resumen['ticket_medio']), 'Por reparación cerrada'],
];
$anchoCifra = ($ancho - 2 * 12) / 3;
foreach ($cifras as $i => [$etiqueta, $valor, $detalle]) {
    $x = $izquierda + ($i % 3) * ($anchoCifra + 12);
    $y = 120 + intdiv($i, 3) * 72;
    $pdf->rectangulo($x, $y, $anchoCifra, 60, $fondo);
    $pdf->texto($x + 10, $y + 17, mb_strtoupper($etiqueta), 7.5, true, 'L', $gris);
    $pdf->texto($x + 10, $y + 38, $valor, 15, true, 'L', $negro);
    $pdf->texto($x + 10, $y + 52, $detalle, 8, false, 'L', $gris);
}
$y = 120 + 2 * 72 + 4;
$pdf->texto($izquierda, $y + 8, 'Pendiente de facturar: ' . euros($pendiente['importe']) . ' (' . (int) $pendiente['reparaciones'] . ' reparaciones)   ·   En el taller ahora: '
    . euros($enTaller['importe']) . ' (' . (int) $enTaller['reparaciones'] . ' reparaciones)', 9, false, 'L', $gris);

// ---------- Gráfica: ingresos por mes (columnas apiladas) ----------
$y += 36;
$pdf->texto($izquierda, $y, 'Ingresos por mes', 12, true, 'L', $negro);
// Leyenda a la derecha
$pdf->rectangulo($derecha - 150, $y - 8, 9, 9, $serie1);
$pdf->texto($derecha - 137, $y, 'Mano de obra', 9, false, 'L', $gris);
$pdf->rectangulo($derecha - 62, $y - 8, 9, 9, $serie2);
$pdf->texto($derecha - 49, $y, 'Piezas', 9, false, 'L', $gris);

$altoGrafico = 170;
$arriba = $y + 22;
$abajo = $arriba + $altoGrafico;
$xEje = $izquierda + 50;
$escala = escala(max(array_column($meses, 'ingresos')));
foreach ($escala['marcas'] as $marca) {
    $yMarca = $abajo - $altoGrafico * $marca / $escala['tope'];
    $pdf->linea($xEje, $yMarca, $derecha, $yMarca, $marca == 0 ? 0.8 : 0.4, $marca == 0 ? [195, 194, 183] : $rejilla);
    $pdf->texto($xEje - 6, $yMarca + 3, euros_corto($marca), 7.5, false, 'R', $gris);
}
$hueco = ($derecha - $xEje) / 12;
$anchoBarra = min(22, $hueco * 0.6);
$mayor = max(array_column($meses, 'ingresos'));
foreach ($meses as $i => $m) {
    $xBarra = $xEje + $i * $hueco + ($hueco - $anchoBarra) / 2;
    $altoMano = $altoGrafico * $m['mano_obra'] / $escala['tope'];
    $altoPiezas = $altoGrafico * $m['piezas'] / $escala['tope'];
    if ($altoMano > 0) {
        $pdf->rectangulo($xBarra, $abajo - $altoMano, $anchoBarra, $altoMano, $serie1);
    }
    if ($altoPiezas > 0) {
        // 1,5 pt de separación blanca entre las dos partes de la columna
        $separacion = $altoMano > 0 ? 1.5 : 0;
        $pdf->rectangulo($xBarra, $abajo - $altoMano - $altoPiezas, $anchoBarra, max(0, $altoPiezas - $separacion), $serie2);
    }
    if ($mayor > 0 && $m['ingresos'] == $mayor) {
        $pdf->texto($xBarra + $anchoBarra / 2, $abajo - $altoMano - $altoPiezas - 5, euros_corto($m['ingresos']), 8, true, 'C', $negro);
    }
    $pdf->texto($xEje + $i * $hueco + $hueco / 2, $abajo + 13, $m['mes'], 8, false, 'C', $gris);
}
$y = $abajo + 42;

// ---------- Tablas ----------
$y = $tabla($y, 'Detalle por mes',
    [['Mes', 95, 'L'], ['Rep.', 45, 'R'], ['Mano de obra', 85, 'R'], ['Piezas', 80, 'R'], ['Ingresos', 85, 'R'], ['Facturas', 50, 'R'], ['Facturado', 75.28, 'R']],
    array_map(fn ($m) => [$m['mes_largo'], $m['reparaciones'], euros($m['mano_obra']), euros($m['piezas']), euros($m['ingresos']), $m['facturas'], euros($m['total'])], $meses),
    ['Total', $resumen['reparaciones'], euros($resumen['mano_obra']), euros($resumen['piezas']), euros($resumen['ingresos']), $resumen['facturas'], euros($resumen['facturado'])]
);

if ($mecanicos) {
    $y = $tabla($y, 'Por mecánico',
        [['Mecánico', 155, 'L'], ['Rep.', 50, 'R'], ['Horas', 60, 'R'], ['Mano de obra', 85, 'R'], ['Piezas', 80, 'R'], ['Ingresos', 85.28, 'R']],
        array_map(fn ($m) => [$m['nombre'], $m['reparaciones'], $horas((float) $m['horas']), euros($m['mano_obra']), euros($m['piezas']), euros($m['ingresos'])], $mecanicos)
    );
}

if ($piezas) {
    $y = $tabla($y, 'Piezas que más ingresan',
        [['Pieza', 235, 'L'], ['Referencia', 115, 'L'], ['Unidades', 70, 'R'], ['Importe', 95.28, 'R']],
        array_map(fn ($p) => [$p['nombre'], $p['referencia'], (int) $p['unidades'], euros($p['importe'])], $piezas)
    );
}

if ($clientes) {
    $y = $tabla($y, 'Clientes que más vienen',
        [['Cliente', 205, 'L'], ['Teléfono', 85, 'L'], ['Visitas', 55, 'R'], ['Vehíc.', 50, 'R'], ['Gastado', 120.28, 'R']],
        array_map(fn ($c) => [$c['nombre'] . ' ' . $c['apellidos'], $c['telefono'], (int) $c['visitas'], (int) $c['vehiculos'], euros($c['importe'])], $clientes)
    );
}

if ($y + 30 > $limiteInferior) {
    $y = $nuevaPagina();
}
foreach ($pdf->partir('Ingresos: reparaciones cerradas en el año, por fecha de salida, con los precios actuales del catálogo y sin IVA. '
    . 'Facturado: facturas emitidas en el año, con sus importes fijos e IVA incluido.', $ancho, 8) as $nota) {
    $pdf->texto($izquierda, $y, $nota, 8, false, 'L', $gris);
    $y += 11;
}

$pie();

$contenido = $pdf->generar();
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="resumen-ventas-' . $anio . '.pdf"');
header('Content-Length: ' . strlen($contenido));
echo $contenido;
