<?php

/* ======================================================================
 * TEMPLATE HELPERS
 * ====================================================================== */
if (!function_exists('tmw_try_parent_template')) {
  /**
   * Attempt to render a template from the parent theme.
   *
   * @param array $candidates Template path candidates.
   * @return bool True when a template was rendered.
   */
  function tmw_try_parent_template(array $candidates): bool {
    $parent_dir = trailingslashit(get_template_directory());

    foreach ($candidates as $candidate) {
      $path = $parent_dir . ltrim($candidate, '/');
      if (!file_exists($path)) {
        continue;
      }

      ob_start();
      include $path;
      $output = ob_get_clean();

      if ($output !== false) {
        echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        return true;
      }
    }

    return false;
  }
}

if (!function_exists('tmw_render_sidebar_layout')) {
  /**
   * Render a two-column layout with sidebar and callback for main content.
   *
   * @param string   $context_class Extra CSS class for the content area.
   * @param callable $callback      Callback that outputs the main content.
   */
  function tmw_render_sidebar_layout(string $context_class, callable $callback): void {
    $context_class = trim($context_class);
    $primary_class = 'content-area with-sidebar-right';
    if ($context_class !== '') {
      $primary_class .= ' ' . $context_class;
    }

    echo '<div id="content" class="site-content row">' . PHP_EOL;
    echo '  <div id="primary" class="' . esc_attr($primary_class) . '">' . PHP_EOL;
    echo '    <main id="main" class="site-main with-sidebar-right" role="main">' . PHP_EOL;

    call_user_func($callback);

    echo '    </main>' . PHP_EOL;
    echo '  </div>' . PHP_EOL;
    echo '  <aside id="sidebar" class="widget-area with-sidebar-right" role="complementary">' . PHP_EOL;
    get_sidebar();
    echo '  </aside>' . PHP_EOL;
    echo '</div>' . PHP_EOL;
  }
}
