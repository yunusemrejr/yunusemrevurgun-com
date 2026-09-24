# Yunus Emre Vurgun / Yemre

Current YunoBot implementation: see [C++ / WebAssembly](dev/yunobot-wasm/README.md). The Wasm worker supersedes the JavaScript inference pipeline described in the earlier release notes below.

Personal website and publishing tools. Server-rendered PHP; no application framework or frontend runtime build is required for deployment.

## Architecture

`index.php` loads environment configuration, session/CSRF helpers, admin middleware and the route table in `router/Router.php`. Public views share `views/includes/ui.php`; authenticated views share `views/admin/includes/`. Models use PDO. Most tables are created lazily, with separate SQLite/MySQL definitions.

- Production: MySQL on Namecheap, Apache/LiteSpeed-compatible rewrites in `.htaccess`, Cloudflare in front. The repository handoff records PHP 8.2 FPM; verify the running version with the host when deploying. Local checks for this change ran on PHP 8.5.
- Local: SQLite in `dev/test_db.sqlite`; explicitly choose SQLite when starting the server to avoid using a configured MySQL database.
- Public UI: plain HTML, CSS, JavaScript; self-hosted Fraunces headings and Inter text/controls, with a warm charcoal, parchment and single-amber-accent palette. Shared tokens and fonts are in `assets/css/variables.css`; public components are in `ui-rebuild.css`. The visual language is deliberately editorial: typography, spacing, and hairline rules carry hierarchy; there are no decorative WebGL/WebGPU scenes, no generative backgrounds, and no entrance or scroll effects. Motion is limited to 100–250ms state transitions, and every page reads fully with animation disabled.
- Admin: jQuery 3.6, Bootstrap 5.3.2, Bootstrap Icons and Font Awesome from their existing CDNs. `admin.css` owns components; `admin-space.css` owns the responsive shell; page styles and scripts are loaded only for the relevant section.
- Travel: Leaflet 1.9.4, database-backed locations/images, static JSON fallback for an empty database. Images come from uploads or the existing travel assets.
- YunoBot: local neural intent recognition, reviewed English/Turkish answers, source passage retrieval, and a public blog snapshot endpoint. No prompt is sent to a remote model. See the reproducible training and evaluation workflow below. The old WASM ranker and duplicated rule engine have been replaced.
- Landing: a static professional masthead (name, current role, facts, actions, framed portrait) under the standard navbar — no staged hero, no canvas. The study photograph appears once, framed with a caption, beside the profile summary.
- Retired decoration: the `dev/scripts/vgpu-hero/` and `dev/scripts/vgpu-pages/` sources remain for reference but no shipped page loads them; the built WebGL/WebGPU bundles, the decorative loader, the travel 3D globe (and its Three.js CDN), and the landing image dialog were removed. The 2D Leaflet travel map is the only map.
- Decorative cameo: `assets/images/hampton.gif` is the archived 2001 hamsterdance.com dance loop with its white background removed — only white connected to the frame border, so the character's own white fur stays — and halved to 58x69. `/`, `/travel`, `/more`, `/science-corner` and `/yunobot` park one copy in a footer corner, at the end of the category filters or on the chat status bar through `ui_render_hampton()`. The markup is hidden from assistive tech, never focusable, sits in space the page already owns, and `hampton-still.png` replaces the motion when the visitor prefers reduced motion. `dev/scripts/build-hampton-cameo.sh` rebuilds both files from the archived original and verifies frame count, transparency and loop; it needs network access to web.archive.org plus curl, ffmpeg, ImageMagick and Pillow.
- Media: gallery and travel upload workflows, local music/video media, external video embeds loaded on demand, downloads linking to release binaries, a normal footer link to the existing Gumroad bookstore, database-controlled social profiles and tracker snippets.
- Contact: `api/contact.php`, CSRF/rate limiting/captcha, `models/Contact.php`. No email was sent during QA.
- Social publishing: Mastodon and Bluesky services run after an update is saved; separate retry/test endpoints. Credentials remain in environment configuration. No social post was sent during QA.

## Page inventory

| Area | Routes and behavior |
| --- | --- |
| Identity | `/`, `/about`, `/contact`; existing biography, professional details, links and contact form |
| Work | `/portfolio`; database projects with filters, technology lists and stable `#project-ID` anchors |
| Writing | `/blog`, `/blog/SLUG`; published posts, paginated list, author/reading details and related writing |
| Personal archive | `/updates`, `/updates/ID`, `/rmrp`, `/rmrp/ID`; paginated entries and individual detail pages |
| Media | `/gallery`, `/travel`, `/music`, `/videos`, `/downloads` |
| Interests | `/post-code`, `/science-corner`, `/comedy`, `/yunobot` |
| Discovery | `/more`, `/search`, `/sitemap`; search results are noindex |
| Policies/errors | `/privacy`, `/terms`, `/cookies`, `/404`; unknown URLs return 404, maintenance returns 503 |
| Machine-readable | `/sitemap.xml`, `/blog.xml`, `/updates.xml`, `/rmrp.xml`, `/llms.txt`, `/robots.txt` |
| Admin | Login/logout, dashboard, blog, updates, memories, portfolio, gallery/albums, travel/photos, music/links, videos, downloads, socials, settings, tracker codes, search and export |

