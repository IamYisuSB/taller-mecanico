<?php
// Sesión del usuario: quién ha entrado, su rol, avisos de un solo uso
// y el freno a los intentos de login fallidos
class Sesion
{
    private const MAX_INTENTOS = 5;
    private const ESPERA_SEGUNDOS = 60;

    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('taller_genil');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,   // JavaScript no puede leer la cookie
            'samesite' => 'Lax',  // otra web no puede enviar formularios con tu sesión
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_start();
    }

    // El usuario que ha entrado: ['id', 'usuario', 'nombre', 'rol'], o null
    public static function usuario(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    public static function esDemo(): bool
    {
        return (self::usuario()['rol'] ?? '') === 'demo';
    }

    public static function entrar(array $usuario): void
    {
        session_regenerate_id(true); // id de sesión nuevo al entrar
        $_SESSION['usuario'] = [
            'id'      => (int) $usuario['id'],
            'usuario' => $usuario['usuario'],
            'nombre'  => $usuario['nombre'],
            'rol'     => $usuario['rol'],
        ];
        unset($_SESSION['intentos'], $_SESSION['bloqueo_hasta']);
    }

    public static function salir(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    // Aviso que se muestra una sola vez en la siguiente página.
    // $tipo es la clase CSS: 'aviso' (verde) o 'error' (rojo)
    public static function avisar(string $texto, string $tipo = 'aviso'): void
    {
        $_SESSION['aviso'] = ['texto' => $texto, 'tipo' => $tipo === 'error' ? 'error' : 'aviso'];
    }

    // ['texto' => …, 'tipo' => …] o null si no hay aviso
    public static function sacarAviso(): ?array
    {
        $aviso = $_SESSION['aviso'] ?? null;
        unset($_SESSION['aviso']);
        return is_array($aviso) ? $aviso : null;
    }

    // Página a la que volver después de entrar (solo direcciones internas)
    public static function recordarDestino(string $url): void
    {
        $_SESSION['destino'] = $url;
    }

    public static function sacarDestino(): string
    {
        $destino = $_SESSION['destino'] ?? '';
        unset($_SESSION['destino']);
        return preg_match('#^index\.php(\?[^\s]*)?$#', $destino) ? $destino : 'index.php';
    }

    // Segundos que faltan para poder volver a intentarlo (0 = puede)
    public static function esperaLogin(): int
    {
        return max(0, ($_SESSION['bloqueo_hasta'] ?? 0) - time());
    }

    public static function intentoFallido(): void
    {
        $_SESSION['intentos'] = ($_SESSION['intentos'] ?? 0) + 1;
        if ($_SESSION['intentos'] >= self::MAX_INTENTOS) {
            $_SESSION['bloqueo_hasta'] = time() + self::ESPERA_SEGUNDOS;
            $_SESSION['intentos'] = 0;
        }
    }
}
