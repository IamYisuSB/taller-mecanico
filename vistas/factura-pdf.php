<?php
// Vista: dibuja la factura en PDF y la envía al navegador
/** @var array $factura */
/** @var array $lineas */
/** @var array $empresa */
/** @var bool  $descargar */
require_once __DIR__ . '/../lib/Pdf.php';

$pdf = new Pdf('Factura ' . $factura['numero']);

// Colores (los mismos que la web)
$oscuro = [24, 32, 43];
$gris = [91, 102, 117];
$grisClaro = [201, 210, 222];
$fondo = [243, 245, 248];
$borde = [221, 226, 232];
$azul = [31, 95, 191];
$blanco = [255, 255, 255];
$negro = [28, 36, 48];

// Márgenes y columnas de la tabla
$izquierda = 40;
$derecha = Pdf::ANCHO - 40;
$colCantidad = 370;
$colPrecio = 460;
$anchoConcepto = 255; // termina antes de la columna Cantidad, aunque ponga «999,99 h»
$limiteInferior = 760;

// Cantidad: «1,50 h» o «2 ud»
$cantidad = function (array $linea): string {
    $numero = (float) $linea['cantidad'];
    $texto = floor($numero) == $numero ? (string) (int) $numero : number_format($numero, 2, ',', '.');
    return $texto . ' ' . $linea['unidad'];
};

$pie = function () use ($pdf, $empresa, $factura, $izquierda, $derecha, $borde, $gris) {
    $pdf->linea($izquierda, 805, $derecha, 805, 0.5, $borde);
    $pdf->texto($izquierda, 820, $empresa['nombre'] . ' · Factura ' . $factura['numero'], 8, false, 'L', $gris);
    $pdf->texto($derecha, 820, 'Página ' . $pdf->numeroPaginas() . ' de {paginas}', 8, false, 'R', $gris);
};

$cabeceraTabla = function (float $y) use ($pdf, $izquierda, $derecha, $colCantidad, $colPrecio, $fondo, $gris): float {
    $pdf->rectangulo($izquierda, $y, $derecha - $izquierda, 22, $fondo);
    $pdf->texto($izquierda + 8, $y + 15, 'CONCEPTO', 8, true, 'L', $gris);
    $pdf->texto($colCantidad, $y + 15, 'CANTIDAD', 8, true, 'R', $gris);
    $pdf->texto($colPrecio, $y + 15, 'PRECIO', 8, true, 'R', $gris);
    $pdf->texto($derecha - 8, $y + 15, 'IMPORTE', 8, true, 'R', $gris);
    return $y + 22;
};

// Página nueva cuando no cabe lo siguiente: pie, página y cabecera pequeña
$saltoPagina = function () use ($pdf, $pie, $factura, $empresa, $izquierda, $derecha, $oscuro, $gris): float {
    $pie();
    $pdf->nuevaPagina();
    $pdf->texto($izquierda, 50, $empresa['nombre'], 11, true, 'L', $oscuro);
    $pdf->texto($derecha, 50, 'Factura ' . $factura['numero'] . ' (continuación)', 9, false, 'R', $gris);
    return 75;
};

$pdf->nuevaPagina();

// ---------- Banda superior: taller y número de factura ----------
$pdf->rectangulo(0, 0, Pdf::ANCHO, 115, $oscuro);
$pdf->rectangulo(0, 115, Pdf::ANCHO, 4, $azul);
$pdf->texto($izquierda, 50, $empresa['nombre'], 20, true, 'L', $blanco);

$datosEmpresa = array_filter([
    $empresa['nif'] ? 'NIF ' . $empresa['nif'] : '',
    $empresa['direccion'],
    $empresa['localidad'],
    $empresa['telefono'] ? 'Tel. ' . $empresa['telefono'] : '',
    $empresa['correo'],
]);
$y = 70;
foreach (array_slice($pdf->partir(implode(' · ', $datosEmpresa), 300, 9), 0, 3) as $lineaEmpresa) {
    $pdf->texto($izquierda, $y, $lineaEmpresa, 9, false, 'L', $grisClaro);
    $y += 12;
}

$pdf->texto($derecha, 50, 'FACTURA', 24, true, 'R', $blanco);
$pdf->texto($derecha, 72, 'Nº ' . $factura['numero'], 12, true, 'R', $grisClaro);
$pdf->texto($derecha, 89, 'Fecha: ' . fecha($factura['fecha']), 10, false, 'R', $grisClaro);

