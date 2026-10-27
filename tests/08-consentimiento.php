<?php
declare(strict_types=1);

// Pruebas de la 0.5.0: consentimiento al subir, denuncias, ajustes legales, idiomas y orden de las comprobaciones de la subida.
require __DIR__ . '/aserciones.php';
$src = __DIR__ . '/../src/';
$svc = $src . 'component/backend/src/Service/';
require $svc . 'Consent.php';
require $svc . 'Reports.php';
require $svc . 'Settings.php';

use Chaska\Component\Mifoto\Administrator\Service\{Consent, Reports, Settings};

seccion('Consentimiento: las dos casillas, exactamente "1"');
afirma(Consent::accepted('1', '1') === true, 'las dos marcadas: vale');
foreach ([['1', ''], ['', '1'], ['1', '0'], ['0', '1'], ['on', 'on'], [true, true], [1, 1], [null, null], [['1'], ['1']], ['1', '1 '], ['11', '1']] as $i => [$a, $b]) {
	afirma(Consent::accepted($a, $b) === false, 'no vale la combinacion #' . $i . ' ' . json_encode([$a, $b]));
}
afirma(Consent::VERSION >= 1, 'el texto tiene numero de version (' . Consent::VERSION . ')');

seccion('Denuncias: motivo y comentario');
foreach (Reports::REASONS as $r) { afirma(Reports::cleanReason($r) === $r, 'motivo valido: ' . $r); }
foreach (['', 'spam', 'NOT_ME', ' not_me', null, 5, ['not_me'], "not_me\0"] as $bad) {
	afirma(Reports::cleanReason($bad) === null, 'motivo no valido: ' . addcslashes(json_encode($bad), "\0..\37"));
}
afirma(Reports::cleanComment("  hola\x00 \x07mundo\x1F  ") === 'hola mundo', 'el comentario pierde los caracteres de control (' . json_encode(Reports::cleanComment("  hola\x00 \x07mundo\x1F  ")) . ')');
afirma(mb_strlen(Reports::cleanComment(str_repeat('ñ', 900))) === Reports::MAX_COMMENT, 'como mucho ' . Reports::MAX_COMMENT . ' caracteres, sin partir letras');
afirma(Reports::cleanComment(['x']) === '' && Reports::cleanComment(null) === '', 'lo que no es texto da vacio');
afirma(str_contains(Reports::cleanComment("linea1\nlinea2"), "\n"), 'los saltos de linea normales se conservan');

seccion('Ajustes legales: direcciones y textos');
afirma(Settings::cleanUrl('https://miweb.es/privacidad') === 'https://miweb.es/privacidad', 'https vale');
afirma(Settings::cleanUrl('/politica-de-privacidad') === '/politica-de-privacidad', 'una ruta de este sitio vale');
foreach (['javascript:alert(1)', 'data:text/html,x', '//evil.example/x', 'ftp://x.es/a', 'miweb.es/p', 'https://miweb.es/a b', 'https://x.es/"onmouseover="x', "/ruta\nmal", '', '   '] as $bad) {
	afirma(Settings::cleanUrl($bad) === '', 'direccion rechazada: ' . addcslashes($bad, "\0..\37"));
}
afirma(Settings::cleanContact('privacidad@miweb.es') === 'privacidad@miweb.es', 'un correo vale como contacto');
afirma(Settings::cleanContact('https://miweb.es/contacto') === 'https://miweb.es/contacto', 'una web vale como contacto');
afirma(Settings::cleanContact('hola') === '' && Settings::cleanContact('javascript:x') === '' && Settings::cleanContact('a@b') === '', 'texto suelto, javascript: y correos rotos no valen');
$limpio = Settings::cleanText('<b>en tu perfil</b><script>x</script> y comentarios', 200);
afirma(!str_contains($limpio, '<') && !str_contains($limpio, '>') && str_starts_with($limpio, 'en tu perfil'), 'el texto de donde se vera pierde las etiquetas (' . $limpio . ')');
afirma(mb_strlen(Settings::cleanText(str_repeat('a', 500), 200)) === 200, 'y se corta a 200');

