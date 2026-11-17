<?php
// Formulario de alta (sin $id) o edición (con $id) de una entidad de gestión
/** @var string   $titulo */
/** @var string   $entidad */
/** @var array    $config      La descripción de la entidad en GestionModelo::ENTIDADES */
/** @var int|null $id */
/** @var array    $valores */
/** @var array    $errores */
/** @var array    $relaciones  [columna => [id => texto]] */
require __DIR__ . '/cabecera.php';

$tiposInput = ['texto' => 'text', 'telefono' => 'tel', 'correo' => 'email'];
?>

<?php if ($errores): ?>
  <ul class="error">
    <?php foreach ($errores as $error): ?>
      <li><?= e($error) ?></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<form method="post" action="index.php?accion=gestion-guardar" class="alta tarjeta">
  <input type="hidden" name="tipo" value="<?= e($entidad) ?>">
  <?php if ($id): ?>
    <input type="hidden" name="id" value="<?= $id ?>">
  <?php endif; ?>

  <?php foreach ($config['campos'] as $columna => $campo): ?>
    <?php
      $valor = (string) ($valores[$columna] ?? '');
      $requerido = empty($campo['opcional']) ? 'required' : '';
    ?>

    <?php if ($campo['tipo'] === 'relacion'): ?>
      <label for="buscar_<?= e($columna) ?>">Buscar <?= e(mb_strtolower($campo['etiqueta'])) ?></label>
      <input type="search" id="buscar_<?= e($columna) ?>" data-filtra="<?= e($columna) ?>" placeholder="Escribe para filtrar…">
    <?php endif; ?>

    <label for="<?= e($columna) ?>">
      <?= e($campo['etiqueta']) ?><?= $requerido ? '' : ' <span class="secundario">(opcional)</span>' ?>
    </label>

    <?php if ($campo['tipo'] === 'relacion'): ?>
      <select name="<?= e($columna) ?>" id="<?= e($columna) ?>" <?= $requerido ?>>
        <option value="">Elige…</option>
        <?php foreach ($relaciones[$columna] as $opcionId => $texto): ?>
          <option value="<?= $opcionId ?>" <?= $valor == $opcionId ? 'selected' : '' ?>><?= e($texto) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($campo['entidad'] === 'clientes'): ?>
        <p class="ayuda">¿Cliente nuevo? <a href="index.php?accion=gestion-editar&amp;tipo=clientes">Dalo de alta primero</a>.</p>
      <?php endif; ?>

    <?php elseif ($campo['tipo'] === 'lista'): ?>
      <select name="<?= e($columna) ?>" id="<?= e($columna) ?>" <?= $requerido ?>>
        <option value="">Elige…</option>
        <?php foreach ($campo['opciones'] as $opcion): ?>
          <option value="<?= e($opcion) ?>" <?= $valor === $opcion ? 'selected' : '' ?>><?= e($opcion) ?></option>
        <?php endforeach; ?>
      </select>

    <?php elseif ($campo['tipo'] === 'dinero'): ?>
      <input type="number" name="<?= e($columna) ?>" id="<?= e($columna) ?>" class="corto"
             min="0" max="99999.99" step="0.01" value="<?= e($valor) ?>" <?= $requerido ?>>

    <?php elseif ($campo['tipo'] === 'anio'): ?>
      <input type="number" name="<?= e($columna) ?>" id="<?= e($columna) ?>" class="corto"
             min="1901" max="<?= (int) date('Y') + 1 ?>" step="1" value="<?= e($valor) ?>" <?= $requerido ?>>

    <?php else: ?>
      <input type="<?= $tiposInput[$campo['tipo']] ?>" name="<?= e($columna) ?>" id="<?= e($columna) ?>"
             maxlength="<?= $campo['max'] ?>" value="<?= e($valor) ?>" <?= $requerido ?>>
    <?php endif; ?>
  <?php endforeach; ?>

  <div class="botones">
    <button class="boton"><?= $id ? 'Guardar cambios' : 'Dar de alta' ?></button>
    <a class="boton secundario" href="index.php?accion=gestion&amp;tipo=<?= e($entidad) ?>">Cancelar</a>
  </div>
</form>

<?php require __DIR__ . '/pie.php'; ?>
