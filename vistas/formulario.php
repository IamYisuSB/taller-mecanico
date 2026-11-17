<?php
/**
 * Vista del formulario para registrar una nueva reparación o editar una existente.
 *
 * Esta plantilla presenta un formulario en el que el usuario puede:
 * - seleccionar el vehículo a reparar (con un buscador para filtrar la lista),
 * - elegir el mecánico responsable,
 * - describir la avería,
 * - indicar el coste de la mano de obra.
 *
 * Sin $id, los datos se envían a la acción `guardar` (alta). Con $id, a la
 * acción `actualizar` (edición). Si hay errores de validación, se muestran
 * antes del formulario.
 *
 * @var string   $titulo
 * @var int|null $id         Id de la reparación que se edita, o null si es nueva.
 * @var array    $errores    Errores de validación devueltos por el controlador.
 * @var array    $valores    Valores con los que se rellena el formulario.
 * @var array    $vehiculos  Listado de vehículos disponibles para asignar.
 * @var array    $mecanicos  Listado de mecánicos disponibles para asignar.
 */

require __DIR__ . '/cabecera.php';
?>

<?php if ($errores): ?>
  <ul class="error">
    <?php foreach ($errores as $error): ?>
      <li><?= e($error) ?></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<form method="post" action="index.php?accion=<?= $id ? 'actualizar' : 'guardar' ?>" class="alta tarjeta">
  <?php if ($id): ?>
    <input type="hidden" name="reparacion_id" value="<?= $id ?>">
  <?php endif; ?>

  <label for="buscar_vehiculo">Buscar vehículo</label>
  <input type="search" id="buscar_vehiculo" data-filtra="vehiculo_id"
         placeholder="Matrícula, marca, modelo o cliente…">

  <label for="vehiculo_id">Vehículo</label>
  <select name="vehiculo_id" id="vehiculo_id" required>
    <option value="">Elige un vehículo…</option>
    <?php foreach ($vehiculos as $v): ?>
      <option value="<?= $v['id'] ?>" <?= ($valores['vehiculo_id'] ?? '') == $v['id'] ? 'selected' : '' ?>>
        <?= e($v['marca'] . ' ' . $v['modelo'] . ' · ' . $v['matricula'] . ' · ' . $v['nombre'] . ' ' . $v['apellidos']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <p class="ayuda">¿No está en la lista? <a href="index.php?accion=gestion-editar&amp;tipo=vehiculos">Da de alta el vehículo</a>.</p>

  <label for="mecanico_id">Mecánico</label>
  <select name="mecanico_id" id="mecanico_id" required>
    <option value="">Elige un mecánico…</option>
    <?php foreach ($mecanicos as $m): ?>
      <option value="<?= $m['id'] ?>" <?= ($valores['mecanico_id'] ?? '') == $m['id'] ? 'selected' : '' ?>>
        <?= e($m['nombre'] . ' (' . $m['especialidad'] . ')') ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label for="descripcion">Avería</label>
  <textarea name="descripcion" id="descripcion" rows="3" maxlength="200" required><?= e($valores['descripcion'] ?? '') ?></textarea>

  <label for="mano_obra">Importe fijo de mano de obra (€) <span class="secundario">(opcional)</span></label>
  <input type="number" name="mano_obra" id="mano_obra" min="0" step="0.01" class="corto" placeholder="0,00" value="<?= e((string) ($valores['mano_obra'] ?? '')) ?>">
  <p class="ayuda">Puedes dejarlo vacío y apuntar las horas de trabajo después, desde la ficha de la reparación.</p>

  <div class="botones">
    <button class="boton"><?= $id ? 'Guardar cambios' : 'Abrir la reparación' ?></button>
    <a class="boton secundario" href="<?= $id ? 'index.php?accion=ver&amp;id=' . $id : 'index.php' ?>">Cancelar</a>
  </div>
</form>

<?php require __DIR__ . '/pie.php'; ?>
