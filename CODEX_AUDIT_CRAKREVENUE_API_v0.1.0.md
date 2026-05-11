# AUDIT ONLY — TMW CrakRevenue LiveCam API Integration Readiness v0.1.0

## Scope and Zero-Change Statement
- **Audit-only execution completed.**
- No production PHP logic modified.
- No template, CSS, or JS changes made.
- No API credentials added.
- This report documents architecture readiness and recommendations only.

---

## 1) Current Architecture Summary

### Theme bootstrap and integration surface
- `functions.php` is a loader-centric entrypoint that wires a large module set (SEO bridges, schema handlers, category tooling, model/video helpers, etc.) and is already dense.
- A large amount of runtime behavior is split into `inc/` modules; this is good for future plugin hooks, but increases coupling risk if external importer code is added directly in theme.
- RankMath behavior is heavily customized via multiple filters in dedicated bridge files.

### Content model shape (high level)
- Core performer content uses a custom post type `model` with archive `/models/` and singles under `/model/{slug}`.
- A custom taxonomy `models` is registered and rewritten to `/model-tag/{term}`.
- Videos are treated as separate post type context (with fallback logic around legacy `post` + detected video post type).
- Legacy `actors` taxonomy is synchronized with `models` taxonomy on save.

### SEO and schema shape
- Extensive RankMath bridges exist for model and category experiences.
- Custom JSON-LD output exists for model/profile, video/videoobject, archive itemlist, and category collection/itemlist.
- This means any future API pages must avoid duplicate schema and canonical conflicts.

---

## 2) Relevant Files Inspected

### Required focus areas
- `functions.php`
- `single-model.php` (current canonical model single template)
- `archive.php`, `archive-model.php`, `taxonomy-models.php`, `tag.php`, `page-categories.php`
- `template-parts/content-model.php`, `template-parts/content-video.php`, `template-parts/breadcrumbs.php`
- `inc/models/tmw-model-register.php`
- `inc/tmw-video-hooks.php`, `inc/tmw-tax-bind-models-video.php`, `inc/tmw-tml-bridge.php`
- `inc/tmw-seo-model-bridge.php`, `inc/tmw-seo-category-bridge.php`
- `inc/tmw-model-schema.php`, `inc/tmw-video-schema.php`, `inc/seo/schema.php`, `inc/tmw-archive-schema.php`
- `inc/models/tmw-model-tools.php`, `inc/models/tmw-model-term-sync.php`, `inc/models/tmw-model-migrations.php`
- `inc/frontend/tmw-featured-models-inject.php` (for policy/compliance-page exclusions)

### Not found / naming mismatch
- `single-model_bio.php` is not present; migration logic indicates old `model_bio` is normalized into `model`.
- No first-class in-repo module named `tmw-seo-autopilot` was found inside this repository tree.

---

## 3) Audit Findings by Question

## Q1. Where should CrakRevenue integration live?

### Recommendation: **New standalone plugin** (safest)
Create a dedicated plugin (e.g., `wp-content/plugins/tmw-crakrevenue-api/`) with clear boundaries:
- API client
- sync/import orchestration
- cache/rate-limit layer
- SEO page generation helpers (or virtual pages)
- admin controls/logging

#### Why not in theme?
- Theme already contains heavy logic and presentation coupling; API business logic in theme would increase risk on theme updates and deployment.
- Import/sync is operational logic and should be portable and switchable independent of templates.

#### Why not force it into existing SEO bridge modules?
- Current bridge files are already focused on RankMath mediation for existing entities.
- Keep CrakRevenue sync and source-of-truth data handling in a plugin; bridge into theme only with narrow hooks/filters later.

---

## Q2. Current CPT/taxonomy structure

### Models
- CPT: `model`
- Archive: `/models/`
- Single slug base: `/model/{postname}`
- Supports: title/editor/thumbnail/comments
- Registered taxonomies: `category`, `post_tag`, plus custom `models` taxonomy relation

### Model taxonomy
- Taxonomy key: `models`
- Public rewrite slug: `model-tag`
- Legacy rewrites for `/actor/*` and `/actors/*`
- Tax term may redirect to matching model CPT page if slug matches

### Videos
- Video logic assumes custom `video` post type context (and fallback/detection in some synchronizers).
- `model` ↔ `video/post` associations are generated via shared tax terms and helper utilities.

