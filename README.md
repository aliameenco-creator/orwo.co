# ORWO Film Community — WordPress theme

A native WordPress block theme for **orwo.co**. Original navy palette, Lora / Fira Sans typefaces, source copy, logo, photographs, two articles and nine film references. Responsive editorial layouts, gentle reduced-motion-aware animation, gallery lightbox and click-to-load YouTube players. No paid builder or proprietary migration service is needed.

## Install

1. Back up your WordPress site. Upload **orwo-co.zip** under **Appearance → Themes → Add New → Upload Theme**, then activate it.
2. Open **Appearance → ORWO Setup** and click **Complete ORWO setup**. Keep the tab open while images are imported in small batches. Setup publishes the original pages and posts and sets Home as the front page.
3. Inspect the setup report. Existing records are skipped, not overwritten. Re-running setup preserves page content, template-part edits and imported media. A record you delete after import is not silently recreated.
4. Set **Settings → General → ORWO Film Community form recipient**. It defaults to the source site's public sales address. Send a real enquiry and confirm receipt; also check **Enquiries**. Configure your host's authenticated mail transport if notifications do not arrive.

## Edit the website

| Content | WordPress location |
| --- | --- |
| Home, Shot on ORWO, Gallery, Blog, Contact, Cookie Policy | Pages → Edit |
| Two original articles and new articles | Posts → Edit / Add New |
| Header, footer and navigation | Appearance → Editor → Patterns → Template parts |
| Colours and typography | Appearance → Editor → Styles |
| Images and alt text | Media Library; image blocks can also be replaced directly in Pages |
| Contact and newsletter labels / confirmation text | Settings → ORWO Forms |
| Submitted messages | Enquiries |
| Theme releases | Appearance → ORWO Film Community Updates |

Use the List View in the editor to select a section, then edit its headings, text, images or buttons. Forms are Shortcode blocks: `[orwoco_form id="contact"]` and `[orwoco_form id="subscribe"]`. Both preserve the original visible Name, Email and Message fields. All three are required because the source definition did not specify required flags. The newsletter form saves and emails sign-ups; connect/export these to your own mailing workflow. It does not send campaigns.

The Blog page contains an editable Query block and updates when posts are published. Film cards contain native editable images linked to their original videos. Seven YouTube references and one Vimeo film load on click. The remaining Vimeo reference (Goodwood 1998) is preserved as a link; Vimeo returned 404 for its public metadata on 2026-10-12. The 12-image gallery uses native image blocks. Clicking a photo opens a keyboard-dismissable lightbox.

## URLs, import safety and SEO

Original main paths and both `/blog/<article>/` paths are retained. `/home` redirects to `/`; `/_blog` and `/blog/categories` to `/blog/`. The cookie policy remains `/pages/cookie-policy/`, with a small Legal parent record for the nested path. `/contact/` recreates the homepage's original form as a full page. `/search` forwards to native WordPress search. The old proprietary map endpoint is replaced by a Google Maps link. Unknown routes return 404.

New pages use imported SEO titles and the source homepage description. Canonicals point to the new installation. Basic metadata defers to Yoast/Rank Math if installed; initial Yoast title/description fields are seeded. After installing Yoast, run its SEO data optimization tool. No SEO scores or fabricated descriptions are added.

Setup moves only the untouched WordPress “Hello world!” starter post to draft. Other existing content is preserved. Theme updates replace theme files; WordPress page and Site Editor edits stay in the database. A saved Site Editor template can override future file-template changes. Before an update, back up both files and database.

## Boundaries

- Installing this theme is separate from changing DNS or updating the public orwo.co site. No hosted deployment has been performed.
- Actual mail delivery cannot be certified by local tests. Check recipient, delivery and spam filtering on the host.
- The archived cookie policy contains references to Strikingly-era analytics and cookie consent. Review it against the WordPress site's actual services before launch. This theme injects no analytics or advertising trackers; video providers load only when requested.
- Source article copy is preserved, including its product claims. The historical source buy-film link returned HTTP 403 during checking; the header uses the working ORWO shop instead. The Clothing tile had no source destination and stays unlinked.
- External films, shop inventory and social profiles remain external. No third-party account, checkout or newsletter campaign backend has been migrated.
- This uses the native WordPress block editor, not Elementor. No Elementor Pro license is required.
- Source screenshots and immutable crawl evidence remain in the migration-engine workspace, outside this theme ZIP. User-maintained manual-data is never imported.

## Build and validation

Runtime requirements: WordPress 6.6+, PHP 8.1+ (mbstring and image support recommended); PHP ZIP extension for verified GitHub updates.

In the migration-engine workspace run `python tools/build_orwoco_theme.py`. The standalone repository can package existing generated content with `python scripts/package.py`.

Sandbox: `python tools/prepare_orwoco_wordpress.py`, then PHP's local server on `127.0.0.1:8899` with `tools/orwoco_wordpress_router.php`. Run `python tools/check_orwoco_theme.py`. The sandbox intercepts mail and never contacts form recipients.

Theme PHP/CSS/JS is GPL-2.0-or-later. Lora and Fira Sans are bundled under their included SIL Open Font Licenses. Site photography, logo and copy remain the respective owners' content; the source repository is intended for the authorized site owner.
