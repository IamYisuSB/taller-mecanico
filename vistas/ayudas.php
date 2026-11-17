<?php
// Funciones de ayuda para las vistas

// Escapa un texto antes de escribirlo en el HTML
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '');
}

// 2026-11-03  →  03/11/2026
function fecha(string $iso): string
{
    return date('d/m/Y', strtotime($iso));
}

// 145  →  145,00 €
function euros($cantidad): string
{
    return number_format((float) $cantidad, 2, ',', '.') . ' €';
}

// Euros sin decimales, para los ejes y las etiquetas de las gráficas.
// 1500 → «1.500 €»; desde 10.000, abreviado: 12500 → «12,5 mil €»
function euros_corto($cantidad): string
{
    $cantidad = (float) $cantidad;
    if (abs($cantidad) >= 10000) {
        return rtrim(rtrim(number_format($cantidad / 1000, 1, ',', '.'), '0'), ',') . ' mil €';
    }
    return number_format($cantidad, 0, ',', '.') . ' €';
}

// Escala «bonita» para el eje de una gráfica: el tope redondeado y sus marcas.
// 1830 → tope 2000, marcas [0, 500, 1000, 1500, 2000]
function escala(float $maximo, int $marcas = 4): array
{
    if ($maximo <= 0) {
        return ['tope' => 1, 'marcas' => [0]];
    }
    $paso = $maximo / $marcas;
    $potencia = 10 ** floor(log10($paso));
    foreach ([1, 2, 2.5, 5, 10] as $factor) {
        if ($factor * $potencia >= $paso) {
            $paso = $factor * $potencia;
            break;
        }
    }
    $tope = $paso * ceil($maximo / $paso);
    return ['tope' => $tope, 'marcas' => range(0, $tope, $paso)];
}

// Porcentaje de $parte sobre $total, entre 0 y 100
function porcentaje(float $parte, float $total): float
{
    return $total > 0 ? max(0, min(100, $parte / $total * 100)) : 0;
}

// Datos de la ventana emergente (tooltip) de un elemento de gráfica, como atributo HTML.
// $filas: [[nombre, valor, clase del color o ''], …]
function tooltip(string $titulo, array $filas): string
{
    return 'data-tip-titulo="' . e($titulo) . '" data-tip="' . e(json_encode($filas, JSON_UNESCAPED_UNICODE)) . '"';
}