The homepage's latest-writing section is populated only from published database entries. No invented projects, testimonials, statistics or biography were added.

## Deployment

The configured Git remote is `namecheap`, targeting a bare repository on the host. The existing handoff describes a post-receive hook checking out `main` into the website document root. `git ls-remote namecheap refs/heads/main` was verified read-only on 2026-09-08 and returned `b86d62039a98e20fbd46fd6f3608cb63e3c53cca`, matching local HEAD at the start of this work. The server hook was inspected on 2026-09-08: it checks out main into the document root; the host CLI runs PHP 8.2.33.

After reviewing the complete diff, committing the intended files, and approving the release:

```sh
git push namecheap main
```

The expected hook output is `Deployed yunusemrevurgun.com from main`. Then check the public pages, an authenticated admin session, `/sitemap.xml`, all feeds, 404 responses, and cache headers through the real domain. PHP configuration changes in `.user.ini` may take several minutes to apply.

This release includes the reviewed website, admin, SEO and YunoBot improvements. Earlier formatting-only changes to travel/GPU files and the knowledge pack were reviewed and reverted before making functional changes. Duplicate design copies and abandoned `.pi` task output were removed; the original design assets remain in `assets/images/`. Useful shared theme/navigation work was retained and refined. Travel image fallback now terminates instead of alternating between missing images. Avoid broad FTP mirrors: scripts in `dev/scripts/` include legacy utilities with old allowlists.

Production `.env`, uploads and database content are outside Git. Keep them separate from deployments and backups of code. Versioned assets use `filemtime` query strings. HTML and admin pages must not be put behind an unconditional Cloudflare cache rule.

## Validation

```sh
DB_CONNECTION=sqlite DB_NAME="$PWD/dev/test_db.sqlite" php -S 127.0.0.1:8817 -t . index.php
php dev/qa/regression.php
php dev/qa/render_fixtures.php
php dev/qa/media_fixtures.php
php dev/qa/yunobot_snapshot.php
python dev/qa/http_smoke.py http://127.0.0.1:8817
```

The PHP regression test uses an in-memory SQLite database. The HTTP test is restricted to localhost and uses existing local test fixtures, including `dev/tests/login_as_admin.php`; it does not create or publish content. Never expose the development directory on the public host. Apache already blocks it.

Checks cover sanitizer attack cases, Unicode/formatting preservation, published-only sitemap entries, no local sitemap overwrites, grouped travel queries, editor module selection, public/admin landmarks, status codes, canonical URLs, valid XML feeds and CSRF failures. Browser testing covers mobile layouts, menu focus restoration, gallery dialog Escape cancellation and explicit browser-draft recovery.

Local fixtures do not reproduce every production media file. Production MySQL, Apache/Cloudflare behavior, email delivery, social posting and real file uploads still need a release smoke test on the actual host; they were not exercised destructively from this environment.

## Public design verification

Public form boundaries and focus rings are explicit; native controls use the dark
color scheme. Placeholder and pagination text use readable secondary ink. Chat
source links, hints, and music hover/selected controls use foregrounds appropriate
to their actual surfaces. Admin theme tokens and hamster assets are unchanged.

Verify the public UI against the local SQLite preview with the smoke suite and
the PHP QA scripts (see “Verify before committing”). The retired
`dev/scripts/vgpu-hero` browser suite tested the removed decorative scene
(canvas, pause control, image dialog) and no longer applies; its sources stay
for reference only. Rendered review is done with screenshots at desktop, tablet
and mobile widths instead.

## Technical SEO and ongoing discovery

The sitemap is rendered from the database on request. Publishing/unpublishing changes its contents without requiring manual regeneration. Compatibility file snapshots from existing publishing hooks use atomic replacement. The HTTP endpoint uses an ETag and a five-minute revalidation window.

Each paginated archive has its own canonical URL. Page size is retained when it changes content; layout and tracking parameters are omitted. Out-of-range pages return 404. Search and administration stay out of the index. Metadata is emitted once; article data is escaped safely. RSS survives arbitrary XML characters in titles and descriptions. Article markup is allowlisted, and internal search links land on individual entries or a specific project anchor.

These changes improve crawlability, usability and content discovery. They do not guarantee traffic or rankings from age alone. Useful original writing, accurate project descriptions and external references still determine whether there is demand for the pages. Search Console can track actual impressions, queries and indexing; no account was connected or property changed in this pass.

