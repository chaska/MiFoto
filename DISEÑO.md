# MiFoto — diseño (borrador 26/10/2026)

Foto de perfil para Joomla 5 y 6. Un paquete (`pkg_mifoto`) que otros componentes pueden aprovechar.

## Piezas
| Pieza | Para qué |
|---|---|
| `com_mifoto` | Opciones del admin, servir fotos con permisos, API pública, pantallas de gestión |
| `plg_user_mifoto` | Añade el campo foto al formulario de perfil (`onContentPrepareForm`), sube/cambia/borra (`onUserAfterSave`, `onUserAfterDelete`) |
| `plg_content_mifoto` | `{user}` / `{user nombre}` en artículos |
| `plg_engage_mifoto` | Responde a `onAkeebaEngageUserAvatarURL` (y `…UserProfileURL`); sin foto no responde |
| `plg_mifoto_mapa` (futuro) | Adaptador para mapa-mundial-interactivo (campo `av`) |

## Papel de MiFoto: proveedor de fotos, no de pantallas ajenas
MiFoto **administra** las fotos (subir, editar, borrar, quién puede subir, quién puede ver) y **las ofrece** a cualquier extensión de Joomla, propia o ajena. Dónde, cómo y si se muestra lo decide cada consumidor en su propia configuración (Engage, el mapa, un módulo, otro componente). "Solo amigos", "solo en el ranking", etc. son filtros del consumidor encima de lo que MiFoto ya permite ver.

### Cuatro puertas de entrada (de más a menos acoplada)
1. **ACL de Joomla (permisos por rol).** `access.xml` con acciones propias: `mifoto.upload` (subir/editar la propia), `mifoto.view` (ver fotos de otros), `mifoto.manage` (moderar). El admin las asigna a grupos en Opciones → Permisos, como en cualquier componente. Cualquier extensión puede preguntar `$user->authorise('mifoto.view', 'com_mifoto')`.
2. **Servicio PHP.** Se obtiene con `Factory::getApplication()->bootComponent('com_mifoto')->getPhotoService()`. Métodos: `getPhoto(int $ownerId, int $size, ?User $viewer = null): ?PhotoData` (URL, ancho/alto, iniciales y color de respaldo, clases CSS de estilo), `has(int $ownerId): bool`. Si el visor no tiene permiso, devuelve `null` — igual que si no hubiera foto. MiFoto comprueba el permiso; el consumidor no tiene que repetirlo.
3. **Eventos (grupo de plugins `mifoto`).** Para quien prefiera plugins: `onMiFotoGetPhoto` (pedir una foto) y los que MiFoto dispara: `onMiFotoAfterUpload`, `onMiFotoAfterDelete` (para que otros limpien cachés). Se usa `Joomla\Event\Event` con argumentos para que funcione en todo Joomla 5 y 6 (las clases de evento propias solo existen desde 5.2).
4. **URL HTTP** `index.php?option=com_mifoto&task=photo.show&u=<clave>&s=<tamaño>` para JS, correo, plantillas y terceros que no usan PHP. Comprueba sesión y permiso en cada petición. Tamaños admitidos: 32, 64, 128 y 300.

### Adaptadores incluidos (solo traducen, no deciden)
- **Engage:** plugin del grupo `engage`. Ver sección Engage.
- **Mapa:** plugin que rellena `av` en `row()` y `forMap`. Si el mapa quiere "solo amigos", lo filtra él con `areFriends()` antes de pedir la foto.
- **`{user}`:** plugin de contenido.
- **Módulo opcional** "Mi foto / foto de un usuario" para posiciones de plantilla.

### Verificado en la documentación de Joomla
- ACL por `access.xml` con acciones `componente.acción`, heredadas desde configuración global → componente → elemento (docs.joomla.org, "Adding ACL rules to your component").
- Plugins con `SubscriberInterface`, `services/provider.php` y manifiesto con `<namespace>` (manual.joomla.org); cualquier extensión puede lanzar eventos propios.
- **Por comprobar al programar:** `bootComponent()` como forma de llegar al servicio de otro componente, y la importación del grupo de plugins `mifoto` antes de lanzar eventos. No he encontrado un ejemplo oficial de ninguno de los dos.

