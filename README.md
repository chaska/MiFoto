# MiFoto

Profile photos for Joomla 5 and 6, as a single package (`pkg_mifoto`) that other extensions can reuse.

- **Safe upload**: real type detected from the bytes, decompression-bomb checks, EXIF orientation, everything re-encoded to WebP (long side 300 px, no distortion, no enlarging). Nothing of the original survives.
- **Private storage**: files under `images/MiFoto/<userId>/`, closed to direct access and served by the component with a per-group permission check on every request.
- **Permissions in one click**: upload / view / moderate, assigned per group from the component's own screen.
- **Admin decides the look**: shape, frame and shadow. Users without a photo get their initials on a stable colour.
- **Legal notice and consent**: before uploading, the person sees who will see the photo and where, and must tick two boxes (owner of the photo + data-protection consent); the acceptance is recorded.
- **Reports and removal**: anyone can report a photo from the enlarged view; moderators review them in a Reports tab; Joomla privacy requests (export / delete) are supported.
- **Enlarge on click**: any MiFoto photo opens bigger, in any extension (switchable).
- **Works with anything**: PHP service, `{user}` tag, module, Akeeba Engage plugin; the map component has its own adapter.

## What is in the package

| Piece | Purpose |
|---|---|
| `com_mifoto` | Admin screens (Panel, Photos, Settings, Permissions), photo delivery, upload endpoints, PHP service |
| `plg_user_mifoto` | Photo block in the user profile and moderation in the administrator's user edit screen |
| `plg_content_mifoto` | `{user}` tags in articles and modules |
| `plg_engage_mifoto` | Photos in Akeeba Engage comments |
| `plg_privacy_mifoto` | MiFoto data in Joomla privacy requests (export and delete) |
| `mod_mifoto_photo` | The profile photo block, placeable anywhere |

## For visitors to see the photos

Three things at once: the **View photos** permission for the Public group, the photo in the **visible** state, and the plugin that draws it ("Engage - MiFoto", "Content - MiFoto") with **Access = Public**. The installer sets the access; the Panel and Settings warn if it is not right.

## Documentation

- [User guide](docs/GUIDE.md) (Spanish: [GUIA.md](docs/GUIA.md))
- [Design notes](docs/DESIGN.md) (Spanish: [DISEÑO.md](DISEÑO.md))

## Build and test

```
python build/build.py        # builds dist/pkg_mifoto_<version>.zip
php tests/01-imagen.php      # also 02, 04, 05, 06, 07, 08; node tests/03-recorte.js and 08-lightbox.js
```

## Status

Version 0.5.0 (consent and legal notice on upload, reports and removal, enlarge-on-click, privacy-request plugin). Unit tests pass and the admin screens were checked in a browser against a simulated Joomla; it has not yet been tested end to end in a real Joomla 6 installation.

License: GPL-3.0-or-later.
