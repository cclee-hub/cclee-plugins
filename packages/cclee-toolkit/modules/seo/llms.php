<?php
/**
 * llms.txt — 为 LLM 爬虫生成站点纯文本摘要
 *
 * 功能：
 * - 注册 /llms.txt rewrite rule
 * - 动态输出站点结构化纯文本
 *
 * Controlled by:
 * - cclee_toolkit_llms_enabled
 * - cclee_toolkit_llms_extra
 *
 * @package CCLEE_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 注册 rewrite rule 和 query variable
 */
add_action( 'init', function () {
	add_rewrite_rule( '^llms\.txt$', 'index.php?cclee_llms=1', 'top' );
	add_rewrite_tag( '%cclee_llms%', '([1])' );
} );

/**
 * 拦截 /llms.txt 请求并输出内容
 */
add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'cclee_llms' ) ) {
		return;
	}
	if ( ! get_option( 'cclee_toolkit_llms_enabled', false ) ) {
		return;
	}

	$lines   = array();
	$lines[] = '# ' . wp_strip_all_tags( get_bloginfo( 'name' ) );
	$lines[] = '> ' . wp_strip_all_tags( get_bloginfo( 'description' ) );
	$lines[] = '';

	// Site Info
	$lines[] = '## Site Info';
	$lines[] = '- URL: ' . esc_url( home_url( '/' ) );
	$lines[] = '- Language: ' . sanitize_text_field( get_bloginfo( 'language' ) );
	$lines[] = '';

	// Core Pages
	$lines[] = '## Core Pages';
	$pages = get_posts( array(
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'posts_per_page' => 20,
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
	) );
	foreach ( $pages as $page ) {
		$lines[] = '- [' . wp_strip_all_tags( $page->post_title ) . '](' . get_permalink( $page->ID ) . ')';
	}
	$lines[] = '';

	// Products (if WooCommerce active)
	if ( class_exists( 'WooCommerce' ) ) {
		$products = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );
		if ( ! empty( $products ) ) {
			$lines[] = '## Products';
			foreach ( $products as $product ) {
				$lines[] = '- [' . wp_strip_all_tags( $product->post_title ) . '](' . get_permalink( $product->ID ) . ')';
			}
			$lines[] = '';
		}
	}

	// Sitemap
	$lines[] = '## Sitemap';
	$lines[] = '- ' . esc_url( home_url( '/sitemap.xml' ) );
	$lines[] = '';

	// Custom extra content
	$extra = get_option( 'cclee_toolkit_llms_extra', '' );
	if ( $extra ) {
		$extra_lines = explode( "\n", wp_strip_all_tags( str_replace( '\\n', "\n", $extra ) ) );
		$lines       = array_merge( $lines, $extra_lines );
	}

	header( 'Content-Type: text/plain; charset=UTF-8' );
	echo wp_strip_all_tags( implode( "\n", $lines ) );
	exit;
} );

/**
 * 当 llms_enabled 选项更新时刷新 rewrite rules
 */
add_action( 'update_option_cclee_toolkit_llms_enabled', function () {
	flush_rewrite_rules();
} );
