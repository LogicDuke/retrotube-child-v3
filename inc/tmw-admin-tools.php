<?php


if (!function_exists('tmw_audit_enabled')) {
  function tmw_audit_enabled(): bool {
    return defined('TMW_AUDIT_ENABLED') && TMW_AUDIT_ENABLED === true;
  }
}

if (!function_exists('tmw_banner_audit_log')) {
  /**
   * Emit debug logs for banner save/audit flow.
   *
   * @param string               $event   Event label.
   * @param array<string, mixed> $context Additional context payload.
   */
  function tmw_banner_audit_log(string $event, array $context = []): void {
    if (!tmw_audit_enabled()) {
      return;
    }

    $request_context = [
      'REST_REQUEST' => defined('REST_REQUEST') ? (bool) REST_REQUEST : false,
      'wp_doing_ajax' => function_exists('wp_doing_ajax') ? wp_doing_ajax() : false,
      'request_method' => isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '',
      'request_uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '',
    ];

    error_log('[TMW-AUDIT-BANNER] ' . $event . ' ' . wp_json_encode(array_merge($request_context, $context))); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
  }
}

/* ======================================================================
 * SLOT BANNER BACKFILL TOOL
 * ====================================================================== */
add_action('admin_menu', function () {
  add_management_page(
    'Slot Banner Backfill',
    'Slot Banner Backfill',
    'manage_options',
    'tmw-slot-banner-backfill',
    'tmw_render_slot_banner_backfill_page'
  );
});

add_action('admin_post_tmw_slot_banner_backfill', function () {
  if (!current_user_can('manage_options')) {
    wp_die('Forbidden');
  }

  check_admin_referer('tmw_slot_banner_backfill');

  $query = new WP_Query([
    'post_type'      => 'model',
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_query'     => [
      'relation' => 'OR',
      [
        'key'     => '_tmw_slot_enabled',
        'compare' => 'NOT EXISTS',
      ],
      [
        'key'     => '_tmw_slot_mode',
        'compare' => 'NOT EXISTS',
      ],
      [
        'key'     => '_tmw_slot_shortcode',
        'compare' => 'NOT EXISTS',
      ],
    ],
  ]);

  $total = is_array($query->posts) ? count($query->posts) : 0;
  $updated = 0;
  $skipped = 0;

  foreach ((array) $query->posts as $post_id) {
    $missing_enabled = !metadata_exists('post', $post_id, '_tmw_slot_enabled');
    $missing_mode = !metadata_exists('post', $post_id, '_tmw_slot_mode');
    $missing_shortcode = !metadata_exists('post', $post_id, '_tmw_slot_shortcode');

    if ($missing_enabled || $missing_mode || $missing_shortcode) {
      update_post_meta($post_id, '_tmw_slot_enabled', '1');
      update_post_meta($post_id, '_tmw_slot_mode', 'shortcode');
      update_post_meta($post_id, '_tmw_slot_shortcode', '[tmw_slot_machine]');
      $updated++;
    } else {
      $skipped++;
    }
  }

  $redirect = add_query_arg([
    'page'    => 'tmw-slot-banner-backfill',
    'updated' => $updated,
    'skipped' => $skipped,
    'total'   => $total,
  ], admin_url('tools.php'));

  wp_safe_redirect($redirect);
  exit;
});

function tmw_render_slot_banner_backfill_page() {
  if (!current_user_can('manage_options')) {
    return;
  }

  $updated = isset($_GET['updated']) ? (int) $_GET['updated'] : null;
  $skipped = isset($_GET['skipped']) ? (int) $_GET['skipped'] : null;
  $total = isset($_GET['total']) ? (int) $_GET['total'] : null;
  ?>
  <div class="wrap">
    <h1><?php esc_html_e('Slot Banner Backfill', 'retrotube-child'); ?></h1>
    <p><?php esc_html_e('Backfill missing slot banner meta for model posts.', 'retrotube-child'); ?></p>
    <?php if ($updated !== null && $skipped !== null && $total !== null) : ?>
      <div class="notice notice-success is-dismissible">
        <p>
          <?php
          printf(
            esc_html__('Processed %1$d posts. Updated %2$d, skipped %3$d.', 'retrotube-child'),
            $total,
            $updated,
            $skipped
          );
          ?>
        </p>
      </div>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('tmw_slot_banner_backfill'); ?>
      <input type="hidden" name="action" value="tmw_slot_banner_backfill" />
      <?php submit_button(__('Backfill Missing Slot Meta', 'retrotube-child')); ?>
    </form>
  </div>
  <?php
}

