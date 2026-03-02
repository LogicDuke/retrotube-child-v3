<?php

if (!defined('ABSPATH')) {
  exit;
}

if (!function_exists('tmw_flipbox_audit_enabled')) {
  function tmw_flipbox_audit_enabled(): bool {
    return (defined('TMW_AUDIT_ENABLED') && TMW_AUDIT_ENABLED === true)
      || (defined('TMW_FLIPBOX_AUDIT') && TMW_FLIPBOX_AUDIT === true);
  }
}

if (!function_exists('tmw_flipbox_audit_log')) {
  /**
   * Emit debug logs for flipbox save/audit flow.
   *
   * @param string               $event   Event label.
   * @param array<string, mixed> $context Additional context payload.
   */
  function tmw_flipbox_audit_log(string $event, array $context = []): void {
    if (!tmw_flipbox_audit_enabled()) {
      return;
    }

    $request_context = [
      'REST_REQUEST' => defined('REST_REQUEST') ? (bool) REST_REQUEST : false,
      'wp_doing_ajax' => function_exists('wp_doing_ajax') ? wp_doing_ajax() : false,
      'request_method' => isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '',
      'request_uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '',
    ];

    error_log('[TMW-AUDIT-FLIPBOX] ' . $event . ' ' . wp_json_encode(array_merge($request_context, $context))); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
  }
}

if (!function_exists('tmw_model_flipbox_metabox_default_values')) {
  /**
   * Default values for model flipbox settings.
   *
   * @return array<string,float|int>
   */
  function tmw_model_flipbox_metabox_default_values(): array {
    return [
      'tmw_flip_front_id' => 0,
      'tmw_flip_back_id' => 0,
      'tmw_flip_pos_front' => 50,
      'tmw_flip_pos_back' => 50,
      'tmw_flip_zoom_front' => 1.0,
      'tmw_flip_zoom_back' => 1.0,
    ];
  }
}

if (!function_exists('tmw_model_flipbox_metabox_keys')) {
  /**
   * Registered flipbox meta keys.
   *
   * @return string[]
   */
  function tmw_model_flipbox_metabox_keys(): array {
    return array_keys(tmw_model_flipbox_metabox_default_values());
  }
}

if (!function_exists('tmw_model_flipbox_metabox_get_term')) {
  /**
   * Resolve the assigned models taxonomy term for a model post.
   *
   * @param int $post_id Model post ID.
   * @return WP_Term|null
   */
  function tmw_model_flipbox_metabox_get_term(int $post_id): ?WP_Term {
    $terms = wp_get_post_terms($post_id, 'models', ['fields' => 'all']);
    if (is_wp_error($terms) || empty($terms) || !($terms[0] instanceof WP_Term)) {
      return null;
    }

    return $terms[0];
  }
}

if (!function_exists('tmw_model_flipbox_metabox_get_values')) {
  /**
   * Load values from post meta first, term meta fallback.
   *
   * @param int $post_id Model post ID.
   * @return array<string,float|int>
   */
  function tmw_model_flipbox_metabox_get_values(int $post_id): array {
    $values = tmw_model_flipbox_metabox_default_values();
    $term = tmw_model_flipbox_metabox_get_term($post_id);

    foreach (tmw_model_flipbox_metabox_keys() as $key) {
      $value = '';
      $value = get_post_meta($post_id, $key, true);

      if ($value === '' && $term) {
        $value = get_term_meta($term->term_id, $key, true);
      }

      if ($value === '') {
        continue;
      }

      if (in_array($key, ['tmw_flip_front_id', 'tmw_flip_back_id'], true)) {
        $values[$key] = absint($value);
      } elseif (strpos($key, 'zoom') !== false) {
        $values[$key] = (float) $value;
      } else {
        $values[$key] = (int) $value;
      }
    }

    $values['tmw_flip_pos_front'] = max(0, min(100, (int) $values['tmw_flip_pos_front']));
    $values['tmw_flip_pos_back'] = max(0, min(100, (int) $values['tmw_flip_pos_back']));
    $values['tmw_flip_zoom_front'] = max(1.0, min(2.5, (float) $values['tmw_flip_zoom_front']));
    $values['tmw_flip_zoom_back'] = max(1.0, min(2.5, (float) $values['tmw_flip_zoom_back']));

    return $values;
  }
}


