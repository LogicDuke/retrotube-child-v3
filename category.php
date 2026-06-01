<?php
/**
 * Category archive template for Retrotube Child theme.
 *
 * Keeps category archive output in the child theme so the existing archive
 * description filters can render the category SEO accordion before the grid.
 */

get_header();

tmw_render_sidebar_layout('category-archive', function () {
    ?>
      <header class="page-header">
        <?php
        $archive_title = single_term_title('', false);
        if ($archive_title === '') {
            $archive_title = wp_strip_all_tags(get_the_archive_title());
        }

        echo tmw_render_title_bar($archive_title, 1);
        ?>
        <?php the_archive_description('<div class="archive-description">', '</div>'); ?>
      </header>

      <?php if (have_posts()) : ?>
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
