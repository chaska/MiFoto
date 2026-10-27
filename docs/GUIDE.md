# MiFoto — user guide

Profile photos for Joomla 5 and 6. Each person uploads their photo from their profile; you decide who may upload, who may view and how it looks. Other parts of the site (comments, the map, modules, articles) use the photo and decide where to show it.

(Spanish version: [GUIA.md](GUIA.md))

## 1. Install

1. Extensions → Manage → Install → upload `pkg_mifoto_<version>.zip` as it is, without unzipping. **To update, install the new ZIP on top, without uninstalling anything**: Joomla keeps the options, the permissions, the photos and which plugins you have enabled.
2. The package installs one component, three plugins and one module. The three plugins are **switched on automatically the first time** (Joomla installs them disabled, which is why sometimes nothing shows up); afterwards your choice is respected. Check in Extensions → Plugins:
   - **User - MiFoto**: puts the photo in the profile and the moderation in the administrator's user edit screen.
   - **Content - MiFoto**: the `{user}` tags in articles and modules.
   - **Engage - MiFoto**: the photo in Akeeba Engage comments (only if you use Engage).
3. The module **MiFoto - User photo** (Extensions → Modules → New) puts the same upload/crop/change/remove block **wherever you place it**, independent of the template. It is the safe way if your template overrides the "Edit your profile" page and the block does not appear inside the form (see section 9). Assign it to that page and the position you want. Do not use it together with the block inside the form: it would appear twice.
4. Components → MiFoto → **Permissions** tab. Permissions start unassigned, so no normal user can upload or view photos until you grant them. **They are granted with one click**: press the box for the group and the permission (Upload, View, Moderate); it is saved immediately in Joomla's permissions and you can undo it. A box with a thin stroke means the group already has it because it inherits it from a group above; Super Users can always do everything. If something is denied on purpose, change it under "Advanced Joomla permissions" (button on that screen):

| Permission | What it is for | Usually given to |
|---|---|---|
| Upload own photo | Upload, change and remove their own | Registered |
| View other people's photos | See other people's photos | Registered (or Public if you want them open) |
| Moderate photos | Hide, show and remove anyone's | Manager, Administrator |

Super User can always do everything.

> **For visitors who are not logged in to see the photos, three things are needed at once:**
> 1. The "View other people's photos" permission for the **Public** group.
> 2. The photo is in the **visible** state (not hidden).
> 3. The plugin that draws it has **Access = Public** in Extensions → Plugins: "Engage - MiFoto" for comments and "Content - MiFoto" for `{user}`. Joomla only loads a plugin for people whose access level matches, so with "Registered" visitors see nothing even if the other two are right. The installer sets them to Public, and the Panel and Settings warn you if they are not.

## 2. Settings

Components → MiFoto → **Settings** tab. They are saved through Joomla's own save (with its permission check).

- **Folder**: where photos are stored (default `images/MiFoto`; it must be inside `images/`). It is closed to direct access with a protection file (`.htaccess` and `web.config`): photos are only served through the component, which checks the permission on every request. If your server is **nginx**, that file is not honoured: block the folder in the server configuration (the Panel warns you).
- **Maximum upload size**: what is rejected before processing. The stored photo weighs far less.
- **Uploads per hour and person**: attempts allowed per hour (failed ones count too). `0` = unlimited.
- **Crop before upload**: the user chooses which part of the photo is kept.
- **Style**: shape (round, square, soft corners), frame (none, thin, thick) and shadow. Applied to every photo on the site, always the same; it is only the look, the image does not change.

## 3. What each person sees

**The user**, in their profile (the page where they change name, email and password), sees the "Profile photo" block: *Upload photo*, *Change photo*, *Remove photo*. When choosing a photo they can frame it (drag and zoom) or use it whole. Without a photo, their initials are shown on a fixed colour.

**You**, on opening Components → MiFoto, see the **album**: the cover says how many users already have a photo, with a bar of one block per user; below, each user is a numbered sticker (with their photo) or a dotted slot if they have not uploaded one yet. On the right, the installation **status** ("All in order", or what is missing, with a button to fix it) and a summary of who can do what.

In the **Photos** tab you see all of them with search and filters (All, Visible, Hidden, Without photo). Clicking a sticker shows it large on the right, with the buttons *Hide* (only the owner and moderators see it) or *Show*, *Remove* and *View user*. The same is available, without leaving the page, in each user's edit screen in the administrator ("Profile photo" tab). A moderator cannot upload a photo on someone else's behalf: only the owner can.

## 4. How the photo is stored

- It is identified by its real content, not by name or extension. Files that are not photos, files that hide code and "bombs" (images tiny in bytes but huge in pixels) are rejected.
- It is redrawn entirely: nothing of the original survives (no camera data, no GPS, nothing embedded).
- It is stored as WebP, with the long side at 300 px, without distortion and without enlarging. Copies at 128, 64 and 32 px are also made.
- The file name is random and changes on every upload; the public address uses a random key, never the user number.

