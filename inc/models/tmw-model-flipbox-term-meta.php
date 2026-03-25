<?php

/* ======================================================================
 * MODELS FLIPBOX TERM META (front/back image + alignment/zoom)
 * ====================================================================== */
if (!function_exists('tmw_model_flipbox_term_fields')) {
  /**
   * Render flipbox fields on model taxonomy add/edit screens.
   *
   * @param WP_Term|null $term Term object when editing.
   */
  function tmw_model_flipbox_term_fields($term = null): void {
    $is_edit = $term instanceof WP_Term;
    $term_id = $is_edit ? (int) $term->term_id : 0;

    $front_id   = $term_id ? (int) get_term_meta($term_id, 'tmw_flip_front_id', true) : 0;
    $back_id    = $term_id ? (int) get_term_meta($term_id, 'tmw_flip_back_id', true) : 0;
    $pos_front  = $term_id ? (int) get_term_meta($term_id, 'tmw_flip_pos_front', true) : 50;
    $pos_back   = $term_id ? (int) get_term_meta($term_id, 'tmw_flip_pos_back', true) : 50;
    $zoom_front = $term_id ? (float) get_term_meta($term_id, 'tmw_flip_zoom_front', true) : 1.0;
    $zoom_back  = $term_id ? (float) get_term_meta($term_id, 'tmw_flip_zoom_back', true) : 1.0;

    $pos_front  = max(0, min(100, $pos_front));
    $pos_back   = max(0, min(100, $pos_back));
    $zoom_front = max(1.0, min(2.5, $zoom_front));
    $zoom_back  = max(1.0, min(2.5, $zoom_back));

    $front_url = $front_id ? wp_get_attachment_image_url($front_id, 'thumbnail') : '';
    $back_url  = $back_id ? wp_get_attachment_image_url($back_id, 'thumbnail') : '';

    $fields = [
      [
        'id' => 'tmw_flip_front_id',
        'label' => 'Flipbox Front Image',
        'button' => 'Choose Front Image',
        'remove' => 'Remove Front Image',
        'value' => $front_id,
        'preview' => $front_url,
      ],
      [
        'id' => 'tmw_flip_back_id',
        'label' => 'Flipbox Back Image',
        'button' => 'Choose Back Image',
        'remove' => 'Remove Back Image',
        'value' => $back_id,
        'preview' => $back_url,
      ],
    ];

    ob_start();
    ?>
    <div class="tmw-flipbox-term-meta-wrap">
      <p class="description">If empty, defaults to Flipbox (TMW) tools / AWE feed.</p>
      <?php foreach ($fields as $field) : ?>
        <div class="tmw-flipbox-media-row" style="margin-bottom:12px;">
          <label for="<?php echo esc_attr($field['id']); ?>"><strong><?php echo esc_html($field['label']); ?></strong></label><br>
          <input type="hidden" id="<?php echo esc_attr($field['id']); ?>" name="<?php echo esc_attr($field['id']); ?>" value="<?php echo esc_attr((string) $field['value']); ?>">
          <button type="button" class="button tmw-flipbox-pick" data-target="<?php echo esc_attr($field['id']); ?>" data-preview="<?php echo esc_attr($field['id']); ?>_preview"><?php echo esc_html($field['button']); ?></button>
          <button type="button" class="button tmw-flipbox-remove" data-target="<?php echo esc_attr($field['id']); ?>" data-preview="<?php echo esc_attr($field['id']); ?>_preview"><?php echo esc_html($field['remove']); ?></button>
          <div style="margin-top:8px;">
            <img id="<?php echo esc_attr($field['id']); ?>_preview" src="<?php echo esc_url((string) $field['preview']); ?>" alt="" style="max-width:120px;height:auto;<?php echo $field['preview'] ? '' : 'display:none;'; ?>">
          </div>
        </div>
      <?php endforeach; ?>

      <div class="tmw-flipbox-controls" style="display:grid;grid-template-columns:repeat(2,minmax(240px,1fr));gap:12px;max-width:640px;">
        <p><label for="tmw_flip_pos_front"><strong>Front Alignment (0–100)</strong></label><br>
          <input type="number" name="tmw_flip_pos_front" id="tmw_flip_pos_front" min="0" max="100" step="1" value="<?php echo esc_attr((string) $pos_front); ?>"></p>
        <p><label for="tmw_flip_pos_back"><strong>Back Alignment (0–100)</strong></label><br>
          <input type="number" name="tmw_flip_pos_back" id="tmw_flip_pos_back" min="0" max="100" step="1" value="<?php echo esc_attr((string) $pos_back); ?>"></p>
        <p><label for="tmw_flip_zoom_front"><strong>Front Zoom (1.0–2.5)</strong></label><br>
          <input type="number" name="tmw_flip_zoom_front" id="tmw_flip_zoom_front" min="1" max="2.5" step="0.1" value="<?php echo esc_attr((string) $zoom_front); ?>"></p>
        <p><label for="tmw_flip_zoom_back"><strong>Back Zoom (1.0–2.5)</strong></label><br>
          <input type="number" name="tmw_flip_zoom_back" id="tmw_flip_zoom_back" min="1" max="2.5" step="0.1" value="<?php echo esc_attr((string) $zoom_back); ?>"></p>
      </div>
    </div>
    <?php
    $html = ob_get_clean();

    if ($is_edit) {
      ?>
      <tr class="form-field">
        <th scope="row"><label>Flipbox Images</label></th>
        <td>
          <?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          <?php wp_nonce_field('tmw_flipbox_term_meta', 'tmw_flipbox_term_meta_nonce'); ?>
        </td>
      </tr>
      <?php
      return;
    }

    ?>
    <div class="form-field term-group">
      <label>Flipbox Images</label>
      <?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      <?php wp_nonce_field('tmw_flipbox_term_meta', 'tmw_flipbox_term_meta_nonce'); ?>
    </div>
    <?php
  }
}

