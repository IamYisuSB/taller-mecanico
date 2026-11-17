<?php
// Controlador de las estadísticas. No contiene SQL ni HTML.
// Las tres salidas (página, CSV y PDF) usan exactamente los mismos datos
class EstadisticasControlador
{
    public const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    public const MESES_LARGOS = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
                                 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    public function __construct(private EstadisticasModelo $estadisticas, private array $empresa) {}

    // index.php?accion=estadisticas[&anio=2026]
    public function ver(): void
    {
        $this->mostrar('estadisticas', $this->datos() + ['titulo' => 'Estadísticas']);
    }

    // index.php?accion=estadisticas-csv&anio=2026  (se abre en Excel)
    public function csv(): void
    {
        $this->mostrar('estadisticas-csv', $this->datos());
    }

    // index.php?accion=estadisticas-pdf&anio=2026
    public function pdf(): void
    {
        $this->mostrar('estadisticas-pdf', $this->datos() + ['empresa' => $this->empresa]);
    }

    // Todos los datos del año pedido, ya preparados para las vistas
    private function datos(): array
    {
        $anios = $this->estadisticas->anios();
        $actual = (int) date('Y');
        if (!in_array($actual, $anios, true)) {
            array_unshift($anios, $actual);
            rsort($anios);
        }
        $anio = filter_input(INPUT_GET, 'anio', FILTER_VALIDATE_INT);
        if (!in_array($anio, $anios, true)) {
            $anio = $actual;
        }

        // Los 12 meses, aunque alguno no tenga datos (así las gráficas no tienen huecos)
        $ingresos = array_column($this->estadisticas->ingresosPorMes($anio), null, 'mes');
        $facturacion = array_column($this->estadisticas->facturacionPorMes($anio), null, 'mes');
        $meses = [];
        foreach (self::MESES as $i => $nombre) {
            $ing = $ingresos[$i + 1] ?? [];
            $fac = $facturacion[$i + 1] ?? [];
            $meses[] = [
                'mes'          => $nombre,
                'mes_largo'    => self::MESES_LARGOS[$i],
                'reparaciones' => (int) ($ing['reparaciones'] ?? 0),
                'mano_obra'    => (float) ($ing['mano_obra'] ?? 0),
                'piezas'       => (float) ($ing['piezas'] ?? 0),
                'ingresos'     => (float) ($ing['mano_obra'] ?? 0) + (float) ($ing['piezas'] ?? 0),
                'horas'        => (float) ($ing['horas'] ?? 0),
                'facturas'     => (int) ($fac['facturas'] ?? 0),
                'base'         => (float) ($fac['base'] ?? 0),
                'iva'          => (float) ($fac['iva'] ?? 0),
                'total'        => (float) ($fac['total'] ?? 0),
            ];
        }

        $suma = fn (string $campo) => array_sum(array_column($meses, $campo));
        $reparaciones = $suma('reparaciones');
        $resumen = [
            'ingresos'     => $suma('ingresos'),
            'mano_obra'    => $suma('mano_obra'),
            'piezas'       => $suma('piezas'),
            'horas'        => $suma('horas'),
            'reparaciones' => $reparaciones,
            'ticket_medio' => $reparaciones ? $suma('ingresos') / $reparaciones : 0,
            'facturas'     => $suma('facturas'),
            'base'         => $suma('base'),
            'iva'          => $suma('iva'),
            'facturado'    => $suma('total'),
        ];

        $mecanicos = array_map(fn ($m) => $m + ['ingresos' => $m['mano_obra'] + $m['piezas']],
                               $this->estadisticas->porMecanico($anio));

        return [
            'anio'       => $anio,
            'anios'      => $anios,
            'meses'      => $meses,
            'resumen'    => $resumen,
            'mecanicos'  => $mecanicos,
            'piezas'     => $this->estadisticas->topPiezas($anio),
            'clientes'   => $this->estadisticas->topClientes($anio),
            'facturas'   => $this->estadisticas->facturas($anio),
            'pendiente'  => $this->estadisticas->pendienteFacturar(),
            'enTaller'   => $this->estadisticas->enTaller(),
        ];
    }

    private function mostrar(string $vista, array $datos): void
    {
        extract($datos);
        require __DIR__ . '/../vistas/' . $vista . '.php';
    }
}
