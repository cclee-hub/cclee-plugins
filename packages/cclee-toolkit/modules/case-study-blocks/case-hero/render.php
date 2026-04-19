<?php
/**
 * Case Study Hero - Server-side render.
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

$client_name = cclee_toolkit_get_case_study_meta( $post_id, 'client_name' );
$client_logo = cclee_toolkit_get_case_study_meta( $post_id, 'client_logo' );
$client_size = cclee_toolkit_get_case_study_meta( $post_id, 'client_size' );

if ( ! $client_name && ! $client_logo && ! $client_size ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'cclee-case-hero' ) ); ?>>
	<?php if ( $client_logo ) : ?>
	<div class="cclee-case-hero__logo">
		<?php echo wp_get_attachment_image( absint( $client_logo ), 'medium', false, array( 'class' => 'cclee-case-hero__logo-img' ) ); ?>
	</div>
	<?php endif; ?>

	<div class="cclee-case-hero__info">
		<?php if ( $client_name ) : ?>
		<span class="cclee-case-hero__name"><?php echo esc_html( $client_name ); ?></span>
		<?php endif; ?>
		<?php if ( $client_size ) : ?>
		<span class="cclee-case-hero__size"><?php echo esc_html( $client_size ); ?></span>
		<?php endif; ?>
	</div>
</div>