### Tags/Categories
- WordPress native `post_tag` and `category` are integrated in model/video and category-page tooling.

### GEO/country/language
- No dedicated first-class country/language taxonomy for models identified in the inspected theme modules.
- Candidate future implementation should avoid overloading generic `post_tag`.

---

## Q3. Existing conflict risks

1. **Import collisions with legacy model sync**
   - `actors` ↔ `models` sync on `save_post` can unexpectedly mutate terms if imported posts touch overlapping taxonomies.

2. **Slug/URL collisions**
   - taxonomy-to-model redirect behavior can clash if imported performer slugs overlap term slugs.

3. **RankMath metadata overrides**
   - model/category bridge filters override title/description/canonical/robots/schema.
   - New API pages must either map cleanly to expected post objects or explicitly bypass/extend bridge logic.

4. **Schema duplication risk**
   - model/video/category schema are already injected; adding live-cam schemas without guard conditions may duplicate entity types.

5. **Featured image assumptions**
   - schema and templates assume image availability via post thumbnail / resolved banner helpers; API thumbnails must be normalized.

6. **Affiliate CTA conflicts**
   - existing model tooling has tracking-link helpers; introducing external affiliate URL fields needs a single canonical resolver to prevent mixed CTAs.

7. **Archive/query pagination fragility**
   - existing audit/fix files indicate historical term-count pagination mismatch; avoid repeating with API-backed archives.

---

## Q4. Safest future performer data model

### Recommended base model
Use **CPT `model` as canonical public page entity**, plus immutable source metadata in prefixed meta keys.

### Required meta fields (prefix all with `tmw_crak_`)
- `tmw_crak_performer_id` (string/int, unique external ID)
- `tmw_crak_brand`
- `tmw_crak_display_name`
- `tmw_crak_slug_source`
- `tmw_crak_live_status` (enum: `live|offline|unknown`)
- `tmw_crak_thumbnail_url`
- `tmw_crak_preview_video_url`
- `tmw_crak_affiliate_url`
- `tmw_crak_country`
- `tmw_crak_language`
- `tmw_crak_gender`
- `tmw_crak_age` (int, nullable)
- `tmw_crak_tags` (normalized CSV/JSON then mapped to taxonomies)
- `tmw_crak_last_seen_live` (UTC datetime)
- `tmw_crak_last_synced_at` (UTC datetime)
- `tmw_crak_source_hash` (hash for change detection)

### Uniqueness constraints
- Soft uniqueness on `tmw_crak_performer_id` + `brand`.
- Hard de-duplication via lookup index table (recommended) for fast idempotent upserts.

---

## Q5. Recommended caching/sync strategy

### Hybrid approach (recommended)
1. **Custom table for sync state/index**
   - Store external IDs, hash, sync timestamps, status, last error, retry count.
   - Avoid heavy `meta_query` scans for each sync batch.

2. **Post meta for public rendering fields**
   - Keep values needed in templates/schema on model posts.

3. **Transients for short-lived API response cache + rate limit backoff**
   - e.g., page-level response cache TTL 2–10 minutes.
   - lock transient for mutual exclusion during cron/manual sync.

4. **WP-Cron scheduled sync + manual admin trigger**
   - Scheduled incremental sync every 5–15 min for live status.
   - Full metadata sweep less frequently (e.g., 6–24h).

5. **Rate-limit protection**
   - token/bucket counters in transient or options; exponential backoff on HTTP 429/5xx.

---

## Q6. Pagination strategy (future API)

### Safe limits
- API fetch page size: **25–50 performers max** (prefer 30).
- Per sync execution hard cap: **200–500 performers** (batch chunked).
- Frontend page size: **24 or 36 cards** for grid stability.
- Always prioritize `live` first, then deterministic secondary sort (e.g., score/name/id).

### Operational controls
- Checkpoint-based continuation token stored after each batch.
- Timeboxed sync run (e.g., stop after N seconds) to prevent long admin/cron runs.

---

## Q7. Recommended SEO structure for future rollout

### Suggested landing architecture (incremental)
Phase 1:
- `/live-cams/` (hub)
- `/live-now/` (live-only filtered list)
- `/brand/{brand}/`

Phase 2:
- `/country/{country}/`
- `/language/{language}/`
- `/tag/{tag}/`

