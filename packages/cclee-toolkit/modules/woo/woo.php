<?php
/**
 * WooCommerce 增强模块
 *
 * @package CCLEE_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 输出 WooCommerce Product Schema + BreadcrumbList
 *
 * Controlled by: cclee_toolkit_woo_schema_enabled
 */
add_action( 'wp_head', function () {
	if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	if ( ! get_option( 'cclee_toolkit_woo_schema_enabled', true ) ) {
		return;
	}

	global $product;
	if ( ! $product ) {
		return;
	}

	$permalink = get_permalink();
	$product_id = $product->get_id();

	// --- Build Product Schema ---
	$schema = array(
		'@context'  => 'https://schema.org',
		'@type'     => 'Product',
		'@id'       => $permalink . '#product',
		'name'      => wp_strip_all_tags( get_the_title() ),
		'url'       => $permalink,
	);

	// Description: strip tags + truncate to 500 chars
	$raw_desc = get_the_content();
	$desc     = wp_trim_words( wp_strip_all_tags( $raw_desc ), 80, '' );
	if ( mb_strlen( $desc ) > 500 ) {
		$desc = mb_substr( $desc, 0, 500 );
	}
	if ( $desc !== '' ) {
		$schema['description'] = $desc;
	}

	// Image
	if ( has_post_thumbnail() ) {
		$schema['image'] = get_the_post_thumbnail_url( null, 'large' );
	}

	// SKU
	$sku = $product->get_sku();
	if ( $sku ) {
		$schema['sku'] = $sku;
	}

	// GTIN / MPN
	$gtin = get_post_meta( $product_id, '_gtin', true );
	if ( $gtin ) {
		$schema['gtin'] = sanitize_text_field( $gtin );
	}
	$mpn = get_post_meta( $product_id, '_mpn', true );
	if ( $mpn ) {
		$schema['mpn'] = sanitize_text_field( $mpn );
	}

	// Brand: taxonomy 'product_brand' or custom field '_brand'
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
		$schema['brand'] = array(
			'@type' => 'Brand',
			'name'  => $brand,
		);
	}

	// Offers
	if ( $product->is_type( 'variable' ) ) {
		// Variable product: output AggregateOffer with price range
		$min_price = $product->get_variation_price( 'min', true );
		$max_price = $product->get_variation_price( 'max', true );
		if ( $min_price !== '' && $max_price !== '' ) {
			$schema['offers'] = array(
				'@type'         => 'AggregateOffer',
				'priceCurrency' => get_woocommerce_currency(),
				'lowPrice'      => $min_price,
				'highPrice'     => $max_price,
				'offerCount'    => count( $product->get_children() ),
				'availability'  => $product->is_in_stock()
					? 'https://schema.org/InStock'
					: 'https://schema.org/OutOfStock',
				'url'           => $permalink,
			);
		}
	} else {
		$price = $product->get_price();
		if ( $price !== '' ) {
			$schema['offers'] = array(
				'@type'         => 'Offer',
				'price'         => $price,
				'priceCurrency' => get_woocommerce_currency(),
				'availability'  => $product->is_in_stock()
					? 'https://schema.org/InStock'
					: 'https://schema.org/OutOfStock',
				'url'           => $permalink,
			);

			// priceValidUntil: from sale price end date
			$sale_end = get_post_meta( $product_id, '_sale_price_dates_to', true );
			if ( $sale_end ) {
				$schema['offers']['priceValidUntil'] = gmdate( 'Y-m-d', $sale_end );
			}
		}
	}

	// AggregateRating: both ratingValue and reviewCount must be > 0
	$avg_rating   = (float) $product->get_average_rating();
	$rating_count = (int) $product->get_rating_count();
	if ( $avg_rating > 0 && $rating_count > 0 ) {
		$schema['aggregateRating'] = array(
			'@type'       => 'AggregateRating',
			'ratingValue' => $avg_rating,
			'reviewCount' => $rating_count,
		);
	}

	// Single Review: latest approved comment
	$comments = get_comments( array(
		'post_id'      => $product_id,
		'status'       => 'approve',
		'type'         => 'review',
		'number'       => 1,
	) );
	if ( ! empty( $comments ) ) {
		$review      = $comments[0];
		$review_data = array(
			'@type'         => 'Review',
			'reviewRating'  => array(
				'@type'       => 'Rating',
				'ratingValue' => get_comment_meta( $review->comment_ID, 'rating', true ) ?: 5,
			),
			'author'        => array(
				'@type' => 'Person',
				'name'  => wp_strip_all_tags( $review->comment_author ),
			),
			'datePublished' => gmdate( 'c', strtotime( $review->comment_date ) ),
		);
		if ( $review->comment_content ) {
			$review_data['reviewBody'] = wp_strip_all_tags( $review->comment_content );
		}
		$schema['review'] = $review_data;
	}

	// --- Output Product Schema ---
	echo "\n<!-- CCLEE Toolkit: WooCommerce Product Schema -->\n";
	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
	);

	// --- Build BreadcrumbList ---
	$breadcrumbs = array(
		array(
			'@type' => 'ListItem',
			'position' => 1,
			'name'  => __( 'Home', 'cclee-toolkit' ),
			'item'  => home_url( '/' ),
		),
	);

	$terms = get_the_terms( $product_id, 'product_cat' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		// Build ancestor chain for deepest category
		$deepest = $terms[0];
		foreach ( $terms as $term ) {
			if ( $term->parent !== 0 ) {
				$deepest = $term;
				break;
			}
		}
		$chain   = array();
		$current = $deepest;
		while ( $current ) {
			array_unshift( $chain, $current );
			$current = $current->parent ? get_term( $current->parent, 'product_cat' ) : null;
		}
		$pos = 2;
		foreach ( $chain as $term ) {
			$breadcrumbs[] = array(
				'@type'   => 'ListItem',
				'position' => $pos++,
				'name'    => $term->name,
				'item'    => get_term_link( $term ),
			);
		}
		$breadcrumbs[] = array(
			'@type'   => 'ListItem',
			'position' => $pos,
			'name'    => wp_strip_all_tags( get_the_title() ),
			'item'    => $permalink,
		);
	} else {
		$breadcrumbs[] = array(
			'@type'   => 'ListItem',
			'position' => 2,
			'name'    => wp_strip_all_tags( get_the_title() ),
			'item'    => $permalink,
		);
	}

	$breadcrumb_schema = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $breadcrumbs,
	);

	// --- Output BreadcrumbList ---
	echo "<!-- CCLEE Toolkit: BreadcrumbList -->\n";
	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		wp_json_encode( $breadcrumb_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
	);
}, 1 );
