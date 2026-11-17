<?php
// Modelo: alta, edición, baja y búsqueda de los datos del taller
// (clientes, vehículos, mecánicos y el catálogo de piezas).
//
// Es un modelo genérico: cada tabla se describe en ENTIDADES. Los nombres de
// tablas y columnas salen SOLO de esa lista fija (nunca de la petición), y los
// valores que escribe el usuario van siempre con ? en consultas preparadas.
class GestionModelo
{
    // Tipos de campo:
    //   texto, telefono, correo -> texto con un máximo de caracteres ('max')
    //   dinero                  -> número decimal >= 0
    //   anio                    -> año de 4 cifras
    //   lista                   -> uno de los valores de 'opciones'
    //   relacion                -> el id de una fila de otra entidad ('entidad')
    // 'opcional' => true permite dejarlo vacío (se guarda NULL)
    public const ENTIDADES = [
        'clientes' => [
            'tabla'    => 'clientes',
            'titulo'   => 'Clientes',
            'singular' => 'cliente',
            'texto'    => "CONCAT(nombre, ' ', apellidos)",
            'orden'    => 'apellidos, nombre',
            'en_uso'   => 'tiene vehículos registrados',
            'campos'   => [
                'nombre'    => ['etiqueta' => 'Nombre', 'tipo' => 'texto', 'max' => 40],
                'apellidos' => ['etiqueta' => 'Apellidos', 'tipo' => 'texto', 'max' => 40],
                'telefono'  => ['etiqueta' => 'Teléfono', 'tipo' => 'telefono', 'max' => 20],
                'correo'    => ['etiqueta' => 'Correo', 'tipo' => 'correo', 'max' => 245],
            ],
        ],
        'vehiculos' => [
            'tabla'    => 'vehiculos',
            'titulo'   => 'Vehículos',
            'singular' => 'vehículo',
            'texto'    => "CONCAT(marca, ' ', modelo, ' · ', matricula)",
            'orden'    => 'marca, modelo',
            'en_uso'   => 'tiene reparaciones',
            'campos'   => [
                'matricula'        => ['etiqueta' => 'Matrícula', 'tipo' => 'texto', 'max' => 10, 'mayusculas' => true],
                'marca'            => ['etiqueta' => 'Marca', 'tipo' => 'texto', 'max' => 20],
                'modelo'           => ['etiqueta' => 'Modelo', 'tipo' => 'texto', 'max' => 50],
                'anio_fabricacion' => ['etiqueta' => 'Año', 'tipo' => 'anio', 'opcional' => true],
                'id_cliente'       => ['etiqueta' => 'Cliente', 'tipo' => 'relacion', 'entidad' => 'clientes'],
            ],
        ],
        'mecanicos' => [
            'tabla'    => 'mecanicos',
            'titulo'   => 'Mecánicos',
            'singular' => 'mecánico',
            'texto'    => 'nombre',
            'orden'    => 'nombre',
            'en_uso'   => 'tiene reparaciones asignadas',
            'campos'   => [
                'nombre'       => ['etiqueta' => 'Nombre', 'tipo' => 'texto', 'max' => 20],
                'especialidad' => [
                    'etiqueta' => 'Especialidad',
                    'tipo'     => 'lista',
                    'opciones' => ['Mecanica general', 'Mecanica Electronica', 'Chapa y pintura'],
                ],
            ],
        ],
        'piezas' => [
            'tabla'    => 'piezas',
            'titulo'   => 'Piezas',
            'singular' => 'pieza',
            'texto'    => "CONCAT(nombre, ' (', referencia, ')')",
            'orden'    => 'nombre',
            'en_uso'   => 'se ha usado en alguna reparación',
            'campos'   => [
                'referencia' => ['etiqueta' => 'Referencia', 'tipo' => 'texto', 'max' => 50, 'mayusculas' => true],
                'nombre'     => ['etiqueta' => 'Nombre', 'tipo' => 'texto', 'max' => 50],
                'precio'     => ['etiqueta' => 'Precio', 'tipo' => 'dinero'],
            ],
        ],
    ];

