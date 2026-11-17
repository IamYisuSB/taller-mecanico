<?php
// Controlador de la gestión de clientes, vehículos, mecánicos y piezas.
// No contiene SQL ni HTML. La entidad llega en ?tipo= (o en el campo oculto
// «tipo» de los formularios) y solo se aceptan las de GestionModelo::ENTIDADES
class GestionControlador
{
    private const AVISOS = [
        'guardado' => 'Cambios guardados.',
        'creado'   => 'Alta realizada.',
        'borrado'  => 'Borrado correctamente.',
    ];

    public function __construct(private GestionModelo $gestion) {}

    // index.php?accion=gestion&tipo=clientes[&q=texto]
    public function listado(): void
    {
        $entidad = $this->entidad($_GET['tipo'] ?? '');
        if (!$entidad) {
            return;
        }
        $config = GestionModelo::ENTIDADES[$entidad];
        $busqueda = trim($_GET['q'] ?? '');

        $errores = [
            'en_uso' => 'No se puede borrar: ' . $config['en_uso'] . '.',
            'borrar' => 'No se ha podido borrar.',
        ];

        $this->mostrar('gestion-listado', [
            'titulo'     => $config['titulo'],
            'entidad'    => $entidad,
            'config'     => $config,
            'filas'      => $this->gestion->todos($entidad, $busqueda),
            'busqueda'   => $busqueda,
            'relaciones' => $this->relaciones($config),
            'aviso'      => self::AVISOS[$_GET['msg'] ?? ''] ?? '',
            'error'      => $errores[$_GET['error'] ?? ''] ?? '',
        ]);
    }

    // index.php?accion=gestion-editar&tipo=clientes[&id=3]  (sin id: alta nueva)
    public function editar(): void
    {
        $entidad = $this->entidad($_GET['tipo'] ?? '');
        if (!$entidad) {
            return;
        }
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;

        $valores = [];
        if ($id) {
            $valores = $this->gestion->buscar($entidad, $id);
            if (!$valores) {
                $this->noEncontrada();
                return;
            }
        }
        $this->formulario($entidad, $id, $valores, []);
    }

    // index.php?accion=gestion-guardar (POST)
    public function guardar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirigir('index.php');
        }
        $entidad = $this->entidad($_POST['tipo'] ?? '');
        if (!$entidad) {
            return;
        }
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: null;
        if ($id && !$this->gestion->buscar($entidad, $id)) {
            $this->noEncontrada();
            return;
        }

        [$datos, $errores] = $this->validar(GestionModelo::ENTIDADES[$entidad]);

        if (!$errores) {
            try {
                if ($id) {
                    $this->gestion->actualizar($entidad, $id, $datos);
                    $msg = 'guardado';
                } else {
                    $this->gestion->crear($entidad, $datos);
                    $msg = 'creado';
                }
                $this->redirigir("index.php?accion=gestion&tipo=$entidad&msg=$msg");
            } catch (PDOException $e) {
                $errores[] = 'No se ha podido guardar.';
            }
        }

        // Hay errores: el formulario otra vez, con lo que escribió el usuario
        $this->formulario($entidad, $id, $_POST, $errores);
    }

    // index.php?accion=gestion-borrar (POST)
    public function borrar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirigir('index.php');
        }
        $entidad = $this->entidad($_POST['tipo'] ?? '');
        if (!$entidad) {
            return;
        }
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $listado = "index.php?accion=gestion&tipo=$entidad";

        if (!$id || !$this->gestion->buscar($entidad, $id)) {
            $this->noEncontrada();
            return;
        }

        try {
            $this->gestion->borrar($entidad, $id);
        } catch (PDOException $e) {
            // 23000: la fila está en uso en otra tabla (clave ajena)
            $this->redirigir($listado . '&error=' . ($e->getCode() === '23000' ? 'en_uso' : 'borrar'));
        }
        $this->redirigir($listado . '&msg=borrado');
    }

    // Lee y valida los campos de la entidad desde $_POST.
    // Devuelve [datos para guardar, mensajes de error]
    private function validar(array $config): array
    {
        $datos = [];
        $errores = [];

        foreach ($config['campos'] as $columna => $campo) {
            $valor = trim((string) ($_POST[$columna] ?? ''));
            $etiqueta = $campo['etiqueta'];

            if ($valor === '') {
                if (!empty($campo['opcional'])) {
                    $datos[$columna] = null;
                } else {
                    $errores[] = "Rellena el campo «{$etiqueta}».";
                }
                continue;
            }

            switch ($campo['tipo']) {
                case 'texto':
                case 'telefono':
                case 'correo':
                    if (!empty($campo['mayusculas'])) {
                        $valor = mb_strtoupper($valor);
                    }
                    if (mb_strlen($valor) > $campo['max']) {
                        $errores[] = "«{$etiqueta}» admite como máximo {$campo['max']} caracteres.";
                    } elseif ($campo['tipo'] === 'correo' && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                        $errores[] = 'El correo no es válido.';
                    } elseif ($campo['tipo'] === 'telefono' && !preg_match('/^[0-9 +()-]{6,}$/', $valor)) {
                        $errores[] = 'El teléfono solo puede tener números, espacios y los signos + ( ) -.';
                    }
                    break;

                case 'dinero':
                    // Se admite la coma decimal: 12,50
                    $valor = filter_var(str_replace(',', '.', $valor), FILTER_VALIDATE_FLOAT);
                    if ($valor === false || $valor < 0 || $valor > 99999.99) {
                        $errores[] = "«{$etiqueta}» debe ser un número entre 0 y 99.999,99.";
                    }
                    break;

                case 'anio':
                    $maximo = (int) date('Y') + 1;
                    $valor = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1901, 'max_range' => $maximo]]);
                    if ($valor === false) {
                        $errores[] = "«{$etiqueta}» debe ser un año entre 1901 y $maximo.";
                    }
                    break;

                case 'lista':
                    if (!in_array($valor, $campo['opciones'], true)) {
                        $errores[] = "Elige una opción válida en «{$etiqueta}».";
                    }
                    break;

                case 'relacion':
                    $valor = filter_var($valor, FILTER_VALIDATE_INT);
                    if ($valor === false || !$this->gestion->buscar($campo['entidad'], $valor)) {
                        $errores[] = "Elige un valor válido en «{$etiqueta}».";
                    }
                    break;
            }
            $datos[$columna] = $valor;
        }

        return [$datos, $errores];
    }

    // Opciones de los campos que apuntan a otra entidad: [columna => [id => texto]]
    private function relaciones(array $config): array
    {
        $relaciones = [];
        foreach ($config['campos'] as $columna => $campo) {
            if ($campo['tipo'] === 'relacion') {
                $relaciones[$columna] = $this->gestion->opciones($campo['entidad']);
            }
        }
        return $relaciones;
    }

    private function formulario(string $entidad, ?int $id, array $valores, array $errores): void
    {
        $config = GestionModelo::ENTIDADES[$entidad];
        $this->mostrar('gestion-formulario', [
            'titulo'     => ($id ? 'Editar ' : 'Nuevo ') . $config['singular'],
            'entidad'    => $entidad,
            'config'     => $config,
            'id'         => $id,
            'valores'    => $valores,
            'errores'    => $errores,
            'relaciones' => $this->relaciones($config),
        ]);
    }

    // Devuelve la entidad si existe; si no, muestra el 404 y devuelve null
    private function entidad(string $tipo): ?string
    {
        if (isset(GestionModelo::ENTIDADES[$tipo])) {
            return $tipo;
        }
        $this->noEncontrada();
        return null;
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
