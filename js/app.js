// Pequeñas mejoras de la interfaz. Todo funciona también sin JavaScript;
// esto solo lo hace más cómodo.

// Quita tildes y pasa a minúsculas, para buscar «leon» y encontrar «León»
function normalizar(texto) {
  return texto.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
}

// Buscador de desplegables:
//   <input type="search" data-filtra="vehiculo_id">  filtra el <select id="vehiculo_id">
// Oculta las opciones que no contienen lo escrito y, si la elegida queda oculta,
// elige la primera que coincida
document.querySelectorAll('[data-filtra]').forEach(function (buscador) {
  var select = document.getElementById(buscador.dataset.filtra);
  if (!select) return;

  buscador.addEventListener('input', function () {
    var texto = normalizar(buscador.value.trim());
    var primera = null;

    Array.from(select.options).forEach(function (opcion) {
      if (opcion.value === '') return; // «Elige…» siempre visible
      var coincide = normalizar(opcion.text).includes(texto);
      opcion.hidden = !coincide;
      if (coincide && !primera) primera = opcion;
    });

    var elegida = select.options[select.selectedIndex];
    if (texto && primera && (!elegida || elegida.hidden || elegida.value === '')) {
      select.value = primera.value;
    }
  });

  // Enter en el buscador no envía el formulario: pasa al desplegable
  buscador.addEventListener('keydown', function (evento) {
    if (evento.key === 'Enter') {
      evento.preventDefault();
      select.focus();
    }
  });
});

// Cuenta demo (solo lectura): los formularios que guardan algo se ven, pero sus
// botones no envían nada. Esto es solo visual: el servidor también los rechaza
if (document.body.hasAttribute('data-demo')) {
  document.querySelectorAll('form[method="post"]:not(.permitido-demo)').forEach(function (formulario) {
    formulario.classList.add('bloqueado-demo');
    formulario.removeAttribute('data-confirmar');
    formulario.querySelectorAll('button, input[type="submit"]').forEach(function (boton) {
      boton.setAttribute('aria-disabled', 'true');
      boton.title = 'No disponible en la cuenta demo';
    });
    formulario.addEventListener('submit', function (evento) {
      evento.preventDefault();
      avisoDemo(formulario);
    });
  });
}

function avisoDemo(formulario) {
  var aviso = document.getElementById('aviso-demo');
  if (!aviso) {
    aviso = document.createElement('p');
    aviso.id = 'aviso-demo';
    aviso.className = 'error';
    aviso.setAttribute('role', 'alert');
    aviso.textContent = 'La cuenta demo es de solo lectura: guardar, editar y borrar están desactivados.';
    var titulo = document.querySelector('main h1');
    titulo.insertAdjacentElement('afterend', aviso);
  }
  aviso.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Confirmación antes de acciones que no se pueden deshacer:
//   <form data-confirmar="¿Seguro?">
document.querySelectorAll('form[data-confirmar]').forEach(function (formulario) {
  formulario.addEventListener('submit', function (evento) {
    if (!window.confirm(formulario.dataset.confirmar)) {
      evento.preventDefault();
    }
  });
});

// Desplegables que envían su formulario al cambiar (filtros del listado)
document.querySelectorAll('select[data-auto]').forEach(function (select) {
  select.addEventListener('change', function () {
    select.form.submit();
  });
});

// Ventana emergente de las gráficas: elementos con data-tip (lista JSON de
// [nombre, valor, clase del color]) y data-tip-titulo. Sale al pasar el ratón
// y también al llegar con el teclado (Tab). Los textos se insertan con
// textContent, nunca como HTML
(function () {
  var tip = null;

  function mostrar(elemento, x, y) {
    var filas;
    try { filas = JSON.parse(elemento.dataset.tip); } catch (e) { return; }
    if (!tip) {
      tip = document.createElement('div');
      tip.className = 'tip';
      tip.setAttribute('role', 'tooltip');
      document.body.appendChild(tip);
    }
    tip.replaceChildren();
    var titulo = document.createElement('div');
    titulo.className = 'tip-titulo';
    titulo.textContent = elemento.dataset.tipTitulo || '';
    tip.appendChild(titulo);
    filas.forEach(function (fila) {
      var linea = document.createElement('div');
      linea.className = 'tip-fila';
      var clave = document.createElement('span');
      clave.className = 'tip-clave ' + (fila[2] || 'vacia');
      var valor = document.createElement('strong');
      valor.textContent = fila[1];   // el valor, delante y destacado
      var nombre = document.createElement('span');
      nombre.textContent = fila[0];
      linea.append(clave, valor, nombre);
      tip.appendChild(linea);
    });
    tip.hidden = false;
    colocar(x, y);
  }

  function colocar(x, y) {
    var ancho = tip.offsetWidth, alto = tip.offsetHeight, margen = 14;
    var izquierda = x + margen + ancho > window.innerWidth ? x - margen - ancho : x + margen;
    var arriba = Math.max(8, Math.min(y - alto / 2, window.innerHeight - alto - 8));
    tip.style.left = Math.max(8, izquierda) + 'px';
    tip.style.top = arriba + 'px';
  }

  function ocultar() { if (tip) tip.hidden = true; }

  document.querySelectorAll('[data-tip]').forEach(function (elemento) {
    elemento.addEventListener('pointermove', function (evento) { mostrar(elemento, evento.clientX, evento.clientY); });
    elemento.addEventListener('pointerleave', ocultar);
    elemento.addEventListener('focus', function () {
      var caja = elemento.getBoundingClientRect();
      mostrar(elemento, caja.right, caja.top + caja.height / 2);
    });
    elemento.addEventListener('blur', ocultar);
  });
  window.addEventListener('scroll', ocultar, { passive: true });
})();
