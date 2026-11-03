<?php

define( 'ABSPATH', __DIR__ );

$GLOBALS['centershop_test_options'] = array();
$GLOBALS['centershop_test_meta']    = array();
$GLOBALS['centershop_test_notices'] = array();

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code;
		public $message;

		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function __( $text ) {
	return $text;
}

function add_action() {}

function get_option( $key, $default = false ) {
	if ( array_key_exists( $key, $GLOBALS['centershop_test_options'] ) ) {
		return $GLOBALS['centershop_test_options'][ $key ];
	}
	return $default;
}

function get_post_meta( $post_id, $key, $single = false ) {
	$value = $GLOBALS['centershop_test_meta'][ $post_id ][ $key ] ?? '';
	return $single ? $value : array( $value );
}

function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['centershop_test_meta'][ $post_id ][ $key ] = $value;
}

function delete_post_meta( $post_id, $key ) {
	unset( $GLOBALS['centershop_test_meta'][ $post_id ][ $key ] );
}

function get_current_user_id() {
	return 1;
}

function set_transient( $key, $value ) {
	$GLOBALS['centershop_test_notices'][ $key ] = $value;
}

require_once dirname( __DIR__ ) . '/includes/functions-facebook-page-id.php';

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

function reset_facebook_test_state() {
	$GLOBALS['centershop_test_options'] = array();
	$GLOBALS['centershop_test_meta']    = array();
	$GLOBALS['centershop_test_notices'] = array();
}

function test_extract_page_id_from_numeric_and_query_urls() {
	assert_same( '', centershop_extract_facebook_page_id_from_url( '' ), 'Empty URL should yield no ID.' );
	assert_same( '123456789012345', centershop_extract_facebook_page_id_from_url( '123456789012345' ), 'A pasted numeric ID should be kept.' );
	assert_same(
		'15087023444',
		centershop_extract_facebook_page_id_from_url( 'https://www.facebook.com/profile.php?id=15087023444' ),
		'profile.php?id= should be the page ID.'
	);
	assert_same(
		'15087023444',
		centershop_extract_facebook_page_id_from_url( 'https://m.facebook.com/pages/Nike/15087023444' ),
		'Legacy /pages/Name/ID should use the trailing number.'
	);
	assert_same(
		'15087023444',
		centershop_extract_facebook_page_id_from_url( 'https://www.facebook.com/people/Nike/15087023444' ),
		'/people/Name/ID should use the trailing number.'
	);
	assert_same(
		'15087023444',
		centershop_extract_facebook_page_id_from_url( 'https://www.facebook.com/p/Nike-15087023444' ),
		'/p/Name-ID should use the trailing number.'
	);
	assert_same(
		'15087023444',
		centershop_extract_facebook_page_id_from_url( 'facebook.com/15087023444' ),
		'Bare facebook.com/ID without a scheme should still parse.'
	);
	assert_same(
		'15087023444',
		centershop_extract_facebook_page_id_from_url( 'https://www.facebook.com/da-dk/profile.php?id=15087023444' ),
		'Locale prefixes should be ignored.'
	);
}

function test_extract_page_id_ignores_vanity_and_non_page_urls() {
	assert_same( '', centershop_extract_facebook_page_id_from_url( 'https://www.facebook.com/nike' ), 'Vanity URLs have no ID in the path.' );
	assert_same( '', centershop_extract_facebook_page_id_from_url( 'https://www.facebook.com/nike/posts/111222333' ), 'Post IDs must not be treated as page IDs.' );
	assert_same( '', centershop_extract_facebook_page_id_from_url( 'https://www.facebook.com/groups/123456789012345' ), 'Group IDs must not be treated as page IDs.' );
	assert_same( '', centershop_extract_facebook_page_id_from_url( 'https://example.com/profile.php?id=15087023444' ), 'Non-Facebook hosts should be ignored.' );
	assert_same( 'nike', centershop_extract_facebook_page_username_from_url( 'https://www.facebook.com/nike/posts/1' ), 'Username should still be read from a vanity URL with a post path.' );
	assert_same( '', centershop_extract_facebook_page_username_from_url( 'https://www.facebook.com/pages/Nike/15087023444' ), 'Reserved paths are not usernames.' );
}