    public function __construct(private PDO $pdo) {}

    // Todas las filas de una entidad. Con $busqueda, solo las que contienen
    // ese texto en algún campo (en las relaciones, en el texto de la fila
    // relacionada: así un vehículo se encuentra por el nombre de su cliente)
    public function todos(string $entidad, string $busqueda = ''): array
    {
        $e = self::ENTIDADES[$entidad];
        $sql = "SELECT * FROM {$e['tabla']}";
        $parametros = [];

        if ($busqueda !== '') {
            $patron = '%' . addcslashes($busqueda, '%_\\') . '%';
            $condiciones = [];
            foreach ($e['campos'] as $columna => $campo) {
                if ($campo['tipo'] === 'relacion') {
                    $otra = self::ENTIDADES[$campo['entidad']];
                    $condiciones[] = "$columna IN (SELECT id FROM {$otra['tabla']} WHERE {$otra['texto']} LIKE ?)";
                } else {
                    $condiciones[] = "$columna LIKE ?";
                }
                $parametros[] = $patron;
            }
            $sql .= ' WHERE ' . implode(' OR ', $condiciones);
        }

        $sql .= " ORDER BY {$e['orden']}";
        $consulta = $this->pdo->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchAll();
    }

    // Una fila por su id; null si no existe
    public function buscar(string $entidad, int $id): ?array
    {
        $e = self::ENTIDADES[$entidad];
        $consulta = $this->pdo->prepare("SELECT * FROM {$e['tabla']} WHERE id = ?");
        $consulta->execute([$id]);
        return $consulta->fetch() ?: null;
    }

    // [id => texto] de una entidad, para los desplegables
    public function opciones(string $entidad): array
    {
        $e = self::ENTIDADES[$entidad];
        return $this->pdo->query(
            "SELECT id, {$e['texto']} AS texto FROM {$e['tabla']} ORDER BY {$e['orden']}"
        )->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // Inserta una fila; $datos es [columna => valor]. Devuelve el id nuevo
    public function crear(string $entidad, array $datos): int
    {
        $e = self::ENTIDADES[$entidad];
        $columnas = $this->columnas($entidad, $datos);
        $huecos = implode(', ', array_fill(0, count($columnas), '?'));

        $insertar = $this->pdo->prepare(
            "INSERT INTO {$e['tabla']} (" . implode(', ', $columnas) . ") VALUES ($huecos)"
        );
        $insertar->execute(array_values($datos));
        return (int) $this->pdo->lastInsertId();
    }

    // Cambia los datos de una fila
    public function actualizar(string $entidad, int $id, array $datos): void
    {
        $e = self::ENTIDADES[$entidad];
        $asignaciones = array_map(fn ($c) => "$c = ?", $this->columnas($entidad, $datos));

        $actualizar = $this->pdo->prepare(
            "UPDATE {$e['tabla']} SET " . implode(', ', $asignaciones) . " WHERE id = ?"
        );
        $actualizar->execute([...array_values($datos), $id]);
    }

    // Borra una fila. Si otra tabla la usa (clave ajena), MySQL lanza una
    // PDOException con código 23000 y no se borra nada
    public function borrar(string $entidad, int $id): void
    {
        $e = self::ENTIDADES[$entidad];
        $borrar = $this->pdo->prepare("DELETE FROM {$e['tabla']} WHERE id = ?");
        $borrar->execute([$id]);
    }

    // Comprueba que todas las columnas de $datos están en ENTIDADES
    private function columnas(string $entidad, array $datos): array
    {
        $columnas = array_keys($datos);
        foreach ($columnas as $columna) {
            if (!isset(self::ENTIDADES[$entidad]['campos'][$columna])) {
                throw new InvalidArgumentException("Columna no permitida: $columna");
            }
        }
        return $columnas;
    }
}
