<?php

if (!defined('ABSPATH')) {
  exit;
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
   * Load values from term meta first, post meta fallback.
   *
   * @param int $post_id Model post ID.
   * @return array<string,float|int>
   */
  function tmw_model_flipbox_metabox_get_values(int $post_id): array {
    $values = tmw_model_flipbox_metabox_default_values();
    $term = tmw_model_flipbox_metabox_get_term($post_id);

    foreach (tmw_model_flipbox_metabox_keys() as $key) {
      $value = '';
      if ($term) {
        $value = get_term_meta($term->term_id, $key, true);
      }

      if ($value === '') {
        $value = get_post_meta($post_id, $key, true);
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

    wp_nonce_field('tmw_flipbox_meta', 'tmw_flipbox_meta_nonce');
    ?>
    <p>If empty, defaults to Flipbox (TMW) tools / AWE feed.</p>

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
          <div class="tmw-mb-card" id="tmw_flip_front_preview" data-target="front" data-url="<?php echo esc_url((string) $front_url); ?>" style="<?php echo $front_url ? 'background-image:url(' . esc_url($front_url) . ');' : ''; ?>"></div>
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
          <div class="tmw-mb-card" id="tmw_flip_back_preview" data-target="back" data-url="<?php echo esc_url((string) $back_url); ?>" style="<?php echo $back_url ? 'background-image:url(' . esc_url($back_url) . ');' : ''; ?>"></div>
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

if (!function_exists('tmw_model_flipbox_metabox_clamp_int')) {
  function tmw_model_flipbox_metabox_clamp_int($value, int $min, int $max): int {
    return max($min, min($max, (int) $value));
  }
}

if (!function_exists('tmw_model_flipbox_metabox_clamp_float')) {
  function tmw_model_flipbox_metabox_clamp_float($value, float $min, float $max): float {
    return max($min, min($max, (float) $value));
  }
}

add_action('save_post_model', function (int $post_id): void {
  if (!isset($_POST['tmw_flipbox_meta_nonce'])) {
    return;
  }

  $nonce = sanitize_text_field(wp_unslash($_POST['tmw_flipbox_meta_nonce']));
  if (!wp_verify_nonce($nonce, 'tmw_flipbox_meta')) {
    return;
  }

  if (!current_user_can('edit_post', $post_id)) {
    return;
  }

  if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
    return;
  }

  $sanitized = [
    'tmw_flip_front_id' => isset($_POST['tmw_flip_front_id']) ? absint($_POST['tmw_flip_front_id']) : 0,
    'tmw_flip_back_id' => isset($_POST['tmw_flip_back_id']) ? absint($_POST['tmw_flip_back_id']) : 0,
    'tmw_flip_pos_front' => isset($_POST['tmw_flip_pos_front']) ? tmw_model_flipbox_metabox_clamp_int(wp_unslash($_POST['tmw_flip_pos_front']), 0, 100) : 50,
    'tmw_flip_pos_back' => isset($_POST['tmw_flip_pos_back']) ? tmw_model_flipbox_metabox_clamp_int(wp_unslash($_POST['tmw_flip_pos_back']), 0, 100) : 50,
    'tmw_flip_zoom_front' => isset($_POST['tmw_flip_zoom_front']) ? tmw_model_flipbox_metabox_clamp_float(wp_unslash($_POST['tmw_flip_zoom_front']), 1.0, 2.5) : 1.0,
    'tmw_flip_zoom_back' => isset($_POST['tmw_flip_zoom_back']) ? tmw_model_flipbox_metabox_clamp_float(wp_unslash($_POST['tmw_flip_zoom_back']), 1.0, 2.5) : 1.0,
  ];

  foreach ($sanitized as $key => $value) {
    update_post_meta($post_id, $key, $value);
  }

  $term = tmw_model_flipbox_metabox_get_term($post_id);
  if (!$term) {
    return;
  }

  foreach ($sanitized as $key => $value) {
    update_term_meta($term->term_id, $key, $value);
  }
});

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
    ['jquery'],
    '1.2.0',
    true
  );

  wp_enqueue_style(
    'tmw-model-flipbox-metabox',
    get_stylesheet_directory_uri() . '/css/tmw-model-flipbox-metabox.css',
    [],
    '1.2.0'
  );
});