/* ======================================================================
 * MODEL BANNER POSITION META BOX
 * ====================================================================== */
if (!function_exists('tmw_render_banner_position_box')) {
  function tmw_render_banner_position_box($post) {
    $value = function_exists('tmw_get_model_banner_focal_y')
      ? (int) round(tmw_get_model_banner_focal_y($post->ID))
      : 50;
    $value = max(0, min(100, $value));
    $banner   = function_exists('tmw_resolve_model_banner_url') ? tmw_resolve_model_banner_url($post->ID) : '';
    $banner_image_id = absint(get_post_meta($post->ID, 'tmw_banner_image_id', true));
    $custom_banner = $banner_image_id > 0 ? wp_get_attachment_url($banner_image_id) : '';
    $preview_banner = $custom_banner ?: $banner;

    wp_nonce_field('tmw_save_banner_position', 'tmw_banner_position_nonce');

    ob_start();
    $rendered = function_exists('tmw_render_model_banner') ? tmw_render_model_banner($post->ID, 'backend') : false;
    $markup   = ob_get_clean();

    echo '<div id="tmw-banner-preview" class="tmw-banner-preview">';
    if ($rendered && $markup) {
      echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    echo '</div>';

    if (!$rendered) {
      echo '<p><em>' . esc_html__('No banner found. Assign a “Models” term with a banner in ACF, or set a featured image.', 'retrotube-child') . '</em></p>';
    }

    echo '<input type="range" min="0" max="100" step="1" value="' . esc_attr($value) . '" id="tmwBannerSlider" class="tmw-slider" name="banner_focal_y">
    <p><small>Vertical focus (%): <span id="tmwBannerValue">' . esc_html($value) . '</span> (0=top, 100=bottom)</small></p>';

    echo '<hr />';
    echo '<p><strong>' . esc_html__('Banner Image Override', 'retrotube-child') . '</strong></p>';
    echo '<input type="hidden" name="tmw_banner_image_id" id="tmw_banner_image_id" value="' . esc_attr((string) $banner_image_id) . '" />';
    echo '<p><img id="tmw_banner_image_preview" src="' . esc_url($preview_banner) . '" alt="" style="max-width:100%;height:auto;display:' . ($preview_banner ? 'block' : 'none') . ';" /></p>';
    echo '<p>';
    echo '<button type="button" class="button" id="tmw_choose_banner_image">' . esc_html__('Choose Banner Image', 'retrotube-child') . '</button> ';
    echo '<button type="button" class="button" id="tmw_remove_banner_image">' . esc_html__('Remove', 'retrotube-child') . '</button>';
    echo '</p>';

    ob_start();
    ?>
    <script>
        (function(){
            var slider      = document.getElementById('tmwBannerSlider');
            var previewWrap = document.getElementById('tmw-banner-preview') || document.getElementById('tmwBannerPreview');
            var previewFrame = previewWrap
                ? (previewWrap.classList.contains('tmw-banner-frame') ? previewWrap : previewWrap.querySelector('.tmw-banner-frame'))
                : null;
            var valSpan     = document.getElementById('tmwBannerValue');
            var imageInput  = document.getElementById('tmw_banner_image_id');
            var imagePreview = document.getElementById('tmw_banner_image_preview');
            var chooseButton = document.getElementById('tmw_choose_banner_image');
            var removeButton = document.getElementById('tmw_remove_banner_image');
            var mediaFrame;

            // ── resolve parent Gutenberg store with retry ──────────────────────
            var _bannerStoreCache = null;
            function resolveBannerStore() {
                if (_bannerStoreCache) { return _bannerStoreCache; }
                var sources = [];
                try {
                    if (window.parent && window.parent !== window && window.parent.wp) {
                        sources.push(window.parent.wp);
                    }
                } catch(e) {}
                if (typeof wp !== 'undefined') { sources.push(wp); }

                for (var i = 0; i < sources.length; i++) {
                    var w = sources[i];
                    if (!w || !w.data || typeof w.data.dispatch !== 'function') { continue; }
                    try {
                        var ed  = w.data.dispatch('core/editor');
                        var sel = w.data.select('core/editor');
                        if (ed && sel && typeof ed.editPost === 'function') {
                            _bannerStoreCache = { editor: ed, select: sel, wpData: w.data };
                            return _bannerStoreCache;
                        }
                    } catch(e) {}
                }
                return null;
            }

            function pushBannerMeta(store) {
                if (!store || !imageInput) { return; }
                var id  = parseInt(imageInput.value, 10) || 0;
                var url = (imagePreview && imagePreview.src && id > 0) ? imagePreview.src : '';
                try {
                    var current = store.select.getEditedPostAttribute('meta') || {};
                    store.editor.editPost({ meta: Object.assign({}, current, {
                        tmw_banner_image_id: id,
                        banner_image: url
                    })});
                } catch(e) {}
            }

            // Subscribe: inject meta just as save triggers
            function initBannerStoreBindings() {
                var store = resolveBannerStore();
                if (!store) { return false; }
                var lastSaving = false;
                store.wpData.subscribe(function() {
                    try {
                        var saving = store.select.isSavingPost();
                        if (saving && !lastSaving) { pushBannerMeta(store); }
                        lastSaving = saving;
                    } catch(e) {}
                });
                return true;
            }

            (function waitBanner() {
                if (initBannerStoreBindings()) { return; }
                var n = 0;
                var t = setInterval(function() {
                    n++;
                    if (initBannerStoreBindings() || n > 40) { clearInterval(t); }
                }, 250);
            })();

            // ── slider ────────────────────────────────────────────────────────
            function applyFocus(value) {
                if (!previewFrame) { return; }
                var img = previewFrame.querySelector('img');
                if (!img) { return; }
                var n = parseInt(value, 10);
                var c = Math.max(0, Math.min(100, isNaN(n) ? 50 : n));
                img.style.objectPosition = '50% ' + c + '%';
                if (valSpan) { valSpan.textContent = c; }
            }

            if (slider) {
                slider.addEventListener('input', function(e){ applyFocus(e.target.value); });
                applyFocus(slider.value);
            }

            // ── image picker ──────────────────────────────────────────────────
            function setPreview(url) {
                if (!imagePreview) { return; }
                imagePreview.src = url || '';
                imagePreview.style.display = url ? 'block' : 'none';
            }

            if (chooseButton && imageInput && typeof wp !== 'undefined' && wp.media) {
                chooseButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (!mediaFrame) {
                        mediaFrame = wp.media({
                            title: 'Choose Banner Image',
                            button: { text: 'Use banner image' },
                            multiple: false,
                            library: { type: 'image' }
                        });
                        mediaFrame.on('select', function() {
                            var att = mediaFrame.state().get('selection').first().toJSON();
                            imageInput.value = att.id || 0;
                            setPreview(att.url || '');
                            // Push immediately so post is marked dirty.
                            pushBannerMeta(resolveBannerStore());
                        });
                    }
                    mediaFrame.open();
                });
            }

            if (removeButton && imageInput) {
                removeButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    imageInput.value = 0;
                    setPreview('');
                    pushBannerMeta(resolveBannerStore());
                });
            }
        })();
    </script>
    <?php
    echo ob_get_clean();
  }
}