Phase 3 (only with sufficient depth + unique content):
- `/cam-girls/` (if mapped to policy-safe gender taxonomy)
- `/ai-girlfriend/` and dating-to-cam bridge pages only if editorially substantial and compliant

### SEO quality guards
- No thin mass generation.
- Index only pages meeting minimum content threshold (intro copy + distinct listing volume).
- Canonicalize filtered permutations to primary taxonomic URLs.

---

## Q8. Compliance gap check

### Findings
- Theme logic includes exclusion references to paths like `dmca` and terms pages in featured-models injection conditions, suggesting such pages may exist in content layer, but policy enforcement is not centralized in inspected code.
- No explicit centralized compliance guard module found for:
  - 18+ gate enforcement
  - affiliate disclosure enforcement across templates
  - generated-copy compliance linting

### Recommended compliance checklist for future PRs
- 18+ warning (sitewide or first-entry gate)
- DMCA page
- takedown policy page
- affiliate disclosure (global + page-level where affiliate links are shown)
- cookie consent integration
- privacy policy
- terms of use
- copy linting rule: prohibit minor-risk or explicit unsafe wording in generated/imported descriptions

---

## Q9. Future debug log tags

Adopt required tags with structured payloads:
- `[TMW-CRAK-API]` — raw API call lifecycle, response code, rate-limit state
- `[TMW-CRAK-SYNC]` — importer state machine, upsert counts, skips, retries
- `[TMW-CRAK-SEO]` — indexability/canonical/schema decisions for generated pages
- `[TMW-CRAK-COMPLIANCE]` — disclosure/gating/policy checks and suppressions

### Logging format guidance
- Prefix + event key + compact JSON context.
- No credentials/tokens in logs.
- Log-level gate with `WP_DEBUG` + plugin setting.

---

## Q10. Recommended staged PR sequence

1. **PR-1 (audit scaffolding only)**
   - Add new plugin skeleton, settings page scaffold, logger, no frontend output.

2. **PR-2 (API client + rate-limit + cache layer)**
   - HTTP client, auth config, retry/backoff, transient cache.

3. **PR-3 (data model + sync table + idempotent upsert)**
   - DB table migration, performer index, hash-based change detection.

4. **PR-4 (manual sync + cron incremental sync)**
   - Admin sync action, WP-Cron scheduling, lock handling.

5. **PR-5 (model mapping + affiliate URL resolver)**
   - Safe mapping to `model` CPT/meta and unified CTA source.

6. **PR-6 (archive pages + pagination + live-first sorting)**
   - `/live-cams/`, `/live-now/`, brand pages with strict pagination caps.

7. **PR-7 (RankMath/schema bridge compatibility layer)**
   - Canonical, meta, schema guards to avoid duplication/conflict.

8. **PR-8 (compliance hardening)**
   - Disclosure enforcement, policy page checks, copy sanitization rules.

9. **PR-9 (observability + QA gates)**
   - Dashboard metrics, error summaries, staging test checklist.

---

## Recommended Future File Paths (for implementation PRs, not this PR)
- `wp-content/plugins/tmw-crakrevenue-api/tmw-crakrevenue-api.php`
- `wp-content/plugins/tmw-crakrevenue-api/includes/class-tmw-crak-api-client.php`
- `wp-content/plugins/tmw-crakrevenue-api/includes/class-tmw-crak-sync-runner.php`
- `wp-content/plugins/tmw-crakrevenue-api/includes/class-tmw-crak-repository.php`
- `wp-content/plugins/tmw-crakrevenue-api/includes/class-tmw-crak-seo-bridge.php`
- `wp-content/plugins/tmw-crakrevenue-api/includes/class-tmw-crak-compliance.php`
- `wp-content/plugins/tmw-crakrevenue-api/includes/class-tmw-crak-logger.php`
- `wp-content/plugins/tmw-crakrevenue-api/admin/class-tmw-crak-settings-page.php`
- `wp-content/plugins/tmw-crakrevenue-api/uninstall.php`

---

## Verification Checklist
- [x] No production PHP logic changed
- [x] No templates changed
- [x] No CSS changed
- [x] No JS changed
- [x] No API credentials added
- [x] Audit report created
- [x] Recommended staged roadmap included
- [x] Compliance risks documented
- [x] Future debug tags documented