if (!function_exists('tmw_render_model_flipbox_metabox')) {
  /**
   * Render model flipbox metabox fields.
   *
   * @param WP_Post $post Post object.
   */
  function tmw_render_model_flipbox_metabox(WP_Post $post): void {
    $values = tmw_model_flipbox_metabox_get_values((int) $post->ID);

    $front_id = (int) $values['tmw_flip_front_id'];
    $back_id = (int) $values['tmw_flip_back_id'];
    $term = tmw_model_flipbox_metabox_get_term((int) $post->ID);

    $front_url = $front_id ? wp_get_attachment_image_url($front_id, 'full') : '';
    $back_url = $back_id ? wp_get_attachment_image_url($back_id, 'full') : '';
    $audit_enabled = tmw_flipbox_audit_enabled();

    wp_nonce_field('tmw_flipbox_meta', 'tmw_flipbox_meta_nonce');
    ?>
    <p>If empty, defaults to Flipbox (TMW) tools / AWE feed.</p>

    <?php if ($audit_enabled) : ?>
      <?php
      $first_term_id = $term instanceof WP_Term ? (int) $term->term_id : 0;
      $first_term_slug = $term instanceof WP_Term ? (string) $term->slug : '';
      $post_meta_debug = [];
      $term_meta_debug = [];

      foreach (tmw_model_flipbox_metabox_keys() as $meta_key) {
        $post_meta_debug[$meta_key] = get_post_meta((int) $post->ID, $meta_key, true);
        $term_meta_debug[$meta_key] = $first_term_id ? get_term_meta($first_term_id, $meta_key, true) : '';
      }
      ?>
      <div class="notice notice-info inline">
        <p><strong>[TMW-FLIPBOX-AUDIT-PREVIEW]</strong> Preview method: <code>div.tmw-flipbox-card</code> with <code>background-image</code>. Container classes: <code>tmw-mb-card-wrap</code>, <code>tmw-flipbox-card</code>. Intended ratio class is <code>.tmw-flipbox-card</code> (CSS aspect-ratio 2 / 3).</p>
      </div>
      <table class="widefat striped" style="margin:12px 0;max-width:980px;">
        <thead>
          <tr>
            <th colspan="3">[TMW-FLIPBOX-AUDIT-SAVE] Storage Debug</th>
          </tr>
          <tr>
            <th>Key</th>
            <th>Post Meta</th>
            <th>Term Meta (first assigned term)</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>Assigned models term</strong></td>
            <td colspan="2">
              <?php echo $first_term_id ? esc_html('ID ' . $first_term_id . ' / ' . $first_term_slug) : esc_html('No assigned models term'); ?>
            </td>
          </tr>
          <?php foreach (tmw_model_flipbox_metabox_keys() as $meta_key) : ?>
            <tr>
              <td><code><?php echo esc_html($meta_key); ?></code></td>
              <td><code><?php echo esc_html(wp_json_encode($post_meta_debug[$meta_key])); ?></code></td>
              <td><code><?php echo esc_html(wp_json_encode($term_meta_debug[$meta_key])); ?></code></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if (!$term) : ?>
      <div class="notice notice-warning inline">
        <p>Assign a Models term in the sidebar and save, then set Flipbox images.</p>
      </div>
    <?php endif; ?>

    <div class="tmw-flipbox-grid">
      <div class="tmw-flipbox-panel" data-side="front">
        <strong>Front Image</strong>
        <input type="hidden" id="tmw_flip_front_id" name="tmw_flip_front_id" value="<?php echo esc_attr((string) $front_id); ?>">
        <div class="tmw-flipbox-actions">
          <button type="button" class="button tmw-flipbox-pick" data-side="front" data-target="tmw_flip_front_id">Choose Front Image</button>
          <button type="button" class="button tmw-flipbox-remove" data-side="front" data-target="tmw_flip_front_id">Remove</button>
        </div>
        <div class="tmw-mb-card-wrap">
          <div class="tmw-flipbox-card" id="tmw-flipbox-front-preview" data-side="front" data-url="<?php echo esc_url((string) $front_url); ?>" style="<?php echo $front_url ? 'background-image:url(' . esc_url($front_url) . ');' : ''; ?>"></div>
          <div class="tmw-flipbox-preview-label">Front</div>
        </div>
        <p class="tmw-flipbox-control-row">
          <label for="tmw_flip_pos_front">Horizontal position</label><br>
          <input type="range" id="tmw_flip_pos_front" name="tmw_flip_pos_front" min="0" max="100" step="1" value="<?php echo esc_attr((string) $values['tmw_flip_pos_front']); ?>" data-readout="#tmw_flip_pos_front_readout" data-unit="%" data-side="front" data-control="position">
          <span id="tmw_flip_pos_front_readout" class="tmw-flipbox-readout"><?php echo esc_html((string) $values['tmw_flip_pos_front']); ?>%</span>
        </p>
        <p class="tmw-flipbox-control-row">
          <label for="tmw_flip_zoom_front">Zoom</label><br>
          <input type="range" id="tmw_flip_zoom_front" name="tmw_flip_zoom_front" min="1" max="2.5" step="0.1" value="<?php echo esc_attr((string) $values['tmw_flip_zoom_front']); ?>" data-readout="#tmw_flip_zoom_front_readout" data-side="front" data-control="zoom">
          <span id="tmw_flip_zoom_front_readout" class="tmw-flipbox-readout"><?php echo esc_html(number_format((float) $values['tmw_flip_zoom_front'], 1)); ?></span>
        </p>
      </div>

      <div class="tmw-flipbox-panel" data-side="back">
        <strong>Back Image</strong>
        <input type="hidden" id="tmw_flip_back_id" name="tmw_flip_back_id" value="<?php echo esc_attr((string) $back_id); ?>">
        <div class="tmw-flipbox-actions">
          <button type="button" class="button tmw-flipbox-pick" data-side="back" data-target="tmw_flip_back_id">Choose Back Image</button>
          <button type="button" class="button tmw-flipbox-remove" data-side="back" data-target="tmw_flip_back_id">Remove</button>
        </div>
        <div class="tmw-mb-card-wrap">
          <div class="tmw-flipbox-card" id="tmw-flipbox-back-preview" data-side="back" data-url="<?php echo esc_url((string) $back_url); ?>" style="<?php echo $back_url ? 'background-image:url(' . esc_url($back_url) . ');' : ''; ?>"></div>
          <div class="tmw-flipbox-preview-label">Back</div>
        </div>
        <p class="tmw-flipbox-control-row">
          <label for="tmw_flip_pos_back">Horizontal position</label><br>
          <input type="range" id="tmw_flip_pos_back" name="tmw_flip_pos_back" min="0" max="100" step="1" value="<?php echo esc_attr((string) $values['tmw_flip_pos_back']); ?>" data-readout="#tmw_flip_pos_back_readout" data-unit="%" data-side="back" data-control="position">
          <span id="tmw_flip_pos_back_readout" class="tmw-flipbox-readout"><?php echo esc_html((string) $values['tmw_flip_pos_back']); ?>%</span>
        </p>
        <p class="tmw-flipbox-control-row">
          <label for="tmw_flip_zoom_back">Zoom</label><br>
          <input type="range" id="tmw_flip_zoom_back" name="tmw_flip_zoom_back" min="1" max="2.5" step="0.1" value="<?php echo esc_attr((string) $values['tmw_flip_zoom_back']); ?>" data-readout="#tmw_flip_zoom_back_readout" data-side="back" data-control="zoom">
          <span id="tmw_flip_zoom_back_readout" class="tmw-flipbox-readout"><?php echo esc_html(number_format((float) $values['tmw_flip_zoom_back'], 1)); ?></span>
        </p>
      </div>
    </div>
    <?php
  }
}

