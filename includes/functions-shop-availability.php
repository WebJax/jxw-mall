<?php
/**
 * Shop vacancy status and shop-list query helpers.
 *
 * A butiksside can be an occupied shop or a vacant unit marked "Til leje / salg".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stored occupancy values for a shop post.
 *
 * @return array<string, string>
 */
function centershop_get_shop_availability_options() {
	return array(
		'occupied' => 'Aktiv butik',
		'for_rent' => 'Til leje / salg',
	);
}

/**
 * Filter values for the shop-list block, including "all".
 *
 * @return array<string, string>
 */
function centershop_get_shop_availability_filter_options() {
	return array(
		'all'      => 'Alle',
		'occupied' => 'Aktiv butik',
		'for_rent' => 'Til leje / salg',
	);
}

/**
 * Sanitize a stored occupancy value.
 *
 * @param mixed $value Raw value.
 * @return string occupied|for_rent
 */
function centershop_sanitize_shop_availability( $value ) {
	$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
	return ( 'for_rent' === $value ) ? 'for_rent' : 'occupied';
}

/**
 * Sanitize the shop-list status filter.
 *
 * @param mixed $value Raw value.
 * @return string all|occupied|for_rent
 */
function centershop_sanitize_shop_availability_filter( $value ) {
	$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
	if ( in_array( $value, array( 'occupied', 'for_rent' ), true ) ) {
		return $value;
	}
	return 'all';
}

/**
 * Sanitize category IDs selected in the shop-list block.
 *
 * @param mixed $categories Raw category IDs.
 * @return int[]
 */
function centershop_sanitize_shop_list_categories( $categories ) {
	if ( ! is_array( $categories ) ) {
		return array();
	}

	$ids = array();
	foreach ( $categories as $id ) {
		$id = (int) $id;
		if ( $id > 0 ) {
			$ids[] = $id;
		}
	}

	return array_values( array_unique( $ids ) );
}

/**
 * Occupancy for a shop post. Missing meta is treated as occupied.
 *
 * @param int $post_id Shop post ID.
 * @return string occupied|for_rent
 */
function centershop_get_shop_availability( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
		return 'occupied';
	}

	return centershop_sanitize_shop_availability( get_post_meta( $post_id, 'butik_availability', true ) );
}

/**
 * Human-readable occupancy label.
 *
 * @param string $availability occupied|for_rent.
 * @return string
 */
function centershop_get_shop_availability_label( $availability ) {
	$options = centershop_get_shop_availability_options();
	$key     = centershop_sanitize_shop_availability( $availability );
	return $options[ $key ];
}

/**
 * Badge markup for vacant units. Empty string for occupied shops.
 *
 * @param int $post_id Shop post ID.
 * @return string
 */
function centershop_get_shop_availability_badge_html( $post_id ) {
	if ( 'for_rent' !== centershop_get_shop_availability( $post_id ) ) {
		return '';
	}

	return '<span class="centershop-availability-badge">' . esc_html( centershop_get_shop_availability_label( 'for_rent' ) ) . '</span>';
}

/**
 * WP_Query / get_posts args for the shop-list block.
 *
 * @param array $attributes Block attributes.
 * @return array
 */
function centershop_get_shop_list_query_args( $attributes ) {
	$attributes   = is_array( $attributes ) ? $attributes : array();
	$availability = centershop_sanitize_shop_availability_filter( $attributes['availability'] ?? 'all' );
	$categories   = centershop_sanitize_shop_list_categories( $attributes['categories'] ?? array() );

	$args = array(
		'post_type'      => 'butiksside',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
	);

	if ( 'for_rent' === $availability ) {
		$args['meta_query'] = array(
			array(
				'key'     => 'butik_availability',
				'value'   => 'for_rent',
				'compare' => '=',
			),
		);
	} elseif ( 'occupied' === $availability ) {
		$args['meta_query'] = array(
			'relation' => 'OR',
			array(
				'key'     => 'butik_availability',
				'value'   => 'occupied',
				'compare' => '=',
			),
			array(
				'key'     => 'butik_availability',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => 'butik_availability',
				'value'   => '',
				'compare' => '=',
			),
		);
	}

	if ( ! empty( $categories ) ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'category',
				'field'    => 'term_id',
				'terms'    => $categories,
			),
		);
	}

	return $args;
}
