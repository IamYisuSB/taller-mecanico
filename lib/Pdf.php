<?php
// Generador de PDF mínimo, sin librerías externas.
//
// Usa las fuentes Helvetica y Helvetica-Bold que traen todos los lectores de
// PDF, con la codificación WinAnsi (Windows-1252), que incluye las tildes,
// la ñ y el símbolo €. Las coordenadas son en puntos (1 pt = 1/72 pulgadas)
// y se miden desde la esquina SUPERIOR izquierda, como en una pantalla.
class Pdf
{
    public const ANCHO = 595.28; // A4
    public const ALTO = 841.89;

    private array $paginas = [];
    private string $titulo = '';

    // Anchos de los caracteres de Helvetica (en milésimas del tamaño de letra)
    private const ANCHOS = [
        ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, '&' => 667, "'" => 191,
        '(' => 333, ')' => 333, '*' => 389, '+' => 584, ',' => 278, '-' => 333, '.' => 278, '/' => 278,
        ':' => 278, ';' => 278, '<' => 584, '=' => 584, '>' => 584, '?' => 556, '@' => 1015,
        'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778, 'H' => 722,
        'I' => 278, 'J' => 500, 'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778, 'P' => 667,
        'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722, 'V' => 667, 'W' => 944, 'X' => 667,
        'Y' => 667, 'Z' => 611, '[' => 278, '\\' => 278, ']' => 278, '_' => 556,
        'a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556, 'h' => 556,
        'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222, 'm' => 833, 'n' => 556, 'o' => 556, 'p' => 556,
        'q' => 556, 'r' => 333, 's' => 500, 't' => 278, 'u' => 556, 'v' => 500, 'w' => 722, 'x' => 500,
        'y' => 500, 'z' => 500, '|' => 260, '·' => 278, 'º' => 365, 'ª' => 370, '€' => 556,
    ];
    // Helvetica-Bold: solo los que cambian respecto a la normal
    private const ANCHOS_NEGRITA = [
        '!' => 333, '"' => 474, ':' => 333, ';' => 333, "'" => 238, '?' => 611, '@' => 975,
        'A' => 722, 'B' => 722, 'J' => 556, 'K' => 722, 'L' => 611,
        'b' => 611, 'c' => 556, 'd' => 611, 'f' => 333, 'g' => 611, 'h' => 611, 'i' => 278, 'j' => 278,
        'k' => 556, 'l' => 278, 'm' => 889, 'n' => 611, 'o' => 611, 'p' => 611, 'q' => 611, 'r' => 389,
        's' => 556, 't' => 333, 'u' => 611, 'v' => 556, 'w' => 778, 'x' => 556, 'y' => 556,
    ];

    public function __construct(string $titulo = '')
    {
        $this->titulo = $titulo;
    }

    public function nuevaPagina(): void
    {
        $this->paginas[] = '';
    }

    public function numeroPaginas(): int
    {
        return count($this->paginas);
    }

    // Escribe un texto. $alinear: 'L' (desde x), 'R' (termina en x) o 'C' (centrado en x)
    public function texto(float $x, float $y, string $texto, float $tam = 10, bool $negrita = false,
                          string $alinear = 'L', array $color = [0, 0, 0]): void
    {
        if ($alinear === 'R') {
            $x -= $this->ancho($texto, $tam, $negrita);
        } elseif ($alinear === 'C') {
            $x -= $this->ancho($texto, $tam, $negrita) / 2;
        }
        $fuente = $negrita ? 'F2' : 'F1';
        $this->dibujar(sprintf(
            "BT %s rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET",
            $this->color($color), $fuente, $tam, $x, self::ALTO - $y, $this->escapar($texto)
        ));
    }

    // Rectángulo relleno; (x, y) es su esquina superior izquierda
    public function rectangulo(float $x, float $y, float $ancho, float $alto, array $relleno): void
    {
        $this->dibujar(sprintf(
            "%s rg %.2F %.2F %.2F %.2F re f",
            $this->color($relleno), $x, self::ALTO - $y - $alto, $ancho, $alto
        ));
    }