function test_sanitize_facebook_page_id() {
	assert_same( '15087023444', centershop_sanitize_facebook_page_id( ' 15087023444 ' ), 'Numeric IDs should be trimmed.' );
	assert_same( '15087023444', centershop_sanitize_facebook_page_id( 'id: 15087023444' ), 'id: prefix should be accepted.' );
	assert_same(
		'15087023444',
		centershop_sanitize_facebook_page_id( 'https://www.facebook.com/profile.php?id=15087023444' ),
		'A URL pasted into the ID field should be parsed.'
	);
	assert_same( '', centershop_sanitize_facebook_page_id( 'nike' ), 'A vanity name is not an ID.' );
}

function test_parse_facebook_graph_id() {
	assert_same(
		'15087023444',
		centershop_parse_facebook_graph_id( array( 'id' => '15087023444', 'name' => 'Nike' ) ),
		'Direct Graph objects expose id.'
	);
	assert_same(
		'15087023444',
		centershop_parse_facebook_graph_id(
			array(
				'https://www.facebook.com/nike' => array(
					'id'   => 15087023444,
					'name' => 'Nike',
				),
			)
		),
		'The ids= lookup wraps the page object in the URL key.'
	);
	assert_same( '', centershop_parse_facebook_graph_id( array( 'error' => array( 'message' => 'fail' ) ) ), 'Error payloads should not yield an ID.' );
}

function test_graph_lookup_uses_ids_then_username() {
	reset_facebook_test_state();
	$GLOBALS['centershop_test_options']['centershop_fb_app_id']     = '111';
	$GLOBALS['centershop_test_options']['centershop_fb_app_secret'] = 'secret';
	$calls = array();

	$http = function ( $endpoint, $params ) use ( &$calls ) {
		$calls[] = $endpoint;
		if ( $endpoint === '' ) {
			return new WP_Error( 'fb_api_error', 'ids lookup failed' );
		}
		if ( $endpoint === 'nike' ) {
			return array( 'id' => '15087023444', 'name' => 'Nike' );
		}
		return new WP_Error( 'unexpected', 'unexpected endpoint ' . $endpoint );
	};

	$result = centershop_lookup_facebook_page_id_via_graph( 'https://www.facebook.com/nike', $http );
	assert_same( '15087023444', $result, 'Vanity URLs should fall back to username lookup.' );
	assert_same( array( '', 'nike' ), $calls, 'Graph should try ids= first, then the username.' );
}

function test_graph_lookup_without_credentials() {
	reset_facebook_test_state();
	$result = centershop_lookup_facebook_page_id_via_graph( 'https://www.facebook.com/nike' );
	assert_true( is_wp_error( $result ), 'Missing App ID/Secret should return an error.' );
	assert_same( 'no_credentials', $result->get_error_code(), 'The error code should be no_credentials.' );
}

function test_sync_extracts_id_from_url_without_graph() {
	reset_facebook_test_state();
	$http = function () {
		throw new RuntimeException( 'Graph should not be called when the URL already contains an ID.' );
	};

	$id = centershop_sync_shop_facebook_page_id(
		12,
		'https://www.facebook.com/profile.php?id=15087023444',
		'',
		$http
	);

	assert_same( '15087023444', $id, 'Saving a numeric Facebook URL should store the ID.' );
	assert_same( '15087023444', get_post_meta( 12, 'butik_payed_fb_id', true ), 'The ID meta should be saved.' );
	assert_same(
		'https://www.facebook.com/profile.php?id=15087023444',
		get_post_meta( 12, 'butik_payed_fb_id_from', true ),
		'The source URL should be stored next to the ID.'
	);
}

