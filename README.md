# Kkkonrad_PageSpeed

Serves every `pub/media` JPEG/PNG on storefront pages as WebP. Only what Hyvä
does not already do for page speed.

- **How:** a plugin on `Controller\ResultInterface::renderResult` (`sortOrder -10`, before
  the full page cache stores the page) rewrites media URLs in the HTML, plain and
  JSON-escaped, to `<file>.webp`. The copy is written next to the original
  (`a/b.jpg` → `a/b.jpg.webp`, GD, quality 80) the first time a page needs it.
- **Only the URL changes.** No `<picture>`: Hyvä's product cards and gallery swap
  `src` from Alpine, and a `<source>` would override that.
- **Skipped:** a file that is missing at render time (it will be resized later), a
  WebP that comes out larger than the original, and anything past 2 s of conversion
  in one request. Those stay JPEG/PNG until the page is next rendered.
- Copies are refreshed when the original is newer. Flushing the catalog image cache
  deletes them along with their originals.

Measured on staging (mobile): home page images 953 → 456 KB, category 297 → 164 KB.

No configuration. Disable with `bin/magento module:disable Kkkonrad_PageSpeed`.
Browsers without WebP (Safari < 14) would get broken images.

Not included, because Hyvä already does it: JS/CSS merging, bundling, minification,
deferred JS, lazy loading. Not included at all: AVIF (GD encodes it too slowly to do
inline), responsive `srcset` for CMS images, delayed third-party scripts, critical CSS.
