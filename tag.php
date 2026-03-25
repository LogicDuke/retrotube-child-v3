<?php
/**
 * Tag archive template for Retrotube Child theme.
 */

if (tmw_try_parent_template(['tag.php', 'archive.php', 'index.php'])) {
    return;
}

get_header();

tmw_render_sidebar_layout('tag-archive', function () {
    ?>
      <?php if (have_posts()) : ?>
        <header class="page-header">
          <?php
          $archive_title = function_exists('tmw_get_clean_archive_heading')
              ? tmw_get_clean_archive_heading()
              : wp_strip_all_tags(get_the_archive_title());
          echo tmw_render_title_bar($archive_title, 1);
          ?>
          <?php the_archive_description('<div class="archive-description">', '</div>'); ?>
          <?php
          if (function_exists('tmw_render_archive_counterpart_link')) {
              echo tmw_render_archive_counterpart_link();
          }
          ?>
        </header>

        <?php while (have_posts()) : the_post(); ?>
          <?php get_template_part('template-parts/content', get_post_type()); ?>
        <?php endwhile; ?>

        <?php the_posts_navigation(); ?>
      <?php else : ?>
        <?php get_template_part('template-parts/content', 'none'); ?>
      <?php endif; ?>
    <?php
});

get_footer();
