<?php
declare(strict_types=1);
require __DIR__ . '/aserciones.php';
$base = __DIR__ . '/../src/component/backend/src/Security/';
require $base . 'SanitizeException.php';
require $base . 'ImageProcessor.php';
require $base . 'PhotoStorage.php';
use Chaska\Component\Mifoto\Administrator\Security\{ImageProcessor, PhotoStorage, SanitizeException};

ini_set('memory_limit', '1G'); // las pruebas fabrican imagenes-bomba de 81 Mpx; el limite que se prueba es el de ImageProcessor
$work = sys_get_temp_dir() . '/mifoto-img-' . bin2hex(random_bytes(4));
mkdir($work, 0700);
register_shutdown_function(static function () use ($work) {
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($work, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($it as $f) { $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
	@rmdir($work);
});

afirma(ImageProcessor::isSupported(), 'GD con soporte WebP/JPEG/PNG disponible');

function fichero(string $contenido, string $ext = 'bin'): string { global $work; $p = $work . '/' . bin2hex(random_bytes(6)) . '.' . $ext; file_put_contents($p, $contenido); return $p; }
function jpeg(int $w, int $h, int $q = 85): string {
	$im = imagecreatetruecolor($w, $h);
	for ($i = 0; $i < 60; $i++) { imagefilledellipse($im, random_int(0, $w), random_int(0, $h), random_int(1, max(1, $w)), random_int(1, max(1, $h)), imagecolorallocate($im, random_int(0, 255), random_int(0, 255), random_int(0, 255))); }
	ob_start(); imagejpeg($im, null, $q); return (string) ob_get_clean();
}
function png(int $w, int $h, bool $alpha = false): string {
	$im = imagecreatetruecolor($w, $h); imagealphablending($im, false); imagesavealpha($im, true);
	imagefill($im, 0, 0, imagecolorallocatealpha($im, 200, 30, 30, $alpha ? 64 : 0));
	ob_start(); imagepng($im); return (string) ob_get_clean();
}
function withApp1(string $jpeg, string $payload): string { $seg = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload; return substr($jpeg, 0, 2) . $seg . substr($jpeg, 2); }
function exif(int $orientation, bool $gps = false): string {
	$tiff = 'MM' . pack('n', 42) . pack('N', 8) . pack('n', 1) . pack('n', 0x0112) . pack('n', 3) . pack('N', 1) . pack('n', $orientation) . "\0\0" . pack('N', 0);
	return "Exif\0\0" . $tiff . ($gps ? 'GPSLatitude=40.4168;GPSLongitude=-3.7038;Make=SECRETCAMERA' : '');
}
function subir(string $path, array $extra = []): array { return array_merge(['name' => 'foto.jpg', 'type' => 'image/jpeg', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($path)], $extra); }
function intenta(ImageProcessor $p, array $file): array {
	global $work; $dest = $work . '/out-' . bin2hex(random_bytes(4)) . '.part';
	try { $r = $p->process($file, $dest); return ['ok' => true, 'r' => $r, 'dest' => $dest]; }
	catch (SanitizeException $e) { return ['ok' => false, 'reason' => $e->getReason(), 'left' => is_file($dest)]; }
}
function rechazada(ImageProcessor $p, array $file, string $reason, string $msg): void {
	// Windows Defender a veces bloquea la lectura de archivos de prueba que llevan codigo PHP: eso no prueba nada de nuestro codigo.
	$tmp = (string) ($file['tmp_name'] ?? '');
	if ($tmp !== '' && is_file($tmp) && filesize($tmp) > 0 && @file_get_contents($tmp, false, null, 0, 1) === false) {
		echo "  OMITIDA (antivirus bloquea el archivo de prueba) $msg\n"; $GLOBALS['omitidas'] = ($GLOBALS['omitidas'] ?? 0) + 1; return;
	}
	$x = intenta($p, $file);
	afirma(!$x['ok'] && $x['reason'] === $reason && !$x['left'], "$msg -> " . ($x['ok'] ? 'ACEPTADA' : $x['reason'] . ($x['left'] ? ' (dejo fichero!)' : '')));
}
$p = new ImageProcessor(10 * 1048576, 300, 82, false, 40000000, 12000, static fn (string $f): bool => true);

seccion('Conversion: WebP, lado largo 300, misma proporcion');
$x = intenta($p, subir(fichero(jpeg(3000, 2000), 'jpg')));
afirma($x['ok'] && $x['r']['width'] === 300 && $x['r']['height'] === 200, 'horizontal 3000x2000 -> 300x200 (' . ($x['r']['width'] ?? '?') . 'x' . ($x['r']['height'] ?? '?') . ')');
afirma($x['ok'] && (new finfo(FILEINFO_MIME_TYPE))->file($x['dest']) === 'image/webp', 'el resultado es WebP de verdad');
afirma($x['ok'] && (fileperms($x['dest']) & 0007) === 0 || DIRECTORY_SEPARATOR === '\\', 'permisos sin acceso para "otros" (solo POSIX)');
$x = intenta($p, subir(fichero(jpeg(2000, 3000), 'jpg')));
afirma($x['ok'] && $x['r']['width'] === 200 && $x['r']['height'] === 300, 'vertical 2000x3000 -> 200x300');
$x = intenta($p, subir(fichero(jpeg(2400, 2400), 'jpg')));
afirma($x['ok'] && $x['r']['width'] === 300 && $x['r']['height'] === 300, 'cuadrada 2400x2400 -> 300x300');
$x = intenta($p, subir(fichero(jpeg(200, 150), 'jpg')));
afirma($x['ok'] && $x['r']['width'] === 200 && $x['r']['height'] === 150, 'pequena 200x150 no se amplia');
$x = intenta($p, subir(fichero(png(1500, 900, true), 'png')));
afirma($x['ok'] && $x['r']['width'] === 300 && $x['r']['height'] === 180, 'PNG con transparencia -> 300x180');
$x = intenta($p, subir(fichero(jpeg(1200, 800), 'webp')));
afirma($x['ok'], 'JPEG con extension equivocada se acepta por su contenido real');

seccion('Orientacion EXIF y borrado de metadatos');
$x = intenta($p, subir(fichero(withApp1(jpeg(1600, 1000), exif(6)), 'jpg')));
afirma($x['ok'] && $x['r']['width'] === 188 && $x['r']['height'] === 300, 'orientacion 6 (movil en vertical): 1600x1000 -> 188x300');
$x = intenta($p, subir(fichero(withApp1(jpeg(1600, 1000), exif(1, true)), 'jpg')));
$out = $x['ok'] ? file_get_contents($x['dest']) : '';
afirma($x['ok'] && !str_contains($out, 'GPS') && !str_contains($out, 'SECRETCAMERA') && !str_contains($out, 'Exif'), 'GPS, marca de camara y EXIF NO sobreviven en la salida');
foreach ([0, 9, 255, 65535] as $o) { $x = intenta($p, subir(fichero(withApp1(jpeg(800, 600), exif($o)), 'jpg'))); afirma($x['ok'] && $x['r']['width'] === 300, "orientacion invalida $o se ignora sin romper"); }
afirma(ImageProcessor::jpegOrientation("\xFF\xD8\xFF\xE1\xFF\xFF") === 1, 'APP1 con longitud fuera del archivo: 1, sin errores');
afirma(ImageProcessor::jpegOrientation(str_repeat("\xFF\xE1\x00\x02", 200)) === 1, 'cadena infinita de segmentos: termina');

seccion('Archivos disfrazados y cabeceras cambiadas');
// Sin la firma tipica "system($_GET": el antivirus de Windows bloquea el archivo y la prueba no llegaria a nuestro codigo.
rechazada($p, subir(fichero("GIF89a" . str_repeat("\x00", 200) . '<?php echo 1; ?>', 'jpg')), 'image_type', 'GIF/PHP que dice ser JPEG');
rechazada($p, subir(fichero('<?php echo 1; ?>' . str_repeat('A', 300), 'php'), ['name' => 'x.php.jpg']), 'image_type', 'PHP puro con Content-Type de imagen');
rechazada($p, subir(fichero('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)">' . str_repeat(' ', 200) . '</svg>', 'png'), ['type' => 'image/png']), 'image_type', 'SVG con script renombrado a .png');
rechazada($p, subir(fichero('<html><script>alert(1)</script></html>' . str_repeat(' ', 200), 'jpg')), 'image_type', 'HTML renombrado a .jpg');
rechazada($p, subir(fichero("%PDF-1.4\n" . str_repeat('x', 300), 'jpg')), 'image_type', 'PDF renombrado');
rechazada($p, subir(fichero("\x00\x00\x00\x18ftypheic" . str_repeat("\x00", 300), 'jpg')), 'image_type', 'HEIC/AVIF renombrado');
rechazada($p, subir(fichero("BM" . str_repeat("\x00", 300), 'jpg')), 'image_type', 'BMP renombrado');
rechazada($p, subir(fichero("PK\x03\x04" . str_repeat("\x00", 300), 'jpg')), 'image_type', 'ZIP renombrado');
rechazada($p, subir(fichero("\xFF\xD8\xFF" . str_repeat("\x00", 300), 'jpg')), 'image_type', 'solo la firma JPEG y basura detras');

seccion('Poliglotas: imagen valida con codigo escondido');
rechazada($p, subir(fichero(jpeg(800, 600) . '<?php echo 1; ?>', 'jpg')), 'image_polyglot', 'JPEG valido + PHP al final');
rechazada($p, subir(fichero(withApp1(jpeg(800, 600), "Exif\0\0<?php eval(\$_POST[1]);?>"), 'jpg')), 'image_polyglot', 'PHP dentro del EXIF');
rechazada($p, subir(fichero(png(800, 600) . '<script>alert(1)</script>', 'png')), 'image_polyglot', 'PNG valido + script al final');
rechazada($p, subir(fichero(jpeg(800, 600) . "\n<SVG onload=alert(1)>", 'jpg')), 'image_polyglot', 'JPEG + SVG en mayusculas');

seccion('Archivos rotos, vacios o enormes');
rechazada($p, subir(fichero('', 'jpg')), 'image_unreadable', 'archivo vacio');
rechazada($p, subir(fichero(substr(jpeg(1200, 800), 0, 4000), 'jpg')), 'image_unreadable', 'JPEG truncado');
rechazada($p, subir(fichero(png(9000, 9000), 'png')), 'image_dimensions', 'bomba de descompresion PNG 9000x9000');
$hdr = "\x89PNG\r\n\x1A\n" . pack('N', 13) . 'IHDR' . pack('N', 60000) . pack('N', 60000) . "\x08\x02\x00\x00\x00" . "\0\0\0\0" . str_repeat("\0", 200);
rechazada($p, subir(fichero($hdr, 'png')), 'image_dimensions', 'cabecera PNG que declara 60000x60000');
$big = jpeg(3000, 3000, 100);
$small = new ImageProcessor(strlen($big) - 1, 300, 82, false, 40000000, 12000, static fn (string $f): bool => true);
rechazada($small, subir(fichero($big, 'jpg')), 'image_too_big', 'archivo mayor que el limite');
rechazada($p, subir(fichero(jpeg(8, 8), 'jpg')), 'image_dimensions', 'imagen minuscula 8x8');

seccion('Peticion manipulada');
rechazada($p, ['name' => 'a.jpg', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0], 'image_upload', 'sin archivo');
rechazada($p, ['name' => 'a.jpg', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0], 'image_too_big', 'supera upload_max_filesize');
rechazada($p, ['name' => 'a.jpg', 'tmp_name' => '', 'error' => UPLOAD_ERR_PARTIAL, 'size' => 0], 'image_upload', 'subida parcial');
$strict = new ImageProcessor();
rechazada($strict, subir(fichero(jpeg(800, 600), 'jpg')), 'image_upload', 'fichero local que PHP no ha recibido por HTTP');
rechazada($p, ['name' => 'a.jpg', 'tmp_name' => "/tmp/x\0.jpg", 'error' => 0, 'size' => 1], 'image_upload', 'byte nulo en la ruta');
rechazada($p, ['name' => 'a.jpg', 'tmp_name' => $work, 'error' => 0, 'size' => 1], 'image_upload', 'tmp_name es un directorio');
rechazada($p, ['name' => 'a.jpg'], 'image_upload', 'entrada sin campos');
$x = intenta($p, subir(fichero(jpeg(900, 600), 'jpg'), ['name' => '../../../../etc/cron.d/x.php', 'type' => 'application/x-php', 'size' => 1]));
afirma($x['ok'] && dirname($x['dest']) === $work, 'nombre, tipo y tamano declarados por el cliente se ignoran por completo');

seccion('Almacen privado: <id>/<token>-<tamano>.webp');
$st = new PhotoStorage($work . '/store');
$st->prepare();
afirma(is_file($work . '/store/.htaccess') && is_file($work . '/store/web.config') && is_file($work . '/store/index.html'), 'se crean .htaccess, web.config e index.html');
afirma(str_contains((string) file_get_contents($work . '/store/.htaccess'), 'Require all denied'), '.htaccess deniega todo');
$tmp = $st->newTemporaryPath();
$p->process(subir(fichero(jpeg(1200, 800), 'jpg')), $tmp);
$t1 = $st->commit($tmp, 12);
afirma(preg_match('/^[a-f0-9]{24}$/D', $t1) === 1 && !is_file($tmp), 'token aleatorio de 96 bits y temporal movido');
foreach (PhotoStorage::SIZES as $s) { afirma($st->pathFor(12, $t1, $s) !== null, "existe el tamano $s"); }
$d = getimagesize($st->pathFor(12, $t1, 128));
afirma($d !== false && $d[0] === 128 && $d[1] === 85, 'el de 128 conserva la proporcion (128x85)');
$d = getimagesize($st->pathFor(12, $t1, 32));
afirma($d !== false && $d[0] === 32 && $d[1] === 21, 'el de 32 conserva la proporcion (32x21)');
$tmp = $st->newTemporaryPath();
$p->process(subir(fichero(jpeg(1000, 1000), 'jpg')), $tmp);
$t2 = $st->commit($tmp, 12);
afirma($t2 !== $t1 && $st->pathFor(12, $t1, 300) === null && $st->pathFor(12, $t2, 300) !== null, 'una subida nueva cambia el token y borra la foto anterior');
afirma(count(glob($work . '/store/12/*.webp') ?: []) === 4, 'queda una sola foto (4 tamanos) en la carpeta del usuario');
foreach ([[12, '../x', 300], [12, $t2, 301], [0, $t2, 300], [13, $t2, 300], [12, strtoupper($t2), 300], [12, $t2 . "\0", 300], [12, '', 300], [12, str_repeat('a', 24), 300]] as [$u, $tk, $sz]) {
	afirma($st->pathFor($u, $tk, $sz) === null, 'pathFor rechaza [' . $u . ', ' . addcslashes($tk, "\0..\37") . ', ' . $sz . ']');
}
afirma(PhotoStorage::nearestSize(20) === 32 && PhotoStorage::nearestSize(33) === 64 && PhotoStorage::nearestSize(100) === 128 && PhotoStorage::nearestSize(129) === 300 && PhotoStorage::nearestSize(900) === 300, 'nearestSize elige el tamano guardado mas cercano por arriba');
$st->deleteUser(12);
afirma($st->pathFor(12, $t2, 300) === null && !is_dir($work . '/store/12'), 'deleteUser quita las fotos y la carpeta vacia');
$st->deleteUser(-5); $st->deleteToken(0, 'x');
afirma(is_dir($work . '/store'), 'borrar con ids hostiles no toca nada');
file_put_contents($work . '/store/tmp/viejo.part', 'x'); touch($work . '/store/tmp/viejo.part', time() - 7200);
$st->sweep(3600); afirma(!is_file($work . '/store/tmp/viejo.part'), 'sweep borra temporales abandonados');
try { $st->commit($work . '/fuera.part', 5); $fuera = false; } catch (RuntimeException $e) { $fuera = true; }
afirma($fuera, 'commit rechaza un temporal que no esta en nuestra carpeta tmp');

if (!empty($GLOBALS['omitidas'])) { echo "\n{$GLOBALS['omitidas']} omitida(s) por el antivirus del equipo: repetir la prueba o correrla en WSL/Linux\n"; }
fin();
