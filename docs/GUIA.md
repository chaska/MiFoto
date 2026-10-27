# MiFoto — guía de uso

Fotos de perfil para Joomla 5 y 6. Cada persona sube su foto desde su perfil; tú decides quién puede subir, quién puede ver y cómo se ve. Otras partes de la web (comentarios, el mapa, módulos, artículos) usan la foto y deciden dónde la enseñan.

## 1. Instalar

1. Extensiones → Gestionar → Instalar → sube `pkg_mifoto_<versión>.zip` tal cual, sin descomprimir. **Para actualizar, instala el ZIP nuevo encima, sin desinstalar nada**: Joomla conserva las opciones, los permisos, las fotos y qué plugins tienes activados.
2. El paquete instala un componente, tres plugins y un módulo. Los tres plugins se **encienden solos la primera vez** (Joomla los instala apagados y por eso a veces no sale nada); después respeta lo que tú decidas. Compruébalo en Extensiones → Plugins:
   - **Usuario - MiFoto**: pone la foto en el perfil y la moderación en la edición de usuarios del administrador.
   - **Contenido - MiFoto**: las etiquetas `{user}` en artículos y módulos.
   - **Engage - MiFoto**: la foto en los comentarios de Akeeba Engage (solo si usas Engage).
3. El módulo **MiFoto - Foto del usuario** (Extensiones → Módulos → Nuevo) pone el mismo bloque de subir/recortar/cambiar/quitar **donde tú lo coloques**, sin depender de la plantilla. Es la forma segura si tu plantilla personaliza la página «Edite su perfil» y el bloque no aparece dentro del formulario (ver el apartado 9). Asígnalo a esa página y a la posición que quieras. No lo uses a la vez que el bloque del formulario: saldría dos veces.
4. Componentes → MiFoto → pestaña **Permisos**. Los permisos empiezan sin asignar, así que ningún usuario normal puede subir ni ver fotos hasta que los des. **Se dan con un clic**: pulsa la casilla del grupo y el permiso (Subir, Ver, Moderar); se guarda al momento en los permisos de Joomla y puedes deshacerlo. Una casilla con trazo fino significa que el grupo ya lo tiene porque lo hereda de uno de arriba; Super Users siempre puede todo. Si algo está denegado a propósito, se cambia en «Permisos avanzados de Joomla» (botón de esa pantalla):

| Permiso | Para qué | Normalmente a |
|---|---|---|
| Subir su propia foto | Subir, cambiar y quitar la suya | Registrado |
| Ver fotos de otros | Ver las fotos de los demás | Registrado (o Público si las quieres abiertas) |
| Moderar fotos | Ocultar, mostrar y quitar la de cualquiera | Gestor, Administrador |

El Super Usuario lo puede todo siempre.

> **Para que los visitantes sin sesión vean las fotos hacen falta tres cosas a la vez:**
> 1. El permiso «Ver fotos de otros» para el grupo **Public**.
> 2. Que la foto esté en estado **visible** (no oculta).
> 3. Que el plugin que las pinta tenga **Acceso = Public** en Extensiones → Plugins: «Engage - MiFoto» para los comentarios y «Contenido - MiFoto» para `{user}`. Joomla solo carga un plugin para quien tenga su nivel de acceso: con «Registered», los visitantes no ven nada aunque las otras dos estén bien. El instalador los deja en Public, y el Panel y Ajustes te avisan si no lo están.

## 2. Ajustes

Componentes → MiFoto → pestaña **Ajustes**. Se guardan con el guardado de Joomla (con su comprobación de permisos).

- **Carpeta**: dónde se guardan (por defecto `images/MiFoto`; tiene que colgar de `images/`). Queda cerrada al acceso directo con un archivo de protección (`.htaccess` y `web.config`): las fotos solo salen por el componente, que comprueba el permiso en cada petición. Si tu servidor es **nginx**, ese archivo no se respeta: bloquea la carpeta en la configuración del servidor (el Panel te avisa).
- **Peso máximo de la subida**: lo que se rechaza antes de procesar. La foto guardada pesa mucho menos.
- **Subidas por hora y persona**: intentos permitidos por hora (cuentan también los fallidos). `0` = sin límite.
- **Recorte antes de subir**: el usuario elige qué parte de la foto se queda.
- **Estilo**: forma (redonda, cuadrada, esquinas suaves), marco (sin, fino, grueso) y sombra. Se aplica a todas las fotos del sitio, siempre igual; es solo el aspecto, la imagen no cambia.