    public function linea(float $x1, float $y1, float $x2, float $y2, float $grosor = 0.5, array $color = [0, 0, 0]): void
    {
        $this->dibujar(sprintf(
            "%s RG %.2F w %.2F %.2F m %.2F %.2F l S",
            $this->color($color), $grosor, $x1, self::ALTO - $y1, $x2, self::ALTO - $y2
        ));
    }

    // Ancho de un texto en puntos
    public function ancho(string $texto, float $tam, bool $negrita = false): float
    {
        $total = 0;
        // {paginas} se cambia al final por el número de páginas: se mide como una cifra
        $texto = str_replace('{paginas}', '0', $texto);
        foreach (mb_str_split($this->sinTildes($texto)) as $caracter) {
            $total += ctype_digit($caracter) ? 556
                : ($negrita ? (self::ANCHOS_NEGRITA[$caracter] ?? self::ANCHOS[$caracter] ?? 556)
                            : (self::ANCHOS[$caracter] ?? 556));
        }
        return $total * $tam / 1000;
    }

    // Parte un texto en líneas que quepan en $anchoMaximo
    public function partir(string $texto, float $anchoMaximo, float $tam, bool $negrita = false): array
    {
        $lineas = [];
        foreach (preg_split('/\R/u', $texto) as $parrafo) {
            $actual = '';
            foreach (preg_split('/\s+/u', trim($parrafo)) as $palabra) {
                $prueba = $actual === '' ? $palabra : "$actual $palabra";
                if ($actual !== '' && $this->ancho($prueba, $tam, $negrita) > $anchoMaximo) {
                    $lineas[] = $actual;
                    $actual = $palabra;
                } else {
                    $actual = $prueba;
                }
            }
            $lineas[] = $actual;
        }
        return $lineas;
    }

    // Devuelve el PDF completo. En los textos, {paginas} se cambia por el total de páginas
    public function generar(): string
    {
        $objetos = [];
        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objetos[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objetos[5] = '<< /Title (' . $this->escapar($this->titulo) . ') /Producer (Taller Genil) >>';

        $hijos = [];
        $siguiente = 6;
        foreach ($this->paginas as $contenido) {
            $contenido = str_replace('{paginas}', (string) count($this->paginas), $contenido);
            $pagina = $siguiente++;
            $flujo = $siguiente++;
            $hijos[] = "$pagina 0 R";
            $objetos[$pagina] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::ANCHO, self::ALTO, $flujo
            );
            $objetos[$flujo] = '<< /Length ' . strlen($contenido) . " >>\nstream\n" . $contenido . "\nendstream";
        }
        $objetos[2] = '<< /Type /Pages /Kids [' . implode(' ', $hijos) . '] /Count ' . count($hijos) . ' >>';
        ksort($objetos);

        // Cuerpo + tabla de posiciones (xref), que es lo que pide el formato PDF
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $posiciones = [];
        foreach ($objetos as $numero => $objeto) {
            $posiciones[$numero] = strlen($pdf);
            $pdf .= "$numero 0 obj\n$objeto\nendobj\n";
        }
        $inicioXref = strlen($pdf);
        $total = count($objetos) + 1;
        $pdf .= "xref\n0 $total\n0000000000 65535 f \n";
        for ($i = 1; $i < $total; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $posiciones[$i]);
        }
        $pdf .= "trailer\n<< /Size $total /Root 1 0 R /Info 5 0 R >>\nstartxref\n$inicioXref\n%%EOF\n";
        return $pdf;
    }

    private function dibujar(string $orden): void
    {
        if (!$this->paginas) {
            $this->nuevaPagina();
        }
        $this->paginas[array_key_last($this->paginas)] .= $orden . "\n";
    }

    // De UTF-8 a Windows-1252 y escapando los caracteres especiales de PDF
    private function escapar(string $texto): string
    {
        $texto = mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
        return strtr($texto, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
    }

    private function color(array $rgb): string
    {
        return sprintf('%.3F %.3F %.3F', $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);
    }

    // Para medir: «á» ocupa lo mismo que «a»
    private function sinTildes(string $texto): string
    {
        return strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            'à' => 'a', 'è' => 'e', 'ò' => 'o', 'ç' => 'c', 'Ç' => 'C',
        ]);
    }
}