## Lo que se reutiliza (leído en los repos el 26/10/2026)

**Del mapa** (`src/component/backend/src/`, mismo autor, GPL):
- `Security/ImageProcessor.php` → la subida entera. Identifica por los bytes (no por nombre ni Content-Type), cruza `finfo` con `getimagesize`, lee dimensiones antes de decodificar (anti bomba), comprueba memoria, busca código escondido (`<?php`, `<script`…) en las zonas que sobreviven, aplica la orientación EXIF con un parser propio mínimo, redimensiona sin deformar y recodifica a WebP. Se copia a MiFoto con `longSide=300`, `upscale=false`, sin marca de agua ni PNG.
- `Security/PrivateStorage.php` → carpeta con `.htaccess` + `web.config` + `index.html` que niegan el acceso directo, nombres de 128 bits aleatorios, carpeta por id de usuario, `realpath` contra salidas de ruta, miniaturas en caché, barrido de temporales. Raíz configurable: `/images/MiFoto` por defecto (con las guardas), o fuera de la web si el hosting lo permite.
- `Service/PhotoServer.php` → entrega. 404 idéntico para "no existe" y "no permitido" (nadie puede sondear ids), `ETag` con HMAC del secreto de Joomla (el nombre del archivo no viaja), `nosniff`, `CSP: default-src 'none'; sandbox`, `Referrer-Policy: no-referrer` y **`Cross-Origin-Resource-Policy: same-origin`**. Esto último sustituye al `.htaccess` por Referer: lo aplica el navegador y no se falsifica, así que ninguna otra web puede incrustar las fotos. Admite una regla `mayView` que le pasa quien llama; en MiFoto esa regla es el permiso `mifoto.view`.
- Pendiente de leer: `SanitizeException`, el controlador de subida del mapa (límites por usuario) y las pruebas `tests/02-imagen.php` para reutilizarlas.

**De Engage** (`src/component/backend/src/Helper/Avatar.php`):
- Confirma el patrón de eventos: `PluginHelper::importPlugin('engage')` y `$dispatcher->dispatch($nombre, new Event($nombre, [$user, $size]))`, leyendo `$result['result']`. MiFoto lo copia con su grupo `mifoto`; ya no es una duda.
- Engage mira **antes** el campo personalizado del avatar y después los plugins. Si el admin de Engage configura ese campo, gana sobre MiFoto. Aviso en la guía; opcional: ofrecer también un campo personalizado de usuario de tipo "foto MiFoto" que rinda `<img src="…">`, que es lo que Engage sabe leer.
- Engage lee el resultado del evento y guarda en caché estática por usuario y petición. MiFoto hará lo mismo en su servicio.
- Engage acepta un segundo argumento `deferred` (URL de terceros pendiente de consentimiento). MiFoto no lo usa: sus fotos son del propio dominio.

## Almacenamiento
- `/images/MiFoto/<idUsuario>/` — Joomla identifica al usuario por id; el id no cambia aunque cambie el nombre de usuario.
- Archivos con nombre aleatorio (`<hash>-32.webp`, `-64`, `-128`, `-300`) generados al subir. No se guarda el original.
- `.htaccess` en `/images/MiFoto/`: `Require all denied`. Las fotos salen solo por `com_mifoto`, que comprueba el grupo permitido (opción del admin) y envía `Cache-Control` + `ETag`.

## Subida (seguridad)
- Solo POST con token CSRF y sesión propia (el id nunca viene del cliente).
- Tamaño máximo, tipo real con `finfo` + `getimagesize`, límite de píxeles (anti bomba de descompresión).
- Se recodifica entero con GD/Imagick a WebP: elimina EXIF, polyglots y código incrustado. Lado mayor 300 px, proporción respetada, sin ampliar.
- Límite de subidas por usuario/hora. Borrado total al borrar el usuario.
- Rutas con `realpath` dentro de la carpeta. Nombre del cliente ignorado.

