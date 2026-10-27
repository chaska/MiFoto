<?php
declare(strict_types=1);
require __DIR__ . '/aserciones.php';
$dir = __DIR__ . '/../src/component/backend/src/Service/';
require $dir . 'PermissionMatrix.php';
require $dir . 'Segments.php';

use Chaska\Component\Mifoto\Administrator\Service\{PermissionMatrix as M, Segments};

// Joomla's default tree: Public(1) > Guest(9); Public > Manager(6) > Administrator(7); Public > Registered(2) > Author(3) > Editor(4) > Publisher(5); Public > Super Users(8)
$groups = [
	['id' => 1, 'parent' => 0, 'title' => 'Public'], ['id' => 9, 'parent' => 1, 'title' => 'Guest'], ['id' => 6, 'parent' => 1, 'title' => 'Manager'], ['id' => 7, 'parent' => 6, 'title' => 'Administrator'],
	['id' => 2, 'parent' => 1, 'title' => 'Registered'], ['id' => 3, 'parent' => 2, 'title' => 'Author'], ['id' => 4, 'parent' => 3, 'title' => 'Editor'], ['id' => 5, 'parent' => 4, 'title' => 'Publisher'], ['id' => 8, 'parent' => 1, 'title' => 'Super Users'],
];
$fixed = [8];
$cell = static function (array $rows, int $id, string $k): string { foreach ($rows as $r) { if ($r['id'] === $id) { return $r['cells'][$k]; } } return '?'; };

seccion('Sin reglas: nadie puede, salvo Super Users');
$rows = M::rows($groups, [], $fixed);
afirma($cell($rows, 2, 'up') === M::NONE && $cell($rows, 1, 'ver') === M::NONE, 'Registrado y Publico: sin asignar');
afirma($cell($rows, 8, 'up') === M::FIXED && $cell($rows, 8, 'mod') === M::FIXED, 'Super Users: siempre (fijo) en las tres');
afirma(count($rows) === 9 && $rows[0]['level'] === 0 && $rows[1]['level'] === 1 && $rows[7]['level'] === 4, 'niveles del arbol: Public 0, Guest 1, Publisher 4');

seccion('Permitido y heredado');
$rules = ['mifoto.upload' => [2 => 1], 'mifoto.view' => [2 => 1]];
$rows = M::rows($groups, $rules, $fixed);
afirma($cell($rows, 2, 'up') === M::OWN, 'Registrado: permitido a proposito');
afirma($cell($rows, 3, 'up') === M::INHERIT && $cell($rows, 5, 'ver') === M::INHERIT, 'Author y Publisher lo heredan de Registrado');
afirma($cell($rows, 6, 'up') === M::NONE && $cell($rows, 1, 'up') === M::NONE, 'Manager y Public (otra rama / mas arriba) no');
afirma($cell($rows, 2, 'mod') === M::NONE, 'moderar no esta asignado');

seccion('Denegado a proposito gana');
$rules = ['mifoto.view' => [2 => 1, 4 => 0]];
$rows = M::rows($groups, $rules, $fixed);
afirma($cell($rows, 3, 'ver') === M::INHERIT, 'Author sigue heredando el permiso');
afirma($cell($rows, 4, 'ver') === M::DENY, 'Editor: denegado a proposito');
afirma($cell($rows, 5, 'ver') === M::DENY_INH, 'Publisher: denegado por un grupo de arriba (Editor)');
$rows = M::rows($groups, ['mifoto.view' => [2 => 0, 3 => 1]], $fixed);
afirma($cell($rows, 3, 'ver') === M::DENY_INH, 'un "permitido" mas abajo no puede con el "denegado" de arriba');

