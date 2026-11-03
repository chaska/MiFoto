# Cómo traducir MiFoto a tu idioma

MiFoto viene en inglés (en-GB) y español (es-ES). Cualquiera puede añadir otro idioma. No hace falta saber programar: solo traducir textos.

Esta guía es para alguien que conoce Joomla y poco de informática. Si algo no se entiende, abre un [issue](https://github.com/chaska/MiFoto/issues) y se mejora la guía.

## En resumen

1. Crea el idioma con una orden (o copia las carpetas a mano).
2. Traduce el texto que hay entre comillas, nada más.
3. Compruébalo con la herramienta.
4. Pruébalo en tu Joomla.
5. Envíalo.

## 1. Crear el idioma nuevo

Necesitas PHP en línea de comandos y una copia del proyecto (descárgala de GitHub o haz un *fork*).

```
php build/idiomas.php plantilla fr-FR
```

Cambia `fr-FR` por tu código: dos letras en minúscula, un guion y dos letras en mayúscula (`pt-BR`, `de-DE`, `it-IT`). Tiene que ser el mismo código que usa Joomla para ese idioma.

La orden copia en-GB en una carpeta nueva `fr-FR` dentro de **cada** extensión y añade los archivos a los manifiestos (los `.xml` que le dicen a Joomla qué archivos de idioma instalar). Después solo queda traducir.

**Si prefieres hacerlo a mano:** dentro de cada carpeta `language/` copia `en-GB` con el nombre nuevo y ejecuta `php build/idiomas.php manifiestos fr-FR` para que los manifiestos los incluyan.

Son 6 extensiones y 7 carpetas `language/en-GB` (el componente tiene dos: administración y parte pública):

| Extensión | Carpeta |
|---|---|
| Componente, administración | `src/component/backend/language/` |
| Componente, parte pública | `src/component/frontend/language/` |
| Módulo «MiFoto - Foto del usuario» | `src/modules/mifoto_photo/language/` |
| Plugins (4: Usuario, Contenido, Engage, Privacidad) | `src/plugins/<grupo>/mifoto/language/` |

Cada carpeta tiene archivos `.ini` y `.sys.ini`. Hay que traducir **los dos**. Los `.sys.ini` son los textos que Joomla enseña al instalar y en las listas de extensiones.

## 2. Qué traducir y qué no

Cada línea tiene este aspecto:

```
COM_MIFOTO_W_CHOOSE="Upload photo"
```

**Traduce** solo lo que está entre comillas: `"Upload photo"`.

**No toques:**

- El nombre de la clave (`COM_MIFOTO_W_CHOOSE`). Si lo cambias, Joomla no encuentra el texto.
- Los marcadores: `%s`, `%d`, `%1$s`, `%2$d`. Se sustituyen por nombres, números o fechas. Tienen que salir **los mismos y en la misma cantidad**. Puedes cambiar su orden dentro de la frase si usas `%1$s`, `%2$s` (con número).
- Las etiquetas HTML, si las hay. Déjalas donde están y traduce solo el texto de dentro. No se pueden añadir etiquetas, atributos ni direcciones web nuevos.
- Los nombres propios: MiFoto, Joomla, Engage, Gravatar, WebP.
- Las líneas que empiezan por `;` (son comentarios).

**Comillas dentro del texto:** no escribas `"` dentro de un texto. Usa las comillas de tu idioma («texto», “texto”) o escribe `\"`.

**Saltos de línea:** cada texto va en **una sola línea**. Guarda el archivo en UTF-8 **sin BOM**.

**Textos legales:** el aviso de subida y las casillas de consentimiento (`COM_MIFOTO_W_NOTICE…`, `COM_MIFOTO_W_CONSENT_…`) citan leyes de protección de datos. Tradúcelos con cuidado, sin cambiar lo que dicen. Si tu país tiene otra ley, avísalo en el envío en lugar de cambiarla tú.

## 3. Tratamiento y glosario

Usa un tono claro y cercano, sin jerga, como en el inglés y el español (que tutea: «Tu foto», «Sube tu foto»). Sé coherente en todos los archivos.

Glosario de partida (adáptalo a como Joomla traduce estos términos en tu idioma; mira tu propio Joomla):

| Inglés | Español (es-ES) |
|---|---|
| profile photo | foto de perfil |
| upload / change / remove | subir / cambiar / quitar |
| crop | recortar |
| hide / show | ocultar / mostrar |
| moderate / moderator | moderar / moderador |
| report (a photo) | denunciar |
| consent | consentimiento |
| privacy policy | política de privacidad |
| enlarge | ampliar |
| album / sticker | álbum / cromo |
| settings | ajustes |
| permissions | permisos |
| guest / registered user | invitado / usuario registrado |

Mantén igual un mismo término en toda la traducción. Si Joomla ya tiene una palabra para algo en tu idioma (por ejemplo «Permisos» o «Usuarios»), usa esa.

## 4. Comprobarlo con la herramienta

```
php build/idiomas.php comprobar fr-FR
```

Compara tu traducción con en-GB (la referencia) en todas las extensiones. Falla (y dice dónde) si:

- falta o sobra alguna clave;
- los marcadores no coinciden;
- hay un texto vacío donde el original tiene texto;
- aparece una etiqueta HTML, un atributo o una dirección web que no está en el original de esa clave;
- hay `<script>`, `on...=`, `javascript:`, `data:`, caracteres de control o comillas sin escapar;
- el archivo no tiene el formato `CLAVE="texto"`, o falta en el manifiesto.

Con `php build/idiomas.php comprobar todos` se comprueban todos los idiomas. También avisa (sin fallar) si más de la mitad de los textos siguen iguales al inglés. `python build/build.py` repite esta comprobación antes de empaquetar: un idioma roto no llega al ZIP.

## 5. Probarlo en tu Joomla

1. Tu Joomla tiene que tener instalado el idioma (el paquete de idioma de Joomla para `fr-FR`) y elegido en tu perfil o en Sistema › Idiomas.
2. Genera el paquete: `python build/build.py`. Queda en `dist/pkg_mifoto_<versión>.zip`.
3. En Joomla, Extensiones › Gestionar › Instalar, sube el ZIP (se instala encima).
4. Cambia tu idioma de administración al nuevo y recorre las pestañas de MiFoto: Panel, Fotos, Ajustes, Denuncias y Permisos.
5. Mira también la web: el bloque «Foto de perfil» de tu perfil (aviso y casillas), una foto ampliada y el formulario de denuncia.

Busca textos cortados, que se salgan de su sitio o que suenen raros.

## 6. Enviarlo

**Opción A: pull request.** Haz un *fork* del repositorio en GitHub, crea una rama, sube las carpetas del idioma y los manifiestos cambiados y abre un *pull request*. La plantilla te da una lista de comprobación.

**Opción B: issue.** Si no usas Git, abre un [issue de traducción](https://github.com/chaska/MiFoto/issues/new?template=traduccion.yml), adjunta los archivos (puedes comprimirlos en un ZIP) y se incorporarán.

No incluyas datos personales en los archivos ni en el texto del envío.

## 7. Criterios de aceptación

Una traducción se acepta cuando:

1. **Está completa:** todas las extensiones, todos los archivos `.ini` y `.sys.ini`, todas las claves.
2. **Pasa la herramienta:** `php build/idiomas.php comprobar xx-XX` sin errores.
3. **Alguien la ha revisado:** una persona que habla el idioma lee la traducción (no solo la herramienta) y no encuentra errores de sentido ni textos engañosos. Los textos legales, con especial cuidado.
4. **Se ha probado** en un Joomla real, al menos las pestañas de MiFoto y el bloque del perfil.
5. **Es tu trabajo y se publica con la misma licencia** que el proyecto: GNU GPL v3 o posterior.

Un idioma a medias no se incorpora (se queda en una rama hasta que esté completo). Cuando entra, sale en la siguiente versión del paquete.

## 8. Créditos

Si quieres, tu nombre o alias aparecerá en [`NOTICE.md`](../NOTICE.md) (sección «Traducciones»). **Es opcional.** Nunca hace falta dar tu nombre real, tu correo ni ningún dato personal: puedes usar solo tu usuario de GitHub o un alias, o no aparecer.
