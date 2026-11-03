<?php
declare(strict_types=1);

// Colaboracion con idiomas: build/idiomas.php (comprobar / plantilla / manifiestos) y el build, con un idioma de prueba (limpio, con <script>,
// con una direccion externa...) en COPIAS temporales de src/ (nunca se toca el src/ real), y las guias y plantillas de GitHub.

require __DIR__ . '/aserciones.php';
$root = dirname(__DIR__);
require_once "$root/build/idiomas.php";
$leer = static fn (string $r): string => (string) file_get_contents("$root/$r");

seccion('es-ES y en-GB pasan la herramienta');
$r = idiomasComprobar("$root/src", 'todos');
afirma(!$r['errores'], 'comprobar todos: sin errores' . ($r['errores'] ? ' (' . implode(' | ', array_slice($r['errores'], 0, 3)) . ')' : ''));
afirma(($r['resumen']['es-ES']['textos'] ?? 0) > 300, 'es-ES: mas de 300 textos comparados (' . ($r['resumen']['es-ES']['textos'] ?? 0) . ')');
afirma(array_keys($r['resumen']) === ['es-ES'], 'idiomas detectados: solo es-ES (ademas de en-GB)');
afirma(count(idiomasExtensiones("$root/src")) === 7, 'se comparan las 7 carpetas language/ (componente: administracion y sitio; modulo; 4 plugins)');

seccion('Reglas de un texto traducido');
$t = static fn (string $o, string $x): array => idiomasTexto($o, $x);
afirma($t('Hello %s, you have %d new', 'Hola %s, tienes %d nuevas') === [], 'marcadores iguales: correcto');
afirma($t('Hello %1$s and %2$s', 'Con %2$s y %1$s') === [], 'marcadores numerados en otro orden: correcto');
afirma($t('Hello %s', 'Hola') !== [] && $t('Hello %s', 'Hola %s %s') !== [] && $t('Hello %s', 'Hola %d') !== [], 'marcadores que faltan, sobran o cambian: error');
afirma($t('Hi', '<script>alert(1)</script>') !== [] && $t('Hi', '<img src=x onerror=alert(1)>') !== [], '<script> y etiquetas nuevas con on*=: error');
afirma($t('Hi', 'ver https://evil.example/x') !== [] && $t('Hi', 'ver www.evil.example') !== [], 'direcciones externas nuevas: error');
afirma($t('Hi', 'java&#115;cript:alert(1)') !== [] && $t('Hi', 'a data:text/html,x') !== [], 'javascript:/data: (tambien con entidades): error');
afirma($t('Hello', '') !== [] && $t('Hello', 'Hola "x" y') !== [] && $t('Hello', 'Hola \\"x\\" y') === [], 'texto vacio y comillas sin escapar: error; comillas escapadas: correcto');

