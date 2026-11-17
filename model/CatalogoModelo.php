<?php
// Modelo: las listas para rellenar los desplegables
class CatalogoModelo
{
    public function __construct(private PDO $pdo) {}

    public function vehiculos(): array
    {
        // TODO 1: id, matricula, marca y modelo de cada vehículo, con el nombre
        //         y los apellidos de su cliente; ordenados por marca y modelo

        return $this->pdo->query(
            "SELECT vehiculos.id, vehiculos.marca, vehiculos.matricula, vehiculos.modelo, clientes.nombre, clientes.apellidos
            FROM vehiculos 
            JOIN clientes ON clientes.id = vehiculos.id_cliente 
            ORDER BY vehiculos.marca, vehiculos.modelo;"
        )->fetchAll();
    }

    public function mecanicos(): array
    {
        // TODO 2: id, nombre y especialidad de cada mecánico, ordenados por nombre
        return $this->pdo->query(
            "SELECT mecanicos.id, mecanicos.nombre, mecanicos.especialidad from mecanicos ORDER BY mecanicos.nombre;"

        )->fetchAll();
    }

    // Todas las piezas para el desplegable
    public function piezas(): array
    {
        // TODO 1: id, nombre y precio de cada pieza, ordenadas por nombre
        return $this->pdo->query(
            "SELECT piezas.id, piezas.nombre, piezas.precio FROM piezas ORDER BY piezas.nombre;"
        )->fetchAll();
    }
}