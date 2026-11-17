<?php
// Pantalla de inicio de sesión (página completa, sin el menú)
/** @var string $titulo */
/** @var string $error */
/** @var string $usuario */
/** @var string $aviso */
/** @var string $demoUsuario */
/** @var string $demoContrasena */
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo) ?> · Taller Genil</title>
  <link rel="stylesheet" href="estilos.css">
</head>
<body class="pagina-login">
  <main class="login">
    <div class="login-marca">
      <span class="marca-icono" aria-hidden="true">⚙</span>
      <div>
        <strong>Taller Mecánico Genil</strong>
        <span>Gestión de reparaciones, facturas y estadísticas</span>
      </div>
    </div>

    <section class="tarjeta login-tarjeta">
      <h1>Iniciar sesión</h1>

      <?php if ($aviso): ?>
        <p class="aviso"><?= e($aviso) ?></p>
      <?php endif; ?>
      <?php if ($error): ?>
        <p class="error" role="alert"><?= e($error) ?></p>
      <?php endif; ?>

      <form method="post" action="index.php?accion=entrar" class="login-formulario">
        <label for="usuario">Usuario</label>
        <input type="text" name="usuario" id="usuario" value="<?= e($usuario) ?>"
               autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>

        <label for="contrasena">Contraseña</label>
        <input type="password" name="contrasena" id="contrasena" autocomplete="current-password" required>

        <button class="boton ancho">Entrar</button>
      </form>
    </section>

    <section class="tarjeta login-demo" aria-labelledby="titulo-demo">
      <h2 id="titulo-demo">¿Solo quieres echar un vistazo?</h2>
      <p>Entra con la cuenta de demostración. Puedes ver todas las secciones y descargar facturas
         y estadísticas, pero los cambios están desactivados.</p>
      <dl class="credenciales">
        <dt>Usuario</dt><dd><code><?= e($demoUsuario) ?></code></dd>
        <dt>Contraseña</dt><dd><code><?= e($demoContrasena) ?></code></dd>
      </dl>
      <form method="post" action="index.php?accion=entrar">
        <input type="hidden" name="usuario" value="<?= e($demoUsuario) ?>">
        <input type="hidden" name="contrasena" value="<?= e($demoContrasena) ?>">
        <button class="boton secundario ancho">Entrar como demo</button>
      </form>
    </section>

    <p class="login-pie">Proyecto de portfolio · PHP, MySQL y MVC sin frameworks</p>
  </main>
</body>
</html>
