<?php
declare(strict_types=1);
require __DIR__ . '/aserciones.php';
require __DIR__ . '/../src/component/backend/src/Service/UploadLimit.php';

use Chaska\Component\Mifoto\Administrator\Service\UploadLimit;

$t0 = 1_800_000_000;

seccion('Ventana de una hora: se cuentan los intentos');
$c = UploadLimit::check(0, 0, $t0, 3);
afirma($c['allowed'] && $c['count'] === 1 && $c['start'] === $t0, 'primer intento: abre ventana con 1');
$c = UploadLimit::check($c['count'], $c['start'], $t0 + 60, 3);
afirma($c['allowed'] && $c['count'] === 2 && $c['start'] === $t0, 'segundo intento: cuenta 2, la ventana no se mueve');
$c = UploadLimit::check($c['count'], $c['start'], $t0 + 120, 3);
afirma($c['allowed'] && $c['count'] === 3, 'tercer intento: cuenta 3 (el limite es 3, aun vale)');
$c = UploadLimit::check($c['count'], $c['start'], $t0 + 600, 3);
afirma(!$c['allowed'] && $c['count'] === 3 && $c['start'] === $t0, 'cuarto intento: rechazado y no sube el contador');
afirma($c['retryAfter'] === 3600 - 600, 'dice cuanto falta para volver a probar (3000 s)');

seccion('Fin de la ventana');
$c = UploadLimit::check(3, $t0, $t0 + 3599, 3);
afirma(!$c['allowed'] && $c['retryAfter'] === 1, 'a 1 segundo del final sigue cerrado');
$c = UploadLimit::check(3, $t0, $t0 + 3600, 3);
afirma($c['allowed'] && $c['count'] === 1 && $c['start'] === $t0 + 3600, 'justo a la hora se abre una ventana nueva con 1');
$c = UploadLimit::check(99, $t0, $t0 + 86400, 3);
afirma($c['allowed'] && $c['count'] === 1, 'un dia despues, vuelve a empezar');

seccion('Valores raros');
$c = UploadLimit::check(2, $t0 + 5000, $t0, 3);
afirma($c['allowed'] && $c['count'] === 1 && $c['start'] === $t0, 'ventana en el futuro (reloj cambiado): empieza una nueva');
$c = UploadLimit::check(5, 0, $t0, 0);
afirma($c['allowed'] && $c['count'] === 5, 'max 0 = sin limite, no toca nada');
$c = UploadLimit::check(50, $t0, $t0 + 10, -4);
afirma($c['allowed'], 'max negativo = sin limite');
$c = UploadLimit::check(-7, $t0, $t0 + 10, 3);
afirma($c['allowed'] && $c['count'] === 1, 'contador negativo guardado por error: se corrige a 1');
$c = UploadLimit::check(0, $t0, $t0 + 10, 1);
afirma($c['allowed'] && $c['count'] === 1, 'limite 1: el primero pasa');
$c = UploadLimit::check(1, $t0, $t0 + 11, 1);
afirma(!$c['allowed'], 'limite 1: el segundo no');

seccion('Simulacion: 25 intentos seguidos con limite 10');
$count = 0; $start = 0; $allowed = 0;
for ($i = 0; $i < 25; $i++) {
	$r = UploadLimit::check($count, $start, $t0 + $i, 10);
	if ($r['allowed']) { $allowed++; }
	$count = $r['count']; $start = $r['start'];
}
afirma($allowed === 10, "pasan exactamente 10 de 25 ($allowed)");
$r = UploadLimit::check($count, $start, $t0 + 3700, 10);
afirma($r['allowed'] && $r['count'] === 1, 'pasada la hora, vuelve a pasar');

fin();
