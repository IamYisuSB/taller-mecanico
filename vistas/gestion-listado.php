<?php
// Listado de una entidad de gestión (clientes, vehículos, mecánicos o piezas)
/** @var string $titulo */
/** @var string $entidad */
/** @var array  $config      La descripción de la entidad en GestionModelo::ENTIDADES */
/** @var array  $filas */
/** @var string $busqueda */
/** @var array  $relaciones  [columna => [id => texto]] */
/** @var string $aviso */
/** @var string $error */
require __DIR__ . '/cabecera.php';
?>

<?php if ($aviso): ?>
  <p class="aviso"><?= e($aviso) ?></p>
<?php endif; ?>
<?php if ($error): ?>
  <p class="error"><?= e($error) ?></p>
<?php endif; ?>

<div class="barra">
  <form method="get" action="index.php" class="buscador">
    <input type="hidden" name="accion" value="gestion">
    <input type="hidden" name="tipo" value="<?= e($entidad) ?>">
    <input type="search" name="q" value="<?= e($busqueda) ?>" aria-label="Buscar"
           placeholder="Buscar <?= e(mb_strtolower($config['titulo'])) ?>…">
    <button class="boton secundario">Buscar</button>
    <?php if ($busqueda !== ''): ?>
      <a class="boton secundario" href="index.php?accion=gestion&amp;tipo=<?= e($entidad) ?>">Limpiar</a>
    <?php endif; ?>
  </form>
  <a class="boton" href="index.php?accion=gestion-editar&amp;tipo=<?= e($entidad) ?>">+ Nuevo <?= e($config['singular']) ?></a>
</div>

<p class="contador"><?= count($filas) ?> resultado<?= count($filas) === 1 ? '' : 's' ?></p>

<?php if (!$filas): ?>
  <p class="vacio"><?= $busqueda !== '' ? 'No hay resultados para «' . e($busqueda) . '».' : 'Todavía no hay datos.' ?></p>
<?php else: ?>
  <div class="tabla-envoltorio tarjeta">
    <table>
      <thead>
        <tr>
          <?php foreach ($config['campos'] as $campo): ?>
            <th <?= $campo['tipo'] === 'dinero' ? 'class="num"' : '' ?>><?= e($campo['etiqueta']) ?></th>
          <?php endforeach; ?>
          <th class="acciones-col"><span class="oculto">Acciones</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($filas as $fila): ?>
          <tr>
            <?php foreach ($config['campos'] as $columna => $campo): ?>
              <?php $valor = $fila[$columna]; ?>
              <?php if ($campo['tipo'] === 'dinero'): ?>
                <td class="num"><?= euros($valor) ?></td>
              <?php elseif ($campo['tipo'] === 'relacion'): ?>
                <td><?= e($relaciones[$columna][$valor] ?? '—') ?></td>
              <?php elseif ($columna === 'matricula'): ?>
                <td><span class="matricula"><?= e($valor) ?></span></td>
              <?php elseif ($campo['tipo'] === 'correo'): ?>
                <td><a href="mailto:<?= e($valor) ?>"><?= e($valor) ?></a></td>
              <?php else: ?>
                <td><?= e($valor === null ? '—' : (string) $valor) ?></td>
              <?php endif; ?>
            <?php endforeach; ?>
            <td class="acciones">
              <a class="boton pequeno secundario" href="index.php?accion=gestion-editar&amp;tipo=<?= e($entidad) ?>&amp;id=<?= $fila['id'] ?>">Editar</a>
              <form method="post" action="index.php?accion=gestion-borrar"
                    data-confirmar="¿Borrar este <?= e($config['singular']) ?>? No se puede deshacer.">
                <input type="hidden" name="tipo" value="<?= e($entidad) ?>">
                <input type="hidden" name="id" value="<?= $fila['id'] ?>">
                <button class="boton pequeno peligro">Borrar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/pie.php'; ?>
