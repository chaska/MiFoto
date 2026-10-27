<?php
declare(strict_types=1);

// Pruebas de la 0.4.1: instalador (acceso de plugins), plugin de Engage (contexto), fusion de reglas, barra del album y
// que el formulario de Ajustes envie TODOS los parametros de config.xml. Joomla se simula con clases minimas.

namespace Joomla\CMS {
	class Factory
	{
		public static object $container;
		public static function getContainer(): object { return self::$container; }
	}
}
namespace Joomla\CMS\Installer {
	class InstallerAdapter {}
	interface InstallerScriptInterface {}
}
namespace Joomla\Database {
	interface DatabaseInterface {}
	class ParameterType { public const STRING = 'string'; public const INTEGER = 'int'; }
}
namespace Joomla\CMS\Component {
	class ComponentHelper { public static function isEnabled(string $c): bool { return true; } }
}
namespace Joomla\CMS\User {
	class User { public int $id = 0; public bool $guest = true; }
}
namespace Joomla\CMS\Plugin {
	class CMSPlugin
	{
		public static object $app;
		protected $allowLegacyListeners = true;
		public function getApplication(): object { return self::$app; }
	}
}
namespace Joomla\Event {
	interface SubscriberInterface {}
	class Event
	{
		private array $a;
		public function __construct(string $n, array $a = []) { $this->a = $a; }
		public function getArgument($k, $d = null) { return $this->a[$k] ?? $d; }
		public function setArgument($k, $v): void { $this->a[$k] = $v; }
	}
}

namespace Fake {
	/** A database that only remembers the SQL it was asked to run. */
	class Db implements \Joomla\Database\DatabaseInterface
	{
		public array $ran = [];
		public ?string $manifest = null;
		public $last;
		public function getQuery(bool $new = true): Q { return new Q(); }
		public function quoteName($n) { return is_array($n) ? $n : '`' . $n . '`'; }
		public function quote($v): string { return "'" . $v . "'"; }
		public function setQuery($q): self { $this->last = $q; return $this; }
		public function execute(): bool { $this->ran[] = $this->last->sql(); return true; }
		public function loadResult() { return $this->manifest; }
	}
	class Q
	{
		private array $parts = []; private array $binds = [];
		public function update($t) { $this->parts[] = 'UPDATE ' . $t; return $this; }
		public function set($s) { $this->parts[] = 'SET ' . $s; return $this; }
		public function where($s) { $this->parts[] = 'WHERE ' . $s; return $this; }
		public function whereIn($c, $v, $t = null) { $this->parts[] = 'IN ' . $c . ' (' . implode(',', $v) . ')'; return $this; }
		public function bind($k, &$v, $t = null) { $this->binds[$k] = $v; return $this; }
		public function select($t) { return $this; }
		public function from($t) { return $this; }
		public function sql(): string { $s = implode(' ', $this->parts); foreach ($this->binds as $k => $v) { $s = str_replace($k, (string) $v, $s); } return $s; }
	}
	class Container { public Db $db; public function get(string $id) { return $this->db; } }
}

namespace {
	require __DIR__ . '/aserciones.php';
	$src = __DIR__ . '/../src/';

	// ---- instalador ----------------------------------------------------------------------------
	seccion('Instalador: acceso de los plugins (solo la primera vez, y solo si estaban en Registrado)');
	$script = require $src . 'package/script.php';
	$run = static function (string $type, ?string $previous) use ($script): array {
		$db = new \Fake\Db();
		$db->manifest = $previous === null ? null : json_encode(['version' => $previous]);
		$c = new \Fake\Container();
		$c->db = $db;
		\Joomla\CMS\Factory::$container = $c;
		$ad = new \Joomla\CMS\Installer\InstallerAdapter();
		$script->preflight($type, $ad);
		$script->postflight($type, $ad);
		return $db->ran;
	};
	$enabled = static fn (array $ran): bool => (bool) array_filter($ran, static fn (string $s): bool => str_contains($s, 'enabled` = 1'));
	$access  = static function (array $ran): ?string { foreach ($ran as $s) { if (str_contains($s, 'access` =')) { return $s; } } return null; };

