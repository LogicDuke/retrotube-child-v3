# Audit v1 — Slot Banner widget shortcode mixing

## Scope

Child-theme audit of the Slot Banner widget mode behavior and CSS wrapper effects.

## Findings

### 1) Widget mode is gated by a marker string

In `tmw_render_model_slot_banner_zone()`, widget mode only accepts widget output if it contains the `tmw-slot-machine-container` marker string. The function checks the widget output for the marker and only uses it when the marker exists; otherwise it falls back to running the shortcode (`[tmw_slot_machine]` by default). This means any other plugin shortcode rendered in the widget will be discarded in widget mode, causing “hybrid” output where only the fallback slot shortcode is used when the marker is missing.【F:inc/frontend/tmw-slot-banner.php†L36-L74】

### 2) Wrapper CSS forces centered layout

The Slot Banner HTML is always wrapped in `.tmw-slot-banner-zone` and `.tmw-slot-banner`. The CSS for `.single-model .tmw-slot-banner-zone .tmw-slot-banner` forces `text-align: center`, width 100%, and auto margins, which can override or interfere with layout expectations for other shortcodes placed inside the widget zone.【F:inc/frontend/tmw-slot-banner.php†L76-L76】【F:style.css†L849-L864】

### 3) Template call site

The Slot Banner renderer is invoked in the model template (`template-parts/content-model.php`) via `tmw_render_model_slot_banner_zone()`, and the returned HTML (including wrapper divs) is echoed directly into the page content. This call site confirms where the widget-mode gating and wrapper CSS apply on the model page.【F:template-parts/content-model.php†L173-L220】
