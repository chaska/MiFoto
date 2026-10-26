<?php
declare(strict_types=1);
require __DIR__ . '/aserciones.php';
// Settings and PhotoService need Joomla classes; only the pure parts are loaded here.
define('JPATH_ROOT', sys_get_temp_dir());
$dir = __DIR__ . '/../src/component/backend/src/Service/';
require $dir . 'AccessPolicy.php';
require $dir . 'Placeholder.php';

use Chaska\Component\Mifoto\Administrator\Service\{AccessPolicy, Placeholder};

seccion('Quien puede ver: dueno, moderador, permiso y estado');
afirma(AccessPolicy::mayView(true, false, false, 1), 'el dueno ve su foto aunque no tenga permiso de ver');
afirma(AccessPolicy::mayView(true, false, false, 0), 'el dueno ve su foto aunque este oculta');
afirma(AccessPolicy::mayView(false, false, true, 0), 'el moderador ve hasta las ocultas');
afirma(AccessPolicy::mayView(false, true, false, 1), 'otro usuario con permiso ve una foto visible');
afirma(!AccessPolicy::mayView(false, true, false, 0), 'otro usuario con permiso NO ve una foto oculta');
afirma(!AccessPolicy::mayView(false, false, false, 1), 'sin permiso de ver no se ve la de otros');
afirma(!AccessPolicy::mayView(false, false, false, 0), 'sin permiso y oculta: no se ve');

seccion('Subir, quitar, moderar');
afirma(AccessPolicy::mayUpload(true, false, true), 'el dueno con permiso sube');
afirma(!AccessPolicy::mayUpload(true, false, false), 'el dueno sin permiso no sube');
afirma(!AccessPolicy::mayUpload(true, true, true), 'un invitado no sube, tenga lo que tenga');
afirma(!AccessPolicy::mayUpload(false, false, true), 'nadie sube por otra persona (ni siquiera con permiso)');
afirma(AccessPolicy::mayRemove(true, false, false), 'el dueno quita su foto aunque haya perdido el permiso de subir');
afirma(!AccessPolicy::mayRemove(true, true, false), 'un invitado no quita nada');
afirma(AccessPolicy::mayRemove(false, false, true), 'el moderador quita la de otros');
afirma(!AccessPolicy::mayRemove(false, false, false), 'un usuario normal no quita la de otros');
afirma(AccessPolicy::mayModerate(true) && !AccessPolicy::mayModerate(false), 'solo el moderador modera');

seccion('Iniciales y color de respaldo');
afirma(Placeholder::initials('Marta López') === 'ML', 'dos palabras -> ML');
afirma(Placeholder::initials('chas') === 'C', 'una palabra -> C');
afirma(Placeholder::initials('  ana   maría   de la cruz ') === 'AM', 'solo las dos primeras palabras, con espacios de mas');
afirma(Placeholder::initials('Álvaro Ñúñez') === 'ÁÑ', 'acentos y eñes en mayúscula');
afirma(Placeholder::initials('') === '?' && Placeholder::initials('   ') === '?' && Placeholder::initials('<>!') === '?', 'vacío o solo símbolos -> ?');
afirma(Placeholder::initials('<script>x</script> Pérez') === 'SP', 'las etiquetas pierden los símbolos y no pasan intactas');
afirma(Placeholder::color('marta') === Placeholder::color('marta'), 'el color es estable para la misma semilla');
afirma(preg_match('/^#[0-9A-F]{6}$/D', Placeholder::color('x')) === 1, 'el color es un hex seguro de 6 cifras');
$colors = []; foreach (range(1, 200) as $i) { $colors[Placeholder::color('usuario' . $i)] = true; }
afirma(count($colors) >= 6, 'los colores se reparten (' . count($colors) . ' de 8 en 200 usuarios)');

seccion('Carpeta de almacenamiento: solo dentro de la web y con caracteres seguros');
// Settings::cleanPath es estatica, pero la clase importa clases de Joomla; se comprueba que la regla sigue en el codigo y se prueba una copia.
$src = (string) file_get_contents($dir . 'Settings.php');
afirma(str_contains($src, "'~^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*\$~D'"), 'la regla de ruta segura sigue en Settings tal como se prueba aqui');
$rule = static function (string $path): string {
	$path = trim(str_replace('\\', '/', $path), '/');
	return ($path === '' || !preg_match('~^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$~D', $path)) ? 'images/MiFoto' : $path;
};
afirma($rule('images/MiFoto') === 'images/MiFoto', 'la ruta por defecto vale');
afirma($rule('/images/fotos/') === 'images/fotos', 'barras sobrantes se quitan');
foreach (['../etc', 'images/../..', 'C:/x', 'php://temp', 'a b', "a\0b", '', '/', '..', 'images//x', 'images/x.php', '%2e%2e/x'] as $bad) {
	afirma($rule($bad) === 'images/MiFoto', 'ruta hostil [' . addcslashes($bad, "\0..\37") . '] vuelve a la de por defecto');
}

fin();
