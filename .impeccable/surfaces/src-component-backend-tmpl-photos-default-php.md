---
version: 1
slug: "src-component-backend-tmpl-photos-default-php"
primary_target: "src/component/backend/tmpl/photos/default.php"
related_targets: ["src/component/backend/tmpl/settings/default.php"]
---

## Scope

Operate. The four administrator screens of com_mifoto: Panel (dashboard), Fotos (moderation), Ajustes (look, upload, storage), Permisos (who can do what, read-only summary; changes go to Joomla's own com_config). Frame: header band with four tabs, like Akeeba Engage's panel; no side menu.

## Task

The administrator (a hospitality professional, little IT) opens MiFoto now and then: sees at a glance whether everything works and who still has no photo, moderates a photo in one click, changes how photos look. Real data only (photos, users without photo, installation checks, permissions per group).

## Direction contract

THESIS: the site's users are an album of numbered stickers; the panel shows the album page by page, what is stuck and what is still missing. Refuses the default dashboard of identical stat cards over a table.
OWN-WORLD: bottle-green album cover band, die-cut sticker slots with printed numbers, a dotted empty slot for users without photo, gold foil only on the active tab and the main action, one red stamp for problems; flat colour, no gradients on text, system sans in heavy weights with tabular numerals, no serif, no cream-and-terracotta look: paper is a cool off-white, ink near-black. Dark mode: deep green cover, charcoal pages.
STORY: the administrator understands in one glance "38 of 50 stuck, 12 missing, 2 hidden, all installation checks fine", and acts from the sticker itself (hide, remove, open the user).
FIRST VIEWPORT: green cover band with the title and a big "38 / 50" progress plus a segmented bar with one segment per user; gold-edged tabs hang under it; below, a sticker page (6 columns x 4 rows) at left with numbered stickers and dotted missing slots; at right a status card ("Todo en orden" or exactly what is missing) and a "Quién puede qué" mini-matrix; the primary action "Moderar fotos" in gold sits top right.
FORM: user-chosen card "Álbum de cromos" (IMPECCABLE'S PICK, rank 1 of 7 grounded candidates); seed key fe6862d4.
FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Constraints

No external requests (no CDN fonts or scripts). Follow Joomla's light/dark setting. Keyboard operable, visible focus, reduced motion respected, no information by colour alone. Spanish copy first, English second; text must survive translation.
