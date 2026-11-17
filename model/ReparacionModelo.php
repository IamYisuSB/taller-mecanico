<?php
// Modelo: todo el SQL de las reparaciones está aquí, y solo aquí
class ReparacionModelo
{
    public function __construct(private PDO $pdo) {}

    // Formas de ordenar el listado. ORDER BY no admite parámetros (?),
    // así que solo se usan estas cláusulas fijas
    public const ORDENES = [
        'estado'    => 'reparacion.salida IS NOT NULL, reparacion.entrada DESC, reparacion.id DESC',
        'recientes' => 'reparacion.entrada DESC, reparacion.id DESC',
        'antiguas'  => 'reparacion.entrada ASC, reparacion.id ASC',
        'vehiculo'  => 'vehiculos.marca, vehiculos.modelo, reparacion.entrada DESC',
        'mecanico'  => 'mecanicos.nombre, reparacion.entrada DESC',
    ];

    // Todas las reparaciones; por defecto, primero las que siguen en el taller
    // y dentro de cada grupo de la más reciente a la más antigua.
    // $filtros admite:
    //   'estado'   => 'abiertas' (sin salida) o 'cerradas' (con salida)
    //   'mecanico' => id del mecánico
    //   'busqueda' => texto que se busca en matrícula, marca, modelo, avería y cliente
    public function todas(string $orden = 'estado', array $filtros = []): array
    {
        $orderBy = self::ORDENES[$orden] ?? self::ORDENES['estado'];

        $condiciones = [];
        $parametros = [];

        if (($filtros['estado'] ?? '') === 'abiertas') {
            $condiciones[] = 'reparacion.salida IS NULL';
        } elseif (($filtros['estado'] ?? '') === 'cerradas') {
            $condiciones[] = 'reparacion.salida IS NOT NULL';
        }

        if (!empty($filtros['mecanico'])) {
            $condiciones[] = 'reparacion.id_mecanico = ?';
            $parametros[] = $filtros['mecanico'];
        }

        if (($filtros['busqueda'] ?? '') !== '') {
            $condiciones[] = "(vehiculos.matricula LIKE ? OR vehiculos.marca LIKE ? OR vehiculos.modelo LIKE ?
                OR reparacion.descripcion LIKE ? OR CONCAT(clientes.nombre, ' ', clientes.apellidos) LIKE ?)";
            // addcslashes: que un % o un _ escrito por el usuario se busque tal cual
            $patron = '%' . addcslashes($filtros['busqueda'], '%_\\') . '%';
            array_push($parametros, $patron, $patron, $patron, $patron, $patron);
        }

        $sql = "SELECT reparacion.id, reparacion.entrada, reparacion.salida, reparacion.descripcion,
                vehiculos.matricula, vehiculos.modelo, vehiculos.marca,
                CONCAT(clientes.nombre, ' ', clientes.apellidos) AS cliente,
                mecanicos.nombre AS mecanico
            FROM reparacion
            JOIN vehiculos ON vehiculos.id = reparacion.id_coche
            JOIN clientes ON clientes.id = vehiculos.id_cliente
            JOIN mecanicos ON mecanicos.id = reparacion.id_mecanico"
            . ($condiciones ? "\nWHERE " . implode(' AND ', $condiciones) : '')
            . "\nORDER BY $orderBy";

        $consulta = $this->pdo->prepare($sql);
        $consulta->execute($parametros);