// ---------- Cliente (izquierda) y vehículo (derecha) ----------
$y = 155;
$pdf->texto($izquierda, $y, 'FACTURAR A', 8, true, 'L', $gris);
$pdf->texto($izquierda, $y + 18, $factura['cliente'], 13, true, 'L', $negro);
$pdf->texto($izquierda, $y + 34, 'Tel. ' . $factura['cliente_telefono'], 10, false, 'L', $gris);
$pdf->texto($izquierda, $y + 48, $factura['cliente_correo'], 10, false, 'L', $gris);

$xDatos = 330;
$pdf->texto($xDatos, $y, 'VEHÍCULO Y REPARACIÓN', 8, true, 'L', $gris);
$datos = [
    'Vehículo'      => $factura['vehiculo'],
    'Matrícula'     => $factura['matricula'],
    'Reparación nº' => (string) $factura['id_reparacion'],
    'Entrada'       => $factura['entrada'] ? fecha($factura['entrada']) : '—',
    'Salida'        => $factura['salida'] ? fecha($factura['salida']) : '—',
];
$yDato = $y + 18;
foreach ($datos as $etiqueta => $valor) {
    $pdf->texto($xDatos, $yDato, $etiqueta, 10, false, 'L', $gris);
    $pdf->texto($derecha, $yDato, $valor, 10, true, 'R', $negro);
    $yDato += 15;
}

// ---------- Avería ----------
$y = max($y + 70, $yDato + 10);
$pdf->texto($izquierda, $y, 'AVERÍA', 8, true, 'L', $gris);
$y += 15;
foreach ($pdf->partir($factura['averia'], $derecha - $izquierda, 10) as $lineaAveria) {
    $pdf->texto($izquierda, $y, $lineaAveria, 10, false, 'L', $negro);
    $y += 13;
}

// ---------- Líneas ----------
$y = $cabeceraTabla($y + 12);
if (!$lineas) {
    $pdf->texto($izquierda + 8, $y + 18, 'Sin conceptos facturados.', 10, false, 'L', $gris);
    $y += 28;
}
foreach ($lineas as $linea) {
    $trozos = $pdf->partir($linea['concepto'], $anchoConcepto, 10);
    $alto = count($trozos) * 13 + 11;
    if ($y + $alto > $limiteInferior) {
        $y = $cabeceraTabla($saltoPagina());
    }
    $yTexto = $y + 16;
    foreach ($trozos as $i => $trozo) {
        $pdf->texto($izquierda + 8, $yTexto + $i * 13, $trozo, 10, false, 'L', $negro);
    }
    $pdf->texto($colCantidad, $yTexto, $cantidad($linea), 10, false, 'R', $negro);
    $pdf->texto($colPrecio, $yTexto, euros($linea['precio']), 10, false, 'R', $negro);
    $pdf->texto($derecha - 8, $yTexto, euros($linea['importe']), 10, true, 'R', $negro);
    $y += $alto;
    $pdf->linea($izquierda, $y, $derecha, $y, 0.5, $borde);
}

// ---------- Totales ----------
if ($y + 95 > $limiteInferior) {
    $y = $saltoPagina();
}
$y += 20;
$xTotales = 340;
$iva = rtrim(rtrim(number_format((float) $factura['iva_porcentaje'], 2, ',', ''), '0'), ',');
$pdf->texto($xTotales, $y, 'Base imponible', 10, false, 'L', $gris);
$pdf->texto($derecha - 8, $y, euros($factura['base']), 10, false, 'R', $negro);
$y += 17;
$pdf->texto($xTotales, $y, "IVA ($iva %)", 10, false, 'L', $gris);
$pdf->texto($derecha - 8, $y, euros($factura['iva']), 10, false, 'R', $negro);
$y += 12;
$pdf->rectangulo($xTotales - 10, $y, $derecha - $xTotales + 10, 30, $azul);
$pdf->texto($xTotales, $y + 20, 'TOTAL', 12, true, 'L', $blanco);
$pdf->texto($derecha - 8, $y + 20, euros($factura['total']), 14, true, 'R', $blanco);

$pdf->texto($izquierda, $y + 20, 'Gracias por su confianza.', 10, false, 'L', $gris);

$pie();

// ---------- Envío ----------
$contenido = $pdf->generar();
$nombreArchivo = 'Factura-' . $factura['numero'] . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($descargar ? 'attachment' : 'inline') . '; filename="' . $nombreArchivo . '"');
header('Content-Length: ' . strlen($contenido));
echo $contenido;