add_action('add_meta_boxes', function () {
  add_meta_box('model_banner_position', __('Banner Focus (Vertical)', 'retrotube-child'), 'tmw_render_banner_position_box', 'model', 'normal', 'default');
});

add_action('save_post_model', function ($post_id) {
  tmw_banner_audit_log('save_post_model:entered', [
    'post_id' => (int) $post_id,
    'current_user_can_edit_post' => current_user_can('edit_post', $post_id),
    'has_nonce' => isset($_POST['tmw_banner_position_nonce']),
    'posted_banner_focal_y' => isset($_POST['banner_focal_y']) ? wp_unslash($_POST['banner_focal_y']) : null,
    'posted_tmw_banner_image_id' => isset($_POST['tmw_banner_image_id']) ? wp_unslash($_POST['tmw_banner_image_id']) : null,
    'existing_meta' => [
      '_banner_focal_y' => get_post_meta($post_id, '_banner_focal_y', true),
      'tmw_banner_image_id' => get_post_meta($post_id, 'tmw_banner_image_id', true),
      'banner_image' => get_post_meta($post_id, 'banner_image', true),
    ],
  ]);

  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
    tmw_banner_audit_log('save_post_model:exit-doing-autosave', ['post_id' => (int) $post_id]);
    return;
  }

  if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
    tmw_banner_audit_log('save_post_model:exit-autosave-or-revision', ['post_id' => (int) $post_id]);
    return;
  }

  if (defined('REST_REQUEST') && REST_REQUEST) {
    tmw_banner_audit_log('save_post_model:exit-rest-request', ['post_id' => (int) $post_id]);
    return;
  }

  if (!isset($_POST['tmw_banner_position_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tmw_banner_position_nonce'])), 'tmw_save_banner_position')) {
    tmw_banner_audit_log('save_post_model:exit-nonce', ['post_id' => (int) $post_id]);
    return;
  }

  if (!current_user_can('edit_post', $post_id)) {
    tmw_banner_audit_log('save_post_model:exit-no-cap', ['post_id' => (int) $post_id]);
    return;
  }

  if (isset($_POST['banner_focal_y'])) {
    $value = wp_unslash($_POST['banner_focal_y']);
    $value = is_numeric($value) ? (float) $value : 50;
    if ($value < 0) {
      $value = 0;
    }
    if ($value > 100) {
      $value = 100;
    }
    tmw_banner_audit_log('save_post_model:update-meta', ['post_id' => (int) $post_id, 'key' => '_banner_focal_y', 'value' => $value]);
    update_post_meta($post_id, '_banner_focal_y', $value);
  }

  if (isset($_POST['tmw_banner_image_id'])) {
    $attachment_id = absint(wp_unslash($_POST['tmw_banner_image_id']));

    if ($attachment_id > 0) {
      $attachment_url = wp_get_attachment_url($attachment_id);
      tmw_banner_audit_log('save_post_model:update-meta', ['post_id' => (int) $post_id, 'key' => 'tmw_banner_image_id', 'value' => $attachment_id]);
      tmw_banner_audit_log('save_post_model:update-meta', ['post_id' => (int) $post_id, 'key' => 'banner_image', 'value' => $attachment_url]);
      update_post_meta($post_id, 'tmw_banner_image_id', $attachment_id);
      update_post_meta($post_id, 'banner_image', $attachment_url);
    } else {
      tmw_banner_audit_log('save_post_model:delete-meta', ['post_id' => (int) $post_id, 'keys' => ['tmw_banner_image_id', 'banner_image']]);
      delete_post_meta($post_id, 'tmw_banner_image_id');
      delete_post_meta($post_id, 'banner_image');
    }
  }
});

