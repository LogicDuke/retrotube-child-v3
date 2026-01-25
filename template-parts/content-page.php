<?php
/**
 * Template part for displaying page content in page.php.
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<?php if ( ! is_front_page() ) : ?>
		<header class="entry-header">
			<?php the_title( '<h1 class="widget-title">', '</h1>' ); ?>
		</header><!-- .entry-header -->
	<?php endif; ?>

	<div class="entry-content">
		<?php
		the_content();

		wp_link_pages(
			array(
				'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'retrotube' ),
				'after'  => '</div>',
			)
		);
		?>
	</div><!-- .entry-content -->

	<?php
	edit_post_link(
		esc_html__( 'Edit', 'retrotube' ),
		'<footer class="entry-footer"><span class="edit-link">',
		'</span></footer>'
	);
	?>
</article><!-- #post-<?php the_ID(); ?> -->
