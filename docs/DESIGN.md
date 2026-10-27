# MiFoto — design notes

(Translation of [DISEÑO.md](../DISEÑO.md); the Spanish file is the original.)

Profile photo for Joomla 5 and 6. One package (`pkg_mifoto`) that other extensions can reuse.

## Pieces
| Piece | Purpose |
|---|---|
| `com_mifoto` | Admin screens, serving photos with permissions, public API |
| `plg_user_mifoto` | Adds the photo field to the profile form, uploads/changes/removes |
| `plg_content_mifoto` | `{user}` in articles |
| `plg_engage_mifoto` | Answers `onAkeebaEngageUserAvatarURL`; does not answer when there is no photo |
| `mod_mifoto_photo` | The profile block, placeable anywhere |
| map adapter | Lives in the map repository (field `av`) |

## MiFoto's role: a photo provider, not other people's screens
MiFoto **administers** photos (upload, edit, delete, who may upload, who may view) and **offers** them to any Joomla extension, its own or third-party. Where, how and whether a photo is shown is decided by each consumer in its own settings (Engage, the map, a module, another component). "Friends only", "only in the ranking", etc. are consumer-side filters on top of what MiFoto already allows to be seen.

### Entry points (from most to least coupled)
1. **Joomla ACL (per-role permissions).** Own actions: `mifoto.upload`, `mifoto.view`, `mifoto.moderate`. Any extension can ask `$user->authorise('mifoto.view', 'com_mifoto')`.
2. **PHP service.** `Factory::getApplication()->bootComponent('com_mifoto')->getPhotoService()` with `getPhoto()`, `getPhotos()` and `avatarHtml()`. If the viewer may not see the photo, it returns `null`, the same as if there were none. MiFoto checks the permission; the consumer does not have to.
3. **Events (plugin group `mifoto`).** `onMiFotoGetPhoto` (planned) and those MiFoto fires (`onMiFotoAfterUpload`, `onMiFotoAfterDelete`). Plain `Joomla\Event\Event` with arguments so it works on all of Joomla 5 and 6.
4. **HTTP URL** `index.php?option=com_mifoto&task=photo.show&u=<key>&s=<size>` for JS and templates. It checks session and permission on every request. Sizes: 32, 64, 128, 300.

### Included adapters (they only translate, they do not decide)
- **Engage:** plugin of group `engage`.
- **Map:** fills `av` in the ranking and map data. If the map wants "friends only", it filters with `areFriends()` before asking for the photo.
- **`{user}`:** content plugin.
- **Optional module** "MiFoto - User photo" for template positions.

## What is reused (read from the repos on 26/10/2026)

**From the map** (same author, GPL):
- `Security/ImageProcessor.php` → the whole upload. Identifies by bytes (not name or Content-Type), cross-checks `finfo` with `getimagesize`, reads dimensions before decoding (anti-bomb), checks memory, looks for hidden code, applies EXIF orientation with its own minimal parser, resizes without distortion and re-encodes to WebP. Copied with `longSide=300`, no upscaling, no watermark, no PNG.
- `Security/PrivateStorage.php` → folder with `.htaccess` + `web.config` + `index.html` denying direct access, random names, per-user folder, `realpath` against path escapes.
- `Service/PhotoServer.php` → delivery. Identical 404 for "does not exist" and "not allowed", HMAC `ETag`, `nosniff`, `CSP: default-src 'none'; sandbox`, `Referrer-Policy: no-referrer` and **`Cross-Origin-Resource-Policy: same-origin`**, which replaces a Referer-based `.htaccess`: the browser enforces it and it cannot be forged, so no other site can embed the photos.

**From Engage** (`Helper/Avatar.php`):
- Confirms the event pattern: `PluginHelper::importPlugin('engage')` and dispatch of an `Event`, reading `$result['result']`.
- Engage checks the custom avatar field **first** and plugins afterwards; if the Engage admin configures that field, it wins over MiFoto.
- Engage caches per user and request; MiFoto does the same in its service.

## Storage
- `/images/MiFoto/<userId>/` — Joomla identifies users by id, which never changes.
- Random file names (`<token>-32.webp`, `-64`, `-128`, `-300`) generated on upload. The original is not kept.
- `.htaccess` in `/images/MiFoto/`: `Require all denied`. Photos leave only through `com_mifoto`.

