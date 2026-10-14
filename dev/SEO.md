# SEO architecture — yunusemrevurgun.com

How search engines and AI systems are told about this site. Everything here
is invisible: no visible text, layout or component depends on it. Guiding
rules: every date is a real content timestamp (never "now", never a
template mtime pretending to be content), structured data only restates
what the page visibly shows, and there is exactly one URL per page.

## Canonicalization (`.htaccess`, `views/includes/ui.php`)

- One host: `https://yunusemrevurgun.com` (`www` and `http` 301 to it).
- One slash policy: no trailing slash except `/` (301; real directories and
  non-GET requests untouched). `/home`, `/index`, `/index.php` 301 to `/`.
- Generator scripts (`/api/sitemap.php`, `/api/llms.php`, feed scripts) 301
  to their pretty URLs (`/sitemap.xml`, `/llms.txt`, `/blog.xml`, ...),
  matched on `THE_REQUEST` so internal rewrites never loop.
- Self-referencing `<link rel="canonical">` from `ui_render_head()`; faceted
  variants (`?view=`, `?topic=`, ...) get `noindex,follow` with a
  self-canonical; tracking parameters canonicalize to the clean page.
- `/search` and raw `/uploads/slop/` files carry `X-Robots-Tag:
  noindex,follow`; admin/API carry `noindex,nofollow`.

## Sitemaps (`models/Sitemap.php`, `api/sitemap.php`)

`/sitemap.xml` is an **index** of four children, all rendered live from the
database (serving never writes files), each with honest `<lastmod>`:

| Child | Contents |
|---|---|
| `sitemap-pages.xml` | Static pages + project documentation, with `image:` entries for page/portrait/cover images |
| `sitemap-posts.xml` | Blog posts (with featured images), updates, memories, slop pages |
| `sitemap-images.xml` | Gallery + travel photographs and download covers (`image:title/caption`) |
| `sitemap-videos.xml` | `video:` entries for `/videos` (thumbnail, player/content URL, duration, publication date) |

Listing pages take the newest row they list as their `lastmod`. No
`<priority>`/`<changefreq>` (ignored by engines). Snapshots are also written
to disk on production when a publishing hook regenerates them.

## Freshness signalling

1. **Sitemap `<lastmod>`** as above.
2. **IndexNow** (`models/IndexNow.php`): publishing hooks already call
   `Sitemap::generateSitemapXML()`; it diffs the previous snapshot against
   the new one and submits exactly the appeared/changed/removed URLs to
   `api.indexnow.org` (Bing, Yandex, Naver, Seznam, Yep — and, via Bing's
   index, ChatGPT search). The key is discovered from the root `<key>.txt`
   file. Google does not participate in IndexNow and its sitemap-ping
   endpoint is retired; Google reads the same `lastmod` values.
3. **Conditional GET**: cookie-less content pages send a strong `ETag`
   (sha256 of the body) from `index.php`, so recrawls validate with
   `If-None-Match` and get `304` when unchanged. Feeds, sitemaps and
   `llms*.txt` send `ETag` + `Last-Modified` likewise.
4. **`dateModified`**: listing pages pass their newest row's timestamp to
   `ui_render_head(['date_modified' => ...])`, surfacing as
   `og:updated_time` and in the `WebPage` JSON-LD node.
5. **Feeds**: `/blog.xml`, `/updates.xml`, `/rmrp.xml` (RSS, real
   `lastBuildDate` = newest entry change) plus merged `/atom.xml` (Atom 1.0)
   and `/feed.json` (JSON Feed 1.1) with per-entry published/updated.
   `/feed`, `/feed.xml`, `/rss.xml` 301 to `/atom.xml`.

## Structured data (`views/includes/ui.php`, `views/includes/seo.php`)

- One entity graph: `/#website` (WebSite + SearchAction) and `/#person`
  (Person: name variants, job, employer, Istanbul home location, `sameAs`
  from the socials table) defined on the home page, referenced by `@id`
  everywhere else.
- `ui_render_head()` adds one `WebPage` node per page (unless the page
  declares its own page node — CollectionPage/ProfilePage/FAQPage — so
  there is never a duplicate page entity): canonical `@id`, `isPartOf`,
  `about`/`author` → `#person`, `primaryImageOfPage` from the OG image,
  breadcrumb `@id` when auto-breadcrumbs are emitted, `dateModified` when
  the view knows it.
- Typed collections via `seo_collection_graph()`: `VideoObject` items on
  /videos, `MusicRecording` on /music, `ImageObject` on /gallery, `Place`
  (real geo coordinates) on /travel, `CreativeWork` on /portfolio.
- Articles keep `BlogPosting`/`TechArticle` with real published/modified
  dates, word counts and reading time; documentation links `about`/
  `mentions` to verified Wikidata entities (`seo_wikidata()` — look up the
  description before adding a Q-id); `FAQPage` only for Q&A visible on the
  page; downloads carry `SoftwareApplication` + `SoftwareSourceCode`.
- Identity: `rel="me"` links, `fediverse:creator` and `twitter:creator`
  are derived from the socials table in the head (Mastodon domain
  verification points both ways).

## For language models and AI agents

- `/llms.txt` (`api/llms.php`) follows the llmstxt.org format: H1,
  blockquote summary, factual detail (entity, expertise, book, citation
  format "Yunus Emre Vurgun (yunusemrevurgun.com)"), then `##` sections of
  `- [Title](url): description` links built from the docs registry,
  downloads catalog, latest posts and socials table.
- `/llms-full.txt` (`api/llms-full.php`) concatenates the full journal,
  documentation, Chessko articles, downloads and latest archives with
  per-item canonical URLs and dates for single-file ingestion/citation.
- `robots.txt` explicitly welcomes AI search/assistant/training crawlers
  (GPTBot, OAI-SearchBot, ClaudeBot, PerplexityBot, Google-Extended,
  Applebot-Extended, CCBot, Meta-ExternalAgent, ...) on all public content
  while keeping private paths closed for everyone; no `Crawl-delay`
  anywhere (it only throttles Bing/Yandex). Yandex gets `Clean-param` for
  tracking parameters.
- Discovery files: `/opensearch.xml` (site-search plugin), `/humans.txt`,
  `/.well-known/security.txt` (RFC 9116; refresh the `Expires` date
  yearly), PWA `manifest.json`.

## Adding a page? Checklist

1. Route it and render through `ui_render_head()` with a unique title
   (~60 chars) and description (~155) that describe the visible page.
2. If it is indexable, add it to `Sitemap::collect()` (static list, docs
   registry, or a posts query) with a truthful `lastmod` source.
3. Pass `date_modified` when the view knows the newest content time
   (`seo_newest_stamp()` over already-fetched rows).
4. Structured data: reuse `seo_collection_graph()`/`seo_*` builders; never
   mark up content that is not visible on the page.
5. Dynamic endpoint? Add the `.htaccess` rewrite, the `Router`
   dev-parity entry, and the pretty-URL 301 for the script path.
6. Run `php dev/qa/regression.php`.
