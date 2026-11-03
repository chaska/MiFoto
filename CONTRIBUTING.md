# Colaborar con MiFoto

Gracias por querer ayudar. Esto es lo básico.

## Traducciones

Se necesitan idiomas nuevos. La guía paso a paso está en [`docs/TRADUCIR.md`](docs/TRADUCIR.md). Resumen: `php build/idiomas.php plantilla xx-XX`, traducir, `php build/idiomas.php comprobar xx-XX` y enviar un *pull request* o un [issue de traducción](https://github.com/chaska/MiFoto/issues/new?template=traduccion.yml).

## Errores y mejoras

Abre un [issue](https://github.com/chaska/MiFoto/issues) y cuenta qué esperabas, qué ha pasado y con qué versión de Joomla, PHP y MiFoto. **No pongas datos personales** (correos, nombres de tu web, claves, IP, fotos de personas reales): si hace falta una captura, tapa antes todo lo que identifique.

Si envías código: no cambies la versión (la gestiona quien publica), añade una prueba en `tests/` y ejecuta las pruebas (`php tests/0X-….php` y `node tests/0X-….js`) y `python build/build.py` antes de abrir el *pull request*.

## Seguridad

**No publiques un fallo de seguridad en un issue ni en un pull request.** Usa la pestaña **Security › Report a vulnerability** de este repositorio en GitHub: es un aviso privado que solo ven los responsables. Cuenta cómo reproducirlo y qué impacto tiene. Se responderá por ahí y se publicará el arreglo antes que los detalles.

## Licencia

Al enviar algo, aceptas que se publique con la licencia del proyecto: GNU GPL v3 o posterior ([`LICENSE`](LICENSE)).