add_action('add_meta_boxes', function (): void {
  add_meta_box('tmw_flipbox', 'Flipbox Images', 'tmw_render_model_flipbox_metabox', 'model', 'normal', 'default');
});


add_action('save_post_model', function (int $post_id): void {
  tmw_flipbox_audit_log('save_post_model:entered', [
    'post_id' => $post_id,
    'current_user_can_edit_post' => current_user_can('edit_post', $post_id),
    'has_nonce' => isset($_POST['tmw_flipbox_meta_nonce']),
    'posted_meta' => [
      'tmw_flip_front_id' => isset($_POST['tmw_flip_front_id']) ? wp_unslash($_POST['tmw_flip_front_id']) : null,
      'tmw_flip_back_id' => isset($_POST['tmw_flip_back_id']) ? wp_unslash($_POST['tmw_flip_back_id']) : null,
      'tmw_flip_pos_front' => isset($_POST['tmw_flip_pos_front']) ? wp_unslash($_POST['tmw_flip_pos_front']) : null,
      'tmw_flip_pos_back' => isset($_POST['tmw_flip_pos_back']) ? wp_unslash($_POST['tmw_flip_pos_back']) : null,
      'tmw_flip_zoom_front' => isset($_POST['tmw_flip_zoom_front']) ? wp_unslash($_POST['tmw_flip_zoom_front']) : null,
      'tmw_flip_zoom_back' => isset($_POST['tmw_flip_zoom_back']) ? wp_unslash($_POST['tmw_flip_zoom_back']) : null,
    ],
  ]);

  if (!current_user_can('edit_post', $post_id)) {
    tmw_flipbox_audit_log('save_post_model:exit-no-cap', ['post_id' => $post_id]);
    return;
  }

  if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
    tmw_flipbox_audit_log('save_post_model:exit-autosave-or-revision', ['post_id' => $post_id]);
    return;
  }

  if (defined('REST_REQUEST') && REST_REQUEST) {
    tmw_flipbox_audit_log('save_post_model:exit-rest-request', ['post_id' => $post_id]);
    return;
  }

  if (!isset($_POST['tmw_flipbox_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tmw_flipbox_meta_nonce'])), 'tmw_flipbox_meta')) {
    tmw_flipbox_audit_log('save_post_model:exit-nonce', ['post_id' => $post_id]);
    return;
  }

  $raw_front_id = isset($_POST['tmw_flip_front_id']) ? wp_unslash($_POST['tmw_flip_front_id']) : 0;
  $raw_back_id = isset($_POST['tmw_flip_back_id']) ? wp_unslash($_POST['tmw_flip_back_id']) : 0;
  $raw_pos_front = isset($_POST['tmw_flip_pos_front']) ? wp_unslash($_POST['tmw_flip_pos_front']) : 50;
  $raw_pos_back = isset($_POST['tmw_flip_pos_back']) ? wp_unslash($_POST['tmw_flip_pos_back']) : 50;
  $raw_zoom_front = isset($_POST['tmw_flip_zoom_front']) ? wp_unslash($_POST['tmw_flip_zoom_front']) : 1.0;
  $raw_zoom_back = isset($_POST['tmw_flip_zoom_back']) ? wp_unslash($_POST['tmw_flip_zoom_back']) : 1.0;

  $term = tmw_model_flipbox_metabox_get_term($post_id);

  $sanitized = [
    'tmw_flip_front_id' => tmw_model_flipbox_sanitize_absint($raw_front_id),
    'tmw_flip_back_id' => tmw_model_flipbox_sanitize_absint($raw_back_id),
    'tmw_flip_pos_front' => tmw_model_flipbox_sanitize_pos($raw_pos_front),
    'tmw_flip_pos_back' => tmw_model_flipbox_sanitize_pos($raw_pos_back),
    'tmw_flip_zoom_front' => tmw_model_flipbox_sanitize_zoom($raw_zoom_front),
    'tmw_flip_zoom_back' => tmw_model_flipbox_sanitize_zoom($raw_zoom_back),
  ];

  foreach ($sanitized as $key => $value) {
    tmw_flipbox_audit_log('save_post_model:update-meta', [
      'post_id' => $post_id,
      'key' => $key,
      'value' => $value,
      'writes_term_meta' => (bool) $term,
      'term_id' => $term ? (int) $term->term_id : 0,
    ]);
    update_post_meta($post_id, $key, $value);

    if ($term) {
      update_term_meta($term->term_id, $key, $value);
    }
  }
}, 30);

