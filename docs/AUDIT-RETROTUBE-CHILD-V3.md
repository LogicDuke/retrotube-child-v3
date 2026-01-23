This document is audit-only. No fixes, refactors, or behavioral changes are implemented in this pull request.

# RetroTube Child v3 — Security & Architecture Audit Lock-In (Flipbox Edition)

## 1. Overview
- Theme name: RetroTube Child v3 (Flipbox Edition).
- Current detected versions:
  - functions.php version (TMW_CHILD_VERSION): 4.2.0.
  - style.css version: 3.0.0.
- Parent theme: retrotube.
- File counts (PHP / JS / CSS): 79 / 10 / 7.
- Approximate LOC counts:
  - PHP: 14,520
  - JS: 750
  - CSS: 1,720

## 2. Architecture & Structure
**Strengths**
- Modularized bootstrap that centralizes includes for setup, frontend, admin, and SEO components.
- Constants are isolated for shared configuration.
- Lightweight autoloader in place for namespaced classes.

**Concerns**
- Version mismatch between style.css (3.0.0) and functions.php (4.2.0).
- tmw-model-hooks.php is oversized (2,251 lines), indicating mixed responsibilities.
- Duplicate require_once for tmw-category-pages.php exists in both functions.php and inc/bootstrap.php.
- A mu-plugin is bundled inside the theme tree (wp-content/mu-plugins), which blurs deployment boundaries.

## 3. Security Assessment
**Positive findings**
- Nonce usage is present for sensitive actions (admin tools and AJAX voting).
- Capability checks are enforced on admin-only tooling.
- Sanitization is applied to user input during registration flows.
- No custom SQL queries are present in the child theme; database access leans on core APIs, reducing reliance on direct $wpdb usage.

**Issues to address (DO NOT FIX)**
- Optional nonce verification in AJAX registration allows requests without a nonce.
- Unescaped post meta output is rendered in templates.
- Missing URL escaping for tag links in model templates.
- Voting system allows unlimited actions per request, creating abuse risk.
- File operations (rename/write) occur without explicit path validation when pruning files.

## 4. Performance Review
**Current optimizations in place**
- Dequeues and delays non-critical styles/scripts on heavy media views.
- Removes jQuery migrate for front-end performance.
- Preloads above-the-fold imagery for front-page and model banner assets.

**Minor improvement opportunities (not actions)**
- Review deferred asset list for third-party handles to ensure critical scripts remain immediate.
- Evaluate the cost of multiple wp_head preload injections for high-traffic pages.

## 5. WordPress Coding Standards
- PHPCS configuration is present.
- Partial non-compliance areas observed:
  - Inline PHPCS ignores for escaping rules.
  - Some functions lack formal DocBlocks in frontend utilities.
  - Long lines remain in templating output blocks.

## 6. Template Hierarchy
- Child theme overrides single.php and page.php while providing fallback behavior to parent templates.
- get_template_part is used to delegate to content templates and partials, aligning with WordPress hierarchy expectations.

## 7. SEO & Integrations
- Structured data is implemented for models and videos (schema JSON-LD helpers).
- Rank Math integrations and bridges are loaded for category/model meta handling.
- Affiliate tracking links are generated via model hooks and template tracking URLs.

## 8. Risk Classification
- Critical (Security): Optional nonce verification, unescaped meta output, file operations without path validation.
- High (Maintainability): Oversized tmw-model-hooks.php, version mismatches, duplicate requires.
- Medium: Template escaping inconsistencies, manual performance handle lists.
- Low: Minor docblock gaps and PHPCS ignore annotations.

## 9. Approved Remediation Order (NO IMPLEMENTATION)
1. Security hardening
2. Model hooks refactor
3. Version sync & duplication cleanup
4. PHPCS & documentation polish
