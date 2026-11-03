<?php

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

function test_add_butik_metaboxes_is_a_real_function() {
	$source = file_get_contents( dirname( __DIR__ ) . '/includes/functions-cpt-butikker.php' );
	$tokens = token_get_all( $source );
	$found  = false;

	for ( $i = 0, $count = count( $tokens ); $i < $count; $i++ ) {
		if ( ! is_array( $tokens[ $i ] ) || T_FUNCTION !== $tokens[ $i ][0] ) {
			continue;
		}

		for ( $j = $i + 1; $j < $count; $j++ ) {
			if ( ! is_array( $tokens[ $j ] ) ) {
				continue;
			}
			if ( T_WHITESPACE === $tokens[ $j ][0] || T_COMMENT === $tokens[ $j ][0] || T_DOC_COMMENT === $tokens[ $j ][0] ) {
				continue;
			}
			$found = ( T_STRING === $tokens[ $j ][0] && 'add_butik_metaboxes' === $tokens[ $j ][1] );
			break;
		}

		if ( $found ) {
			break;
		}
	}

	assert_true( $found, 'add_butik_metaboxes must be a defined function, not commented out.' );
}

function test_cpt_args_point_to_callable_metabox_callback() {
	if ( ! function_exists( '_x' ) ) {
		function _x( $text ) {
			return $text;
		}
	}
	if ( ! function_exists( '__' ) ) {
		function __( $text ) {
			return $text;
		}
	}
	if ( ! function_exists( 'add_action' ) ) {
		function add_action() {}
	}
	if ( ! function_exists( 'add_filter' ) ) {
		function add_filter() {}
	}

	require dirname( __DIR__ ) . '/includes/functions-cpt-butikker.php';

	assert_true( function_exists( 'add_butik_metaboxes' ), 'Including the CPT file should define add_butik_metaboxes.' );
	assert_true( function_exists( 'butik_status_meta_options' ), 'Including the CPT file should define the vacancy status metabox renderer.' );
	assert_true( is_callable( $args['register_meta_box_cb'] ), 'register_meta_box_cb must be a valid callback.' );
	assert_same( 'add_butik_metaboxes', $args['register_meta_box_cb'], 'CPT should register add_butik_metaboxes as the metabox callback.' );
}

function test_add_butik_metaboxes_registers_expected_boxes() {
	$registered = array();

	if ( ! function_exists( 'add_meta_box' ) ) {
		function add_meta_box( $id, $title, $callback, $screen ) {
			global $centershop_test_metaboxes;
			$centershop_test_metaboxes[] = array(
				'id'       => $id,
				'title'    => $title,
				'callback' => $callback,
				'screen'   => $screen,
			);
		}
	}

	$GLOBALS['centershop_test_metaboxes'] = array();
	add_butik_metaboxes();
	$registered = $GLOBALS['centershop_test_metaboxes'];

	assert_same( 4, count( $registered ), 'Shop edit screen should register opening hours, contact, status, and logo metaboxes.' );
	assert_same( 'butik_open_info', $registered[0]['id'], 'Opening hours metabox should be registered first.' );
	assert_same( 'jxw_opening_hours_v2_metabox_html', $registered[0]['callback'], 'Opening hours should use the v2 metabox renderer.' );
	assert_same( 'butik_kontakt_info', $registered[1]['id'], 'Contact metabox should be registered.' );
	assert_same( 'butik_lokale_status', $registered[2]['id'], 'Vacancy status metabox should be registered.' );
	assert_same( 'butik_status_meta_options', $registered[2]['callback'], 'Vacancy status should use butik_status_meta_options.' );
	assert_same( 'butik_logo_pic', $registered[3]['id'], 'Logo metabox should be registered.' );
}

test_add_butik_metaboxes_is_a_real_function();
test_cpt_args_point_to_callable_metabox_callback();
test_add_butik_metaboxes_registers_expected_boxes();

echo "cpt-butikker tests passed\n";
