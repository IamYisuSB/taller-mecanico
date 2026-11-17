<?php
// Modelo: las consultas de las estadísticas (ingresos y facturación)
//
// «Ingresos» = lo que vale el trabajo de las reparaciones CERRADAS (mano de
// obra + piezas, sin IVA), por la fecha de salida. Se calcula con los precios
// actuales del catálogo.
// «Facturación» = lo que dicen las facturas emitidas (con su copia fija de
// importes), por la fecha de la factura.
class EstadisticasModelo
{
    // Importe de cada reparación: mano de obra (fija + horas) y piezas.
    // Se usa como subconsulta en varias estadísticas
    private const IMPORTES = "
        SELECT r.id, r.id_mecanico, r.id_coche, r.entrada, r.salida,
            r.mano_obra + COALESCE(mo.importe, 0) AS mano_obra,
            COALESCE(mo.horas, 0) AS horas,
            COALESCE(pz.importe, 0) AS piezas
        FROM reparacion r
        LEFT JOIN (
            SELECT id_reparacion, SUM(horas) AS horas, SUM(horas * precio_hora) AS importe
            FROM mano_obra_reparacion GROUP BY id_reparacion
        ) mo ON mo.id_reparacion = r.id
        LEFT JOIN (
            SELECT pr.id_reparacion, SUM(pr.unidades * p.precio) AS importe
            FROM piezas_reparacion pr JOIN piezas p ON p.id = pr.id_piezas
            GROUP BY pr.id_reparacion
        ) pz ON pz.id_reparacion = r.id";

    public function __construct(private PDO $pdo) {}

    // Años con reparaciones cerradas o facturas, del más reciente al más antiguo
    public function anios(): array
    {
        return array_map('intval', $this->pdo->query(
            "SELECT YEAR(salida) AS anio FROM reparacion WHERE salida IS NOT NULL
             UNION
             SELECT YEAR(fecha) FROM facturas
             ORDER BY anio DESC"
        )->fetchAll(PDO::FETCH_COLUMN));
    }

    // Ingresos de las reparaciones cerradas en el año, mes a mes
    public function ingresosPorMes(int $anio): array
    {
        return $this->filas(
            "SELECT MONTH(salida) AS mes, COUNT(*) AS reparaciones,
                SUM(mano_obra) AS mano_obra, SUM(piezas) AS piezas, SUM(horas) AS horas
             FROM (" . self::IMPORTES . ") i
             WHERE salida IS NOT NULL AND YEAR(salida) = ?
             GROUP BY MONTH(salida)",
            [$anio]
        );
    }

    // Facturas emitidas en el año, mes a mes
    public function facturacionPorMes(int $anio): array
    {
        return $this->filas(
            "SELECT MONTH(fecha) AS mes, COUNT(*) AS facturas,
                SUM(base) AS base, SUM(iva) AS iva, SUM(total) AS total
             FROM facturas
             WHERE YEAR(fecha) = ?
             GROUP BY MONTH(fecha)",
            [$anio]
        );
    }

    // Ingresos del año por mecánico, del que más al que menos
    public function porMecanico(int $anio): array
    {
        return $this->filas(
            "SELECT m.nombre, COUNT(*) AS reparaciones,
                SUM(i.mano_obra) AS mano_obra, SUM(i.piezas) AS piezas, SUM(i.horas) AS horas
             FROM (" . self::IMPORTES . ") i
             JOIN mecanicos m ON m.id = i.id_mecanico
             WHERE i.salida IS NOT NULL AND YEAR(i.salida) = ?
             GROUP BY m.id, m.nombre
             ORDER BY SUM(i.mano_obra + i.piezas) DESC",
            [$anio]
        );
    }

    // Las piezas que más ingresos han dado en el año
    public function topPiezas(int $anio, int $limite = 8): array
    {
        return $this->filas(
            "SELECT p.nombre, p.referencia, SUM(pr.unidades) AS unidades, SUM(pr.unidades * p.precio) AS importe
             FROM piezas_reparacion pr
             JOIN piezas p ON p.id = pr.id_piezas
             JOIN reparacion r ON r.id = pr.id_reparacion
             WHERE r.salida IS NOT NULL AND YEAR(r.salida) = ?
             GROUP BY p.id, p.nombre, p.referencia
             ORDER BY importe DESC, p.nombre
             LIMIT " . max(1, $limite),
            [$anio]
        );
    }

    // Los clientes que más vienen: reparaciones que han ENTRADO en el año (abiertas
    // o cerradas), con cuántos vehículos distintos han traído y lo que han gastado
    public function topClientes(int $anio, int $limite = 8): array
    {
        return $this->filas(
            "SELECT c.nombre, c.apellidos, c.telefono,
                COUNT(*) AS visitas,
                COUNT(DISTINCT i.id_coche) AS vehiculos,
                SUM(i.mano_obra + i.piezas) AS importe,
                MAX(i.entrada) AS ultima
             FROM (" . self::IMPORTES . ") i
             JOIN vehiculos v ON v.id = i.id_coche
             JOIN clientes c ON c.id = v.id_cliente
             WHERE YEAR(i.entrada) = ?
             GROUP BY c.id, c.nombre, c.apellidos, c.telefono
             ORDER BY visitas DESC, importe DESC, c.apellidos
             LIMIT " . max(1, $limite),
            [$anio]
        );
    }

    // Reparaciones cerradas (de cualquier año) que todavía no tienen factura
    public function pendienteFacturar(): array
    {
        return $this->pdo->query(
            "SELECT COUNT(*) AS reparaciones, COALESCE(SUM(mano_obra + piezas), 0) AS importe
             FROM (" . self::IMPORTES . ") i
             WHERE salida IS NOT NULL AND id NOT IN (SELECT id_reparacion FROM facturas)"
        )->fetch();
    }

    // Reparaciones en el taller ahora mismo y lo que llevan acumulado
    public function enTaller(): array
    {
        return $this->pdo->query(
            "SELECT COUNT(*) AS reparaciones, COALESCE(SUM(mano_obra + piezas), 0) AS importe
             FROM (" . self::IMPORTES . ") i
             WHERE salida IS NULL"
        )->fetch();
    }

    // Las facturas del año (para exportarlas)
    public function facturas(int $anio): array
    {
        return $this->filas(
            "SELECT numero, fecha, id_reparacion, cliente, vehiculo, matricula, base, iva, total
             FROM facturas WHERE YEAR(fecha) = ? ORDER BY fecha, numero",
            [$anio]
        );
    }

    private function filas(string $sql, array $parametros): array
    {
        $consulta = $this->pdo->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchAll();
    }
}
