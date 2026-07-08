=== KDNA PDF Flipbook ===
Contributors: krulldna
Tags: pdf, flipbook, elementor, viewer, pdf.js
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A self-contained PDF-to-flipbook plugin with a fully styleable Elementor widget.

== Description ==

KDNA PDF Flipbook turns a PDF uploaded in the WordPress backend into a slick, responsive, page-flipping viewer on the front end. It is built for a pitching scenario. Each client gets their own page, built from a single custom post type entry, on which one or more PDF flipbooks are displayed, for example a welcome letter followed by a proposal.

The plugin is completely self-contained. It relies on no paid services and no third-party plugin, only two open-source JavaScript libraries that ship inside the plugin folder, PDF.js and StPageFlip. Nothing loads from an external server at runtime.

Key points:

* Configurable custom post type, named on first run.
* One entry per client, holding a repeater of flipbooks.
* Easy backend with the standard WordPress media uploader and drag to reorder.
* Elementor widget with a dynamic source and a matching dynamic tag.
* Live rendering in the browser with PDF.js, realistic page turns with StPageFlip.
* Crisp zoom that re-renders at a higher resolution.
* Per-page access code that gates the widget, with admin bypass.
* Every front-end control is a toggle and every element is styleable inside the widget.

== Installation ==

1. Go to Plugins, Add New, Upload Plugin and upload the ZIP.
2. Activate the plugin.
3. Complete the first-run setup to name your custom post type.
4. Adjust settings later under Settings, KDNA PDF Flipbook.

== Frequently Asked Questions ==

= Does it call any external servers? =

No. PDF.js and StPageFlip are bundled inside the plugin, and PDFs are served from your own media library.

= Do I need Elementor? =

The front-end viewer is delivered as an Elementor widget, so Elementor is required to place it on your client template.

== Changelog ==

= 1.0.0 =
* Stage 0: Plugin scaffold, configurable custom post type, first-run setup and settings screen.
* Stage 1: Flipbooks drag-to-reorder repeater and per-page access code, saved as post meta.
* Stage 2: Front-end viewer rendering PDFs with bundled PDF.js and flipping them with bundled StPageFlip, with desktop spread, single cover and mobile single-page swipe. Conditional asset loading.
* Stage 3: Front-end sidebar listing every flipbook with an icon and name, active highlight, and switching between flipbooks without a full page reload. Sidebar sits beside the viewer on desktop and above it on mobile.
* Stage 4: Crisp zoom that re-renders the current page with PDF.js at a higher resolution, with wheel or button zoom and drag to pan on desktop, pinch and drag on mobile, plus a fullscreen control. Adds the viewer toolbar.
* Stage 5: Full toolbar control suite, each able to be shown or hidden (arrows, thumbnails, zoom, fullscreen, table of contents, download, share, flip sound, deep-linking), plus fade-away or persistent toolbar behaviour and light, dark or custom chrome. Defaults read from settings for now.
* Stage 6: Per-page access code gate. A coded entry shows a code box in the viewer area, verified over admin-ajax with a nonce, then remembered for the session with a short-lived signed cookie. Admins and post editors bypass the gate, and pages with no code show the flipbooks straight away.
* Stage 7: Elementor widget built for Atomic markup, with the full Content tab (source, default view, control toggles, autoplay, toolbar behaviour, chrome theme, sidebar) and a registered dynamic tag that returns a client entry's flipbook PDF. The temporary content preview is retired in favour of the widget.
* Stage 8: Full Style tab. Every front-end element is styleable, scoped to the widget instance with Elementor's selector keyword: viewer, sidebar, toolbar and buttons, thumbnails, page numbers and captions, zoom and fullscreen, loading spinner and the access code box.
