<?php
// Modelo: todo el SQL de las reparaciones está aquí, y solo aquí
class ReparacionModelo
{
    public function __construct(private PDO $pdo) {}

    // Todas las reparaciones, de la más reciente a la más antigua
    public function todas(): array
    {
        $sql = "SELECT reparacion.id, reparacion.entrada, reparacion.salida, reparacion.descripcion, vehiculos.matricula, vehiculos.matricula, vehiculos.modelo, mecanicos.nombre as mecanico\n"

    . "FROM reparacion, vehiculos, mecanicos\n"

    . "WHERE reparacion.id_coche = vehiculos.id\n"

    . "and reparacion.id_mecanico = mecanicos.id;";

    $consulta = $this->pdo->query($sql);
    return $consulta->fetchAll();
    }
}












