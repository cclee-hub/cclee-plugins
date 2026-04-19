<?php
/**
 * Case Study Testimonial - Server-side render.
 *
 * @package CCLEE_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
if ( ! $post_id ) {
	return;
}

$content = cclee_toolkit_get_case_study_meta( $post_id, 'testimonial_content' );
$author  = cclee_toolkit_get_case_study_meta( $post_id, 'testimonial_author' );
$title   = cclee_toolkit_get_case_study_meta( $post_id, 'testimonial_title' );

if ( ! $content ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'cclee-case-testimonial' ) ); ?>>
	<blockquote class="cclee-case-testimonial__quote">
		<p><?php echo esc_html( $content ); ?></p>
	</blockquote>
	<?php if ( $author ) : ?>
	<div class="cclee-case-testimonial__author">
		<span class="cclee-case-testimonial__name"><?php echo esc_html( $author ); ?></span>
		<?php if ( $title ) : ?>
		<span class="cclee-case-testimonial__role"><?php echo esc_html( $title ); ?></span>
		<?php endif; ?>
	</div>
	<?php endif; ?>
</div>
