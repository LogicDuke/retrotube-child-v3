# [TMW-SEO-AUTO] PR 566 — Category Term Editing Conflict Audit

## Scope and guardrails
- Audit-only review of category-term editing flow and Category Page CPT routing.
- No runtime behavior changes in this PR.

---

## A) Current category architecture

### 1) Real live taxonomy
- Live archives are WordPress taxonomy terms in `category`.
- Category URLs resolve via `get_term_link()` and archive templates (`archive.php` / theme archive title flow).

### 2) Category Page CPT
- A custom post type `tmw_category_page` is registered as `TMW_CATEGORY_PAGE_CPT`. This CPT has `show_ui => true`, is queryable, and supports title/editor/excerpt/revisions. (`inc/tmw-category-pages.php`)
- Each CPT post links to a real category term through post meta:
  - `_tmw_linked_term_id`
  - `_tmw_linked_taxonomy = category`
- Link resolution and lookup are handled by:
  - `tmw_get_category_page_post()` (find by linked term meta; fallback slug matching)
  - `tmw_create_category_page_post()` (create CPT post for a term)

### 3) Relationship directionality
- **Term → CPT sync exists for slug** on `edited_category`: if term slug changes, CPT `post_name` is updated.
- **CPT → term sync does not exist** for term name/description/slug edits from CPT editor in this file.
- `template_redirect` on singular `tmw_category_page` sends frontend requests back to the linked term URL, reinforcing taxonomy URL as canonical destination.

---

## B) Current admin behavior

### 1) What native `Edit` currently does
Root behavior is overridden in admin:
- `get_edit_term_link` filter (admin single editor file) returns `tmw_category_page_admin_link($term)` for category terms.
- That custom link points to:
  - `/wp-admin/admin-post.php?action=tmw_category_page_edit&term_id=...&_wpnonce=...`
- The `admin_post_tmw_category_page_edit` handler resolves/creates the linked CPT post and then redirects to:
  - `/wp-admin/post.php?post={category_page_post_id}&action=edit`

So the native category `Edit` action is replaced at link-generation time, then redirected through admin-post to CPT edit.

### 2) What `Edit Category Page` action does
- A separate `category_row_actions` filter adds explicit `Edit Category Page`, also pointing to `tmw_category_page_admin_link($term)`.
- Therefore, both the native Edit path and the custom action converge to the same admin-post route and then CPT editor.

### 3) Additional forced routing
- `admin_init` in `inc/admin/tmw-category-pages-admin-single-editor.php` intercepts direct hits to `term.php` for `taxonomy=category` and redirects to linked CPT edit (unless `tmw_force_term_edit` is present).
- This means even manually opening `term.php?taxonomy=category&tag_ID=...` is auto-rerouted by default.

### 4) Is native term editor still accessible?
- Yes, but only through bypass query flag: `tmw_force_term_edit=1` on the term URL.
- Without this flag, `term.php` for categories is redirected to CPT editor.

---

## C) Root cause

## Primary cause (exact location)
1. **Link replacement of native Edit URL**
- File: `inc/admin/tmw-category-pages-admin-single-editor.php`
- Hook: `add_filter('get_edit_term_link', ...)`
- Effect: replaces WordPress native term edit link with custom admin-post link.

2. **Direct term.php redirect enforcement**
- File: `inc/admin/tmw-category-pages-admin-single-editor.php`
- Hook: `add_action('admin_init', ...)`
- Effect: redirects category term editor requests (`term.php`) to linked CPT editor unless `tmw_force_term_edit` is set.

3. **Custom handler to CPT editor**
- File: `inc/tmw-category-pages.php`
- Hook: `add_action('admin_post_tmw_category_page_edit', ...)`
- Effect: resolves/creates linked CPT post and redirects to `post.php?post=...&action=edit`.

### Is it row-action override vs redirect?
- It is **both**:
  - Native edit URL is replaced via `get_edit_term_link` filter.
  - Direct term editor access is redirected via `admin_init` guard.
  - Custom `Edit Category Page` row action is additive, not the main override by itself.

---

## D) Indexing impact

1. **Why rename/description updates are blocked in practice**
- User clicking `Edit` in category list is not sent to term editor; they are sent to Category Page CPT editor.
- Term name/description fields needed for live archive title/content tuning are not directly editable through that route.