function test_sync_skips_graph_when_id_already_bound_to_url() {
	reset_facebook_test_state();
	$GLOBALS['centershop_test_meta'][12]['butik_payed_fb_id']      = '15087023444';
	$GLOBALS['centershop_test_meta'][12]['butik_payed_fb_id_from'] = 'https://www.facebook.com/nike';

	$http = function () {
		throw new RuntimeException( 'Graph should not run again for an unchanged vanity URL.' );
	};

	$id = centershop_sync_shop_facebook_page_id( 12, 'https://www.facebook.com/nike', '15087023444', $http );
	assert_same( '15087023444', $id, 'An already resolved vanity URL should keep its ID.' );
}

function test_sync_uses_graph_for_new_vanity_url() {
	reset_facebook_test_state();
	$GLOBALS['centershop_test_options']['centershop_fb_app_id']     = '111';
	$GLOBALS['centershop_test_options']['centershop_fb_app_secret'] = 'secret';

	$http = function ( $endpoint ) {
		if ( $endpoint === '' ) {
			return array(
				'https://www.facebook.com/nike' => array(
					'id'   => '15087023444',
					'name' => 'Nike',
				),
			);
		}
		return new WP_Error( 'unexpected', 'username lookup should not be needed' );
	};

	$id = centershop_sync_shop_facebook_page_id( 12, 'https://www.facebook.com/nike', '', $http );
	assert_same( '15087023444', $id, 'A vanity URL should be resolved through Graph.' );
}

function test_sync_clears_stale_id_when_url_changes_and_lookup_fails() {
	reset_facebook_test_state();
	$GLOBALS['centershop_test_meta'][12]['butik_payed_fb_id']      = '11111111111';
	$GLOBALS['centershop_test_meta'][12]['butik_payed_fb_id_from'] = 'https://www.facebook.com/oldshop';

	$id = centershop_sync_shop_facebook_page_id( 12, 'https://www.facebook.com/newshop', '11111111111' );
	assert_same( '', $id, 'A stale ID from a previous URL should be dropped.' );
	assert_same( '', get_post_meta( 12, 'butik_payed_fb_id', true ), 'Stale ID meta should be deleted.' );
	assert_true( ! empty( $GLOBALS['centershop_test_notices'] ), 'A warning should be stored when lookup fails.' );
}

function test_sync_keeps_manual_id_when_lookup_fails() {
	reset_facebook_test_state();
	$id = centershop_sync_shop_facebook_page_id( 12, 'https://www.facebook.com/nike', '15087023444' );
	assert_same( '15087023444', $id, 'A manually entered ID should be kept when Graph is unavailable.' );
}

function test_sync_clears_id_when_url_is_empty() {
	reset_facebook_test_state();
	$GLOBALS['centershop_test_meta'][12]['butik_payed_fb_id']      = '15087023444';
	$GLOBALS['centershop_test_meta'][12]['butik_payed_fb_id_from'] = 'https://www.facebook.com/nike';

	$id = centershop_sync_shop_facebook_page_id( 12, '', '15087023444' );
	assert_same( '', $id, 'Clearing the Facebook URL should clear the page ID.' );
	assert_same( '', get_post_meta( 12, 'butik_payed_fb_id', true ), 'ID meta should be deleted with the URL.' );
}

function test_resolve_prefers_url_over_graph() {
	$http = function () {
		throw new RuntimeException( 'Graph must not run when the URL already contains an ID.' );
	};
	assert_same(
		'15087023444',
		centershop_resolve_facebook_page_id( 'https://www.facebook.com/pages/Nike/15087023444', $http ),
		'Resolve should parse the URL before calling Graph.'
	);
}

test_extract_page_id_from_numeric_and_query_urls();
test_extract_page_id_ignores_vanity_and_non_page_urls();
test_sanitize_facebook_page_id();
test_parse_facebook_graph_id();
test_graph_lookup_uses_ids_then_username();
test_graph_lookup_without_credentials();
test_sync_extracts_id_from_url_without_graph();
test_sync_skips_graph_when_id_already_bound_to_url();
test_sync_uses_graph_for_new_vanity_url();
test_sync_clears_stale_id_when_url_changes_and_lookup_fails();
test_sync_keeps_manual_id_when_lookup_fails();
test_sync_clears_id_when_url_is_empty();
test_resolve_prefers_url_over_graph();

echo "facebook-page-id tests passed\n";
