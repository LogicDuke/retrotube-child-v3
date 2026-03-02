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
   * Resolve a models taxonomy term by model post slug.
   *
   * @param int $post_id Model post ID.
   * @return WP_Term|null
   */
  function tmw_model_flipbox_metabox_get_term(int $post_id): ?WP_Term {
    $slug = (string) get_post_field('post_name', $post_id);
    if ($slug === '') {
      return null;
    }

    $term = get_term_by('slug', $slug, 'models');
    if (!$term instanceof WP_Term) {
      return null;
    }

    return $term;
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

    $front_url = $front_id ? wp_get_attachment_image_url($front_id, 'thumbnail') : '';
    $back_url = $back_id ? wp_get_attachment_image_url($back_id, 'thumbnail') : '';

    wp_nonce_field('tmw_flipbox_meta', 'tmw_flipbox_meta_nonce');
    ?>
    <p>If empty, defaults to Flipbox (TMW) tools / AWE feed.</p>

    <div class="tmw-flipbox-media-row" style="margin-bottom:12px;">
      <strong>Front Image</strong><br>
      <input type="hidden" id="tmw_flip_front_id" name="tmw_flip_front_id" value="<?php echo esc_attr((string) $front_id); ?>">
      <button type="button" class="button tmw-flipbox-pick" data-target="tmw_flip_front_id" data-preview="tmw_flip_front_id_preview">Choose Front Image</button>
      <button type="button" class="button tmw-flipbox-remove" data-target="tmw_flip_front_id" data-preview="tmw_flip_front_id_preview">Remove</button>
      <div class="tmw-flipbox-preview tmw-flipbox-preview-front" style="margin-top:8px;">
        <img id="tmw_flip_front_id_preview" src="<?php echo esc_url((string) $front_url); ?>" alt="" style="max-width:120px;height:auto;<?php echo $front_url ? '' : 'display:none;'; ?>">
      </div>
    </div>

    <div class="tmw-flipbox-media-row" style="margin-bottom:12px;">
      <strong>Back Image</strong><br>
      <input type="hidden" id="tmw_flip_back_id" name="tmw_flip_back_id" value="<?php echo esc_attr((string) $back_id); ?>">
      <button type="button" class="button tmw-flipbox-pick" data-target="tmw_flip_back_id" data-preview="tmw_flip_back_id_preview">Choose Back Image</button>
      <button type="button" class="button tmw-flipbox-remove" data-target="tmw_flip_back_id" data-preview="tmw_flip_back_id_preview">Remove</button>
      <div class="tmw-flipbox-preview tmw-flipbox-preview-back" style="margin-top:8px;">
        <img id="tmw_flip_back_id_preview" src="<?php echo esc_url((string) $back_url); ?>" alt="" style="max-width:120px;height:auto;<?php echo $back_url ? '' : 'display:none;'; ?>">
      </div>
    </div>

    <p class="tmw-flipbox-control-row">
      <label for="tmw_flip_pos_front"><strong>Front Horizontal Position (0–100)</strong></label><br>
      <input type="range" id="tmw_flip_pos_front" name="tmw_flip_pos_front" min="0" max="100" step="1" value="<?php echo esc_attr((string) $values['tmw_flip_pos_front']); ?>" data-readout="#tmw_flip_pos_front_readout" data-unit="%">
      <span id="tmw_flip_pos_front_readout" class="tmw-flipbox-readout"><?php echo esc_html((string) $values['tmw_flip_pos_front']); ?>%</span>
    </p>
    <p class="tmw-flipbox-control-row">
      <label for="tmw_flip_pos_back"><strong>Back Horizontal Position (0–100)</strong></label><br>
      <input type="range" id="tmw_flip_pos_back" name="tmw_flip_pos_back" min="0" max="100" step="1" value="<?php echo esc_attr((string) $values['tmw_flip_pos_back']); ?>" data-readout="#tmw_flip_pos_back_readout" data-unit="%">
      <span id="tmw_flip_pos_back_readout" class="tmw-flipbox-readout"><?php echo esc_html((string) $values['tmw_flip_pos_back']); ?>%</span>
    </p>
    <p class="tmw-flipbox-control-row">
      <label for="tmw_flip_zoom_front"><strong>Front Zoom (1.0–2.5)</strong></label><br>
      <input type="range" id="tmw_flip_zoom_front" name="tmw_flip_zoom_front" min="1" max="2.5" step="0.1" value="<?php echo esc_attr((string) $values['tmw_flip_zoom_front']); ?>" data-readout="#tmw_flip_zoom_front_readout">
      <span id="tmw_flip_zoom_front_readout" class="tmw-flipbox-readout"><?php echo esc_html(number_format((float) $values['tmw_flip_zoom_front'], 1)); ?></span>
    </p>
    <p class="tmw-flipbox-control-row">
      <label for="tmw_flip_zoom_back"><strong>Back Zoom (1.0–2.5)</strong></label><br>
      <input type="range" id="tmw_flip_zoom_back" name="tmw_flip_zoom_back" min="1" max="2.5" step="0.1" value="<?php echo esc_attr((string) $values['tmw_flip_zoom_back']); ?>" data-readout="#tmw_flip_zoom_back_readout">
      <span id="tmw_flip_zoom_back_readout" class="tmw-flipbox-readout"><?php echo esc_html(number_format((float) $values['tmw_flip_zoom_back'], 1)); ?></span>
    </p>
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
    '1.0.0',
    true
  );

  wp_enqueue_style(
    'tmw-model-flipbox-metabox',
    get_stylesheet_directory_uri() . '/css/tmw-model-flipbox-metabox.css',
    [],
    '1.0.0'
  );
});