## Upload (security)
- POST only with CSRF token and the user's own session (the id never comes from the client).
- Maximum size, real type with `finfo` + `getimagesize`, pixel limit (anti decompression bomb).
- Fully re-encoded with GD/Imagick to WebP: removes EXIF, polyglots and embedded code. Long side 300 px, proportion kept, no enlarging.
- Uploads per user per hour limit. Full deletion when the user is deleted.
- Paths with `realpath` inside the folder. Client file name ignored.

## Style (admin only)
Shape (circle, square, soft corners), frame (thickness/colour), shadow. CSS by classes in `media/com_mifoto/` with CSS variables. The image is not touched. Without a photo: a circle with initials and a stable colour per user.

## Crop (decided 26/10/2026)
Optional step in the browser when choosing the file: a frame with the shape the admin set, move and adjust, a "Use the whole photo" button. Only the chosen part is sent, but the server revalidates everything (cropping is convenience, not security). Own JS, no libraries. The admin can disable it; on by default.

## State at 28/10/2026 (v0.5.0, package `dist/pkg_mifoto_0.5.0.zip`)
0.5.0 adds: required consent on upload (owner of the photo + data protection) with a notice of who sees the photo and where, recorded per upload in `#__mifoto_consents` (no IP or browser, withdrawn when the photo is removed); reports from the enlarged photo (`#__mifoto_reports`, a Reports tab, hide/remove/dismiss); a privacy plugin for Joomla export/delete requests; and "Allow enlarging the profile photo" (a lightbox that recognises MiFoto photos by their address, so it works in any extension). 354 automatic tests. Not tested in a real Joomla: the lightbox, the consent block and the privacy plugin (its event classes follow Joomla 5.4).

## Earlier: 0.4.1
Full redesign of the four administration screens with the "sticker album" world (green cover with a sentence and a one-block-per-user bar, index tabs, numbered stickers and dotted slots; gold only on the active tab and the main action). Panel (paged album, filters with counts, latest additions, installation status and permissions summary), Photos (grid, search, viewer with Hide/Show/Remove), Settings (live preview, saves through com_config) and Permissions (one-click boxes writing to Joomla's permissions, with inheritance, undo and a locked Super Users row). Product context in `PRODUCT.md`; direction contract in `.impeccable/surfaces/`.
0.4.1 fixes the findings of two security reviews (locked permission saving that creates its asset, people lists for moderators only, `{user N}` no longer reveals accounts, safer upload and folder path, public access for the Engage/Content plugins, `page` context in the Engage plugin). Tested here: 275 automatic tests (66 image, 45 rules, 19 crop, 63 avatar and tag, 16 limits, 38 permissions and segments, 28 security and installer); the real templates were rendered with a simulated Joomla and viewed in a browser (desktop, mobile, light and dark), including the permissions click against the real logic. A mobile overflow found in that review was fixed.
NOT yet tested: any of this inside a real Joomla 6 (SQL queries, saving rules in `#__assets`, controllers). The Barlow Condensed typeface is not bundled: without it the system condensed font is used.

## To confirm
- Exact shortcode syntax.
- Joomla 6: use only non-deprecated API (SubscriberInterface, service provider, DI). Engage is already proven on 6.1.4 with plugins of this style.

## Engage (confirmed 26/10/2026)
- Plugin of group `engage`, a normal Joomla 5/6 plugin. Events `onAkeebaEngageUserAvatarURL` (user `Joomla\CMS\User\User`, size in px) and `onAkeebaEngageUserProfileURL`.
- Response: add to the list, do not replace it: `$event->setArgument('result', array_merge($event->getArgument('result', []), [$url]));`
- Without a photo: do not answer or touch `result`.
- URLs of the site's own domain, no quotes or `javascript:`.
- Plugin order: Engage takes the first non-empty URL and Gravatar (default modes) always answers with a generic local avatar. Robust fix: Engage 3.4.12 will prefer a real photo over a generic one. Until then, put MiFoto before Gravatar in System → Plugins.
- Photos go through `com_mifoto` (per-role permissions), so the returned URL is the endpoint's, not the file path.
