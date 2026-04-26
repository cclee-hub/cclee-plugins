<?php
/**
 * Case Study Metrics - Server-side render.
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

$metrics = cclee_toolkit_get_case_study_metrics( $post_id );
if ( empty( $metrics ) ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'cclee-case-metrics' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php foreach ( $metrics as $metric ) : ?>
	<div class="cclee-case-metrics__item">
		<span class="cclee-case-metrics__value"><?php echo esc_html( $metric['value'] ); ?></span>
		<span class="cclee-case-metrics__label"><?php echo esc_html( $metric['label'] ); ?></span>
	</div>
	<?php endforeach; ?>
</div>
