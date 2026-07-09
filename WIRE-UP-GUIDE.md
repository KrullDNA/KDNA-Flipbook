# KDNA PDF Flipbook, wire-up guide

A short guide to getting the plugin live on a client page. It covers naming the
custom post type, creating a client entry, adding flipbooks and an access code,
and placing and styling the widget on the client template.

Everything runs on your own site. PDF.js and StPageFlip are bundled inside the
plugin, so nothing loads from an outside server.

---

## 1. Install and activate

1. Go to **Plugins, Add New, Upload Plugin** and upload `kdna-flipbook-v1.0.4.zip`.
2. Click **Install Now**, then **Activate**.
3. On activation you are taken to a short setup screen.

## 2. Name the custom post type

On activation you are taken to a setup screen. If you are not redirected
automatically, a prompt appears at the top of the admin with a **Name your
pages** button, and you can always reach it from **Settings, KDNA PDF Flipbook**.

Choose what to call the pages that hold your flipbooks. Each entry is one client
page, so you might call these Clients, Pitches or Proposals.

1. Enter a **Singular label** (for example, Client).
2. Enter a **Plural label** (for example, Clients).
3. Enter a **Slug** (for example, kdna-client). Lowercase letters, numbers and
   hyphens, up to 20 characters.
4. Click **Save and continue**.

You can change these later under **Settings, KDNA PDF Flipbook**. That page also
holds the front-end defaults, which the widget can override per placement.

## 3. Create a client entry

1. In the admin menu, open your new post type (for example, **Clients**) and
   click **Add new**.
2. Give it a title, for example the client's name.
3. Publish the entry, or save a draft while you build it.

## 4. Add flipbooks

On the client entry you will see a **Flipbooks** panel.

1. Click **Add flipbook**.
2. Type a **Name**, for example Welcome letter.
3. Click **Choose PDF** and pick or upload the PDF from the media library.
4. Optionally click **Choose icon** to set a small icon for the sidebar. You can
   pick one of the built-in icons, or click **Upload SVG or image** to use your
   own SVG or image. If you skip this, a default document icon is used.
5. Repeat **Add flipbook** for each PDF, for example a welcome letter followed by
   a proposal.
6. Drag the rows by the handle on the left to set their order. The top row shows
   first.
7. Update the entry to save.

## 5. Set an access code, optional

In the **Access code** panel on the same screen:

- Leave it **empty** to keep the page open to everyone.
- Enter a **code** to protect the page. Visitors then see a code box where the
  flipbooks would be, and must enter the code to view them.

Admins and users who can edit the entry always see the flipbooks, with no code
prompt. Once a visitor enters the correct code it is remembered for a few hours
so they are not asked again while they browse.

## 6. Place the widget on the client template

The viewer is delivered by an Elementor widget, so it appears wherever you place
that widget. Usually you place it once on the template that all client pages use.

1. Edit the client template in Elementor. This is normally a Theme Builder single
   template for your post type, or the entry itself.
2. Search the widget panel for **KDNA PDF Flipbook** and drag it in.
3. By default the widget reads the **current page**, so the one template serves
   every client automatically.

### Content tab

- **Source**: current page (default), a specific page, or a dynamic tag.
- **Default view**: which flipbook opens first, the start page, and autoplay.
- **Controls**: show or hide the arrows, thumbnails, zoom, fullscreen, table of
  contents, download, share, flip sound and deep-linking.
- **Sidebar**: show or hide it. On mobile the sidebar moves above the flipbook.
- **Toolbar and chrome**: toolbar over the document (fade-away or persistent) or
  below the flipbook, a light, dark or custom colour theme, and an editable
  reader hint shown at the top of the sidebar or below the flipbook.

## 7. Style the widget

Open the **Style** tab to style every part, scoped to that widget so nothing
leaks to other widgets:

- **Viewer**: maximum width, aspect ratio, background, page background, corner
  radius and page shadow.
- **Sidebar**: background, width, padding, item spacing, typography, icon colour
  and size, active and hover styling, and dividers.
- **Toolbar and buttons**: background, colours, hover colours, borders, icon
  colour and size, padding, button size, radius and spacing.
- **Thumbnails**: background, size, spacing, active and hover, and border.
- **Page numbers and captions**: typography and colour.
- **Zoom and fullscreen**: overlay backgrounds.
- **Loading spinner**: colour and size.
- **Access code box**: background, input styling, button styling and hover,
  heading and helper-text typography, and error-message colour.

## 8. Optional, use the flipbook PDF elsewhere

A dynamic tag called **KDNA Flipbook PDF** is registered. Anywhere Elementor
offers a dynamic value for a URL, for example a button link, choose
**KDNA PDF Flipbook, KDNA Flipbook PDF** to pull a client entry's PDF directly.

## 9. After each change

Run the standard post-update routine so your changes show:

1. Regenerate Elementor CSS and Data (**Elementor, Tools, Regenerate CSS & Data**).
2. Clear your caching plugin, for example WP Rocket.
3. Hard refresh the front-end page.

## Quick test checklist

- The custom post type appears in the admin menu with your chosen name.
- A client entry shows the Flipbooks repeater and the Access code field.
- Two flipbooks render, with the sidebar listing both.
- Desktop shows a two-page spread with a single cover, mobile shows one page with
  swipe and the sidebar above.
- Zoom keeps fine print sharp, and fullscreen works.
- Each toolbar control can be toggled and works, including deep-linking.
- A set access code gates the page for visitors, and admins bypass it.
- Open dev tools, Console, and confirm there are no errors.
- The viewer does not load its assets on pages without the widget.
