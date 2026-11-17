<?php
// Modelo: todo el SQL de las facturas
class FacturaModelo
{
    public function __construct(private PDO $pdo) {}

    // Todas las facturas, la más reciente primero. Con $busqueda, solo las que
    // la contienen en el número, el cliente, el vehículo o la matrícula
    public function todas(string $busqueda = ''): array
    {
        $sql = "SELECT id, numero, id_reparacion, fecha, cliente, vehiculo, matricula, base, iva, total
                FROM facturas";
        $parametros = [];
        if ($busqueda !== '') {
            $sql .= " WHERE numero LIKE ? OR cliente LIKE ? OR vehiculo LIKE ? OR matricula LIKE ?";
            $patron = '%' . addcslashes($busqueda, '%_\\') . '%';
            $parametros = [$patron, $patron, $patron, $patron];
        }
        $sql .= " ORDER BY fecha DESC, id DESC";

        $consulta = $this->pdo->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchAll();
    }

    // Una factura por su id; null si no existe
    public function buscar(int $id): ?array
    {
        $consulta = $this->pdo->prepare("SELECT * FROM facturas WHERE id = ?");
        $consulta->execute([$id]);
        return $consulta->fetch() ?: null;
    }

    // La factura de una reparación; null si todavía no se ha facturado
    public function deReparacion(int $reparacionId): ?array
    {
        $consulta = $this->pdo->prepare("SELECT id, numero, fecha, total FROM facturas WHERE id_reparacion = ?");
        $consulta->execute([$reparacionId]);
        return $consulta->fetch() ?: null;
    }

    public function lineas(int $facturaId): array
    {
        $consulta = $this->pdo->prepare(
            "SELECT concepto, cantidad, unidad, precio, importe FROM factura_lineas WHERE id_factura = ? ORDER BY id"
        );
        $consulta->execute([$facturaId]);
        return $consulta->fetchAll();
    }

    // Crea la factura de una reparación y devuelve su id.
    // Copia los datos y las líneas tal como están ahora, y le da el siguiente
    // número del año (F-2026-0001, F-2026-0002…). Todo en una transacción:
    // o se guarda la factura entera, o nada
    public function crear(int $reparacionId, float $ivaPorcentaje): int
    {
        $propia = !$this->pdo->inTransaction();
        if ($propia) {
            $this->pdo->beginTransaction();
        }

        try {
            // FOR UPDATE: bloquea la reparación mientras se factura
            $consulta = $this->pdo->prepare(
                "SELECT reparacion.id, reparacion.entrada, reparacion.salida, reparacion.descripcion, reparacion.mano_obra,
                    vehiculos.marca, vehiculos.modelo, vehiculos.matricula,
                    clientes.nombre, clientes.apellidos, clientes.telefono, clientes.correo
                 FROM reparacion
                 JOIN vehiculos ON vehiculos.id = reparacion.id_coche
                 JOIN clientes ON clientes.id = vehiculos.id_cliente
                 WHERE reparacion.id = ?
                 FOR UPDATE"
            );
            $consulta->execute([$reparacionId]);
            $r = $consulta->fetch();
            if (!$r) {
                throw new RuntimeException('La reparación no existe.');
            }

            // Líneas: importe fijo de mano de obra, horas de trabajo y piezas.
            // Cada línea: [concepto, cantidad, unidad, precio, importe (se calcula abajo), tipo]
            $lineas = [];
            if ($r['mano_obra'] > 0) {
                $lineas[] = ['Mano de obra', 1, 'ud', (float) $r['mano_obra'], 0, 'mano_obra'];
            }

            $trabajos = $this->pdo->prepare(
                "SELECT descripcion, horas, precio_hora FROM mano_obra_reparacion WHERE id_reparacion = ? ORDER BY fecha, id"
            );
            $trabajos->execute([$reparacionId]);
            foreach ($trabajos as $t) {
                $lineas[] = ['Mano de obra: ' . $t['descripcion'], (float) $t['horas'], 'h', (float) $t['precio_hora'], 0, 'mano_obra'];
            }

            $piezas = $this->pdo->prepare(
                "SELECT piezas.nombre, piezas.referencia, SUM(pr.unidades) AS unidades, piezas.precio
                 FROM piezas_reparacion AS pr
                 JOIN piezas ON piezas.id = pr.id_piezas
                 WHERE pr.id_reparacion = ?
                 GROUP BY piezas.id, piezas.nombre, piezas.referencia, piezas.precio
                 ORDER BY piezas.nombre"
            );
            $piezas->execute([$reparacionId]);
            foreach ($piezas as $p) {
                $lineas[] = [$p['nombre'] . ' (' . $p['referencia'] . ')', (float) $p['unidades'], 'ud', (float) $p['precio'], 0, 'pieza'];
            }

            $base = 0.0;
            foreach ($lineas as &$linea) {
                $linea[4] = round($linea[1] * $linea[3], 2);
                $base += $linea[4];
            }
            unset($linea);
            $base = round($base, 2);
            $iva = round($base * $ivaPorcentaje / 100, 2);

            // Siguiente número del año
            $anio = date('Y');
            $ultimo = $this->pdo->prepare("SELECT MAX(numero) FROM facturas WHERE numero LIKE ? FOR UPDATE");
            $ultimo->execute(["F-$anio-%"]);
            $numero = sprintf('F-%s-%04d', $anio, (int) substr((string) $ultimo->fetchColumn(), -4) + 1);

            $insertar = $this->pdo->prepare(
                "INSERT INTO facturas (numero, id_reparacion, cliente, cliente_telefono, cliente_correo,
                    vehiculo, matricula, averia, entrada, salida, base, iva_porcentaje, iva, total)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $insertar->execute([
                $numero, $reparacionId,
                $r['nombre'] . ' ' . $r['apellidos'], $r['telefono'], $r['correo'],
                $r['marca'] . ' ' . $r['modelo'], $r['matricula'], $r['descripcion'],
                $r['entrada'], $r['salida'],
                $base, $ivaPorcentaje, $iva, round($base + $iva, 2),
            ]);
            $facturaId = (int) $this->pdo->lastInsertId();

            $insertarLinea = $this->pdo->prepare(
                "INSERT INTO factura_lineas (id_factura, tipo, concepto, cantidad, unidad, precio, importe) VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            foreach ($lineas as [$concepto, $cantidad, $unidad, $precio, $importe, $tipo]) {
                $insertarLinea->execute([$facturaId, $tipo, mb_substr($concepto, 0, 250), $cantidad, $unidad, $precio, $importe]);
            }

            if ($propia) {
                $this->pdo->commit();
            }
            return $facturaId;
        } catch (Throwable $e) {
            if ($propia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
