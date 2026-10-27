<?php

define( 'ABSPATH', __DIR__ );

require_once dirname( __DIR__ ) . '/includes/functions-shop-availability.php';

function assert_true( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		throw new RuntimeException(
			$message . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true )
		);
	}
}

function test_sanitize_shop_availability() {
	assert_same( 'occupied', centershop_sanitize_shop_availability( '' ), 'Empty availability should default to occupied.' );
	assert_same( 'occupied', centershop_sanitize_shop_availability( 'occupied' ), 'occupied should be kept.' );
	assert_same( 'for_rent', centershop_sanitize_shop_availability( 'for_rent' ), 'for_rent should be kept.' );
	assert_same( 'for_rent', centershop_sanitize_shop_availability( 'FOR_RENT' ), 'Availability should be case-insensitive.' );
	assert_same( 'occupied', centershop_sanitize_shop_availability( 'unknown' ), 'Unknown values should fall back to occupied.' );
}

function test_sanitize_shop_availability_filter() {
	assert_same( 'all', centershop_sanitize_shop_availability_filter( '' ), 'Empty filter should be all.' );
	assert_same( 'all', centershop_sanitize_shop_availability_filter( 'everything' ), 'Unknown filter should be all.' );
	assert_same( 'occupied', centershop_sanitize_shop_availability_filter( 'occupied' ), 'occupied filter should be kept.' );
	assert_same( 'for_rent', centershop_sanitize_shop_availability_filter( 'for_rent' ), 'for_rent filter should be kept.' );
}

function test_sanitize_shop_list_categories() {
	assert_same( array(), centershop_sanitize_shop_list_categories( null ), 'Non-arrays should become an empty list.' );
	assert_same( array( 3, 7 ), centershop_sanitize_shop_list_categories( array( '3', 7, 0, -2, 3 ) ), 'Category IDs should be unique positive integers.' );
}

function test_shop_list_query_args_default_has_no_filters() {
	$args = centershop_get_shop_list_query_args( array() );

	assert_same( 'butiksside', $args['post_type'], 'Shop list should query butiksside.' );
	assert_true( ! isset( $args['meta_query'] ), 'Default list should not filter by availability.' );
	assert_true( ! isset( $args['tax_query'] ), 'Default list should not filter by category.' );
}

function test_shop_list_query_args_filter_for_rent_and_categories() {
	$args = centershop_get_shop_list_query_args(
		array(
			'availability' => 'for_rent',
			'categories'   => array( 12, '18', 0 ),
		)
	);

	assert_same( 'for_rent', $args['meta_query'][0]['value'], 'for_rent filter should query vacancy meta.' );
	assert_same( array( 12, 18 ), $args['tax_query'][0]['terms'], 'Category filter should keep valid term IDs.' );
	assert_same( 'category', $args['tax_query'][0]['taxonomy'], 'Category filter should use the category taxonomy.' );
}

function test_shop_list_query_args_treats_missing_meta_as_occupied() {
	$args = centershop_get_shop_list_query_args( array( 'availability' => 'occupied' ) );

	assert_same( 'OR', $args['meta_query']['relation'], 'Occupied filter should include shops without vacancy meta.' );
	assert_same( 'NOT EXISTS', $args['meta_query'][1]['compare'], 'Occupied filter should include missing butik_availability.' );
}

function test_shop_list_render_callback_accepts_array_attributes() {
	$source = file_get_contents( dirname( __DIR__ ) . '/jxw-mall.php' );

	assert_true(
		false !== strpos( $source, 'function centershop_render_shop_list_block( array $attributes' ),
		'Shop list render callback must accept the attributes array WordPress passes.'
	);
	assert_true(
		false === strpos( $source, 'function centershop_render_shop_list_block( string $attributes' ),
		'Shop list render callback must not type-hint attributes as string.'
	);
}

test_sanitize_shop_availability();
test_sanitize_shop_availability_filter();
test_sanitize_shop_list_categories();
test_shop_list_query_args_default_has_no_filters();
test_shop_list_query_args_filter_for_rent_and_categories();
test_shop_list_query_args_treats_missing_meta_as_occupied();
test_shop_list_render_callback_accepts_array_attributes();

echo "shop-availability tests passed\n";
