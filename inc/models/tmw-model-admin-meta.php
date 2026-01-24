<?php

/* ======================================================================
 * MODELS ⇄ AWE mapping UI + save + list columns
 * ====================================================================== */
add_action('models_add_form_fields', function(){
  ?>
  <div class="form-field term-group">
    <label for="tmw_aw_nick">AWE Nickname / Performer ID</label>
    <input type="text" name="tmw_aw_nick" id="tmw_aw_nick" value="" class="regular-text" />
    <p class="description">Must match <code>performerId</code>, <code>displayName</code>, or <code>nickname</code> in your AWE feed.</p>
  </div>
  <div class="form-field term-group">
    <label for="tmw_aw_subaff">AWE SubAff (optional)</label>
    <input type="text" name="tmw_aw_subaff" id="tmw_aw_subaff" value="" class="regular-text" />
    <p class="description">Used as <code>subAffId</code> ({SUBAFFID}) in tracking links.</p>
  </div>
  <?php wp_nonce_field('tmw_aw_term_meta', 'tmw_aw_term_meta_nonce'); ?>
  <?php
});
add_action('models_edit_form_fields', function($term){
  $nick = get_term_meta($term->term_id,'tmw_aw_nick',true);
  $sub  = get_term_meta($term->term_id,'tmw_aw_subaff',true);
  ?>
  <tr class="form-field">
    <th scope="row"><label for="tmw_aw_nick">AWE Nickname / Performer ID</label></th>
    <td>
      <input type="text" name="tmw_aw_nick" id="tmw_aw_nick" value="<?php echo esc_attr($nick); ?>" class="regular-text" />
      <p class="description">Must match <code>performerId</code>, <code>displayName</code>, or <code>nickname</code> in your AWE feed.</p>
      <?php wp_nonce_field('tmw_aw_term_meta', 'tmw_aw_term_meta_nonce'); ?>
    </td>
  </tr>
  <tr class="form-field">
    <th scope="row"><label for="tmw_aw_subaff">AWE SubAff (optional)</label></th>
    <td>
      <input type="text" name="tmw_aw_subaff" id="tmw_aw_subaff" value="<?php echo esc_attr($sub); ?>" class="regular-text" />
      <p class="description">Used as <code>subAffId</code> ({SUBAFFID}) in tracking links.</p>
    </td>
  </tr>
  <?php
});
add_action('created_models', 'tmw_save_models_aw_meta', 10);
add_action('edited_models',  'tmw_save_models_aw_meta', 10);
if (!function_exists('tmw_save_models_aw_meta')) {
  /**
   * Persist AWE term meta fields for models taxonomy.
   *
   * @param int $term_id Term ID being saved.
   */
  function tmw_save_models_aw_meta($term_id){
    if (!isset($_POST['tmw_aw_term_meta_nonce']) ||
        !wp_verify_nonce($_POST['tmw_aw_term_meta_nonce'], 'tmw_aw_term_meta')) {
      return;
    }
    if (isset($_POST['tmw_aw_nick']))   update_term_meta($term_id, 'tmw_aw_nick',   sanitize_text_field(wp_unslash($_POST['tmw_aw_nick'])));
    if (isset($_POST['tmw_aw_subaff'])) update_term_meta($term_id, 'tmw_aw_subaff', sanitize_text_field(wp_unslash($_POST['tmw_aw_subaff'])));
    delete_transient('tmw_aw_feed_v1'); // refresh cache after edits
  }
}
add_filter('manage_edit-models_columns', function($cols){
  $cols['tmw_aw_nick']   = 'AWE Nick/ID';
  $cols['tmw_aw_subaff'] = 'SubAff';
  return $cols;
});
add_filter('manage_models_custom_column', function($out, $col, $term_id){
  if ($col === 'tmw_aw_nick')   $out = esc_html(get_term_meta($term_id,'tmw_aw_nick',true));
  if ($col === 'tmw_aw_subaff') $out = esc_html(get_term_meta($term_id,'tmw_aw_subaff',true));
  return $out;
}, 10, 3);