## 5. `{user}` tags (content plugin)

Written in the text of an article or module:

| Tag | Result |
|---|---|
| `{user}` | The photo of whoever is reading the page (nothing for a guest) |
| `{user 42}` | The photo of user number 42 |
| `{user size=96}` | The reader's, at 96 px |
| `{user 42 size=96}` | User 42's, at 96 px |

Sizes from 16 to 300; without `size=` the plugin option default is used. With `{user}` (your own) and no photo, your initials are shown. With `{user 42}` the photo only appears if that person has one, it is visible and the reader may see it; otherwise nothing is shown (so nobody can discover user numbers).

> If you use Joomla page cache, `{user}` (the reader's photo) may be repeated between visitors. For those pages use `{user 42}` or disable the cache on them.

## 6. Akeeba Engage

With "Engage - MiFoto" enabled, comments use the MiFoto photo. Two things:

- Engage takes the first address the plugins give it. "Engage - Gravatar" always answers with a generic avatar, so put **Engage - MiFoto before Engage - Gravatar** in Extensions → Plugins (sort by order). The next Engage version (3.4.12) will make a real photo always win over the generic avatar.
- For **visitors who are not logged in** to see the photo, "Engage - MiFoto" must be enabled with **Access = Public** (see section 1). It only gives the photo on live pages, never in e-mails or shared caches (Engage 3.4.13 or later says so with a `page`, `email` or `static` context).
- If you chose an "avatar field" for users in Engage's options, Engage looks at it before the plugins and that field wins.

## 7. The map

The map (Travel map component) uses the photos automatically if MiFoto is installed: on the podium and in the ranking they replace the coloured initial. Without MiFoto, or for someone without a photo, nothing changes. It respects the "View other people's photos" permission.

## 8. For programming another extension

```php
$service = \Joomla\CMS\Factory::getApplication()->bootComponent('com_mifoto')->getPhotoService();

$photo  = $service->getPhoto($userId, 64);            // PhotoData or null (no photo, or the viewer may not see it)
$photos = $service->getPhotos([$id1, $id2], 64);      // several with ONE query: [userId => PhotoData]
$html   = $service->avatarHtml($userId, 64, null, 'Marta');   // <img> or initials, with the admin's style, already escaped
```

- `PhotoData` carries `url` (path on the same site), `size`, `width`, `height` and `version`.
- `getPhoto` and `getPhotos` apply the view permission of **whoever is looking** (the logged-in user, or the one you pass as the last parameter). `null` means both "has none" and "you may not see it".
- First check that MiFoto is there: `ComponentHelper::isEnabled('com_mifoto')`, and wrap it in `try/catch` if your extension can work without it. The map adapter (`ProfilePhoto.php`) is an example.
- For your own filter (for example "friends only") apply it yourself before asking for the photo. MiFoto knows nothing about friends.
- Joomla permissions: `$user->authorise('mifoto.view', 'com_mifoto')`, `mifoto.upload`, `mifoto.moderate`.
- The photo address needs the visitor's session (it belongs to the site itself and browsers do not let other sites embed it). It does not work for emails or for linking from outside.

## 9. If something does not work

| Symptom | What to check |
|---|---|
| The block does not appear in the profile | 1) Is "User - MiFoto" enabled? 2) If the "Edit your profile" page is customised (template with an override in `html/com_users/profile/edit.php`), many templates only print the basic fields and skip the ones plugins add. Two solutions: use the **module** "MiFoto - User photo" (section 1), or in that `edit.php` add, just before the Save and Cancel buttons: `<?php foreach ($this->form->getFieldsets() as $fieldset) : ?><?php if ($fieldset->name === 'core') { continue; } ?><?php echo $this->form->renderFieldset($fieldset->name); ?><?php endforeach; ?>` |
| "Your group cannot upload a photo" | The "Upload" box for your group in the Permissions tab |
| Visitors who are not logged in see no photos | The three things in section 1: View permission for Public, visible photo and plugin with Access = Public |
| Uploads fine but others do not see it | The "View other people's photos" permission, and that the photo is not hidden in Photos |
| "The server cannot process photos" | The PHP **GD with WebP** extension is missing on the hosting |
| "Too many attempts in the last hour" | The "Uploads per hour and person" setting |
| Comments show the generic avatar | Plugin order: MiFoto before Gravatar |

A photo you hide, or whose group loses the permission, may still show in the browser of someone who already has it for up to 10 minutes (the cache time; changed in Settings). The photo of a blocked user stops being served.

## 10. Uninstall

Uninstalling the package **does not delete data**: the MiFoto tables and the photos in `images/MiFoto` stay, so if you install it again everything is still there. What is lost on uninstall are the options (shape, limits…) and the permissions, because Joomla deletes the extension; set them again. To delete everything for real: remove the tables `#__mifoto_photos` and `#__mifoto_limits` and the folder `images/MiFoto` by hand.
