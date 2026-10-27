# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Primary: the person who runs a Joomla site (Chas, and clients like him: people from hospitality who know a bar well and little IT). They open the MiFoto screens now and then to check that all is well, moderate a photo, or change how photos look. Secondary: a moderator with only the "moderate photos" permission. The visitors who upload photos (registered users of the site) never see these screens; they use a small widget in their own profile.

Language of the interface: Spanish first, English second.

## Product Purpose

MiFoto gives a Joomla 5/6 site profile photos that are safe by construction (real file type check, re-encoded to WebP at 300 px, no metadata, closed storage folder, per-hour upload limit). The administrator decides who may upload, who may see photos, and how they look (shape, frame, shadow). Other parts of the site (Akeeba Engage comments, the Mapa de viajes component, articles with `{user}`, any module) read the photo through MiFoto and decide where and how to show it.

Success for the admin screens: in a few seconds the administrator knows whether everything works, can moderate a photo in one click, and can change the look without reading documentation.

## Positioning

A photo provider for the whole Joomla site, not for one component: one place to upload, moderate and style profile photos, with permissions by user group, usable by any extension. Competing avatar extensions tie the photo to one component or to a third-party service (Gravatar).

## Operating Context

- Joomla 6.2 administrator (template Atum) on the site hueva.net; front-end template Helix Ultimate. Components from the same author already installed: Akeeba Engage (fork, own admin panel) and Mapa de viajes (own admin panel, own side menu, hides Joomla's).
- Screens of this component: Panel (dashboard), Fotos (list and moderation), Ajustes (look, upload rules, storage), Permisos (who can do what; saving permissions stays Joomla's own com_config).
- Settings are saved through Joomla's own com_config `component.save` (token, permissions, validation).
- Photos are only served by the component; the admin screens show them through the same checked delivery.

## Capabilities and Constraints

- Data available for a dashboard: number of photos, visible and hidden, uploads per week, users without a photo, last uploads, upload attempts per hour, installation checks (plugins enabled, GD with WebP, folder protected, module placed, permissions assigned to groups).
- No external requests from the admin screens: no CDN fonts, no remote scripts, no third-party tracking.
- Must follow the administrator's light/dark setting.
- The frame is a header with tabs (like Akeeba Engage's panel), not a side menu. Four tabs: Panel, Fotos, Ajustes, Permisos.
- Terminology: "foto de perfil", "moderar" (ocultar, mostrar, quitar), "Registrado", grupos de usuarios de Joomla.

## Brand Commitments

None fixed. The user binds these: modern, "a 2026 dashboard", surprising; explicitly NOT the default blue Joomla admin look; coherent with their other two components in structure (cards, status chips, quick access) without copying their colours.

## Evidence on Hand

Two screenshots of the user's own panels as structural references: `E:\Temp\claude\D--Claude-CartaLogo-Code\29c29c9d-be62-4c14-9869-3eb40d0b6c46\images\3.png` (Akeeba Engage) and `...\4.png` (Mapa de viajes). Real numbers come from the database; nothing is invented. There are no real user photos yet on the test site except what the administrator uploads.

## Product Principles

1. Easy first: a hospitality professional must understand each screen without help; one obvious action per card.
2. Say the state in words and colour, never colour alone: "todo bien" or exactly what is missing.
3. Short answers: numbers and one-line explanations; details live in the guide.
4. Safe by default: destructive actions ask once, and permissions are never changed silently.
5. One look for everything: whatever the admin sets for photos is what every screen shows.

## Accessibility & Inclusion

WCAG 2.2 AA: keyboard operable, visible focus, contrast in light and dark, reduced motion respected, no information by colour alone. Spanish text must fit when translated to English without breaking layouts.