## Estilo (solo admin)
Forma (círculo, cuadrado, esquinas suaves), marco (grosor/color), sombra. CSS por clases en `media/com_mifoto/`, con variables CSS. No se toca la imagen. Sin foto: círculo con iniciales y color estable por usuario.

## Pantallas (a diseñar con los skills de diseño)
1. Perfil de usuario: zona de subida (arrastrar/clic), recorte previo, vista previa en la forma elegida, quitar foto.
2. Admin · Opciones: grupos que pueden subir y ver, tamaño máximo, estilo con vista previa en vivo.
3. Admin · Fotos: rejilla con búsqueda y borrado/moderación.
4. Guía de uso: `{user}`, Engage y la API.

## Recorte (decidido 26/10/2026)
Paso opcional en el navegador al elegir el archivo: marco con la forma que fijó el admin, mover y ajustar, botón «Usar la foto entera». Se envía solo la parte elegida, pero el servidor revalida todo igual (el recorte es comodidad, no seguridad). JS propio sin librerías. El admin puede desactivarlo en Opciones; activo por defecto.

## Estado a 26/10/2026 (v0.2.0, paquete `dist/pkg_mifoto_0.2.0.zip`)
Hecho: componente (permisos, opciones, servicio con `getPhoto`/`getPhotos`/`avatarHtml`, entrega, endpoints subir/quitar, pantalla Fotos con búsqueda, ocultar, mostrar y quitar, límite de subidas por hora), plugin de usuario (widget en el perfil con recorte; moderación en la edición de usuarios del administrador), plugin de contenido (`{user}`, `{user 42}`, `{user size=96}`), plugin de Engage, guía en `docs/GUIA.md`. Adaptador del mapa hecho en el repo del mapa (`ProfilePhoto.php`, campo `av` en `RankingController::row()` y `TripRepository::forMap`, JS y CSS), sin commit allí. 224 pruebas automáticas de MiFoto pasan (PHP y node) y 20 del adaptador del mapa; el widget se probó en un navegador real con la subida simulada.
NO probado todavía: instalación y funcionamiento dentro de un Joomla 6 real (Chas lo prueba con el ZIP).
Falta: evento `onMiFotoGetPhoto` para plugins, probar el orden Engage/Gravatar con Engage 3.4.12, cambiar el archivo de idioma `COM_MAPAVIAJES_REPORT_REASON_` (fallo previo de las pruebas del mapa, no es de MiFoto).

## Pendiente de confirmar
- Sintaxis exacta del shortcode.
- Joomla 6: usar solo API no obsoleta (SubscriberInterface, service provider, DI). Engage ya está probado en 6.1.4 con plugins de este estilo.

## Engage (confirmado 26/10/2026)
- Plugin del grupo `engage`, normal de Joomla 5/6. Eventos `onAkeebaEngageUserAvatarURL` (usuario `Joomla\CMS\User\User`, tamaño px) y `onAkeebaEngageUserProfileURL`.
- Respuesta: añadir a la lista, sin sustituirla: `$event->setArgument('result', array_merge($event->getArgument('result', []), [$url]));`
- Sin foto: no responder ni tocar `result`.
- URLs del propio dominio, sin comillas ni `javascript:`.
- Orden de plugins: Engage coge la primera URL no vacía y Gravatar (modos por defecto) siempre responde con un avatar genérico local. Solución sólida: Engage 3.4.12 preferirá una foto real a una genérica. Mientras tanto, poner MiFoto antes que Gravatar en Sistema → Plugins.
- Las fotos van por `com_mifoto` (permisos por rol), así que la URL que se devuelve es la del endpoint, no la ruta del archivo.