add_action('models_add_form_fields', function () {
  tmw_model_flipbox_term_fields();
});

add_action('models_edit_form_fields', function ($term) {
  tmw_model_flipbox_term_fields($term);
});

if (!function_exists('tmw_model_flipbox_clamp_int')) {
  function tmw_model_flipbox_clamp_int($value, int $min, int $max): int {
    return max($min, min($max, (int) $value));
  }
}

if (!function_exists('tmw_model_flipbox_clamp_float')) {
  function tmw_model_flipbox_clamp_float($value, float $min, float $max): float {
    return max($min, min($max, (float) $value));
  }
}

if (!function_exists('tmw_save_model_flipbox_term_meta')) {
  /**
   * Persist flipbox term meta fields.
   *
   * @param int $term_id Term ID.
   */
  function tmw_save_model_flipbox_term_meta($term_id): void {
    if (!isset($_POST['tmw_flipbox_term_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tmw_flipbox_term_meta_nonce'])), 'tmw_flipbox_term_meta')) {
      return;
    }

    $front_id = isset($_POST['tmw_flip_front_id']) ? absint($_POST['tmw_flip_front_id']) : 0;
    $back_id  = isset($_POST['tmw_flip_back_id']) ? absint($_POST['tmw_flip_back_id']) : 0;

    $pos_front  = isset($_POST['tmw_flip_pos_front']) ? tmw_model_flipbox_clamp_int(wp_unslash($_POST['tmw_flip_pos_front']), 0, 100) : 50;
    $pos_back   = isset($_POST['tmw_flip_pos_back']) ? tmw_model_flipbox_clamp_int(wp_unslash($_POST['tmw_flip_pos_back']), 0, 100) : 50;
    $zoom_front = isset($_POST['tmw_flip_zoom_front']) ? tmw_model_flipbox_clamp_float(wp_unslash($_POST['tmw_flip_zoom_front']), 1.0, 2.5) : 1.0;
    $zoom_back  = isset($_POST['tmw_flip_zoom_back']) ? tmw_model_flipbox_clamp_float(wp_unslash($_POST['tmw_flip_zoom_back']), 1.0, 2.5) : 1.0;

    update_term_meta($term_id, 'tmw_flip_front_id', $front_id);
    update_term_meta($term_id, 'tmw_flip_back_id', $back_id);
    update_term_meta($term_id, 'tmw_flip_pos_front', $pos_front);
    update_term_meta($term_id, 'tmw_flip_pos_back', $pos_back);
    update_term_meta($term_id, 'tmw_flip_zoom_front', $zoom_front);
    update_term_meta($term_id, 'tmw_flip_zoom_back', $zoom_back);
  }
}

add_action('created_models', 'tmw_save_model_flipbox_term_meta');
add_action('edited_models', 'tmw_save_model_flipbox_term_meta');

add_action('admin_enqueue_scripts', function () {
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || !in_array($screen->base, ['edit-tags', 'term'], true)) {
    return;
  }

  if (($screen->taxonomy ?? '') !== 'models') {
    return;
  }

  wp_enqueue_media();
  wp_enqueue_script(
    'tmw-model-flipbox-term-meta',
    get_stylesheet_directory_uri() . '/js/tmw-model-flipbox-term-meta.js',
    ['jquery'],
    '1.0.0',
    true
  );
});