	$r = $run('install', null);
	$a = $access($r);
	afirma($enabled($r) && $a !== null, 'instalacion nueva: enciende los plugins y fija el acceso');
	afirma($a !== null && str_contains($a, 'IN `folder` (content,engage)') && !str_contains($a, 'user'), 'solo toca Engage y Contenido (el de Usuario se queda como esta)');
	afirma($a !== null && str_contains($a, '`access` = 1') && str_contains($a, '`access` = 2'), 'pone Public (1) solo donde estaba en Registrado (2): no pisa lo que el admin eligio');

	$r = $run('update', '0.4.0');
	afirma(!$enabled($r) && $access($r) !== null, 'actualizar desde 0.4.0: arregla el acceso pero NO vuelve a encender plugins que el admin apagara');
	$r = $run('update', '0.4.1');
	afirma($r === [], 'actualizar desde 0.4.1: no toca nada');
	$r = $run('update', '0.5.0');
	afirma($r === [], 'actualizar desde una version posterior: no toca nada');
	$r = $run('update', '0.2.0');
	afirma($enabled($r) && $access($r) !== null, 'actualizar desde antes de la 0.3.0: enciende y arregla acceso una vez');
	$r = $run('update', null);
	afirma($r === [], 'actualizar sin saber la version anterior: no toca nada');

	// ---- plugin de Engage ----------------------------------------------------------------------------
	seccion('Engage: la foto solo se da para una pagina viva (contexto page)');
	require $src . 'component/backend/src/Service/PhotoData.php';
	require $src . 'plugins/engage/mifoto/src/Extension/Mifoto.php';

	$photo = new \Chaska\Component\Mifoto\Administrator\Service\PhotoData('/index.php?option=com_mifoto&task=photo.show&u=0123456789abcdef&s=48&v=aabbccdd', 48, 48, 48, 'aabbccdd');
	$guest = new \Joomla\CMS\User\User(); // quien mira es un invitado sin sesion
	\Joomla\CMS\Plugin\CMSPlugin::$app = new class ($photo, $guest) {
		public function __construct(private $photo, private $guest) {}
		public function bootComponent(string $c): object { $p = $this->photo; return new class ($p) { public function __construct(private $p) {} public function getPhotoService(): object { $p = $this->p; return new class ($p) { public function __construct(private $p) {} public function getPhoto(int $id, int $size, $viewer) { return $this->p; } }; } }; }
		public function getIdentity() { return $this->guest; }
	};
	$plugin = new \Chaska\Plugin\Engage\Mifoto\Extension\Mifoto();
	$owner  = new \Joomla\CMS\User\User();
	$owner->id = 42;
	$ask = static function (array $args) use ($plugin): array {
		$e = new \Joomla\Event\Event('onAkeebaEngageUserAvatarURL', $args);
		$plugin->onAkeebaEngageUserAvatarURL($e);
		return (array) $e->getArgument('result', []);
	};

	$res = $ask([0 => $owner, 1 => 48, 2 => 'page']);
	afirma(count($res) === 1 && str_contains($res[0], 'option=com_mifoto&task=photo.show'), 'contexto page, lector invitado: devuelve la direccion de com_mifoto (photo.show)');
	afirma($ask([0 => $owner, 1 => 48, 2 => 'email']) === [], 'contexto email: no devuelve nada');
	afirma($ask([0 => $owner, 1 => 48, 2 => 'static']) === [], 'contexto static (cache compartida): no devuelve nada');
	afirma(count($ask([0 => $owner, 1 => 48])) === 1, 'Engage antiguo (sin tercer argumento): funciona como pagina');
	afirma(count($ask([0 => $owner, 1 => 48, 2 => ''])) === 1, 'contexto vacio: se trata como pagina');
	$fake = new \Joomla\CMS\User\User();
	afirma($ask([0 => $fake, 1 => 48, 2 => 'page']) === [], 'usuario falso de invitado (id 0): nada');