add_action('rest_after_insert_model', function (WP_Post $post, WP_REST_Request $request, bool $creating): void {
  $post_id = (int) $post->ID;

  // Only touch banner_image if this REST request explicitly contained tmw_banner_image_id.
  // Without this guard, every Gutenberg save (title edit, status change, etc.) would
  // delete banner_image because tmw_banner_image_id defaults to 0 in the DB — wiping any
  // banner configured via ACF term fields or a prior picker save.
  $request_meta = (array) ($request->get_param('meta') ?: []);
  $banner_key_in_request = array_key_exists('tmw_banner_image_id', $request_meta);

  if ($banner_key_in_request) {
    $attachment_id = absint(get_post_meta($post_id, 'tmw_banner_image_id', true));

    if ($attachment_id > 0) {
      $attachment_url = wp_get_attachment_url($attachment_id);
      if ($attachment_url) {
        update_post_meta($post_id, 'banner_image', $attachment_url);
      }
    } else {
      // User explicitly cleared the image — remove the override so the resolver
      // falls back to the ACF term field rather than a stale empty string.
      delete_post_meta($post_id, 'tmw_banner_image_id');
      delete_post_meta($post_id, 'banner_image');
    }
  }

  tmw_banner_audit_log('rest_after_insert_model', [
    'post_id' => $post_id,
    'creating' => $creating,
    'current_user_can_edit_post' => current_user_can('edit_post', $post_id),
    'banner_key_in_request' => $banner_key_in_request,
    'request_meta' => $request->get_param('meta'),
    'persisted_meta' => [
      '_banner_focal_y' => get_post_meta($post_id, '_banner_focal_y', true),
      'tmw_banner_image_id' => get_post_meta($post_id, 'tmw_banner_image_id', true),
      'banner_image' => get_post_meta($post_id, 'banner_image', true),
    ],
  ]);
}, 20, 3);


