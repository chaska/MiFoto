<?php
declare(strict_types=1);
require __DIR__ . '/aserciones.php';
$dir = __DIR__ . '/../src/';
require $dir . 'component/backend/src/Service/PhotoData.php';
require $dir . 'component/backend/src/Service/AvatarRenderer.php';
require $dir . 'plugins/content/mifoto/src/Service/Shortcode.php';

use Chaska\Component\Mifoto\Administrator\Service\{AvatarRenderer, PhotoData};
use Chaska\Plugin\Content\Mifoto\Service\Shortcode;

$photo = new PhotoData('/index.php?option=com_mifoto&task=photo.show&u=0123456789abcdef&s=64&v=aabbccdd', 64, 64, 64, 'aabbccdd');
$cls   = 'mifoto-avatar mifoto-shape-circle mifoto-frame-none mifoto-shadow-none';

seccion('Foto');
$h = AvatarRenderer::html($photo, $cls, 64, 'ML', '#534AB7', 'Marta');
afirma(str_starts_with($h, '<img ') && str_contains($h, 'width="64"') && str_contains($h, 'height="64"') && str_contains($h, 'alt="Marta"'), 'una foto sale como <img> con tamano y alt');
afirma(str_contains($h, '&amp;task=photo.show'), 'los & de la URL salen escapados (&amp;)');
afirma(substr_count($h, '"') % 2 === 0, 'las comillas del HTML estan balanceadas');

seccion('Sin foto: iniciales con color');
$h = AvatarRenderer::html(null, $cls, 48, 'ML', '#534AB7');
afirma(str_starts_with($h, '<span ') && str_contains($h, '>ML</span>') && str_contains($h, 'background:#534AB7') && str_contains($h, 'aria-hidden="true"'), 'sin alt: decorativo (aria-hidden)');
$h = AvatarRenderer::html(null, $cls, 48, 'ML', '#534AB7', 'Marta');
afirma(str_contains($h, 'role="img"') && str_contains($h, 'aria-label="Marta"') && !str_contains($h, 'aria-hidden'), 'con alt: role=img y aria-label');
afirma(str_contains(AvatarRenderer::html(null, $cls, 100, 'X', '#000000'), 'font-size:38px'), 'el tamano de letra va con el tamano (100 -> 38px)');
afirma(str_contains(AvatarRenderer::html(null, $cls, 100, '', '#000000'), '>?</span>'), 'iniciales vacias -> ?');
afirma(str_contains(AvatarRenderer::html(null, $cls, 100, 'ABCDEF', '#000000'), '>AB</span>'), 'maximo dos letras');

seccion('Tamano acotado');
afirma(str_contains(AvatarRenderer::html($photo, $cls, 5, 'A', '#000000'), 'width="16"'), 'tamano 5 sube a 16');
afirma(str_contains(AvatarRenderer::html($photo, $cls, 9999, 'A', '#000000'), 'width="300"'), 'tamano 9999 baja a 300');
afirma(str_contains(AvatarRenderer::html(null, $cls, -3, 'A', '#000000'), 'width:16px'), 'tamano negativo -> 16');

seccion('Nada hostil llega a un atributo');
$evilAlt = '"><script>alert(1)</script>';
$h = AvatarRenderer::html($photo, $cls, 64, 'ML', '#534AB7', $evilAlt);
afirma(!str_contains($h, '<script') && str_contains($h, '&quot;&gt;&lt;script&gt;'), 'alt con comillas y <script> sale escapado en la foto');
$h = AvatarRenderer::html(null, $cls, 64, 'ML', '#534AB7', $evilAlt);
afirma(!str_contains($h, '<script'), 'y tambien en las iniciales');
$h = AvatarRenderer::html(null, $cls, 64, '<b>', '#534AB7');
afirma(!str_contains($h, '<b>') && str_contains($h, '&lt;b'), 'iniciales con HTML se escapan');
foreach (['red; background:url(x)', '#12345', '#GGGGGG', 'expression(alert(1))', '#534AB7"', ''] as $badColor) {
	afirma(str_contains(AvatarRenderer::html(null, $cls, 64, 'A', $badColor), 'background:#5F5E5A"') , 'color raro [' . $badColor . '] -> gris por defecto');
}
$h = AvatarRenderer::html($photo, 'a" onmouseover="alert(1) b<c', 64, 'A', '#000000');
afirma(!str_contains($h, 'onmouseover') && !str_contains($h, '<c') && str_contains($h, 'class=""'), 'una ficha con comillas, = o < se descarta entera (class="" ): no queda ni rastro del atributo');
$h = AvatarRenderer::html($photo, 'ok x" y', 64, 'A', '#000000');
afirma(str_contains($h, 'class="ok y"'), 'las fichas buenas de al lado se conservan (ok y)');
afirma(AvatarRenderer::cleanClasses("  mifoto-avatar   x_y-1 ") === 'mifoto-avatar x_y-1', 'cleanClasses compacta espacios');
afirma(AvatarRenderer::cleanClasses(str_repeat('a', 41)) === '', 'una ficha de mas de 40 caracteres se descarta');