Implementation guidance: [Google's sitemap documentation](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap), [pagination and canonicals](https://developers.google.com/search/docs/specialty/ecommerce/pagination-and-incremental-page-loading), and [ranking systems](https://developers.google.com/search/docs/appearance/ranking-systems-guide).

## YunoBot training, sources and limitations

- `dev/yunobot-nn/training.json` contains intent examples, separate from the browser application. Equivalent English/Turkish intents share labels. The deterministic trainer removes conflicting duplicates and balances rare classes.
- `assets/js/yunobot/text.js` is shared by training and inference: Unicode normalization, Turkish diacritics, words, character trigrams and word bigrams. The embedding classifier exports int8 weights with a full-precision softmax head.
- The runtime uses an out-of-scope class, probability margins and vocabulary support to abstain. These scores are model scores, not measured probabilities that an answer is true.
- `answers.js` holds reviewed answers with public source links. It avoids stale repository/country counts, unsupported availability, guessed personal details, and claims that the model needs no download.
- `dev/yunobot-kb/corpus.json` stores 933 passages from 165 public URLs, fetched on 2026-09-08. The builder preserves paragraphs and removes menus, author cards, related-post blocks and other unrelated text. `build.mjs` creates the shipped source pack deterministically from this corpus.
- `kb.js` combines BM25, title/heading relevance, query coverage, and a small neural tie-break. Excerpts remain verbatim and link to the source; they may contain the author's historical or speculative statements. Retrieval is not fact verification or a general-purpose LLM.
- In production, the page fetches `/api/yunobot-knowledge.php` once without credentials or prompt text. It refreshes the latest 200 published blog articles and uses the public URL manifest to remove unpublished blog sources. The response has ETag/five-minute revalidation. If unavailable, the bundled corpus remains usable. Other archives refresh through the corpus build below.
- Conversation context is held in memory. Clear creates a fresh conversation; exports include source links. Navigation is an explicit link rather than an automatic popup. The service worker only caches the public chat page and assets, not APIs or admin responses.

```sh
node dev/yunobot-nn/train.mjs
node dev/yunobot-nn/test-runtime.mjs
node dev/yunobot-nn/test-engine-e2e.mjs

# Read-only refresh from the public website; then an offline pack build.
python3 dev/yunobot-kb/refresh.py
node dev/yunobot-kb/build.mjs
node dev/yunobot-kb/test-kb.mjs
```

The source refresh fails without replacing the corpus if a page cannot be fetched. Its temporary fetch cache lives in ignored `dev/_work/` and expires after one hour. Commit the generated weights/source pack together with their training data and source corpus. Live API behavior is tested with isolated fixtures; production smoke checks remain part of release verification.

The behavioral suite covers 47 cases/checks, and the source suite covers 16 including relevant excerpts, new article discovery, unpublishing and offline fallback. The neural-only evaluation reports its held-out accuracy and misses explicitly; a correct top intent does not establish general conversational intelligence.

## Companion page improvements

Post-Code and Science Corner share searchable category filters, result counts, a complete topic index, stable entry links and matching CollectionPage/ItemList structured data. The decorative one-page pagination is removed. Content stays visible without JavaScript. Entrance animations use progressive enhancement and respect reduced motion.

Music has keyboard-accessible seeking, truthful play/mute labels and visible playback failures; it no longer intercepts keyboard shortcuts across the whole document. Video embeds have descriptive titles and preserve keyboard focus. Music, videos and downloads expose structured collections tied to real card IDs. Comedy defers Tenor animations until requested. The auto-opening floating book widget and its third-party proxy fetches are replaced by a normal Books footer link, keeping controls unobstructed. The Explore page provides clearer descriptions and links across the smaller sections. Mobile checks cover 320px and 390px widths.

## Admin hardening (2026-09-09)

Password and Cloudflare Turnstile login remain unchanged. Routed admin actions,
direct admin APIs and model write guards now share session validation. Mutations
reject invalid CSRF types, unsupported methods, cross-site browser submissions and
requests larger than PHP's configured body limit. CSRF tokens remain stable across
tabs. Direct access to view templates is denied by Apache.

Turnstile verification also checks the configured site's hostname. Client IP
headers are trusted only when the connection peer belongs to Cloudflare's published
IPv4/IPv6 ranges (checked 2026-09-09); other forwarded headers cannot change the
rate-limit identity. If the host already restores REMOTE_ADDR, that address is used.

Gallery deletion removes travel references in the same transaction. Travel location
deletion cleans up its own uploads after commit and preserves shared gallery files.
Repeated gallery linking is idempotent; referenced filenames come from the database.
Media deletion refuses traversal paths and symlinks. No new schema migration is required.

Additional local checks: `php dev/qa/admin_security.php` and
`python3 dev/qa/admin_http_security.py` (local preview on port 8817 only).