add_action('rest_after_insert_model', function (WP_Post $post, WP_REST_Request $request, bool $creating): void {
  $post_id = (int) $post->ID;
  $term = tmw_model_flipbox_metabox_get_term($post_id);
  $post_meta_snapshot = [];
  $term_meta_snapshot = [];

  foreach (tmw_model_flipbox_metabox_keys() as $meta_key) {
    $raw_value = get_post_meta($post_id, $meta_key, true);

    if (in_array($meta_key, ['tmw_flip_front_id', 'tmw_flip_back_id'], true)) {
      $sanitized_value = tmw_model_flipbox_sanitize_absint($raw_value);
    } elseif (strpos($meta_key, 'zoom') !== false) {
      $sanitized_value = tmw_model_flipbox_sanitize_zoom($raw_value);
    } else {
      $sanitized_value = tmw_model_flipbox_sanitize_pos($raw_value);
    }

    update_post_meta($post_id, $meta_key, $sanitized_value);

    if ($term) {
      update_term_meta((int) $term->term_id, $meta_key, $sanitized_value);
    }

    $post_meta_snapshot[$meta_key] = get_post_meta($post_id, $meta_key, true);
    $term_meta_snapshot[$meta_key] = $term ? get_term_meta((int) $term->term_id, $meta_key, true) : null;
  }

  tmw_flipbox_audit_log('rest_after_insert_model', [
    'post_id' => $post_id,
    'creating' => $creating,
    'current_user_can_edit_post' => current_user_can('edit_post', $post_id),
    'request_meta' => $request->get_param('meta'),
    'persisted_post_meta' => $post_meta_snapshot,
    'persisted_term_meta' => $term_meta_snapshot,
    'term_id' => $term ? (int) $term->term_id : 0,
  ]);
}, 10, 3);