seccion('Idioma de prueba en una COPIA: plantilla, comprobar y build');
$tmp = sys_get_temp_dir() . '/mifoto-idiomas-' . bin2hex(random_bytes(4));
mkdir($tmp);
$copiar = static function (string $de, string $a) use (&$copiar): void {
	mkdir($a, 0755, true);
	foreach (scandir($de) as $n) {
		if ($n === '.' || $n === '..') { continue; }
		is_dir("$de/$n") ? $copiar("$de/$n", "$a/$n") : copy("$de/$n", "$a/$n");
	}
};
$borrar = static function (string $d) use (&$borrar): void {
	foreach (scandir($d) as $n) {
		if ($n === '.' || $n === '..') { continue; }
		is_dir("$d/$n") ? $borrar("$d/$n") : unlink("$d/$n");
	}
	rmdir($d);
};
$copia = static function () use ($tmp, $root, $copiar): string { $d = $tmp . '/src-' . bin2hex(random_bytes(3)); $copiar("$root/src", $d); return $d; };
$correr = static function (string $cmd, array $env): array {
	foreach ($env as $k => $v) { putenv("$k=$v"); }
	$o = [];
	exec($cmd . ' 2>&1', $o, $rc);
	foreach ($env as $k => $v) { putenv($k); }
	return [$rc, implode("\n", $o)];
};
$cli = static fn (string $src, string $args): array => $correr(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$root/build/idiomas.php") . " $args", ['MIFOTO_BUILD_SRC' => $src]);
$python = stripos(PHP_OS, 'WIN') === 0 ? 'python' : 'python3';
$build = static function (string $src) use ($correr, $root, $tmp, $python): array {
	$dist = $tmp . '/dist-' . bin2hex(random_bytes(3));
	[$rc, $out] = $correr($python . ' ' . escapeshellarg("$root/build/build.py"), ['MIFOTO_BUILD_SRC' => $src, 'MIFOTO_BUILD_DIST' => $dist, 'MIFOTO_PHP' => PHP_BINARY]);
	return [$rc, $out, glob("$dist/pkg_mifoto_*.zip") ?: []];
};
$editar = static function (string $f, callable $fn): void { file_put_contents($f, $fn((string) file_get_contents($f))); };

$s = $copia();
[$rc, $out] = $cli($s, 'plantilla xx-XX');
$nEn = 0;
foreach (idiomasExtensiones($s) as $d) { $nEn += count(glob("$d/en-GB/*.ini")); }
afirma($rc === 0 && preg_match('/Creado xx-XX: (\d+) archivos/', $out, $m) === 1 && (int) $m[1] === $nEn, "plantilla xx-XX: copia en-GB en todas las extensiones ($nEn archivos)");
[$rc] = $cli($s, 'plantilla xx-XX');
[$rc2] = $cli($s, 'plantilla en-GB');
[$rc3] = $cli($s, 'plantilla ../x');
afirma($rc !== 0 && $rc2 !== 0 && $rc3 !== 0, 'repetir un idioma o etiquetas no validas (en-GB, ../x): error, no pisa nada');
$sin = [];
foreach (array_merge(glob("$s/component/*.xml"), glob("$s/modules/*/*.xml"), glob("$s/plugins/*/*/*.xml")) as $x) {
	$c = (string) file_get_contents($x);
	if (str_contains($c, '<languages') && !str_contains($c, 'tag="xx-XX"')) { $sin[] = basename(dirname($x)) . '/' . basename($x); }
	if (@simplexml_load_string($c) === false) { $sin[] = basename($x) . ' (XML roto)'; }
}
afirma($sin === [], 'los 6 manifiestos siguen siendo XML valido y listan xx-XX' . ($sin ? ' (' . implode(', ', $sin) . ')' : ''));
[$rc, $out] = $cli($s, 'comprobar xx-XX');
afirma($rc === 0 && str_contains($out, 'Todo correcto') && str_contains($out, 'iguales al ingles'), 'comprobar xx-XX (sin traducir aun): pasa y avisa de que sigue en ingles');
[$rc, $out, $zips] = $build($s);
afirma($rc === 0 && count($zips) === 1, 'build con el idioma de prueba: se empaqueta sin tocar codigo' . ($rc ? ' [' . substr($out, -200) . ']' : ''));
$dentro = 0;
if ($zips) {
	$z = new ZipArchive();
	$z->open($zips[0]);
	for ($i = 0; $i < $z->numFiles; $i++) {
		$tp = tempnam($tmp, 'z');
		file_put_contents($tp, (string) $z->getFromIndex($i));
		$y = new ZipArchive();
		if (str_ends_with((string) $z->getNameIndex($i), '.zip') && $y->open($tp) === true) {
			for ($j = 0; $j < $y->numFiles; $j++) { $dentro += str_contains((string) $y->getNameIndex($j), '/xx-XX/') ? 1 : 0; }
			$y->close();
		}
		unlink($tp);
	}
	$z->close();
}
afirma($dentro === $nEn, "los ZIP de las extensiones llevan los $nEn archivos xx-XX (hay $dentro)");

$casos = [
	'<script> en un texto' => [static fn ($d) => $editar("$d/component/frontend/language/xx-XX/com_mifoto.ini", static fn ($c) => preg_replace('/^(COM_MIFOTO_W_CHOOSE=")[^"]*"/m', '$1<script>alert(1)</script>"', $c, 1)), '<script>'],
	'direccion externa' => [static fn ($d) => $editar("$d/component/backend/language/xx-XX/com_mifoto.ini", static fn ($c) => preg_replace('/^(COM_MIFOTO_A_SUB=")[^"]*"/m', '$1Vea https://evil.example/x"', $c, 1)), 'direccion externa'],
	'falta una clave' => [static fn ($d) => $editar("$d/plugins/user/mifoto/language/xx-XX/plg_user_mifoto.ini", static fn ($c) => preg_replace('/^PLG_USER_MIFOTO_ADMIN_HIDE="[^"]*"\R/m', '', $c, 1)), 'falta la clave'],
	'marcador perdido' => [static fn ($d) => $editar("$d/component/frontend/language/xx-XX/com_mifoto.ini", static fn ($c) => preg_replace('/^(COM_MIFOTO_W_HELP=")[^"]*"/m', '$1sin marcador"', $c, 1)), 'marcadores distintos'],
	'archivo borrado' => [static fn ($d) => unlink("$d/modules/mifoto_photo/language/xx-XX/mod_mifoto_photo.sys.ini"), 'falta el archivo'],
];
foreach ($casos as $nombre => [$mut, $frase]) {
	$d = $copia();
	$cli($d, 'plantilla xx-XX');
	$mut($d);
	[$rc, $out] = $cli($d, 'comprobar xx-XX');
	[$brc, $bout, $bz] = $build($d);
	afirma($rc === 1 && str_contains($out, $frase), "idioma roto o malicioso ($nombre): la herramienta falla y dice por que");
	afirma($brc !== 0 && $bz === [] && str_contains($bout, 'IDIOMAS'), "idioma roto o malicioso ($nombre): el build falla y no deja paquete");
}
$d = $copia();
$cli($d, 'plantilla xx-XX');
$editar("$d/plugins/user/mifoto/mifoto.xml", static fn ($c) => preg_replace('#\s*<language tag="xx-XX">[^<]*</language>#', '', $c));
[$rc, $out] = $cli($d, 'comprobar xx-XX');
[$rc2] = $cli($d, 'manifiestos xx-XX');
[$rc3] = $cli($d, 'comprobar xx-XX');
afirma($rc === 1 && str_contains($out, 'no esta en el manifiesto') && $rc2 === 0 && $rc3 === 0, 'archivo fuera del manifiesto: falla, y «manifiestos xx-XX» lo repara');
$borrar($tmp);
afirma(!is_dir("$root/src/component/backend/language/xx-XX"), 'el src/ real queda intacto');

seccion('Guias y plantillas de GitHub');
foreach (['docs/TRADUCIR.md', 'CONTRIBUTING.md', 'NOTICE.md', '.github/pull_request_template.md', '.github/ISSUE_TEMPLATE/traduccion.yml', 'build/idiomas.php'] as $f) {
	afirma(is_file("$root/$f") && filesize("$root/$f") > 400, "existe $f");
}
$yml = $leer('.github/ISSUE_TEMPLATE/traduccion.yml');
afirma(str_contains($yml, 'id: codigo') && str_contains($yml, 'GPL v3') && substr_count($yml, 'required: true') >= 5, 'formulario de traduccion: codigo, licencia y campos obligatorios');
$tr = $leer('docs/TRADUCIR.md');
foreach (['idiomas.php plantilla', 'idiomas.php comprobar', '.sys.ini', '%1$s', 'Glosario', 'Criterios de aceptación', 'NOTICE.md'] as $k) { afirma(str_contains($tr, $k), "TRADUCIR.md cubre: $k"); }
$todo = $tr . $leer('CONTRIBUTING.md') . $leer('NOTICE.md') . $leer('.github/pull_request_template.md') . $yml;
afirma(str_contains($leer('CONTRIBUTING.md'), 'Report a vulnerability'), 'CONTRIBUTING: los fallos de seguridad se avisan en privado');
afirma(!preg_match('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,}|hue' . 'va|\b[A-Za-z]:\\\\|\/Users\/|(?:\d{1,3}\.){3}\d{1,3}/i', $todo), 'ningun correo, sitio real, ruta del equipo ni IP en guias y plantillas');
$rd = $leer('README.md');
afirma(str_contains($rd, '(docs/TRADUCIR.md)') && str_contains($rd, '(CONTRIBUTING.md)'), 'README enlaza TRADUCIR.md y CONTRIBUTING.md');
afirma(str_contains($leer('build/build.py'), 'check_languages()'), 'build.py comprueba los idiomas antes de empaquetar');
fin();
