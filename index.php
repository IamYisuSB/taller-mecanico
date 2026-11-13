<?php
// Controlador frontal: todas las peticiones de la aplicación entran por aquí
require __DIR__ . '/config/conexion.php';
require __DIR__ . '/vistas/ayudas.php';
require __DIR__ . '/model/reparacionModelo.php';

$pdo = conectar();

$modelo = new ReparacionModelo($pdo);

// echo "Se ha conectado correctamente";


$accion = $_GET["accion"] ?? "";

match ($accion){
    "listado" => print('<pre>' . htmlspecialchars(print_r($modelo->todas(), true)) . '</pre>'),
    default => print("No existe la accion " . htmlspecialchars($accion)),
};


// TODO 2: con match, escribe «Aquí irá el listado de reparaciones» si la acción
//         es 'listado' y «No existe la acción …» (con la acción escapada)
//         en cualquier otro caso