add_action('admin_enqueue_scripts', function ($hook) {
  if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
    return;
  }

  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || ($screen->post_type ?? '') !== 'model') {
    return;
  }

  wp_enqueue_media();
}, 20);

/* ======================================================================
 * FEATURED MODELS SHORTCODE META BOX
 * ====================================================================== */
if (!function_exists('tmw_featured_shortcode_meta_box_cb')) {
  function tmw_featured_shortcode_meta_box_cb($post) {
    $value = get_post_meta($post->ID, 'tmw_featured_shortcode', true);
    $value = is_string($value) ? $value : '';

    wp_nonce_field('tmw_featured_shortcode_save', 'tmw_featured_shortcode_nonce');
    ?>
    <p>
      <label for="tmw_featured_shortcode_field" class="screen-reader-text"><?php esc_html_e('Featured Models shortcode (optional)', 'retrotube-child'); ?></label>
      <input type="text" name="tmw_featured_shortcode" id="tmw_featured_shortcode_field" value="<?php echo esc_attr($value); ?>" class="widefat" />
    </p>
    <p class="description"><?php esc_html_e('Leave blank to use [tmw_featured_models].', 'retrotube-child'); ?></p>
    <?php
  }
}

add_action('add_meta_boxes', function () {
  $post_types = ['post', 'page', 'model', 'video', 'videos', 'wpsc-video', 'wp-script-video', 'wpws_video'];
  $post_types = array_unique($post_types);
  foreach ($post_types as $post_type) {
    if (!post_type_exists($post_type)) {
      continue;
    }

    add_meta_box(
      'tmw-featured-shortcode',
      __('Featured Models shortcode (optional)', 'retrotube-child'),
      'tmw_featured_shortcode_meta_box_cb',
      $post_type,
      'side',
      'default'
    );
  }
});

add_action('save_post', function ($post_id) {
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
    return;
  }

  if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
    return;
  }

  if (!isset($_POST['tmw_featured_shortcode_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tmw_featured_shortcode_nonce'])), 'tmw_featured_shortcode_save')) {
    return;
  }

  if (!current_user_can('edit_post', $post_id)) {
    return;
  }

  $value = '';
  if (isset($_POST['tmw_featured_shortcode'])) {
    $value = tmw_clean_featured_shortcode(wp_unslash($_POST['tmw_featured_shortcode']));
  }

  if ($value !== '') {
    update_post_meta($post_id, 'tmw_featured_shortcode', $value);
  } else {
    delete_post_meta($post_id, 'tmw_featured_shortcode');
  }
});

/* ======================================================================
 * FEATURED MODELS SHORTCODE TERM META
 * ====================================================================== */
if (!function_exists('tmw_featured_shortcode_term_add_field')) {
  function tmw_featured_shortcode_term_add_field($taxonomy) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
    wp_nonce_field('tmw_featured_shortcode_term_save', 'tmw_featured_shortcode_term_nonce');
    ?>
    <div class="form-field term-featured-shortcode-wrap">
      <label for="tmw_featured_shortcode_term_field"><?php esc_html_e('Featured Models shortcode (optional)', 'retrotube-child'); ?></label>
      <input type="text" name="tmw_featured_shortcode" id="tmw_featured_shortcode_term_field" value="" class="regular-text" />
      <p class="description"><?php esc_html_e('Leave blank to use [tmw_featured_models].', 'retrotube-child'); ?></p>
    </div>
    <?php
  }
}

