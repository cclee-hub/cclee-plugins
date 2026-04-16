<?php
/**
 * Case Study Blocks Module
 *
 * Registers 4 dynamic blocks for the case-study CPT.
 *
 * @package CCLEE_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register all case study blocks.
 */
function cclee_toolkit_register_case_study_blocks() {
	$blocks_dir = CCLEE_TOOLKIT_PATH . 'modules/case-study-blocks/';

	$blocks = array(
		'case-hero',
		'case-metrics',
		'case-testimonial',
		'case-meta',
	);

	foreach ( $blocks as $block ) {
		$json_path = $blocks_dir . $block . '/block.json';
		if ( file_exists( $json_path ) ) {
			register_block_type( $json_path );
		}
	}
}
add_action( 'init', 'cclee_toolkit_register_case_study_blocks' );
