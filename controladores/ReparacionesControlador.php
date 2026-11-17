<?php
// Controlador: recibe la petición, usa los modelos y elige la vista.
// No contiene SQL ni HTML.
class ReparacionesControlador
{
    public function __construct(
        private ReparacionModelo $reparaciones,
        private CatalogoModelo $catalogo,
        private FacturaModelo $facturas,
    ) {}

    // index.php?accion=listado
    public function listado(): void
    {
        // TODO 1: pide las reparaciones al modelo y muestra la vista «listado»
        //         con dos datos: 'titulo' => 'Reparaciones' y 'reparaciones'
        // Textos del desplegable de orden; la clave es la que se manda en ?orden=
        $ordenes = [
            'estado'    => 'En el taller primero',
            'recientes' => 'Más recientes primero',
            'antiguas'  => 'Más antiguas primero',
            'vehiculo'  => 'Por vehículo',
            'mecanico'  => 'Por mecánico',
        ];
        $orden = $_GET['orden'] ?? 'estado';
        if (!isset($ordenes[$orden])) {
            $orden = 'estado';
        }

        // Filtros: ?estado=abiertas|cerradas, ?mecanico=2, ?q=texto
        $estados = [
            ''         => 'Todas',
            'abiertas' => 'En el taller',
            'cerradas' => 'Cerradas',
        ];
        $filtros = [
            'estado'   => isset($estados[$_GET['estado'] ?? '']) ? ($_GET['estado'] ?? '') : '',
            'mecanico' => filter_input(INPUT_GET, 'mecanico', FILTER_VALIDATE_INT) ?: null,
            'busqueda' => trim($_GET['q'] ?? ''),
        ];

        $avisos = [
            'cerrada' => 'Reparación cerrada.',
        ];

        $this->mostrar("listado", [
            "titulo" => "Reparaciones",
            "reparaciones" => $this->reparaciones->todas($orden, $filtros),
            "ordenes" => $ordenes,
            "orden" => $orden,
            "estados" => $estados,
            "filtros" => $filtros,
            "mecanicos" => $this->catalogo->mecanicos(),
            "aviso" => $avisos[$_GET['msg'] ?? ''] ?? '',
        ]);
    }

        // index.php?accion=ver&id=3
    public function ver(): void
    {
        // TODO 3: lee el id de la dirección como número entero

        $id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);


        // TODO 4: pide la reparación al modelo; si no hay id o no existe,
        //         llama a $this->noEncontrada() y termina con return

        $repacion = $id ? $this->reparaciones->buscar($id) : null;
        if(!$repacion){
            $this->noEncontrada();
            return;
        }

        // TODO 5: pide sus piezas y calcula el total:
        //         mano de obra más la suma de los importes

        $piezas = $this->reparaciones->piezas($id);
        $manoObra = $this->reparaciones->manoObra($id);

        // Total = importe fijo de mano de obra + líneas de mano de obra (horas) + piezas
        $totalPiezas = array_sum(array_column($piezas, "importe"));
        $totalManoObra = $repacion["mano_obra"] + array_sum(array_column($manoObra, "importe"));
        $total = $totalManoObra + $totalPiezas;

        // TODO 6: muestra la vista «ficha» con cuatro datos: titulo
        //         («Reparación n.º 3»), reparacion, piezas y total

        // TODO 12: textos de los avisos y de los errores
        $avisos = [
            'creada'  => 'Reparación abierta.',
            'pieza'   => 'Pieza añadida.',
            'quitada' => 'Pieza quitada.',
            'cerrada' => 'Reparación cerrada.',
            'editada' => 'Cambios guardados.',
            'trabajo' => 'Mano de obra añadida.',
            'trabajo-quitado' => 'Mano de obra quitada.',
            'facturada' => 'Factura generada.',
        ];
        $errores = [
            'cantidad' => 'Elige una pieza y una cantidad de 1 o más.',
            'pieza'    => 'No se ha podido añadir la pieza.',
            'quitar'   => 'No se ha podido quitar la pieza.',
            'cerrada'  => 'Esta reparación ya tiene fecha de salida.',
            'cerrar'   => 'No se ha podido cerrar la reparación.',
            'trabajo-datos' => 'Describe el trabajo (150 caracteres como máximo), con horas mayores que 0 y un precio por hora de 0 o más.',
            'trabajo'  => 'No se ha podido guardar la mano de obra.',
            'abierta'   => 'Cierra la reparación antes de facturarla.',
            'facturada' => 'Esta reparación ya está facturada y no se puede modificar.',
            'facturar'  => 'No se ha podido generar la factura.',
        ];