if (!function_exists('tmw_featured_shortcode_term_edit_field')) {
  function tmw_featured_shortcode_term_edit_field($term) {
    $value = get_term_meta($term->term_id, 'tmw_featured_shortcode', true);
    $value = is_string($value) ? $value : '';

    wp_nonce_field('tmw_featured_shortcode_term_save', 'tmw_featured_shortcode_term_nonce');
    ?>
    <tr class="form-field term-featured-shortcode-wrap">
      <th scope="row"><label for="tmw_featured_shortcode_term_field"><?php esc_html_e('Featured Models shortcode (optional)', 'retrotube-child'); ?></label></th>
      <td>
        <input type="text" name="tmw_featured_shortcode" id="tmw_featured_shortcode_term_field" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e('Leave blank to use [tmw_featured_models].', 'retrotube-child'); ?></p>
      </td>
    </tr>
    <?php
  }
}

add_action('category_add_form_fields', 'tmw_featured_shortcode_term_add_field');
add_action('post_tag_add_form_fields', 'tmw_featured_shortcode_term_add_field');
add_action('category_edit_form_fields', 'tmw_featured_shortcode_term_edit_field');
add_action('post_tag_edit_form_fields', 'tmw_featured_shortcode_term_edit_field');

if (!function_exists('tmw_save_featured_shortcode_term_meta')) {
  function tmw_save_featured_shortcode_term_meta($term_id) {
    if (!is_admin()) {
      return;
    }

    if (!isset($_POST['tmw_featured_shortcode_term_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tmw_featured_shortcode_term_nonce'])), 'tmw_featured_shortcode_term_save')) {
      return;
    }

    if (!current_user_can('manage_categories')) {
      return;
    }

    $value = '';
    if (isset($_POST['tmw_featured_shortcode'])) {
      $value = tmw_clean_featured_shortcode(wp_unslash($_POST['tmw_featured_shortcode']));
    }

    if ($value !== '') {
      update_term_meta($term_id, 'tmw_featured_shortcode', $value);
    } else {
      delete_term_meta($term_id, 'tmw_featured_shortcode');
    }
  }
}

add_action('created_category', 'tmw_save_featured_shortcode_term_meta');
add_action('edited_category', 'tmw_save_featured_shortcode_term_meta');
add_action('created_post_tag', 'tmw_save_featured_shortcode_term_meta');
add_action('edited_post_tag', 'tmw_save_featured_shortcode_term_meta');
/**
 * Fix: Prevent PHP warnings in canonical.php
 * Avoids "Undefined array key host/scheme" and strtolower(null) notices.
 */
add_filter( 'redirect_canonical', function( $redirect_url, $requested_url ) {
    // If empty or not a string, cancel redirect
    if ( empty( $redirect_url ) || ! is_string( $redirect_url ) ) {
        return false;
    }

    $parts = wp_parse_url( $redirect_url );

    // Cancel redirect if host/scheme missing
    if ( empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
        return false;
    }

    return $redirect_url;
}, 10, 2 );

/**
 * Sync LiveJasmin performer profiles with Retrotube Model CPT
 * Triggered automatically after each imported video is linked to a model
 */
add_action( 'lvjm_model_profile_attached_video', 'rt_child_sync_model_profile', 10, 2 );

