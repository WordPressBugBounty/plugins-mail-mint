<?php
/**
 * Subscribe Page
 *
 * This template can be overridden by copying it to yourtheme/mrm/page-templates/template-subscribe-page.php.
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

get_header();
?>

<main class="mintmrm-main mintmrm-page-template-main">
	<section class="mintmrm-container">
		<?php the_content(); ?>
	</section>
</main>


<?php
get_footer();
?>