	// ---- reglas de permisos ----------------------------------------------------------------------------
	seccion('Guardar permisos: solo cambian las acciones mifoto.*, el resto queda intacto');
	require $src . 'component/backend/src/Service/PermissionMatrix.php';
	require $src . 'component/backend/src/Service/PermissionStore.php';
	$S = \Chaska\Component\Mifoto\Administrator\Service\PermissionStore::class;
	$raw = '{"core.admin":{"8":1},"core.manage":{"7":1},"mifoto.view":{"2":1},"core.options":{}}';
	$out = json_decode($S::mergeRules($raw, ['mifoto.upload' => [2 => 1], 'mifoto.view' => [2 => 1, 3 => 0]]), true);
	afirma($out['core.admin'] === [8 => 1] && $out['core.manage'] === [7 => 1] && array_key_exists('core.options', $out), 'core.admin, core.manage y core.options quedan como estaban');
	afirma($out['mifoto.upload'] === [2 => 1] && $out['mifoto.view'] === [2 => 1, 3 => 0], 'las acciones de MiFoto son las nuevas');
	$txt = $S::mergeRules($raw, []);
	afirma(!str_contains($txt, 'mifoto.view') && str_contains($txt, '"core.options":{}'), 'sin reglas de MiFoto desaparecen; los objetos vacios siguen siendo objetos {}');
	afirma(str_contains($S::mergeRules('', ['mifoto.upload' => [0 => 1, 1 => 1]]), '{"mifoto.upload":{"0":1,"1":1}}'), 'ids 0, 1 se guardan como objeto, no como lista');
	afirma($S::mergeRules('esto no es json', ['mifoto.view' => [2 => 1]]) === '{"mifoto.view":{"2":1}}', 'JSON roto en la base: se rehace sin error');

	// ---- barra del album ----------------------------------------------------------------------------
	seccion('Barra del album: no se llena si falta alguien');
	require $src . 'component/backend/src/Service/Segments.php';
	$Sg = \Chaska\Component\Mifoto\Administrator\Service\Segments::class;
	$s = $Sg::build(1000, 999, 0, 50);
	afirma(count($s) === 50 && in_array('', $s, true), '999 de 1000: queda al menos un bloque vacio');
	$s = $Sg::build(1000, 499, 500, 50);
	afirma(count($s) === 50 && in_array('', $s, true), '499 visibles + 500 ocultas de 1000: queda un bloque vacio');
	$s = $Sg::build(1000, 1000, 0, 50);
	afirma(!in_array('', $s, true), 'si TODOS tienen foto, la barra esta llena');
	$s = $Sg::build(50, 36, 2, 50);
	afirma(count(array_keys($s, 'on')) === 36 && count(array_keys($s, 'hid')) === 2, 'los casos de antes siguen igual (36 + 2)');
	$s = $Sg::build(3, 1, 1, 2);
	afirma(count($s) === 2 && in_array('', $s, true), 'pocos bloques y pocos usuarios: tambien deja un hueco');

	// ---- Ajustes: el formulario envia todo config.xml ---------------------------------------------------
	seccion('Ajustes: cada parametro de config.xml esta en el formulario (si no, com_config lo borraria al guardar)');
	$xml = simplexml_load_file($src . 'component/backend/config.xml');
	$names = [];
	foreach ($xml->xpath('//field') as $f) { $n = (string) $f['name']; if ($n !== '' && $n !== 'rules') { $names[] = $n; } }
	$tpl = (string) file_get_contents($src . 'component/backend/tmpl/settings/default.php');
	$missing = array_values(array_filter($names, static fn (string $n): bool => !str_contains($tpl, 'jform[' . $n . ']') && !str_contains($tpl, "'" . $n . "'")));
	afirma($names !== [] && $missing === [], count($names) . ' parametros en config.xml; faltan en el formulario: ' . ($missing ? implode(', ', $missing) : 'ninguno'));

	// ---- ruta y restricciones ---------------------------------------------------------------------------
	seccion('Carpeta, entrega y buscador');
	$settings = (string) file_get_contents($src . 'component/backend/src/Service/Settings.php');
	afirma(str_contains($settings, '~^images/'), 'la carpeta de fotos tiene que colgar de images/');
	$ph = (string) file_get_contents($src . 'component/backend/src/Service/PhotoService.php');
	afirma(str_contains($ph, "'u.block') . ' = 0'") && str_contains($ph, "'INNER'"), 'la entrega por clave pasa por usuarios no bloqueados');
	$ad = (string) file_get_contents($src . 'component/backend/src/Service/AdminData.php');
	afirma(!str_contains($ad, 'u.email'), 'el buscador del administrador ya no mira el correo');

	fin();
}