## 3. Lo que ve cada persona

**El usuario**, en su perfil (la página donde cambia nombre, correo y contraseña), ve el bloque «Foto de perfil»: *Subir foto*, *Cambiar foto*, *Quitar foto*. Al elegir una foto puede encuadrarla (arrastrar y zoom) o usarla entera. Sin foto se muestran sus iniciales sobre un color fijo.

**Tú**, al abrir Componentes → MiFoto, ves el **álbum**: la portada dice cuántos usuarios ya tienen foto y una barra con un bloque por usuario; debajo, cada usuario es un cromo numerado (con su foto) o un hueco con puntos si todavía no la ha subido. A la derecha, el **estado** de la instalación («Todo en orden» o lo que falta, con un botón para arreglarlo) y un resumen de quién puede qué.

En la pestaña **Fotos** ves todas con buscador y filtros (Todas, Visibles, Ocultas, Sin foto). Al pulsar un cromo aparece a la derecha en grande, con sus botones *Ocultar* (solo la ven su dueño y los moderadores) o *Mostrar*, *Quitar* y *Ver usuario*. Lo mismo, sin salir de la página, en la edición de cada usuario del administrador (pestaña «Foto de perfil»). Un moderador no puede subir una foto por otra persona: solo el dueño.

## 4. Cómo se guarda la foto

- Se identifica por su contenido real, no por el nombre ni la extensión. Se rechazan los archivos que no son foto, los que esconden código y los que son «bombas» (imágenes minúsculas en bytes pero enormes en píxeles).
- Se vuelve a dibujar entera: no sobrevive nada del original (ni datos de la cámara, ni GPS, ni nada incrustado).
- Se guarda en WebP, con el lado mayor a 300 px, sin deformar y sin ampliar. Además se hacen copias de 128, 64 y 32 px.
- El nombre de archivo es aleatorio y cambia en cada subida; la dirección pública usa una clave aleatoria, nunca el número de usuario.

## 5. Etiquetas `{user}` (plugin de contenido)

Se escriben en el texto de un artículo o un módulo:

| Etiqueta | Resultado |
|---|---|
| `{user}` | La foto de quien está leyendo la página (a un invitado no le sale nada) |
| `{user 42}` | La foto del usuario número 42 |
| `{user size=96}` | La del lector a 96 px |
| `{user 42 size=96}` | La del usuario 42 a 96 px |

Tamaños de 16 a 300; sin `size=` se usa el de las opciones del plugin. Con `{user}` (la tuya) sin foto salen tus iniciales. Con `{user 42}` solo sale la foto de esa persona si la tiene, es visible y quien lee puede verla; si no, no sale nada (así no se pueden descubrir números de usuario).

> Si usas la caché de páginas de Joomla, `{user}` (la foto del lector) puede repetirse entre visitantes. Para esas páginas usa `{user 42}` o desactiva la caché en ellas.

## 6. Akeeba Engage

Con «Engage - MiFoto» activado, los comentarios usan la foto de MiFoto. Dos cosas:

- Engage coge la primera dirección que le dan los plugins. «Engage - Gravatar» siempre responde con un avatar genérico, así que pon **Engage - MiFoto antes que Engage - Gravatar** en Extensiones → Plugins (ordenar por orden). La próxima versión de Engage (3.4.12) hará que una foto real gane siempre al avatar genérico.
- Para que **los visitantes sin sesión** vean la foto, «Engage - MiFoto» tiene que estar activado y con **Acceso = Public** (ver el apartado 1). Solo da la foto en páginas vivas, no en correos ni en cachés compartidas (Engage 3.4.13 o posterior lo indica con un contexto `page`, `email` o `static`).
- Si en las opciones de Engage tienes elegido un «campo de avatar» de usuario, Engage lo mira antes que a los plugins y gana ese campo.

