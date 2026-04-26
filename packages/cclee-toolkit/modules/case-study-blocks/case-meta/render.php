<?php
/**
 * Case Study Meta - Server-side render.
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

$duration = cclee_toolkit_get_case_study_meta( $post_id, 'project_duration' );
$size     = cclee_toolkit_get_case_study_meta( $post_id, 'client_size' );

if ( ! $duration && ! $size ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'cclee-case-meta' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $duration ) : ?>
	<div class="cclee-case-meta__item">
		<span class="cclee-case-meta__label"><?php esc_html_e( 'Duration', 'cclee-toolkit' ); ?></span>
		<span class="cclee-case-meta__value"><?php echo esc_html( $duration ); ?></span>
	</div>
	<?php endif; ?>
	<?php if ( $size ) : ?>
	<div class="cclee-case-meta__item">
		<span class="cclee-case-meta__label"><?php esc_html_e( 'Team Size', 'cclee-toolkit' ); ?></span>
		<span class="cclee-case-meta__value"><?php echo esc_html( $size ); ?></span>
	</div>
	<?php endif; ?>
</div>
