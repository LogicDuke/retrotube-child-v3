# PR 221 Audit — Category Term Edit Conflict (Child Theme)

## Root cause summary
The child theme deliberately rewires category editing for admins so category-term edit flows are redirected to the linked `tmw_category_page` CPT editor.

Two mechanisms combine to produce the observed behavior:

1. On `term.php` for taxonomy `category`, `admin_init` in `inc/admin/tmw-category-pages-admin-single-editor.php` automatically redirects to `/wp-admin/post.php?post={id}&action=edit` (unless `tmw_force_term_edit` is present).  
2. The `get_edit_term_link` filter in the same file overrides category edit links in admin to a custom `admin-post.php?action=tmw_category_page_edit&term_id={id}` URL, which then redirects to the CPT editor via `admin_post_tmw_category_page_edit` in `inc/tmw-category-pages.php`.

This is why admins can land on `post.php?post=4516&action=edit` instead of native term editing.

## Files inspected
- `functions.php`
- `inc/bootstrap.php`
- `inc/tmw-category-pages.php`
- `inc/admin/tmw-category-pages-admin-single-editor.php`
- `inc/tmw-admin-tools.php` (for unrelated `edited_category` usage sanity)

## Hooks found
### Involved in edit-link/edit-screen behavior
- `admin_bar_menu` (audit-only logging of current edit node)
- `get_edit_term_link` (audit log in one file, hard override in admin single-editor file)
- `category_row_actions` (adds “Edit Category Page”, removes inline actions at higher priority)
- `admin_init` (term.php redirect to CPT editor)
- `admin_post_tmw_category_page_edit` (custom endpoint that resolves/creates linked CPT post and redirects)
- `created_category` (auto-create linked category page post)
- `edited_category` (sync slug to linked CPT post)

### Not found as active conflict source for category edit routing
- `tag_row_actions`, `term_row_actions`, `load-edit-tags.php`, `load-term.php`, `wp_redirect` category-term rerouter outside the above.

## Admin bar edit link source
The admin bar edit node is not explicitly replaced with a CPT URL in this code; instead, it is audited in `inc/tmw-category-pages.php` (`admin_bar_menu` priority 999) and will reflect whatever `get_edit_term_link()` returns for the queried term.

Because `get_edit_term_link` is overridden in admin by `inc/admin/tmw-category-pages-admin-single-editor.php`, the practical edit target for category term edit contexts becomes the Category Page path.

## Real taxonomy edit link behavior
Current behavior for category terms is intentionally changed:
- Expected core URL would be `term.php?taxonomy=category&tag_ID={term_id}`.
- Child theme filter rewrites that to the custom category-page admin-post action.
- If user still reaches `term.php` directly, `admin_init` then redirects to CPT editor unless `tmw_force_term_edit=1` is present.

So the real term editor is effectively bypassed for normal admin interactions.

## Category Page CPT link behavior
The “Edit Category Page” link is added to category row actions and category term edit form via `tmw_category_page_admin_link()`.
That URL calls `admin-post.php?action=tmw_category_page_edit&term_id={id}&_wpnonce=...`, and handler `admin_post_tmw_category_page_edit` resolves/creates linked CPT post then redirects to `post.php?post={id}&action=edit`.

## Term ↔ Category Page relationship
Mapping is stored by post meta on CPT posts:
- `_tmw_linked_term_id`
- `_tmw_linked_taxonomy` = `category`

Resolver `tmw_get_category_page_post()` tries:
1. exact meta mapping, then
2. slug-match fallback (and writes mapping meta when found).

Category creation auto-creates a linked CPT post (`created_category`), and category slug edits sync CPT `post_name` (`edited_category`).

## Whether PR 220 is involved
The redirect/override behavior appears to come from the “single editor enforcement” layer in `inc/admin/tmw-category-pages-admin-single-editor.php`, plus custom admin-post routing in `inc/tmw-category-pages.php`.

Based on local git history labels, behavior appears introduced by the commit titled “Fix v1.0.6 — Single editor for categories (term edit → Category Page)” and then audited later; PR 220 likely exposed or surfaced the issue operationally, but the core redirect intent predates it.

## Recommended follow-up fix (do not implement in this PR)
Smallest safe change in child theme only:
1. Stop overriding native category term edit links:
   - Remove/guard the `get_edit_term_link` admin override in `inc/admin/tmw-category-pages-admin-single-editor.php`.
2. Stop forced redirect on `term.php`:
   - Remove/guard the `admin_init` redirect block in same file.
3. Keep explicit custom path:
   - Retain `Edit Category Page` row/form actions and `admin_post_tmw_category_page_edit` flow.

Result:
- Native “Edit” goes to real term editor.
- “Edit Category Page” still goes to CPT editor.
- Frontend category layout and CPT content/rendering remain unchanged.

## Scope confirmation
- Fix should be child theme only (files above).
- No TMW SEO Engine plugin changes required for this routing issue.
- No frontend category layout changes required.

## Verification checklist
- [x] `/wp-admin/edit-tags.php?taxonomy=category` behavior path inspected from code.
- [x] Normal category Edit URL source identified (`get_edit_term_link` override + `admin_init` redirect).
- [x] “Edit Category Page” URL source identified (`tmw_category_page_admin_link` + `admin_post_tmw_category_page_edit`).
- [x] Frontend admin-bar “Edit Video Category” source path identified (admin bar edit node + edit-term-link chain).
- [x] No category names changed.
- [x] No category slugs changed.
- [x] No category descriptions changed.
- [x] No indexing settings changed.
- [x] No Rank Math fields changed.
- [x] No content generation changed.
- [x] No auto-publish behavior introduced.
- [x] Category archive layout untouched.
