<?php
// Controlador del login. No contiene SQL ni HTML
class AuthControlador
{
    // Datos de la cuenta demo que se enseñan en la pantalla de login
    public const DEMO_USUARIO = 'demo';
    public const DEMO_CONTRASENA = 'demo1234';

    public function __construct(private UsuarioModelo $usuarios) {}

    // index.php?accion=login
    public function formulario(string $error = '', string $usuario = ''): void
    {
        if (Sesion::usuario()) {
            $this->redirigir('index.php');
        }
        $espera = Sesion::esperaLogin();
        if ($espera > 0 && $error === '') {
            $error = "Demasiados intentos. Espera $espera segundos y vuelve a probar.";
        }
        extract([
            'titulo'          => 'Iniciar sesión',
            'error'           => $error,
            'usuario'         => $usuario,
            'aviso'           => Sesion::sacarAviso()['texto'] ?? '',
            'demoUsuario'     => self::DEMO_USUARIO,
            'demoContrasena'  => self::DEMO_CONTRASENA,
        ]);
        require __DIR__ . '/../vistas/login.php';
    }

    // index.php?accion=entrar (POST)
    public function entrar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirigir('index.php?accion=login');
        }

        $usuario = trim($_POST['usuario'] ?? '');
        $contrasena = (string) ($_POST['contrasena'] ?? '');

        $espera = Sesion::esperaLogin();
        if ($espera > 0) {
            $this->formulario("Demasiados intentos. Espera $espera segundos y vuelve a probar.", $usuario);
            return;
        }

        $cuenta = $usuario !== '' ? $this->usuarios->buscarActivo($usuario) : null;

        // Se comprueba siempre un hash, exista o no el usuario, para que el
        // tiempo de respuesta no delate qué usuarios existen
        $hash = $cuenta['contrasena'] ?? '$2y$12$J0Y9CIWP/i/IfLIHe5iliOmZARV9ha6fnzmmJf6TrQv3imIbURuU.';
        if (!password_verify($contrasena, $hash) || !$cuenta) {
            Sesion::intentoFallido();
            $this->formulario('Usuario o contraseña incorrectos.', $usuario);
            return;
        }

        if (password_needs_rehash($cuenta['contrasena'], PASSWORD_DEFAULT)) {
            $this->usuarios->actualizarHash((int) $cuenta['id'], password_hash($contrasena, PASSWORD_DEFAULT));
        }
        $this->usuarios->registrarAcceso((int) $cuenta['id']);
        Sesion::entrar($cuenta);

        if ($cuenta['rol'] === 'demo') {
            Sesion::avisar('Estás en la cuenta demo: puedes verlo todo, pero los cambios están desactivados.');
        }
        $this->redirigir(Sesion::sacarDestino());
    }

    // index.php?accion=salir (POST)
    public function salir(): void
    {
        Sesion::salir();
        $this->redirigir('index.php?accion=login');
    }

    private function redirigir(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}