## 7. El mapa

El mapa (componente Mapa de viajes) usa las fotos automáticamente si MiFoto está instalado: en el podio y en el ranking sustituyen a la inicial de color. Sin MiFoto, o para quien no tiene foto, no cambia nada. Respeta el permiso «Ver fotos de otros».

## 8. Para programar otra extensión

```php
$service = \Joomla\CMS\Factory::getApplication()->bootComponent('com_mifoto')->getPhotoService();

$photo  = $service->getPhoto($userId, 64);            // PhotoData o null (sin foto, o quien mira no puede verla)
$photos = $service->getPhotos([$id1, $id2], 64);      // varias con UNA consulta: [idUsuario => PhotoData]
$html   = $service->avatarHtml($userId, 64, null, 'Marta');   // <img> o iniciales, con el estilo del admin y ya escapado
```

- `PhotoData` trae `url` (ruta del propio sitio), `size`, `width`, `height` y `version`.
- `getPhoto` y `getPhotos` aplican el permiso de ver de **quien mira** (el usuario conectado, o el que pases como último parámetro). `null` vale igual para «no tiene» y «no puedes verla».
- Comprueba antes que MiFoto está: `ComponentHelper::isEnabled('com_mifoto')`, y envuélvelo en `try/catch` si tu extensión puede funcionar sin él. El adaptador del mapa (`ProfilePhoto.php`) es un ejemplo.
- Para tu propio filtro (por ejemplo «solo amigos») aplícalo tú antes de pedir la foto. MiFoto no sabe de amigos.
- Permisos de Joomla: `$user->authorise('mifoto.view', 'com_mifoto')`, `mifoto.upload`, `mifoto.moderate`.
- La dirección de la foto necesita la sesión del visitante (es del propio sitio y los navegadores no la dejan incrustar en otras webs). No sirve para correos ni para enlazar desde fuera.

## 9. Si algo no funciona

| Síntoma | Qué mirar |
|---|---|
| No sale el bloque en el perfil | 1) ¿Está activado «Usuario - MiFoto»? 2) Si la página «Edite su perfil» está personalizada (plantilla con override en `html/com_users/profile/edit.php`), muchas plantillas solo pintan los campos básicos y se saltan los que añaden los plugins. Dos soluciones: usa el **módulo** «MiFoto - Foto del usuario» (apartado 1), o en ese `edit.php` añade, justo antes de los botones Guardar y Cancelar: `<?php foreach ($this->form->getFieldsets() as $fieldset) : ?><?php if ($fieldset->name === 'core') { continue; } ?><?php echo $this->form->renderFieldset($fieldset->name); ?><?php endforeach; ?>` |
| «Tu grupo no puede subir foto» | La casilla «Subir» de tu grupo en la pestaña Permisos |
| Los visitantes sin sesión no ven ninguna foto | Las tres cosas del apartado 1: permiso «Ver» para Public, foto visible y plugin con Acceso = Public |
| Sube bien pero los demás no la ven | Permiso «Ver fotos de otros», y que la foto no esté oculta en Fotos |
| «El servidor no puede procesar fotos» | Falta la extensión PHP **GD con WebP** en el hosting |
| «Muchos intentos en la última hora» | Opción «Subidas por hora y persona» |
| En los comentarios sale el avatar genérico | Orden de plugins: MiFoto antes que Gravatar |

Una foto que ocultas, o a cuyo grupo le quitas el permiso, puede seguir viéndose en el navegador de quien ya la tenía hasta 10 minutos (el tiempo de caché; se cambia en Ajustes). La foto de un usuario bloqueado deja de servirse.

## 10. Desinstalar

Desinstalar el paquete **no borra datos**: las tablas de MiFoto y las fotos de `images/MiFoto` se quedan, así que si lo vuelves a instalar todo sigue ahí. Lo que sí se pierde al desinstalar son las opciones (forma, límites…) y los permisos, porque Joomla borra la extensión; vuelve a ponerlos. Para borrarlo todo de verdad: elimina a mano las tablas `#__mifoto_photos` y `#__mifoto_limits` y la carpeta `images/MiFoto`.
