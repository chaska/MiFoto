# MiFoto — guía de uso

Fotos de perfil para Joomla 5 y 6. Cada persona sube su foto desde su perfil; tú decides quién puede subir, quién puede ver y cómo se ve. Otras partes de la web (comentarios, el mapa, módulos, artículos) usan la foto y deciden dónde la enseñan.

## 1. Instalar

1. Extensiones → Gestionar → Instalar → sube `pkg_mifoto_<versión>.zip` tal cual, sin descomprimir. **Para actualizar, instala el ZIP nuevo encima, sin desinstalar nada**: Joomla conserva las opciones, los permisos, las fotos y qué plugins tienes activados.
2. El paquete instala un componente, tres plugins y un módulo. Los tres plugins se **encienden solos la primera vez** (Joomla los instala apagados y por eso a veces no sale nada); después respeta lo que tú decidas. Compruébalo en Extensiones → Plugins:
   - **Usuario - MiFoto**: pone la foto en el perfil y la moderación en la edición de usuarios del administrador.
   - **Contenido - MiFoto**: las etiquetas `{user}` en artículos y módulos.
   - **Engage - MiFoto**: la foto en los comentarios de Akeeba Engage (solo si usas Engage).
3. El módulo **MiFoto - Foto del usuario** (Extensiones → Módulos → Nuevo) pone el mismo bloque de subir/recortar/cambiar/quitar **donde tú lo coloques**, sin depender de la plantilla. Es la forma segura si tu plantilla personaliza la página «Edite su perfil» y el bloque no aparece dentro del formulario (ver el apartado 9). Asígnalo a esa página y a la posición que quieras. No lo uses a la vez que el bloque del formulario: saldría dos veces.
4. Componentes → MiFoto → **Permisos**. Los permisos empiezan sin asignar, así que ningún usuario normal puede subir ni ver fotos hasta que los des:

| Permiso | Para qué | Normalmente a |
|---|---|---|
| Subir su propia foto | Subir, cambiar y quitar la suya | Registrado |
| Ver fotos de otros | Ver las fotos de los demás | Registrado (o Público si las quieres abiertas) |
| Moderar fotos | Ocultar, mostrar y quitar la de cualquiera | Gestor, Administrador |

El Super Usuario lo puede todo siempre.

## 2. Opciones

Componentes → MiFoto → Opciones.

- **Carpeta**: dónde se guardan (por defecto `images/MiFoto`). Queda cerrada al acceso directo: las fotos solo salen por el componente, que comprueba el permiso en cada petición.
- **Peso máximo de la subida**: lo que se rechaza antes de procesar. La foto guardada pesa mucho menos.
- **Subidas por hora y persona**: intentos permitidos por hora (cuentan también los fallidos). `0` = sin límite.
- **Recorte antes de subir**: el usuario elige qué parte de la foto se queda.
- **Estilo**: forma (redonda, cuadrada, esquinas suaves), marco (sin, fino, grueso) y sombra. Se aplica a todas las fotos del sitio, siempre igual; es solo el aspecto, la imagen no cambia.

## 3. Lo que ve cada persona

**El usuario**, en su perfil (la página donde cambia nombre, correo y contraseña), ve el bloque «Foto de perfil»: *Subir foto*, *Cambiar foto*, *Quitar foto*. Al elegir una foto puede encuadrarla (arrastrar y zoom) o usarla entera. Sin foto se muestran sus iniciales sobre un color fijo.

**Tú**, en Componentes → MiFoto → Fotos, ves todas las fotos con buscador. Puedes *Ocultar* una (solo la ven su dueño y los moderadores), *Mostrar* y *Quitar*. Lo mismo, sin salir de la página, en la edición de cada usuario del administrador (pestaña «Foto de perfil»). Un moderador no puede subir una foto por otra persona: solo el dueño.

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

Tamaños de 16 a 300; sin `size=` se usa el de las opciones del plugin. Si la persona no tiene foto salen sus iniciales. Si quien lee no tiene permiso de ver fotos, no sale nada.

> Si usas la caché de páginas de Joomla, `{user}` (la foto del lector) puede repetirse entre visitantes. Para esas páginas usa `{user 42}` o desactiva la caché en ellas.

## 6. Akeeba Engage

Con «Engage - MiFoto» activado, los comentarios usan la foto de MiFoto. Dos cosas:

- Engage coge la primera dirección que le dan los plugins. «Engage - Gravatar» siempre responde con un avatar genérico, así que pon **Engage - MiFoto antes que Engage - Gravatar** en Extensiones → Plugins (ordenar por orden). La próxima versión de Engage (3.4.12) hará que una foto real gane siempre al avatar genérico.
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
| «Tu grupo no puede subir foto» | Permiso «Subir su propia foto» en Opciones → Permisos |
| Sube bien pero los demás no la ven | Permiso «Ver fotos de otros», y que la foto no esté oculta en Fotos |
| «El servidor no puede procesar fotos» | Falta la extensión PHP **GD con WebP** en el hosting |
| «Muchos intentos en la última hora» | Opción «Subidas por hora y persona» |
| En los comentarios sale el avatar genérico | Orden de plugins: MiFoto antes que Gravatar |

## 10. Desinstalar

Desinstalar el paquete **no borra datos**: las tablas de MiFoto y las fotos de `images/MiFoto` se quedan, así que si lo vuelves a instalar todo sigue ahí. Lo que sí se pierde al desinstalar son las opciones (forma, límites…) y los permisos, porque Joomla borra la extensión; vuelve a ponerlos. Para borrarlo todo de verdad: elimina a mano las tablas `#__mifoto_photos` y `#__mifoto_limits` y la carpeta `images/MiFoto`.