seccion('La subida exige el consentimiento antes de tocar nada');
$ph = (string) file_get_contents($svc . 'PhotoService.php');
$pConsent = strpos($ph, "throw new SanitizeException('consent_required')");
$pAttempt = strpos($ph, '$this->consumeUploadAttempt($ownerId)');
$pRecord  = strpos($ph, '$this->consents()->record(');
$pSave    = strpos($ph, '$this->saveRow(');
afirma($pConsent !== false && $pAttempt !== false && $pConsent < $pAttempt, 'sin consentimiento se rechaza ANTES de contar el intento y de leer el archivo');
afirma($pRecord !== false && $pSave !== false && $pRecord < $pSave, 'la prueba de aceptacion se guarda antes de mostrar la foto');
afirma(str_contains($ph, '$this->consents()->forget(') && str_contains($ph, '$this->consents()->withdraw($ownerId)'), 'si falla la subida se borra el registro; al quitar la foto se marca retirado');
afirma(str_contains($ph, '$this->consents()->deleteForUser($ownerId)') && str_contains($ph, '$this->reports()->deleteForUser($ownerId)'), 'al borrar la cuenta se borran aceptaciones y denuncias');
$ctl = (string) file_get_contents($src . 'component/frontend/src/Controller/PhotoController.php');
afirma(str_contains($ctl, "Consent::accepted(\$this->input->post->get('consent_owner'") && str_contains($ctl, "'consent_data'"), 'el controlador lee las dos casillas y las pasa a la subida');
afirma(str_contains($ctl, 'viewerMaySee($user, (int) $row->user_id'), 'solo se puede denunciar una foto que se puede ver (no se sonda quien tiene foto)');

seccion('El formulario de subida');
$w = (string) file_get_contents($svc . 'PhotoWidget.php');
afirma(str_contains($w, 'consent-owner') && str_contains($w, 'consent-data') && str_contains($w, 'COM_MIFOTO_W_NOTICE_COOKIES'), 'tiene las dos casillas y el aviso de cookies');
afirma(str_contains($w, 'Access::checkGroup(1,'), 'quien lo vera se calcula con los permisos reales, no con un texto');
$js = (string) file_get_contents($src . 'component/media/js/widget.js');
afirma(str_contains($js, '!consentGiven()') && str_contains($js, "fd.append('consent_owner', '1')"), 'el boton de elegir foto no abre nada sin las dos casillas y se envian con la foto');

seccion('Idiomas: mismas claves en es-ES y en-GB, y todo lo que usa el codigo existe');
$keys = static function (string $file): array {
	$out = [];
	foreach (file($file, FILE_IGNORE_NEW_LINES) as $l) { if (preg_match('/^([A-Z][A-Z_0-9]*)="/', $l, $m)) { $out[$m[1]] = true; } }
	return $out;
};
$pairs = [
	'com_mifoto backend'  => ['component/backend/language/es-ES/com_mifoto.ini', 'component/backend/language/en-GB/com_mifoto.ini'],
	'com_mifoto frontend' => ['component/frontend/language/es-ES/com_mifoto.ini', 'component/frontend/language/en-GB/com_mifoto.ini'],
	'privacy plugin'      => ['plugins/privacy/mifoto/language/es-ES/plg_privacy_mifoto.ini', 'plugins/privacy/mifoto/language/en-GB/plg_privacy_mifoto.ini'],
];
foreach ($pairs as $name => [$es, $en]) {
	$a = array_keys($keys($src . $es)); $b = array_keys($keys($src . $en));
	afirma($a !== [] && $a === array_values(array_intersect($a, $b)) && count($a) === count($b), $name . ': ' . count($a) . ' claves en cada idioma; sin pares: ' . implode(',', array_merge(array_diff($a, $b), array_diff($b, $a))));
}
$all = $keys($src . 'component/backend/language/es-ES/com_mifoto.ini') + $keys($src . 'component/frontend/language/es-ES/com_mifoto.ini') + $keys($src . 'component/backend/language/es-ES/com_mifoto.sys.ini');
$used = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src . 'component', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
	if ($f->isFile() && in_array($f->getExtension(), ['php', 'xml'], true)) {
		preg_match_all("/['\"(>]\\s*(COM_MIFOTO_[A-Z0-9_]+)\\b(?!['\"]?\\s*\\.)/", (string) file_get_contents($f->getPathname()), $m);
		foreach ($m[1] as $k) { $used[$k] = $f->getBasename(); }
	}
}
$missing = [];
foreach ($used as $k => $where) { if (!isset($all[$k]) && !str_ends_with($k, '_')) { $missing[] = $k . ' (' . $where . ')'; } }
afirma($missing === [], count($used) . ' claves usadas en el componente; faltan: ' . ($missing ? implode(', ', array_slice($missing, 0, 6)) : 'ninguna'));
foreach (['com_mifoto.ini', 'com_mifoto.sys.ini'] as $f) {
	foreach (['backend', 'frontend'] as $side) {
		$p = $src . "component/$side/language/en-GB/$f";
		if (!is_file($p)) { continue; }
		$bad = array_filter(file($p, FILE_IGNORE_NEW_LINES), static fn (string $l): bool => trim($l) !== '' && $l[0] !== ';' && !preg_match('/^[A-Z][A-Z_0-9]*="[^"]*"$/', $l));
		afirma($bad === [], "$side/$f: todas las lineas son CLAVE=\"texto\" sin comillas dentro");
	}
}

fin();