2. **Live frontend title source (`/category/...`)**
- Archive title rendering uses archive/term context (`get_the_archive_title()` and category title helpers), i.e., taxonomy term naming layer.
- The Category Page CPT primarily acts as a linked SEO/content wrapper and admin surface; frontend singular CPT requests redirect to term URL.

3. **Sync implications**
- Existing sync shown is from edited category slug to CPT `post_name`, not from CPT title/editor back to term name/description.
- Therefore, editing CPT title alone will not reliably rename live category archive heading.

---

## E) Recommended minimal fix PR (do not implement in this audit PR)

## Goal behavior
Keep two separate actions in category rows:
1. `Edit` → native term editor (`term.php?...`)
2. `Edit Category Page` → custom CPT editor (`post.php?post=...&action=edit` via admin-post route)

## Minimal safe implementation strategy
1. **Stop overriding native edit links** for `category` terms.
   - Remove or conditionally disable the `get_edit_term_link` filter in `inc/admin/tmw-category-pages-admin-single-editor.php`.
2. **Stop forced redirect from `term.php`** for categories.
   - Remove or conditionally gate the `admin_init` redirect block in `inc/admin/tmw-category-pages-admin-single-editor.php`.
3. **Keep** `category_row_actions` custom `Edit Category Page` link and `admin_post_tmw_category_page_edit` handler untouched.

## Files likely to modify in fix PR
- `inc/admin/tmw-category-pages-admin-single-editor.php` (primary)
- Possibly no changes needed in `inc/tmw-category-pages.php` unless cleanup or explicit naming adjustments are desired.

## Expected post-fix admin behavior
- Clicking native `Edit` on `/wp-admin/edit-tags.php?taxonomy=category` opens real term editor (`term.php?...`).
- Clicking `Edit Category Page` opens linked CPT editor (`post.php?post=...&action=edit`).
- Category Page CPT workflow remains available, but no longer hijacks taxonomy term editing.

---

## Answers to requested audit questions
1. **Why does clicking Edit open CPT editor?**
   - Because `get_edit_term_link` is filtered to a custom admin-post URL, then handler redirects to CPT edit.
2. **Is normal term edit link replaced?**
   - Yes, via `get_edit_term_link` filter.
3. **Is custom row action overriding native Edit?**
   - Not directly; native Edit is independently replaced by link filter.
4. **Is taxonomy intentionally routed through CPT?**
   - Yes, enforced by both link replacement and `admin_init` redirect.
5. **Direct way to edit real term still exists?**
   - Yes: add `tmw_force_term_edit=1` to term editor URL.
6. **Exact cause file/function/hook?**
   - `inc/admin/tmw-category-pages-admin-single-editor.php`:
     - `add_filter('get_edit_term_link', ...)`
     - `add_action('admin_init', ...)` redirect logic
   - Supporting redirect: `inc/tmw-category-pages.php` `admin_post_tmw_category_page_edit`.
7. **Can native Edit be restored safely while keeping CPT action?**
   - Yes; remove/disable native-link override + term.php redirect, keep explicit CPT row action.
8. **Does CPT sync back to real term?**
   - Not for term name/description in inspected code; only edited term slug sync to CPT `post_name` is present.
9. **Live frontend title source?**
   - Taxonomy term/archive title context, not CPT title.
10. **Minimal PR needed?**
   - Adjust `inc/admin/tmw-category-pages-admin-single-editor.php` to stop hijacking native term edit, retain separate `Edit Category Page` action.

---

## Manual verification map (documented expected observations)
1. Open `/wp-admin/edit-tags.php?taxonomy=category`
2. Hover category row `Edit`: currently points to admin-post action (custom route), not native `term.php`.
3. Hover `Edit Category Page`: also points to same custom admin-post action.
4. Open `/wp-admin/admin-post.php?action=tmw_category_page_edit&term_id={id}&_wpnonce=...`: redirects to `/wp-admin/post.php?post={id}&action=edit`.
5. Open native term URL manually:
   - `/wp-admin/term.php?taxonomy=category&tag_ID={id}&post_type=post`
   - gets redirected unless `&tmw_force_term_edit=1` is appended.
6. Frontend `/category/amateur-cam-girls/` title remains taxonomy-driven category archive title.

