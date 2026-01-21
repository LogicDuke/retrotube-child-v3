<?php
if (!defined('ABSPATH')) { exit; }

// Ensure legacy banner helpers remain loaded (background renderer, admin CSS).
$banner_autoload = TMW_CHILD_PATH . '/inc/_autoload_tmw_banner_bg.php';
if (is_readable($banner_autoload)) {
    require_once $banner_autoload;
}

/**
 * Admin: capture the preview's actual hero height and store it as _tmw_offset_base.
 * Kept for legacy records while the old pixel offset meta is still present.
 */
add_action('admin_footer-post.php', 'tmw_model_offset_admin_probe');
add_action('admin_footer-post-new.php', 'tmw_model_offset_admin_probe');
if (!function_exists('tmw_model_offset_admin_probe')) {
    function tmw_model_offset_admin_probe() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'model') {
            return;
        }
        ?>
        <input type="hidden" id="tmw_offset_base" name="tmw_offset_base" value="" />
        <script>
        (function(){
          function frame(){ return document.querySelector('.tmw-banner-frame'); }
          function writeBase(){
            var el = frame();
            var h  = el ? Math.round(el.getBoundingClientRect().height || 0) : 0;
            var inp = document.getElementById('tmw_offset_base');
            if (inp && h) inp.value = String(h);
          }
          document.addEventListener('DOMContentLoaded', writeBase, {once:true});
          window.addEventListener('resize', writeBase, {passive:true});
          document.addEventListener('input', writeBase, true);
          document.addEventListener('click', function(e){
            var id = (e.target && e.target.id) || '';
            if (/publish|save-post|save/i.test(id)) writeBase();
          }, true);
        })();
        </script>
        <?php
    }
}

add_action('save_post_model', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    if (isset($_POST['tmw_offset_base'])) {
        update_post_meta($post_id, '_tmw_offset_base', (int) $_POST['tmw_offset_base']);
    }
}, 10, 1);