        $this->mostrar("ficha", [
            "titulo" => "Reparación nº " . $repacion["id"],
            "reparacion" => $repacion,
            "piezas" => $piezas,
            "manoObra" => $manoObra,
            // Precio por hora propuesto en el formulario: el de la última línea
            "precioHora" => $manoObra ? end($manoObra)["precio_hora"] : "",
            "totalPiezas" => $totalPiezas,
            "totalManoObra" => $totalManoObra,
            "total" => $total,
            "factura" => $this->facturas->deReparacion($id),
            'aviso' => $avisos[$_GET['msg'] ?? ''] ?? '',
            'error' => $errores[$_GET['error'] ?? ''] ?? '',
            'catalogo' => $this->catalogo->piezas(),
        ]);
    }

    // index.php?accion=pieza (POST)
    public function anadirPieza(): void
    {
        // TODO 4: si la petición no es POST, redirige a index.php
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            $this->redirigir("index.php");
        }

        // TODO 5: lee reparacion_id, pieza_id y cantidad como enteros
        $id = filter_input(INPUT_POST, "reparacion_id", FILTER_VALIDATE_INT);
        $piezaId = filter_input(INPUT_POST, "pieza_id", FILTER_VALIDATE_INT);
        $cantidad = filter_input(INPUT_POST, "cantidad", FILTER_VALIDATE_INT);

        // TODO 6: busca la reparación; si no existe, noEncontrada() y return
        $reparacion = $id ? $this->reparaciones->buscar($id) : null;
        if (!$reparacion) {
            $this->noEncontrada();
            return;
        }

        $ficha = 'index.php?accion=ver&id=' . $id;

        // TODO 7: si la reparación ya tiene fecha de salida,
        //         redirige a $ficha . '&error=cerrada'
        if ($reparacion["salida"] !== null) {
            $this->redirigir($ficha . '&error=cerrada');
        }

        // TODO 8: si falta la pieza o la cantidad es menor que 1,
        //         redirige a $ficha . '&error=cantidad'
        if (!$piezaId || !$cantidad || $cantidad < 1) {
            $this->redirigir($ficha . '&error=cantidad');
        }

        // TODO 9: añade la pieza con el modelo; si lanza una PDOException,
        //         redirige con &error=pieza; si va bien, con &msg=pieza
        try {
            $this->reparaciones->anadirPieza($id, $piezaId, $cantidad);
        } catch (PDOException $e) {
            $this->redirigir($ficha . '&error=pieza');
        }
        $this->redirigir($ficha . '&msg=pieza');
    }

    // index.php?accion=mano-obra (POST): apunta horas de trabajo
    public function anadirManoObra(): void
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            $this->redirigir("index.php");
        }

        $id = filter_input(INPUT_POST, "reparacion_id", FILTER_VALIDATE_INT);
        $reparacion = $id ? $this->reparaciones->buscar($id) : null;
        if (!$reparacion) {
            $this->noEncontrada();
            return;
        }

        $ficha = 'index.php?accion=ver&id=' . $id;

        if ($reparacion["salida"] !== null) {
            $this->redirigir($ficha . '&error=cerrada');
        }

        $descripcion = trim($_POST["descripcion"] ?? "");
        $horas = $this->decimal($_POST["horas"] ?? "");
        $precioHora = $this->decimal($_POST["precio_hora"] ?? "");

        if ($descripcion === "" || mb_strlen($descripcion) > 150
            || $horas === false || $horas <= 0 || $horas > 999.99
            || $precioHora === false || $precioHora < 0 || $precioHora > 99999.99) {
            $this->redirigir($ficha . '&error=trabajo-datos');
        }

        try {
            $this->reparaciones->anadirManoObra($id, $descripcion, $horas, $precioHora);
        } catch (PDOException $e) {
            $this->redirigir($ficha . '&error=trabajo');
        }
        $this->redirigir($ficha . '&msg=trabajo');
    }

    // index.php?accion=quitar-mano-obra (POST)
    public function quitarManoObra(): void
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            $this->redirigir("index.php");
        }

        $id = filter_input(INPUT_POST, "reparacion_id", FILTER_VALIDATE_INT);
        $lineaId = filter_input(INPUT_POST, "linea_id", FILTER_VALIDATE_INT);
        $reparacion = $id ? $this->reparaciones->buscar($id) : null;
        if (!$reparacion) {
            $this->noEncontrada();
            return;
        }

        $ficha = 'index.php?accion=ver&id=' . $id;

        if ($reparacion["salida"] !== null) {
            $this->redirigir($ficha . '&error=cerrada');
        }

        try {
            if ($lineaId) {
                $this->reparaciones->quitarManoObra($id, $lineaId);
            }
        } catch (PDOException $e) {
            $this->redirigir($ficha . '&error=trabajo');
        }
        $this->redirigir($ficha . '&msg=trabajo-quitado');
    }

    // Convierte un texto en número decimal; admite la coma («1,5»).
    // Devuelve false si no es un número
    private function decimal(string $texto): float|false
    {
        return filter_var(str_replace(',', '.', trim($texto)), FILTER_VALIDATE_FLOAT);
    }

    // index.php?accion=quitar-pieza (POST)
    public function quitarPieza(): void
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            $this->redirigir("index.php");
        }

        $id = filter_input(INPUT_POST, "reparacion_id", FILTER_VALIDATE_INT);
        $piezaId = filter_input(INPUT_POST, "pieza_id", FILTER_VALIDATE_INT);

        $reparacion = $id ? $this->reparaciones->buscar($id) : null;
        if (!$reparacion) {
            $this->noEncontrada();
            return;
        }

        $ficha = 'index.php?accion=ver&id=' . $id;

        // Una reparación cerrada ya no se toca
        if ($reparacion["salida"] !== null) {
            $this->redirigir($ficha . '&error=cerrada');
        }
        if (!$piezaId) {
            $this->redirigir($ficha . '&error=quitar');
        }

        try {
            $this->reparaciones->quitarPieza($id, $piezaId);
        } catch (PDOException $e) {
            $this->redirigir($ficha . '&error=quitar');
        }
        $this->redirigir($ficha . '&msg=quitada');
    }

    // index.php?accion=cerrar (POST). Pone la fecha de salida.
    // Si llega volver=listado, vuelve al listado; si no, a la ficha
    public function cerrar(): void
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            $this->redirigir("index.php");
        }

        $id = filter_input(INPUT_POST, "reparacion_id", FILTER_VALIDATE_INT);
        $reparacion = $id ? $this->reparaciones->buscar($id) : null;
        if (!$reparacion) {
            $this->noEncontrada();
            return;
        }

        $ficha = 'index.php?accion=ver&id=' . $id;
        $volver = ($_POST['volver'] ?? '') === 'listado' ? 'index.php?accion=listado' : $ficha;

        if ($reparacion["salida"] !== null) {
            $this->redirigir($ficha . '&error=cerrada');
        }

        try {
            $this->reparaciones->cerrar($id);
        } catch (PDOException $e) {
            $this->redirigir($ficha . '&error=cerrar');
        }
        $this->redirigir($volver . '&msg=cerrada');
    }

    // index.php?accion=editar&id=3
    public function editar(): void
    {
        $id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
        $reparacion = $id ? $this->reparaciones->buscar($id) : null;
        if (!$reparacion) {
            $this->noEncontrada();
            return;
        }
        // Una reparación facturada ya no se cambia: la factura no cuadraría
        if ($this->facturas->deReparacion($id)) {
            $this->redirigir("index.php?accion=ver&id=$id&error=facturada");
        }

        $this->formulario([], [
            "vehiculo_id" => $reparacion["id_coche"],
            "mecanico_id" => $reparacion["id_mecanico"],
            "descripcion" => $reparacion["descripcion"],
            "mano_obra"   => $reparacion["mano_obra"],
        ], $id);
    }

    // index.php?accion=actualizar (POST)
    public function actualizar(): void
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            $this->redirigir("index.php");
        }

        $id = filter_input(INPUT_POST, "reparacion_id", FILTER_VALIDATE_INT);
        if (!$id || !$this->reparaciones->buscar($id)) {
            $this->noEncontrada();
            return;
        }
        if ($this->facturas->deReparacion($id)) {
            $this->redirigir("index.php?accion=ver&id=$id&error=facturada");
        }

        $vehiculoId = filter_input(INPUT_POST, "vehiculo_id", FILTER_VALIDATE_INT);
        $mecanicoId = filter_input(INPUT_POST, "mecanico_id", FILTER_VALIDATE_INT);
        $descripcion = trim($_POST["descripcion"] ?? "");
        // La mano de obra puede ir vacía (cuenta como 0): luego se añaden las horas
        $manoObra = trim($_POST["mano_obra"] ?? "") === "" ? 0.0 : $this->decimal($_POST["mano_obra"]);

        $errores = [];
        if (!$vehiculoId) {
            $errores[] = "Elige un vehículo.";
        }
        if (!$mecanicoId) {
            $errores[] = "Elige un mecánico.";
        }
        if ($descripcion === "" || mb_strlen($descripcion) > 200) {
            $errores[] = "Describe la avería (200 caracteres como máximo).";
        }
        if ($manoObra === false || $manoObra === null || $manoObra < 0) {
            $errores[] = "La mano de obra debe ser un número igual o mayor que 0.";
        }

        if (!$errores) {
            try {
                $this->reparaciones->actualizar($id, $vehiculoId, $mecanicoId, $descripcion, $manoObra);
                $this->redirigir("index.php?accion=ver&id=$id&msg=editada");
            } catch (PDOException $e) {
                $errores[] = "No se han podido guardar los cambios.";
            }
        }

        $this->formulario($errores, [
            "vehiculo_id" => $vehiculoId,
            "mecanico_id" => $mecanicoId,
            "descripcion" => $descripcion,
            "mano_obra"   => $_POST["mano_obra"] ?? "",
        ], $id);
    }

    // Cualquier acción que no exista
    public function noEncontrada(): void
    {
        // TODO 2: responde con el código 404 y muestra la vista «no-encontrada»
        //         con el título «Página no encontrada»
        http_response_code(404);
        $this->mostrar("no-encontrada", [
            "titulo" => "Pagina no encontrada"
        ]);
    }

    // Carga una vista. extract() convierte ['titulo' => 'Reparaciones']
    // en la variable $titulo, y así con cada dato
    private function mostrar(string $vista, array $datos): void
    {
        extract($datos);
        require __DIR__ . '/../vistas/' . $vista . '.php';
    }

     // index.php?accion=nueva
    public function nueva(): void
    {
        $this->formulario([], []);
    }

    // index.php?accion=guardar (POST)
    public function guardar(): void
    {
        // TODO 4: si la petición no es POST, redirige a index.php?accion=nueva
        if($_SERVER["REQUEST_METHOD"] !== "POST"){
            $this->redirigir("index.php?accion=nueva");
        }

        // TODO 5: lee vehiculo_id y mecanico_id como enteros, descripcion sin espacios
        //         a los lados y mano_obra como número decimal (FILTER_VALIDATE_FLOAT)
        $vehiculoId = filter_input(INPUT_POST, "vehiculo_id", FILTER_VALIDATE_INT);
        $mecanicoId = filter_input(INPUT_POST, "mecanico_id", FILTER_VALIDATE_INT);
        $descripcion = trim($_POST["descripcion"] ?? "");
        // La mano de obra puede ir vacía (cuenta como 0): luego se añaden las horas
        $manoObra = trim($_POST["mano_obra"] ?? "") === "" ? 0.0 : $this->decimal($_POST["mano_obra"]);

        // TODO 6: guarda en el array $errores un mensaje por cada dato incorrecto:
        //         «Elige un vehículo.», «Elige un mecánico.»,
        //         «Describe la avería (200 caracteres como máximo).»,
        //         «La mano de obra debe ser un número igual o mayor que 0.»
        $errores = [];
        if(!$vehiculoId){
            $errores[] = "elige un vehiculo";
        }
        if(!$mecanicoId){
            $errores[] = "Elige un mecánico";
        }
        if($descripcion === "" || mb_strlen($descripcion) > 200){
            $errores[] = "describe la averia, 255 caracteres maximos";
        }
        if($manoObra === false || $manoObra === null || $manoObra < 0){
            $errores[] = "La mano de obra debe ser un número igual o mayor que el 0";
        }

        // TODO 7: si no hay errores, crea la reparación con el modelo y redirige
        //         a su ficha con &msg=creada. Si el modelo lanza una PDOException,
        //         añade el error «No se ha podido guardar la reparación.»
        if(!$errores){
            try{
             $id = $this->reparaciones->crear($vehiculoId, $mecanicoId, $descripcion, $manoObra);
             $this->redirigir("index.php?accion=ver&id=$id&msg=creada");
            }catch (PDOException $e){
                $errores[] = "No se ha podido guardar la reparación";
            }
        }

        $this->formulario($errores, [
            "vehiculo_id" => $vehiculoId,
            "mecanico_id" => $mecanicoId,
            "descripcion" => $descripcion,
        "mano_obra" => $_POST["mano_obra"] ?? "",
        ]);




        // TODO 8: si has llegado aquí, hay errores: vuelve a mostrar el formulario
        //         con $errores y con los valores que escribió el usuario
    }

    // Muestra el formulario de nueva reparación, vacío o con errores.
    // Con $id, es el formulario para editar esa reparación
    private function formulario(array $errores, array $valores, ?int $id = null): void
    {
        $this->mostrar('formulario', [
            'titulo' => $id ? "Editar reparación nº $id" : 'Nueva reparación',
            'id' => $id,
            'vehiculos' => $this->catalogo->vehiculos(),
            'mecanicos' => $this->catalogo->mecanicos(),
            'errores' => $errores,
            'valores' => $valores,
        ]);
    }

    private function redirigir(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}