function rt_child_sync_model_profile( $model_post_id, $video_post_id ) {
    if ( ! $model_post_id || ! $video_post_id ) {
        return;
    }

    $model_post = get_post( $model_post_id );
    if ( ! $model_post || $model_post->post_status === 'trash' ) {
        return;
    }

    // Get performer terms attached to this video
    $performers = wp_get_post_terms( $video_post_id, 'models', [ 'fields' => 'names' ] );
    $performer_name = ! empty( $performers ) ? $performers[0] : $model_post->post_title;

    // Update title if needed
    if ( $model_post->post_title !== $performer_name ) {
        wp_update_post([
            'ID'         => $model_post_id,
            'post_title' => $performer_name,
        ]);
    }

    // Ensure featured image or placeholder
    if ( ! has_post_thumbnail( $model_post_id ) ) {
        $placeholder = get_post_meta( $model_post_id, 'lvjm_model_placeholder_image', true );
        if ( $placeholder && filter_var( $placeholder, FILTER_VALIDATE_URL ) ) {
            // Store placeholder as external featured image meta
            update_post_meta( $model_post_id, '_external_thumbnail_url', esc_url( $placeholder ) );
        }
    }

    // Link related videos (avoid duplicates)
    $related = (array) get_post_meta( $model_post_id, 'rt_model_videos', true );
    if ( ! in_array( $video_post_id, $related, true ) ) {
        $related[] = $video_post_id;
        update_post_meta( $model_post_id, 'rt_model_videos', $related );
    }

}

add_action('after_switch_theme', function () {
    flush_rewrite_rules();
});

/**
 * === [TMW FIX] Restore Models Taxonomy + Auto-Link ===
 * Version: v1.5.6-taxonomy-link-fix
 * Date: 2025-10-19
 */

add_action('init', function() {
  if (!taxonomy_exists('models')) {
    return;
  }

  if (function_exists('tmw_bind_models_taxonomy')) {
    tmw_bind_models_taxonomy();
  }

}, 20);

if (!function_exists('tmw_extract_model_slug_from_title')) {
  function tmw_extract_model_slug_from_title($title) {
    $title = trim((string) $title);
    if ($title === '') return null;

    if (preg_match('/with\s+([A-Za-z][A-Za-z0-9\-]+)/i', $title, $match)) {
      return sanitize_title($match[1]);
    }

    if (preg_match('/\b([A-Za-z][A-Za-z0-9]+)\b/', $title, $match)) {
      return sanitize_title($match[1]);
    }

    return null;
  }
}

if (!function_exists('tmw_autolink_video_models')) {
  function tmw_autolink_video_models($post_id, $post, $update) {
    if (wp_is_post_revision($post_id)) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!taxonomy_exists('models')) return;

    $title = get_the_title($post_id);
    if ($title === '') return;

    $slug = tmw_extract_model_slug_from_title(strtolower($title));
    if (!$slug) {
      return;
    }

    $term = get_term_by('slug', $slug, 'models');
    if (!$term instanceof WP_Term) {
      $term = get_term_by('name', ucwords(str_replace('-', ' ', $slug)), 'models');
    }

    if ($term instanceof WP_Term) {
      wp_set_post_terms($post_id, [$term->term_id], 'models', true);
    }
  }
}

add_action('save_post_video', 'tmw_autolink_video_models', 20, 3);

add_action('admin_init', function() {
  if (!is_admin() || !current_user_can('manage_options')) return;
  if (!taxonomy_exists('models')) return;
  if (get_option('tmw_models_relinked_v156')) return;

  $video_ids = get_posts([
    'post_type'      => 'video',
    'fields'         => 'ids',
    'posts_per_page' => -1,
    'no_found_rows'  => true,
  ]);

  foreach ($video_ids as $video_id) {
    $post = get_post($video_id);
    if ($post instanceof WP_Post) {
      tmw_autolink_video_models($video_id, $post, true);
    }
  }

  update_option('tmw_models_relinked_v156', 1);
}, 20);
// === [TMW-MODEL-COMMENTS] Enable comments for model post type ===
add_action( 'init', function() {
    add_post_type_support( 'model', 'comments' );
});


// === [TMW-MODEL-COMMENTS-FORCE] Always keep comments open for model pages ===
add_filter( 'comments_open', function( $open, $post_id ) {
    $post = get_post( $post_id );
    if ( $post && $post->post_type === 'model' ) {
        return true;
    }
    return $open;
}, 99, 2 );

add_filter( 'pings_open', function( $open, $post_id ) {
    $post = get_post( $post_id );
    if ( $post && $post->post_type === 'model' ) {
        return true;
    }
    return $open;
}, 99, 2 );