seccion('Un clic: poner y quitar');
$r = M::toggle($groups, [], $fixed, 2, 'mifoto.upload');
afirma($r['ok'] && $r['rules'] === ['mifoto.upload' => [2 => 1]] && $r['state'] === M::OWN, 'sin asignar -> permitido a proposito');
$r2 = M::toggle($groups, $r['rules'], $fixed, 2, 'mifoto.upload');
afirma($r2['ok'] && $r2['rules'] === [] && $r2['state'] === M::NONE, 'permitido -> quitado, y la accion vacia desaparece');
$r = M::toggle($groups, ['mifoto.upload' => [2 => 1]], $fixed, 3, 'mifoto.upload');
afirma($r['ok'] && $r['rules']['mifoto.upload'] === [2 => 1, 3 => 1] && $r['state'] === M::OWN, 'heredado -> tambien se puede fijar a proposito');
$r = M::toggle($groups, ['mifoto.upload' => [2 => 1, 3 => 1]], $fixed, 3, 'mifoto.upload');
afirma($r['ok'] && $r['rules'] === ['mifoto.upload' => [2 => 1]] && $r['state'] === M::INHERIT, 'quitar el de Author deja el heredado de Registrado');
$r = M::toggle($groups, ['mifoto.view' => [2 => 0]], $fixed, 2, 'mifoto.view');
afirma(!$r['ok'] && $r['reason'] === 'denied' && $r['rules'] === ['mifoto.view' => [2 => 0]], 'denegado a proposito: no se toca, se explica');
$r = M::toggle($groups, ['mifoto.view' => [2 => 0]], $fixed, 5, 'mifoto.view');
afirma(!$r['ok'] && $r['reason'] === 'denied', 'denegado por un grupo de arriba: tampoco');
$r = M::toggle($groups, [], $fixed, 8, 'mifoto.upload');
afirma(!$r['ok'] && $r['reason'] === 'fixed', 'Super Users no se cambia');
$r = M::toggle($groups, [], $fixed, 99, 'mifoto.upload');
afirma(!$r['ok'] && $r['reason'] === 'unknown_group', 'grupo que no existe');
foreach (['core.admin', 'core.manage', 'mifoto.nada', '', 'mifoto.upload; DROP', "mifoto.view\0"] as $mala) {
	$r = M::toggle($groups, [], $fixed, 2, $mala);
	afirma(!$r['ok'] && $r['reason'] === 'unknown_action' && $r['rules'] === [], 'accion ajena rechazada [' . addcslashes($mala, "\0..\37") . ']');
}

seccion('Leer las reglas de Joomla');
$n = M::normalize(['core.admin' => [8 => 1], 'mifoto.upload' => ['2' => 1, '3' => 0, '0' => 1, '-4' => 1, 'x' => 1, '5' => 7], 'mifoto.view' => 'raro', 'mifoto.moderate' => [6 => '1']]);
afirma($n === ['mifoto.upload' => [2 => 1, 3 => 0], 'mifoto.moderate' => [6 => 1]], 'solo acciones de MiFoto, grupos > 0 y valores 0/1 (' . json_encode($n) . ')');
afirma(M::normalize('no es un array') === [] && M::normalize(null) === [] && M::normalize([]) === [], 'basura -> vacio');

seccion('Arbol roto no cuelga');
$ciclo = [['id' => 1, 'parent' => 2, 'title' => 'A'], ['id' => 2, 'parent' => 1, 'title' => 'B']];
$rows = M::rows($ciclo, ['mifoto.view' => [1 => 1]], []);
afirma(count($rows) === 2 && $rows[1]['cells']['ver'] === M::INHERIT, 'padres que se apuntan entre si: termina (' . $rows[1]['cells']['ver'] . ')');

seccion('Barra de segmentos del album');
$s = Segments::build(50, 36, 2, 50);
afirma(count($s) === 50 && count(array_keys($s, 'on')) === 36 && count(array_keys($s, 'hid')) === 2 && count(array_keys($s, '')) === 12, '50 usuarios: 36 + 2 + 12 (un bloque por usuario)');
afirma($s[0] === 'on' && $s[35] === 'on' && $s[36] === 'hid' && $s[38] === '', 'orden: visibles, ocultas, vacios');
$s = Segments::build(200, 100, 20, 50);
afirma(count(array_keys($s, 'on')) === 25 && count(array_keys($s, 'hid')) === 5 && count($s) === 50, '200 usuarios: cada bloque = 4 usuarios (25 + 5 + 20)');
$s = Segments::build(10, 10, 0, 50);
afirma(count(array_keys($s, 'on')) === 50, 'todos con foto: barra llena');
$s = Segments::build(10, 4, 6, 50);
afirma(count(array_keys($s, 'on')) + count(array_keys($s, 'hid')) === 50, 'todos con foto aunque unas ocultas: barra llena');
$s = Segments::build(1000, 1, 0, 50);
afirma(count(array_keys($s, 'on')) === 1, 'una foto entre mil no se redondea a cero');
$s = Segments::build(1000, 0, 1, 50);
afirma(count(array_keys($s, 'hid')) === 1 && count(array_keys($s, 'on')) === 0, 'una oculta entre mil tampoco');
afirma(Segments::build(0, 0, 0, 50) === array_fill(0, 50, ''), 'sin usuarios: barra vacia');
$s = Segments::build(5, 99, 99, 50);
afirma(count($s) === 50 && count(array_keys($s, '')) === 0, 'cifras absurdas se acotan a los usuarios que hay');
afirma(count(Segments::build(5, 2, 1, 0)) === 1 && count(Segments::build(5, 2, 1, 9999)) === 200, 'numero de bloques acotado (1..200)');

fin();
