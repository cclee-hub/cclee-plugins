<?php
/**
 * SEO 增强 — OG 标签 + 基础 Schema 输出
 *
 * 功能：
 * - Open Graph 标签
 * - Twitter Card 标签
 * - 基础 JSON-LD Schema
 *
 * @package CCLEE_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 输出 Open Graph 和 Twitter Card 标签
 *
 * Controlled by:
 * - cclee_toolkit_seo_enabled       (master switch)
 * - cclee_toolkit_seo_og_enabled    (OG output)
 */
add_action( 'wp_head', function () {
	// 仅前端输出
	if ( is_admin() ) {
		return;
	}
	if ( ! get_option( 'cclee_toolkit_seo_enabled', true ) || ! get_option( 'cclee_toolkit_seo_og_enabled', true ) ) {
		return;
	}

	$site_name = get_bloginfo( 'name' );
	$title     = '';
	$desc      = '';
	$url       = '';
	$image     = '';
	$type      = 'website';

	// 单页/文章
	if ( is_singular() ) {
		$title = get_the_title();
		$desc  = get_the_excerpt() ?: wp_trim_words( get_the_content(), 55 );
		$url   = get_permalink();

		if ( has_post_thumbnail() ) {
			$image = get_the_post_thumbnail_url( null, 'large' );
		}

		$type = is_page() ? 'article' : 'article';
	}

	// 归档页
	if ( is_archive() ) {
		$title = get_the_archive_title();
		$desc  = get_the_archive_description();

		// 获取归档链接
		if ( is_post_type_archive() ) {
			$url = get_post_type_archive_link( get_post_type() );
		} elseif ( is_tax() || is_category() || is_tag() ) {
			$term_link = get_term_link( get_queried_object() );
			$url = is_wp_error( $term_link ) ? home_url( '/' ) : $term_link;
		} else {
			$url = home_url( '/' );
		}
	}

	// 首页
	if ( is_front_page() ) {
		$title = $site_name;
		$desc  = get_bloginfo( 'description' );
		$url   = home_url( '/' );
	}

	// 默认值
	$title = $title ?: $site_name;
	$desc  = $desc ?: get_bloginfo( 'description' );
	$url   = $url ?: home_url( '/' );

	// 转义
	$title = esc_attr( $title );
	$desc  = esc_attr( wp_trim_words( $desc, 160 ) );
	$url   = esc_url( $url );
	$image = $image ? esc_url( $image ) : '';

	echo "\n<!-- CCLEE Toolkit: Open Graph / Twitter Card -->\n";

	// Open Graph
	printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( $site_name ) );
	printf( '<meta property="og:title" content="%s" />' . "\n", $title );
	printf( '<meta property="og:description" content="%s" />' . "\n", $desc );
	printf( '<meta property="og:url" content="%s" />' . "\n", $url );
	// og:type: defer to product-specific output on product pages
	$is_product_page = function_exists( 'is_product' ) && is_product();
	if ( ! $is_product_page ) {
		printf( '<meta property="og:type" content="%s" />' . "\n", esc_attr( $type ) );
	}

	if ( $image ) {
		printf( '<meta property="og:image" content="%s" />' . "\n", $image );
	}

	// Twitter Card
	printf( '<meta name="twitter:card" content="summary_large_image" />' . "\n" );
	printf( '<meta name="twitter:title" content="%s" />' . "\n", $title );
	printf( '<meta name="twitter:description" content="%s" />' . "\n", $desc );

	if ( $image ) {
		printf( '<meta name="twitter:image" content="%s" />' . "\n", $image );
	}

	// WooCommerce Product OG extension
	if ( function_exists( 'is_product' ) && is_product() ) {
		global $product;
		if ( $product ) {
			$product_id = $product->get_id();

			// Override og:type for product pages
			printf( '<meta property="og:type" content="product" />' . "\n" );

			// og:price:amount
			if ( $product->is_type( 'variable' ) ) {
				$min_price = $product->get_variation_price( 'min', true );
				if ( $min_price !== '' ) {
					printf( '<meta property="og:price:amount" content="%s" />' . "\n", esc_attr( $min_price ) );
				}
			} else {
				$price = $product->get_price();
				if ( $price !== '' ) {
					printf( '<meta property="og:price:amount" content="%s" />' . "\n", esc_attr( $price ) );
				}
			}

			// og:price:currency
			printf( '<meta property="og:price:currency" content="%s" />' . "\n", esc_attr( get_woocommerce_currency() ) );

			// og:availability
			$availability = $product->is_in_stock() ? 'instock' : 'oos';
			printf( '<meta property="og:availability" content="%s" />' . "\n", esc_attr( $availability ) );

			// og:brand (same logic as Woo Schema module)
			$brand = '';
			$brand_terms = get_the_terms( $product_id, 'product_brand' );
			if ( $brand_terms && ! is_wp_error( $brand_terms ) ) {
				$brand = $brand_terms[0]->name;
			}
			if ( ! $brand ) {
				$brand_meta = get_post_meta( $product_id, '_brand', true );
				if ( $brand_meta ) {
					$brand = sanitize_text_field( $brand_meta );
				}
			}
			if ( $brand ) {
				printf( '<meta property="og:brand" content="%s" />' . "\n", esc_attr( $brand ) );
			}

			// og:sku
			$sku = get_post_meta( $product_id, '_sku', true );
			if ( $sku ) {
				printf( '<meta property="og:sku" content="%s" />' . "\n", esc_attr( $sku ) );
			}
		}
	}
}, 1 );


/**
 * 输出基础 JSON-LD Schema
 *
 * Controlled by:
 * - cclee_toolkit_seo_enabled         (master switch)
 * - cclee_toolkit_seo_jsonld_enabled  (JSON-LD output)
 */
add_action( 'wp_head', function () {
	if ( is_admin() || ! is_singular() ) {
		return;
	}
	if ( ! get_option( 'cclee_toolkit_seo_enabled', true ) || ! get_option( 'cclee_toolkit_seo_jsonld_enabled', true ) ) {
		return;
	}

	$schema = [
		'@context' => 'https://schema.org',
		'@type'    => is_page() ? 'WebPage' : 'Article',
		'headline' => get_the_title(),
		'url'      => get_permalink(),
		'datePublished' => get_the_date( 'c' ),
		'dateModified'  => get_the_modified_date( 'c' ),
		'author'   => [
			'@type' => 'Person',
			'name'  => get_the_author(),
		],
		'publisher' => [
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
		],
	];

	if ( has_post_thumbnail() ) {
		$schema['image'] = get_the_post_thumbnail_url( null, 'large' );
	}

	echo "\n<!-- CCLEE Toolkit: JSON-LD Schema -->\n";
	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
	);
}, 2 );
