'use strict';

// Pruebas de lightbox.js: que reconozca SOLO las fotos de MiFoto de este mismo sitio y saque bien la clave.
const assert = require('assert');
const path = require('path');
let ok = 0;
let fail = 0;

function check(cond, msg) {
	if (cond) { ok++; console.log('  ok    ' + msg); } else { fail++; console.log('  FALLA ' + msg); }
}

const listeners = [];
global.window = {
	location: { href: 'https://miweb.es/foro/tema', origin: 'https://miweb.es' },
	document: { addEventListener: (ev, fn) => listeners.push([ev, fn]), createElement: () => ({}), body: {} },
	Joomla: { getOptions: () => ({}) },
};

require(path.join(__dirname, '..', 'src', 'component', 'media', 'js', 'lightbox.js'));

const key = (src) => window.MiFotoLightbox.photoKey({ getAttribute: () => src });
const K = '0123456789abcdef';

console.log('\n== Reconoce fotos de MiFoto ==');
check(key('/index.php?option=com_mifoto&task=photo.show&u=' + K + '&s=64&v=aabbccdd') === K, 'ruta del propio sitio');
check(key('https://miweb.es/index.php?option=com_mifoto&task=photo.show&u=' + K + '&s=64') === K, 'direccion completa del propio sitio');
check(key('/sub/index.php?option=com_mifoto&s=64&task=photo.show&u=' + K) === K, 'los parametros en otro orden y sitio en subcarpeta');
check(listeners.some((l) => l[0] === 'click'), 'escucha clics en el documento (no hace falta tocar las otras extensiones)');

console.log('\n== No reconoce otra cosa ==');
check(key('https://otra.example/index.php?option=com_mifoto&task=photo.show&u=' + K) === '', 'una foto con el mismo formato en OTRO sitio no');
check(key('/index.php?option=com_mifoto&task=photo.remove&u=' + K) === '', 'otra tarea del componente no');
check(key('/index.php?option=com_content&task=photo.show&u=' + K) === '', 'aunque se llame igual, otro componente no es MiFoto');
check(key('/index.php?option=com_mifoto&task=photo.show&u=XYZ') === '', 'clave que no es de 16 hex no');
check(key('/index.php?option=com_mifoto&task=photo.show&u=' + K.toUpperCase()) === '', 'clave en mayusculas no (son minusculas)');
check(key('/index.php?option=com_mifoto&task=photo.show&u=' + K + '0') === '', 'clave de 17 caracteres no');
check(key('/img/avatar.png') === '' && key('') === '' && key('javascript:alert(1)') === '', 'imagenes normales y direcciones raras no');
check(key('data:image/png;base64,AAAA') === '', 'una imagen incrustada no');

console.log('\n' + ok + ' correctas, ' + fail + ' fallos');
process.exit(fail ? 1 : 0);
