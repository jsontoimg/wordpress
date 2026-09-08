=== jsontoimg ===
Contributors: skndan
Tags: images, templates, gutenberg, shortcode, render
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Embed jsontoimg templates as signed images with a shortcode or Gutenberg block.

== Description ==

jsontoimg connects WordPress to the [jsontoimg Render API](https://docs.jsontoimg.com/docs/api). Editors pick a template, override layer text, and the plugin mints a signed image URL for an `<img>` tag.

The signed URL is the CMS path (`POST /api/v1/img/sign`). The visitor's browser fetches `GET /api/v1/img/{template}` with an HMAC `sig` — no API key is sent to the frontend. The first unique GET bills the template owner; later hits are cache hits.

= Features =

* Settings page for API key and optional self-hosted base URL
* `[jsontoimg]` shortcode
* Gutenberg block with template picker, schema-driven layer fields, and Set as Featured Image
* PHP helpers for themes: `jsontoimg_sign_url()`, `jsontoimg_render_image()`, `jsontoimg_list_templates()`
* Transient cache so posts do not mint a URL on every view

== Installation ==

1. Upload the `jsontoimg` folder to `/wp-content/plugins/`
1. Activate the plugin through the Plugins menu
1. Go to Settings → jsontoimg
1. Paste an API key from [Integrations → API keys](https://app.jsontoimg.com/integrations/api-keys)
1. Click Test connection, then Save changes

== Frequently Asked Questions ==

= How do I embed an image in a post? =

Use the jsontoimg block, or a shortcode:

`[jsontoimg template="DESIGN_ID" format="png" alt="Hello" class="aligncenter" layers='{"headline":{"text":"Hello"}}']`

= Why does an image_url layer fail? =

The Render API only accepts `image_url` values that are:

* `https://…/file.png` (also `.jpg`, `.jpeg`, `.webp`), or
* a dashboard Assets path `/api/uploads/files/{id}`

`https://placehold.co/600x400` is rejected (no file extension). `https://placehold.co/600x400.png` may pass validation and still fail when the renderer fetches it, because placeholder CDNs often block server-side downloads. Use a real public file from the Media Library or the jsontoimg Assets library.

For `logo`, send JSON like `{"logo":{"image_url":"https://example.com/logo.png"}}` — not a `layers` query you hand-edit. The plugin mints that signed URL for you.

= How do I set the featured image? =

In the post editor, insert the jsontoimg block, pick a template, then click **Set as Featured Image**. The plugin downloads the signed render into the Media Library and assigns it as the post thumbnail. The post type must support featured images.

= Does every page view consume a credit? =

No. The plugin caches the signed URL. The first unique GET of that URL bills once; repeats are cache hits on jsontoimg.

= Where is the API key stored? =

In the WordPress options table (`jsontoimg_api_key`). It is never printed in frontend HTML.

= Can I change the API origin? =

Yes. Base URL defaults to `https://app.jsontoimg.com`. A trailing `/api/v1` is stripped. Change it only if you self-host.

= How do I rebuild the Gutenberg block? =

From this plugin directory:

`npm install && npm run build`

== Changelog ==

= 1.0.0 =
* Initial release: settings, signed-URL shortcode, Gutenberg block, REST helpers.
