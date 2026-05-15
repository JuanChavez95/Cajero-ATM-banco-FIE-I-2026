/**
 * voz.js — Módulo de accesibilidad por voz para cajero ATM
 * Usa Web Speech API nativa del navegador (sin dependencias externas)
 *
 * Funciones exportadas al scope global:
 *   hablar(texto, opciones)         → Text-to-Speech
 *   iniciarReconocimiento(config)   → Speech Recognition
 *   detenerReconocimiento()         → Detiene el micrófono
 *   iniciarVozPagina(titulo, desc)  → Lee automáticamente al cargar la página
 *   crearBotonMicrofono(config)     → Inserta el botón flotante de micrófono
 *   obtenerIdioma()                 → Lee el idioma desde sessionStorage
 */

(function (global) {
  'use strict';

  // ─────────────────────────────────────────────
  // 1. CONFIGURACIÓN DE IDIOMAS
  // ─────────────────────────────────────────────

  /**
   * Mapeo de códigos internos del cajero a códigos BCP-47
   * para la Web Speech API.
   */
  const IDIOMAS_BCP47 = {
    es: 'es-BO',   // Español (Bolivia)
    en: 'en-US',   // Inglés
    ay: 'es-BO',   // Aymara → fallback español (no hay soporte nativo aún)
  };

  /**
   * Mensajes de estado del micrófono según idioma.
   */
  const MENSAJES_MIC = {
    es: {
      activado:    'Micrófono activado. Diga un comando.',
      desactivado: 'Micrófono desactivado.',
      noSoportado: 'Su navegador no soporta reconocimiento de voz. Use Chrome o Edge.',
      escuchando:  'Escuchando…',
      error:       'No se entendió el comando. Intente de nuevo.',
    },
    en: {
      activado:    'Microphone on. Say a command.',
      desactivado: 'Microphone off.',
      noSoportado: 'Your browser does not support voice recognition. Use Chrome or Edge.',
      escuchando:  'Listening…',
      error:       'Command not understood. Please try again.',
    },
    ay: {
      activado:    'Micrófono activado. Diga un comando.',
      desactivado: 'Micrófono desactivado.',
      noSoportado: 'Su navegador no soporta reconocimiento de voz.',
      escuchando:  'Escuchando…',
      error:       'No se entendió el comando.',
    },
  };

  // ─────────────────────────────────────────────
  // 2. UTILIDADES INTERNAS
  // ─────────────────────────────────────────────

  /**
   * Obtiene el idioma activo guardado en sessionStorage.
   * Claves posibles: 'idioma', 'lang', 'language'
   * @returns {string} Código corto: 'es' | 'en' | 'ay'
   */
  function obtenerIdioma() {
    const raw = (
      sessionStorage.getItem('idioma') ||
      sessionStorage.getItem('lang') ||
      sessionStorage.getItem('language') ||
      'es'
    ).toLowerCase().trim();

    // Normaliza variantes como 'es-bo', 'spanish', etc.
    if (raw.startsWith('en')) return 'en';
    if (raw.startsWith('ay') || raw === 'aymara') return 'ay';
    return 'es';
  }

  /**
   * Devuelve el código BCP-47 para la Web Speech API.
   * @returns {string}
   */
  function obtenerCodigoBCP47() {
    return IDIOMAS_BCP47[obtenerIdioma()] || 'es-BO';
  }

  /**
   * Devuelve los mensajes de UI en el idioma activo.
   * @returns {object}
   */
  function obtenerMensajes() {
    return MENSAJES_MIC[obtenerIdioma()] || MENSAJES_MIC.es;
  }

  // ─────────────────────────────────────────────
  // 3. TEXT-TO-SPEECH (el cajero habla)
  // ─────────────────────────────────────────────

  /** Referencia a la utterance activa para poder cancelarla */
  let utteranceActiva = null;

  /**
   * Lee un texto en voz alta usando SpeechSynthesis.
   *
   * @param {string} texto           - Texto a leer
   * @param {object} [opciones={}]
   * @param {number} [opciones.velocidad=0.9]    - Rate: 0.1–10
   * @param {number} [opciones.volumen=1]        - Volume: 0–1
   * @param {number} [opciones.tono=1]           - Pitch: 0–2
   * @param {string} [opciones.idioma]           - Fuerza un idioma BCP-47
   * @param {Function} [opciones.alTerminar]     - Callback al finalizar
   * @param {Function} [opciones.alError]        - Callback en error
   * @returns {SpeechSynthesisUtterance|null}
   */
  function hablar(texto, opciones) {
    if (!('speechSynthesis' in window)) {
      console.warn('[voz.js] SpeechSynthesis no soportado en este navegador.');
      return null;
    }

    // Cancela cualquier lectura en curso
    window.speechSynthesis.cancel();

    if (!texto || texto.trim() === '') return null;

    const config = opciones || {};
    const utt = new SpeechSynthesisUtterance(texto.trim());

    utt.lang   = config.idioma    || obtenerCodigoBCP47();
    utt.rate   = config.velocidad !== undefined ? config.velocidad : 0.9;
    utt.volume = config.volumen   !== undefined ? config.volumen   : 1;
    utt.pitch  = config.tono      !== undefined ? config.tono      : 1;

    if (typeof config.alTerminar === 'function') {
      utt.onend = config.alTerminar;
    }
    if (typeof config.alError === 'function') {
      utt.onerror = function (e) { config.alError(e); };
    }

    utteranceActiva = utt;
    window.speechSynthesis.speak(utt);
    return utt;
  }

  /**
   * Detiene inmediatamente cualquier síntesis en curso.
   */
  function detenerHabla() {
    if ('speechSynthesis' in window) {
      window.speechSynthesis.cancel();
    }
    utteranceActiva = null;
  }

  /**
   * Lee el título y descripción de la página actual al cargarla.
   * Llama a esta función al inicio de cada página del cajero.
   *
   * @param {string} titulo      - Título de la pantalla
   * @param {string} [descripcion] - Instrucciones adicionales (opcional)
   * @param {object} [opciones]  - Opciones pasadas a hablar()
   */
  function iniciarVozPagina(titulo, descripcion, opciones) {
    // Pequeño delay para que el DOM esté listo
    setTimeout(function () {
      const textoCompleto = descripcion
        ? titulo + '. ' + descripcion
        : titulo;
      hablar(textoCompleto, opciones);
    }, 400);
  }

  // ─────────────────────────────────────────────
  // 4. RECONOCIMIENTO DE VOZ (el cajero escucha)
  // ─────────────────────────────────────────────

  /** Instancia global de SpeechRecognition */
  let recognition = null;
  /** Estado del micrófono */
  let micActivo = false;
  /** Referencia al botón de micrófono en el DOM */
  let botonMic = null;

  /**
   * Verifica si el navegador soporta SpeechRecognition.
   * @returns {boolean}
   */
  function soportaReconocimiento() {
    return !!(window.SpeechRecognition || window.webkitSpeechRecognition);
  }

  /**
   * Inicia el reconocimiento de voz con los comandos definidos.
   *
   * @param {object} config
   * @param {Array<{palabras: string[], accion: Function}>} config.comandos
   *   - palabras: array de palabras/frases que activan la acción
   *   - accion:   función a ejecutar cuando se reconoce el comando
   * @param {boolean} [config.continuo=false]   - Si true, sigue escuchando después de cada resultado
   * @param {Function} [config.alEscuchar]      - Callback cuando empieza a escuchar
   * @param {Function} [config.alDetener]       - Callback cuando se detiene
   * @param {Function} [config.alError]         - Callback en error
   * @param {Function} [config.alResultado]     - Callback con el texto reconocido (raw)
   * @returns {boolean} true si se inició correctamente
   */
  function iniciarReconocimiento(config) {
    if (!soportaReconocimiento()) {
      const msg = obtenerMensajes().noSoportado;
      hablar(msg);
      console.warn('[voz.js]', msg);
      return false;
    }

    if (micActivo) {
      detenerReconocimiento();
      return false; // Toggle: si ya estaba activo, lo apaga
    }

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    recognition = new SpeechRecognition();

    recognition.lang       = obtenerCodigoBCP47();
    recognition.continuous = !!(config && config.continuo);
    recognition.interimResults = false;
    recognition.maxAlternatives = 3;

    // ── Eventos del reconocimiento ──

    recognition.onstart = function () {
      micActivo = true;
      actualizarBotonMic(true);
      if (config && typeof config.alEscuchar === 'function') {
        config.alEscuchar();
      }
    };

    recognition.onend = function () {
      micActivo = false;
      actualizarBotonMic(false);
      if (config && typeof config.alDetener === 'function') {
        config.alDetener();
      }
    };

    recognition.onerror = function (evento) {
      micActivo = false;
      actualizarBotonMic(false);
      if (config && typeof config.alError === 'function') {
        config.alError(evento);
      } else {
        // Errores silenciosos esperados (el usuario no habló)
        const erroresSilenciosos = ['no-speech', 'aborted'];
        if (!erroresSilenciosos.includes(evento.error)) {
          console.warn('[voz.js] Error de reconocimiento:', evento.error);
        }
      }
    };

    recognition.onresult = function (evento) {
      // Recopila todas las alternativas de todos los resultados
      const textosBrutos = [];
      for (let i = evento.resultIndex; i < evento.results.length; i++) {
        const resultado = evento.results[i];
        for (let j = 0; j < resultado.length; j++) {
          textosBrutos.push(resultado[j].transcript.toLowerCase().trim());
        }
      }

      if (config && typeof config.alResultado === 'function') {
        config.alResultado(textosBrutos[0] || '');
      }

      // Busca coincidencia con los comandos definidos
      if (config && Array.isArray(config.comandos)) {
        let comandoEjecutado = false;

        for (const cmd of config.comandos) {
          if (!Array.isArray(cmd.palabras) || typeof cmd.accion !== 'function') continue;

          const coincide = textosBrutos.some(function (texto) {
            return cmd.palabras.some(function (palabra) {
              return texto.includes(palabra.toLowerCase());
            });
          });

          if (coincide) {
            detenerHabla();
            cmd.accion();
            comandoEjecutado = true;
            break;
          }
        }

        if (!comandoEjecutado) {
          // Ningún comando reconocido
          hablar(obtenerMensajes().error);
        }
      }
    };

    recognition.start();
    return true;
  }

  /**
   * Detiene el reconocimiento de voz.
   */
  function detenerReconocimiento() {
    if (recognition) {
      try { recognition.stop(); } catch (e) { /* ya estaba detenido */ }
      recognition = null;
    }
    micActivo = false;
    actualizarBotonMic(false);
  }

  // ─────────────────────────────────────────────
  // 5. BOTÓN DE MICRÓFONO
  // ─────────────────────────────────────────────

  /**
   * Actualiza el estado visual del botón de micrófono.
   * @param {boolean} activo
   */
  function actualizarBotonMic(activo) {
    if (!botonMic) return;

    if (activo) {
      botonMic.classList.add('voz-mic--activo');
      botonMic.setAttribute('aria-label', obtenerMensajes().escuchando);
      botonMic.setAttribute('aria-pressed', 'true');
      botonMic.title = obtenerMensajes().escuchando;
    } else {
      botonMic.classList.remove('voz-mic--activo');
      botonMic.setAttribute('aria-label', obtenerMensajes().activado);
      botonMic.setAttribute('aria-pressed', 'false');
      botonMic.title = obtenerMensajes().activado;
    }
  }

  /**
   * Crea e inserta un botón flotante de micrófono en el DOM.
   *
   * @param {object} [config={}]
   * @param {string} [config.posicion='bottom-right'] - 'bottom-right' | 'bottom-left' | 'top-right' | 'top-left'
   * @param {string} [config.selector]               - Selector CSS del contenedor (default: body)
   * @param {Array}  [config.comandos]               - Comandos para iniciarReconocimiento()
   * @param {boolean}[config.continuo=false]         - Reconocimiento continuo
   * @param {Function}[config.alEscuchar]
   * @param {Function}[config.alDetener]
   * @param {Function}[config.alError]
   * @param {Function}[config.alResultado]
   * @returns {HTMLButtonElement}
   */
  function crearBotonMicrofono(config) {
    config = config || {};

    // Estilos inyectados una sola vez
    if (!document.getElementById('voz-estilos')) {
      const style = document.createElement('style');
      style.id = 'voz-estilos';
      style.textContent = [
        '.voz-mic-btn {',
        '  position: fixed;',
        '  z-index: 9999;',
        '  width: 56px;',
        '  height: 56px;',
        '  border-radius: 50%;',
        '  border: none;',
        '  cursor: pointer;',
        '  background: #1a4fa0;',
        '  color: #fff;',
        '  box-shadow: 0 4px 16px rgba(0,0,0,0.35);',
        '  display: flex;',
        '  align-items: center;',
        '  justify-content: center;',
        '  transition: background 0.2s, transform 0.15s, box-shadow 0.2s;',
        '  outline: none;',
        '  font-size: 24px;',
        '}',
        '.voz-mic-btn:focus-visible {',
        '  outline: 3px solid #f0c040;',
        '  outline-offset: 3px;',
        '}',
        '.voz-mic-btn:hover {',
        '  background: #1560c8;',
        '  transform: scale(1.07);',
        '}',
        '.voz-mic-btn.voz-mic--activo {',
        '  background: #c0392b;',
        '  box-shadow: 0 0 0 6px rgba(192,57,43,0.25), 0 4px 16px rgba(0,0,0,0.35);',
        '  animation: voz-pulso 1.2s infinite;',
        '}',
        '@keyframes voz-pulso {',
        '  0%   { box-shadow: 0 0 0 0 rgba(192,57,43,0.5), 0 4px 16px rgba(0,0,0,0.35); }',
        '  70%  { box-shadow: 0 0 0 14px rgba(192,57,43,0), 0 4px 16px rgba(0,0,0,0.35); }',
        '  100% { box-shadow: 0 0 0 0 rgba(192,57,43,0), 0 4px 16px rgba(0,0,0,0.35); }',
        '}',
      ].join('\n');
      document.head.appendChild(style);
    }

    botonMic = document.createElement('button');
    botonMic.id = 'voz-mic-btn';
    botonMic.className = 'voz-mic-btn';
    botonMic.setAttribute('role', 'button');
    botonMic.setAttribute('aria-pressed', 'false');
    botonMic.setAttribute('aria-label', obtenerMensajes().activado);
    botonMic.title = obtenerMensajes().activado;

    // Ícono SVG de micrófono (sin dependencias externas)
    botonMic.innerHTML = [
      '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24"',
      '  fill="none" stroke="currentColor" stroke-width="2"',
      '  stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">',
      '  <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>',
      '  <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>',
      '  <line x1="12" y1="19" x2="12" y2="23"/>',
      '  <line x1="8" y1="23" x2="16" y2="23"/>',
      '</svg>',
    ].join('');

    // Posicionamiento
    const pos = config.posicion || 'bottom-right';
    const offsetPx = '24px';
    if (pos.includes('bottom')) botonMic.style.bottom = offsetPx;
    if (pos.includes('top'))    botonMic.style.top    = offsetPx;
    if (pos.includes('right'))  botonMic.style.right  = offsetPx;
    if (pos.includes('left'))   botonMic.style.left   = offsetPx;

    // Si no tiene soporte, deshabilita el botón
    if (!soportaReconocimiento()) {
      botonMic.disabled = true;
      botonMic.title = obtenerMensajes().noSoportado;
      botonMic.setAttribute('aria-disabled', 'true');
      botonMic.style.opacity = '0.4';
      botonMic.style.cursor  = 'not-allowed';
    } else {
      botonMic.addEventListener('click', function () {
        if (micActivo) {
          detenerReconocimiento();
          hablar(obtenerMensajes().desactivado);
        } else {
          hablar(obtenerMensajes().activado, {
            alTerminar: function () {
              iniciarReconocimiento({
                comandos:   config.comandos   || [],
                continuo:   config.continuo   || false,
                alEscuchar: config.alEscuchar || null,
                alDetener:  config.alDetener  || null,
                alError:    config.alError    || null,
                alResultado: config.alResultado || null,
              });
            },
          });
        }
      });
    }

    const contenedor = config.selector
      ? (document.querySelector(config.selector) || document.body)
      : document.body;

    contenedor.appendChild(botonMic);
    return botonMic;
  }

  // ─────────────────────────────────────────────
  // 6. COMANDOS PREDETERMINADOS DEL MENÚ PRINCIPAL
  // ─────────────────────────────────────────────

  /**
   * Devuelve el array de comandos estándar del cajero.
   * Cada entrada tiene `palabras` (disparadores) y `accion` (navegación).
   *
   * Uso típico en menu.js:
   *   crearBotonMicrofono({ comandos: obtenerComandosMenu() });
   *
   * @returns {Array<{palabras: string[], accion: Function}>}
   */
  function obtenerComandosMenu() {
    return [
      {
        palabras: ['retirar', 'retiro', 'withdraw', 'sacar dinero'],
        accion: function () { location.href = 'retiro.html'; },
      },
      {
        palabras: ['depositar', 'depósito', 'deposito', 'deposit'],
        accion: function () { location.href = 'deposito.html'; },
      },
      {
        palabras: ['consulta', 'saldo', 'balance', 'cuánto tengo'],
        accion: function () { location.href = 'consulta.html'; },
      },
      {
        palabras: ['transferir', 'transferencia', 'transfer', 'enviar dinero'],
        accion: function () { location.href = 'transferencia.html'; },
      },
      {
        palabras: ['movimientos', 'historial', 'history', 'transactions'],
        accion: function () { location.href = 'movimientos.html'; },
      },
      {
        palabras: ['salir', 'cerrar sesión', 'logout', 'exit', 'terminar'],
        accion: function () {
          sessionStorage.clear();
          location.href = 'index.html';
        },
      },
    ];
  }

  // ─────────────────────────────────────────────
  // 7. EXPOSICIÓN AL SCOPE GLOBAL
  // ─────────────────────────────────────────────

  global.Voz = {
    hablar:               hablar,
    detenerHabla:         detenerHabla,
    iniciarVozPagina:     iniciarVozPagina,
    iniciarReconocimiento: iniciarReconocimiento,
    detenerReconocimiento: detenerReconocimiento,
    crearBotonMicrofono:  crearBotonMicrofono,
    obtenerIdioma:        obtenerIdioma,
    obtenerComandosMenu:  obtenerComandosMenu,
    soportaReconocimiento: soportaReconocimiento,
  };

}(window));