add_action('admin_enqueue_scripts', function ($hook): void {
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen) {
    return;
  }

  if (!in_array($screen->base, ['post', 'post-new'], true)) {
    return;
  }

  if (($screen->post_type ?? '') !== 'model') {
    return;
  }

  wp_enqueue_media();
  wp_enqueue_script(
    'tmw-model-flipbox-metabox',
    get_stylesheet_directory_uri() . '/js/tmw-model-flipbox-metabox.js',
    ['jquery', 'wp-data'],
    '1.4.0',
    true
  );

  $meta_rest_registration = [];
  foreach (tmw_model_flipbox_metabox_keys() as $meta_key) {
    $meta_rest_registration[$meta_key] = false;
    if (function_exists('registered_meta_key_exists') && registered_meta_key_exists('post', $meta_key, 'model')) {
      $registered = get_registered_meta_keys('post', 'model');
      $meta_rest_registration[$meta_key] = isset($registered[$meta_key]['show_in_rest']) && (bool) $registered[$meta_key]['show_in_rest'];
    }
  }

  wp_localize_script('tmw-model-flipbox-metabox', 'tmwFlipboxAudit', [
    'enabled' => tmw_flipbox_audit_enabled(),
    'metaKeys' => tmw_model_flipbox_metabox_keys(),
    'metaRegisteredInRest' => $meta_rest_registration,
  ]);

  wp_enqueue_style(
    'tmw-model-flipbox-metabox',
    get_stylesheet_directory_uri() . '/css/tmw-model-flipbox-metabox.css',
    [],
    '1.4.0'
  );
});

add_action('admin_notices', function (): void {
  if (!tmw_flipbox_audit_enabled()) {
    return;
  }

  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || !in_array($screen->base, ['post', 'post-new'], true) || ($screen->post_type ?? '') !== 'model') {
    return;
  }

  echo '<div class="notice notice-warning"><p>[TMW-FLIPBOX-AUDIT] Flipbox audit enabled. Open DevTools console for [TMW-FLIPBOX-AUDIT-*] logs.</p></div>';
});

add_action('admin_footer', function (): void {
  if (!tmw_flipbox_audit_enabled()) {
    return;
  }

  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || !in_array($screen->base, ['post', 'post-new'], true) || ($screen->post_type ?? '') !== 'model') {
    return;
  }

  $registered = get_registered_meta_keys('post', 'model');
  $missing_rest = [];
  foreach (tmw_model_flipbox_metabox_keys() as $meta_key) {
    if (!isset($registered[$meta_key]['show_in_rest']) || !$registered[$meta_key]['show_in_rest']) {
      $missing_rest[] = $meta_key;
    }
  }

  if (!empty($missing_rest)) {
    echo '<div class="notice notice-error inline"><p>[TMW-FLIPBOX-AUDIT-SAVE] Meta not registered in REST; Gutenberg save won\'t persist classic metabox POST. Missing keys: <code>' . esc_html(implode(', ', $missing_rest)) . '</code></p></div>';
  }
});
