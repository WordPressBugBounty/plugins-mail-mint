<?php
/**
 * Unsubscribe Page
 *
 * This template can be overridden by copying it to yourtheme/mrm/page-templates/template-unsubscribe-page.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see 'doc/url'
 * @package mrm/page-templates
 * @since 1.0.0
 */

// Block (FSE) themes and themes without header.php get a minimal HTML shell.
// Classic themes with header.php use get_header() as normal.
$has_classic_header = current_theme_supports( 'block-templates' ) ? false : locate_template( 'header.php' );

if ( $has_classic_header ) {
	get_header();
} else {
	?>
	<!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<?php wp_head(); ?>
	</head>
	<body <?php body_class(); ?>>
	<?php
	if ( function_exists( 'wp_body_open' ) ) {
		wp_body_open();
	}
}
?>

<main class="mintmrm-main mintmrm-page-template-main">
	<section class="mintmrm-container">
		<?php the_content(); ?>
	</section>
</main>

<?php
if ( $has_classic_header ) {
	get_footer();
} else {
	wp_footer();
	?>
	</body>
	</html>
	<?php
}
?>