        return $consulta->fetchAll();
    }
    // Una reparación con su vehículo, su cliente y su mecánico; null si no existe
    public function buscar(int $id): ?array
    {
        // TODO 1: consulta preparada que una reparaciones, vehiculos, clientes y mecanicos.
        //         Columnas: id, fecha_entrada, fecha_salida, descripcion, mano_obra,
        //         matricula, marca, modelo, telefono y, con alias,
        //         cliente_nombre, cliente_apellidos y mecanico.
        //         Devuelve la fila, o null si no hay ninguna
        $consulta = $this->pdo->prepare(
            "SELECT reparacion.id, reparacion.entrada, reparacion.salida, reparacion.descripcion, reparacion.mano_obra,
            reparacion.id_coche, reparacion.id_mecanico,
            vehiculos.matricula, vehiculos.marca, vehiculos.modelo,
            clientes.nombre as cliente_nombre, clientes.apellidos as cliente_apellidos, clientes.telefono,
            mecanicos.nombre as mecanico
            FROM reparacion 
            JOIN vehiculos ON vehiculos.id = reparacion.id_coche
            JOIN clientes ON clientes.id = vehiculos.id_cliente
            JOIN mecanicos on mecanicos.id = reparacion.id_mecanico
        WHERE reparacion.id = ?"
        );
        $consulta->execute([$id]);
        $fila = $consulta->fetch();
        return $fila ?: null;
    }

    // Las piezas usadas en una reparación, con el importe de cada línea
    public function piezas(int $id): array
    {
        // TODO 2: consulta preparada que una reparacion_piezas y piezas.
        //         Columnas: referencia, nombre, cantidad, precio
        //         e importe (cantidad por precio), ordenadas por nombre

        $consulta = $this->pdo->prepare(
            "SELECT piezas.id AS pieza_id, piezas.referencia, piezas.nombre, pr.unidades as cantidad, piezas.precio, pr.unidades * piezas.precio as importe
            FROM piezas_reparacion as pr
            JOIN piezas ON piezas.id = pr.id_piezas
            WHERE pr.id_reparacion = ?
            ORDER BY piezas.nombre;"
        );
        $consulta->execute([$id]);
        return $consulta->fetchAll();
    }


        // Abre una reparación con la fecha de hoy y devuelve su id
    public function crear(int $vehiculoId, int $mecanicoId, string $descripcion, float $manoObra): int
    {
        // TODO 3: INSERT preparado; la fecha de entrada la pone MySQL con CURDATE().
        //         Devuelve el id nuevo con lastInsertId(), convertido a entero
        $insertar = $this->pdo->prepare(
            "INSERT INTO reparacion (id_coche, id_mecanico, descripcion, mano_obra, entrada)
             VALUES (?, ?, ?, ?, CURDATE())"
        );
        $insertar->execute([
            $vehiculoId,
            $mecanicoId,
            $descripcion,
            $manoObra
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // Añade unidades de una pieza a una reparación
    public function anadirPieza(int $reparacionId, int $piezaId, int $cantidad): void
    {
        // TODO 2: UPDATE preparado que sume $cantidad a la fila
        //         de esa reparación y esa pieza
        $actualizar = $this->pdo->prepare(
            "UPDATE piezas_reparacion SET unidades = unidades + ?
             WHERE id_reparacion = ? AND id_piezas = ?"
        );
        $actualizar->execute([$cantidad, $reparacionId, $piezaId]);

        // TODO 3: si no ha cambiado ninguna fila (rowCount()), es que la pieza
        //         no estaba todavía en la reparación: insértala
        if ($actualizar->rowCount() === 0) {
            $insertar = $this->pdo->prepare(
                "INSERT INTO piezas_reparacion (id_reparacion, id_piezas, unidades) VALUES (?, ?, ?)"
            );
            $insertar->execute([$reparacionId, $piezaId, $cantidad]);
        }
    }

    // Las líneas de mano de obra de una reparación, con su importe (horas por precio)
    public function manoObra(int $id): array
    {
        $consulta = $this->pdo->prepare(
            "SELECT id, descripcion, horas, precio_hora, horas * precio_hora AS importe, fecha
             FROM mano_obra_reparacion
             WHERE id_reparacion = ?
             ORDER BY fecha, id"
        );
        $consulta->execute([$id]);
        return $consulta->fetchAll();
    }

    // Apunta un trabajo de mano de obra en una reparación
    public function anadirManoObra(int $reparacionId, string $descripcion, float $horas, float $precioHora): void
    {
        $insertar = $this->pdo->prepare(
            "INSERT INTO mano_obra_reparacion (id_reparacion, descripcion, horas, precio_hora)
             VALUES (?, ?, ?, ?)"
        );
        $insertar->execute([$reparacionId, $descripcion, $horas, $precioHora]);
    }

    // Borra una línea de mano de obra (solo si es de esa reparación)
    public function quitarManoObra(int $reparacionId, int $lineaId): void
    {
        $borrar = $this->pdo->prepare(
            "DELETE FROM mano_obra_reparacion WHERE id = ? AND id_reparacion = ?"
        );
        $borrar->execute([$lineaId, $reparacionId]);
    }

    // Quita una pieza de una reparación (todas sus unidades)
    public function quitarPieza(int $reparacionId, int $piezaId): void
    {
        $borrar = $this->pdo->prepare(
            "DELETE FROM piezas_reparacion WHERE id_reparacion = ? AND id_piezas = ?"
        );
        $borrar->execute([$reparacionId, $piezaId]);
    }

    // Cierra una reparación poniendo la fecha y hora de salida.
    // Devuelve false si ya estaba cerrada
    public function cerrar(int $id): bool
    {
        $cerrar = $this->pdo->prepare(
            "UPDATE reparacion SET salida = NOW() WHERE id = ? AND salida IS NULL"
        );
        $cerrar->execute([$id]);
        return $cerrar->rowCount() > 0;
    }

    // Cambia los datos de una reparación (no toca las fechas ni las piezas)
    public function actualizar(int $id, int $vehiculoId, int $mecanicoId, string $descripcion, float $manoObra): void
    {
        $actualizar = $this->pdo->prepare(
            "UPDATE reparacion SET id_coche = ?, id_mecanico = ?, descripcion = ?, mano_obra = ?
             WHERE id = ?"
        );
        $actualizar->execute([$vehiculoId, $mecanicoId, $descripcion, $manoObra, $id]);
    }
}
