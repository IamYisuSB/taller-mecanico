<?php
// Controlador de las facturas. No contiene SQL ni HTML
class FacturasControlador
{
    public function __construct(
        private FacturaModelo $facturas,
        private ReparacionModelo $reparaciones,
        private array $empresa,
    ) {}

    // index.php?accion=facturas[&q=texto]
    public function listado(): void
    {
        $busqueda = trim($_GET['q'] ?? '');
        $facturas = $this->facturas->todas($busqueda);

        $this->mostrar('facturas', [
            'titulo'   => 'Facturas',
            'facturas' => $facturas,
            'busqueda' => $busqueda,
            'total'    => array_sum(array_column($facturas, 'total')),
        ]);
    }

    // index.php?accion=facturar (POST): crea la factura de una reparación cerrada
    public function generar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirigir('index.php');
        }

        $id = filter_input(INPUT_POST, 'reparacion_id', FILTER_VALIDATE_INT);
        $reparacion = $id ? $this->reparaciones->buscar($id) : null;
        if (!$reparacion) {
            $this->noEncontrada();
            return;
        }

        $ficha = 'index.php?accion=ver&id=' . $id;

        // Solo se factura lo que ya ha salido del taller, y una sola vez
        if ($reparacion['salida'] === null) {
            $this->redirigir($ficha . '&error=abierta');
        }
        if ($this->facturas->deReparacion($id)) {
            $this->redirigir($ficha . '&error=facturada');
        }

        try {
            $this->facturas->crear($id, (float) $this->empresa['iva']);
        } catch (PDOException | RuntimeException $e) {
            $this->redirigir($ficha . '&error=facturar');
        }
        $this->redirigir($ficha . '&msg=facturada');
    }

    // index.php?accion=factura-pdf&id=3[&descargar=1]
    // Sin «descargar» se abre en el navegador; con él, se descarga el archivo
    public function pdf(): void
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $factura = $id ? $this->facturas->buscar($id) : null;
        if (!$factura) {
            $this->noEncontrada();
            return;
        }

        $this->mostrar('factura-pdf', [
            'factura'   => $factura,
            'lineas'    => $this->facturas->lineas($id),
            'empresa'   => $this->empresa,
            'descargar' => ($_GET['descargar'] ?? '') === '1',
        ]);
    }

    private function noEncontrada(): void
    {
        http_response_code(404);
        $this->mostrar('no-encontrada', ['titulo' => 'Página no encontrada']);
    }

    private function mostrar(string $vista, array $datos): void
    {
        extract($datos);
        require __DIR__ . '/../vistas/' . $vista . '.php';
    }

    private function redirigir(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}