seccion('URLs de la foto');
foreach (['/index.php?a=1&b=2', '/images/x.webp', 'https://ejemplo.es/a.webp', 'http://ejemplo.es/a.webp'] as $valida) {
	afirma(AvatarRenderer::safeUrl($valida), "URL valida [$valida]");
}
foreach (['javascript:alert(1)', 'data:image/svg+xml;base64,AAAA', '//evil.example/x.webp', '', 'x.webp', '/a"onerror="x', "/a'b", '/a b', '/a<b', "/a\nb", '/a\\b', 'JaVaScRiPt:alert(1)', 'vbscript:x'] as $bad) {
	afirma(!AvatarRenderer::safeUrl($bad), 'URL rechazada [' . addcslashes($bad, "\0..\37") . ']');
}
$h = AvatarRenderer::html(new PhotoData('javascript:alert(1)', 64, 64, 64, 'v'), $cls, 64, 'ML', '#534AB7');
afirma(str_starts_with($h, '<span ') && !str_contains($h, 'javascript'), 'una foto con URL peligrosa se cambia por las iniciales');

seccion('Etiqueta {user}');
$log = [];
$render = function (int $id, int $size) use (&$log): string { $log[] = [$id, $size]; return "[$id@$size]"; };
afirma(Shortcode::replace('Hola {user}!', 64, $render) === 'Hola [0@64]!', '{user} = el lector, tamano por defecto');
afirma(Shortcode::replace('{user 42}', 64, $render) === '[42@64]', '{user 42}');
afirma(Shortcode::replace('{user size=96}', 64, $render) === '[0@96]', '{user size=96}');
afirma(Shortcode::replace('{user 42 size=128}', 64, $render) === '[42@128]', '{user 42 size=128}');
afirma(Shortcode::replace('{user 1} y {user 2}', 32, $render) === '[1@32] y [2@32]', 'varias etiquetas en un texto');
afirma(Shortcode::replace('{user size=5000}', 64, $render) === '[0@300]' || Shortcode::replace('{user size=999}', 64, $render) === '[0@300]', 'un tamano enorme se acota a 300');
afirma(Shortcode::replace('{user size=10}', 64, $render) === '[0@16]', 'un tamano diminuto sube a 16');
foreach (['{usuario}', '{users}', '{user 42x}', '{user abc}', '{ user }', '{user size=}', '{user  42  size=96}', '{USER}', '{user 42 size=96 extra}', '{user 99999999999}'] as $noTag) {
	$out = Shortcode::replace($noTag, 64, $render);
	if ($noTag === '{user  42  size=96}') { afirma($out === '[42@96]', 'varios espacios entre partes si valen'); continue; }
	afirma($out === $noTag, 'no se toca [' . $noTag . ']');
}
afirma(Shortcode::has('texto {user}') && !Shortcode::has('sin etiqueta'), 'has() detecta la etiqueta');
afirma(Shortcode::replace('', 64, $render) === '', 'texto vacio');
$big = str_repeat('{user 1}', 500);
afirma(substr_count(Shortcode::replace($big, 64, $render), '[1@64]') === 500, '500 etiquetas seguidas se resuelven');
afirma(Shortcode::replace("linea\n{user}\notra", 64, $render) === "linea\n[0@64]\notra", 'etiqueta en medio de saltos de linea');

fin();
