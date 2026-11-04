<?php
/**
 * Resolve a Facebook Page ID from a shop's Facebook page URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CENTERSHOP_FB_GRAPH_VERSION' ) ) {
	define( 'CENTERSHOP_FB_GRAPH_VERSION', 'v21.0' );
}

/**
 * Hosts treated as Facebook page URLs.
 *
 * @param string $host Host without a leading www.
 * @return bool
 */
function centershop_is_facebook_host( $host ) {
	$host = strtolower( (string) $host );
	if ( strpos( $host, 'www.' ) === 0 ) {
		$host = substr( $host, 4 );
	}

	return in_array( $host, array( 'facebook.com', 'fb.com', 'fb.me' ), true )
		|| substr( $host, -13 ) === '.facebook.com';
}

/**
 * Split a Facebook URL into host, path segments and query.
 *
 * @param string $url Raw URL, host/path, or numeric ID.
 * @return array<string, mixed>|null
 */
function centershop_parse_facebook_url( $url ) {
	$url = trim( (string) $url );
	if ( $url === '' ) {
		return null;
	}

	if ( preg_match( '/^\d{5,}$/', $url ) ) {
		return array(
			'host'           => '',
			'path_segments'  => array(),
			'query'          => array(),
			'numeric'        => $url,
			'url'            => $url,
		);
	}

	if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $url ) ) {
		$url = 'https://' . ltrim( $url, '/' );
	}

	$parts = parse_url( $url );
	if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! centershop_is_facebook_host( $parts['host'] ) ) {
		return null;
	}

	$path     = isset( $parts['path'] ) ? trim( (string) $parts['path'], '/' ) : '';
	$segments = ( $path === '' ) ? array() : explode( '/', $path );

	if ( isset( $segments[0] ) && count( $segments ) > 1 && preg_match( '/^[a-z]{2}[-_][a-z]{2}$/i', $segments[0] ) ) {
		array_shift( $segments );
	}

	$query = array();
	if ( ! empty( $parts['query'] ) ) {
		parse_str( $parts['query'], $query );
	}

	return array(
		'host'          => strtolower( $parts['host'] ),
		'path_segments' => $segments,
		'query'         => $query,
		'numeric'       => '',
		'url'           => $url,
	);
}

/**
 * Whether the value is a Facebook page URL (or a pasted numeric ID).
 *
 * @param string $url Raw value.
 * @return bool
 */
function centershop_is_facebook_page_url( $url ) {
	return centershop_parse_facebook_url( $url ) !== null;
}

/**
 * Stable form of a Facebook URL, used to know if a stored ID still belongs to it.
 *
 * @param string $url Raw URL.
 * @return string
 */
function centershop_normalize_facebook_page_url( $url ) {
	$parsed = centershop_parse_facebook_url( $url );
	if ( ! $parsed ) {
		return '';
	}

	if ( $parsed['numeric'] !== '' ) {
		return $parsed['numeric'];
	}

	$path = implode( '/', $parsed['path_segments'] );
	$normalized = 'https://www.facebook.com/' . $path;

	if ( ! empty( $parsed['query']['id'] ) && preg_match( '/^\d{5,}$/', (string) $parsed['query']['id'] ) ) {
		$normalized .= '?id=' . $parsed['query']['id'];
	} elseif ( ! empty( $parsed['query']['page_id'] ) && preg_match( '/^\d{5,}$/', (string) $parsed['query']['page_id'] ) ) {
		$normalized .= '?id=' . $parsed['query']['page_id'];
	}

	return strtolower( rtrim( $normalized, '/' ) );
}

/**
 * Path segments that are not page usernames.
 *
 * @return string[]
 */
function centershop_facebook_reserved_path_segments() {
	return array(
		'pages',
		'people',
		'p',
		'pg',
		'profile.php',
		'groups',
		'events',
		'watch',
		'reel',
		'reels',
		'stories',
		'marketplace',
		'gaming',
		'photo.php',
		'photos',
		'video.php',
		'videos',
		'posts',
		'permalink.php',
		'share',
		'story.php',
		'login',
		'dialog',
		'plugins',
		'privacy',
		'help',
		'settings',
		'notifications',
		'messages',
		'friends',
		'live',
		'ads',
		'business',
		'hashtag',
		'sharer',
		'sharer.php',
		'home.php',
		'index.php',
	);
}

/**
 * Parents of numeric IDs that are posts, groups, or similar — not pages.
 *
 * @return string[]
 */
function centershop_facebook_non_page_id_parents() {
	return array(
		'groups',
		'events',
		'posts',
		'photos',
		'videos',
		'reel',
		'reels',
		'watch',
		'share',
		'stories',
		'story.php',
		'permalink.php',
		'photo.php',
		'video.php',
		'hashtag',
	);
}

/**
 * Read a numeric page ID out of a Facebook URL when the URL already contains it.
 *
 * @param string $url Facebook page URL or numeric ID.
 * @return string Numeric ID or empty string.
 */
function centershop_extract_facebook_page_id_from_url( $url ) {
	$parsed = centershop_parse_facebook_url( $url );
	if ( ! $parsed ) {
		return '';
	}

	if ( $parsed['numeric'] !== '' ) {
		return $parsed['numeric'];
	}

	foreach ( array( 'id', 'page_id' ) as $key ) {
		if ( empty( $parsed['query'][ $key ] ) ) {
			continue;
		}
		$candidate = (string) $parsed['query'][ $key ];
		if ( preg_match( '/^\d{5,}$/', $candidate ) ) {
			return $candidate;
		}
	}

	$segments = $parsed['path_segments'];
	if ( empty( $segments ) ) {
		return '';
	}

	if ( isset( $segments[0], $segments[1] ) && strtolower( $segments[0] ) === 'p' && preg_match( '/-(\d{5,})$/', $segments[1], $match ) ) {
		return $match[1];
	}

	$last_index = count( $segments ) - 1;
	$last       = $segments[ $last_index ];
	$parent     = $last_index > 0 ? strtolower( $segments[ $last_index - 1 ] ) : '';
	$skip       = centershop_facebook_non_page_id_parents();

	if ( preg_match( '/^\d{5,}$/', $last ) && ! in_array( $parent, $skip, true ) ) {
		return $last;
	}

	if ( preg_match( '/-(\d{10,})$/', $last, $match ) && ! in_array( $parent, $skip, true ) ) {
		return $match[1];
	}

	return '';
}

/**
 * Vanity username from a Facebook page URL, if present.
 *
 * @param string $url Facebook page URL.
 * @return string
 */
function centershop_extract_facebook_page_username_from_url( $url ) {
	$parsed = centershop_parse_facebook_url( $url );
	if ( ! $parsed || $parsed['numeric'] !== '' || empty( $parsed['path_segments'] ) ) {
		return '';
	}

	$first = $parsed['path_segments'][0];
	if ( in_array( strtolower( $first ), centershop_facebook_reserved_path_segments(), true ) ) {
		return '';
	}

	if ( preg_match( '/^\d+$/', $first ) ) {
		return '';
	}

	if ( preg_match( '/^(.+)-(\d{10,})$/', $first, $match ) ) {
		$first = $match[1];
	}

	if ( ! preg_match( '/^[A-Za-z0-9.]+$/', $first ) ) {
		return '';
	}

	return $first;
}

/**
 * Keep only a numeric Facebook Page ID, or extract one from a pasted URL.
 *
 * @param mixed $value Raw field value.
 * @return string
 */
function centershop_sanitize_facebook_page_id( $value ) {
	$value = trim( (string) $value );
	if ( $value === '' ) {
		return '';
	}

	if ( preg_match( '/^\d{5,}$/', $value ) ) {
		return $value;
	}

	if ( preg_match( '/^id:\s*(\d{5,})$/i', $value, $match ) ) {
		return $match[1];
	}

	return centershop_extract_facebook_page_id_from_url( $value );
}

/**
 * App or user token used for Graph lookups.
 *
 * @return string
 */
function centershop_get_facebook_access_token() {
	$token = trim( (string) get_option( 'centershop_fb_access_token', '' ) );
	if ( $token !== '' ) {
		return $token;
	}

	$app_id     = trim( (string) get_option( 'centershop_fb_app_id', '' ) );
	$app_secret = trim( (string) get_option( 'centershop_fb_app_secret', '' ) );
	if ( $app_id !== '' && $app_secret !== '' ) {
		return $app_id . '|' . $app_secret;
	}

	return '';
}

/**
 * Pull a numeric page ID out of a Graph API JSON payload.
 *
 * @param mixed $data Decoded JSON.
 * @return string
 */
function centershop_parse_facebook_graph_id( $data ) {
	if ( ! is_array( $data ) || isset( $data['error'] ) ) {
		return '';
	}

	if ( isset( $data['id'] ) && preg_match( '/^\d{5,}$/', (string) $data['id'] ) ) {
		return (string) $data['id'];
	}

	foreach ( $data as $value ) {
		if ( ! is_array( $value ) || ! empty( $value['error'] ) || empty( $value['id'] ) ) {
			continue;
		}
		if ( preg_match( '/^\d{5,}$/', (string) $value['id'] ) ) {
			return (string) $value['id'];
		}
	}

	return '';
}

/**
 * Default Graph HTTP client.
 *
 * @param string               $endpoint Path after the version, or empty for /?ids=.
 * @param array<string, mixed> $params   Query parameters including access_token.
 * @return array<string, mixed>|WP_Error
 */
function centershop_facebook_graph_http( $endpoint, array $params ) {
	$base = 'https://graph.facebook.com/' . CENTERSHOP_FB_GRAPH_VERSION . '/';
	$url  = $base . ltrim( (string) $endpoint, '/' );
	$url .= ( strpos( $url, '?' ) === false ? '?' : '&' ) . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );

	$response = wp_remote_get(
		$url,
		array(
			'timeout'     => 15,
			'redirection' => 3,
			'headers'     => array(
				'Accept' => 'application/json',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $body ) ) {
		return new WP_Error( 'fb_api_error', __( 'Ugyldigt svar fra Facebook Graph API.', 'centershop_txtdomain' ) );
	}

	if ( isset( $body['error']['message'] ) ) {
		return new WP_Error( 'fb_api_error', (string) $body['error']['message'], $body['error'] );
	}

	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error( 'fb_api_error', __( 'Facebook Graph API svarede med en fejl.', 'centershop_txtdomain' ) );
	}

	return $body;
}

/**
 * Look up a page ID via Graph API. Vanity URLs need App ID + Secret (or an access token).
 *
 * @param string        $url  Facebook page URL.
 * @param callable|null $http Optional HTTP client for tests.
 * @return string|WP_Error
 */
function centershop_lookup_facebook_page_id_via_graph( $url, $http = null ) {
	if ( ! centershop_is_facebook_page_url( $url ) ) {
		return new WP_Error( 'invalid_url', __( 'Det ligner ikke en Facebook-sideadresse.', 'centershop_txtdomain' ) );
	}

	$token = centershop_get_facebook_access_token();
	if ( $token === '' ) {
		return new WP_Error(
			'no_credentials',
			__( 'Facebook App ID og Secret er ikke konfigureret. Tilføj dem under CenterShop → Indstillinger, eller indtast Page ID manuelt.', 'centershop_txtdomain' )
		);
	}

	$http = is_callable( $http ) ? $http : 'centershop_facebook_graph_http';
	$parsed = centershop_parse_facebook_url( $url );
	$lookup_url = ( $parsed && ! empty( $parsed['url'] ) ) ? $parsed['url'] : $url;

	$result = call_user_func(
		$http,
		'',
		array(
			'ids'           => $lookup_url,
			'fields'        => 'id,name',
			'access_token'  => $token,
		)
	);

	if ( is_wp_error( $result ) ) {
		$username = centershop_extract_facebook_page_username_from_url( $url );
		if ( $username === '' ) {
			return $result;
		}
	} else {
		$id = centershop_parse_facebook_graph_id( $result );
		if ( $id !== '' ) {
			return $id;
		}
		$username = centershop_extract_facebook_page_username_from_url( $url );
		if ( $username === '' ) {
			return new WP_Error( 'not_found', __( 'Facebook kunne ikke finde et Page ID for adressen.', 'centershop_txtdomain' ) );
		}
	}

	$result = call_user_func(
		$http,
		$username,
		array(
			'fields'       => 'id,name',
			'access_token' => $token,
		)
	);

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$id = centershop_parse_facebook_graph_id( $result );
	if ( $id === '' ) {
		return new WP_Error( 'not_found', __( 'Facebook kunne ikke finde et Page ID for adressen.', 'centershop_txtdomain' ) );
	}

	return $id;
}

/**
 * Resolve a page ID from a URL: parse first, then Graph.
 *
 * @param string        $url  Facebook page URL.
 * @param callable|null $http Optional HTTP client for tests.
 * @return string|WP_Error
 */
function centershop_resolve_facebook_page_id( $url, $http = null ) {
	$id = centershop_extract_facebook_page_id_from_url( $url );
	if ( $id !== '' ) {
		return $id;
	}

	return centershop_lookup_facebook_page_id_via_graph( $url, $http );
}

/**
 * Persist the page ID and the URL it was resolved from.
 *
 * @param int    $post_id        Shop post ID.
 * @param string $page_id        Numeric page ID.
 * @param string $normalized_url Normalized Facebook URL.
 * @return void
 */
function centershop_store_facebook_page_id( $post_id, $page_id, $normalized_url ) {
	update_post_meta( $post_id, 'butik_payed_fb_id', $page_id );
	update_post_meta( $post_id, 'butik_payed_fb_id_from', $normalized_url );
}

/**
 * Drop a stored page ID.
 *
 * @param int $post_id Shop post ID.
 * @return void
 */
function centershop_clear_facebook_page_id( $post_id ) {
	delete_post_meta( $post_id, 'butik_payed_fb_id' );
	delete_post_meta( $post_id, 'butik_payed_fb_id_from' );
}

/**
 * Flash an admin notice after shop save.
 *
 * @param int    $post_id Shop post ID.
 * @param string $type    notice, warning, or error.
 * @param string $message Notice text.
 * @return void
 */
function centershop_set_facebook_page_id_notice( $post_id, $type, $message ) {
	if ( ! function_exists( 'set_transient' ) ) {
		return;
	}

	$user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
	set_transient(
		'centershop_fb_page_id_notice_' . $user_id . '_' . (int) $post_id,
		array(
			'type'    => $type,
			'message' => $message,
		),
		90
	);
}

/**
 * Sync stored page ID after a Facebook URL is saved.
 *
 * @param int           $post_id   Shop post ID.
 * @param string        $url       Facebook URL just saved.
 * @param string        $posted_id Manual ID from the edit form, if any.
 * @param callable|null $http      Optional HTTP client for tests.
 * @return string Stored ID or empty string.
 */
function centershop_sync_shop_facebook_page_id( $post_id, $url, $posted_id = '', $http = null ) {
	$post_id   = (int) $post_id;
	$url       = trim( (string) $url );
	$posted_id = centershop_sanitize_facebook_page_id( $posted_id );

	if ( $url === '' ) {
		if ( get_post_meta( $post_id, 'butik_payed_fb_id', true ) !== '' || get_post_meta( $post_id, 'butik_payed_fb_id_from', true ) !== '' ) {
			centershop_clear_facebook_page_id( $post_id );
		}
		return '';
	}

	$normalized = centershop_normalize_facebook_page_url( $url );
	$extracted  = centershop_extract_facebook_page_id_from_url( $url );
	if ( $extracted !== '' ) {
		centershop_store_facebook_page_id( $post_id, $extracted, $normalized );
		return $extracted;
	}

	$stored_id = (string) get_post_meta( $post_id, 'butik_payed_fb_id', true );
	$bound_url = (string) get_post_meta( $post_id, 'butik_payed_fb_id_from', true );

	if ( $stored_id !== '' && $bound_url === $normalized ) {
		if ( $posted_id !== '' && $posted_id !== $stored_id ) {
			centershop_store_facebook_page_id( $post_id, $posted_id, $normalized );
			return $posted_id;
		}
		return $stored_id;
	}

	$lookup = centershop_lookup_facebook_page_id_via_graph( $url, $http );
	if ( ! is_wp_error( $lookup ) && $lookup !== '' ) {
		centershop_store_facebook_page_id( $post_id, $lookup, $normalized );
		return $lookup;
	}

	$stale_posted_id = ( $posted_id !== '' && $posted_id === $stored_id && $bound_url !== $normalized );
	if ( $posted_id !== '' && ! $stale_posted_id ) {
		centershop_store_facebook_page_id( $post_id, $posted_id, $normalized );
		return $posted_id;
	}

	centershop_clear_facebook_page_id( $post_id );

	$message = is_wp_error( $lookup )
		? $lookup->get_error_message()
		: __( 'Page ID kunne ikke findes ud fra Facebook-adressen. Indtast ID’et manuelt.', 'centershop_txtdomain' );
	centershop_set_facebook_page_id_notice( $post_id, 'warning', $message );

	return '';
}

/**
 * Stored page ID for a shop.
 *
 * @param int $post_id Shop post ID.
 * @return string
 */
function centershop_get_shop_facebook_page_id( $post_id ) {
	return centershop_sanitize_facebook_page_id( get_post_meta( $post_id, 'butik_payed_fb_id', true ) );
}

/**
 * AJAX: resolve a page ID from the URL currently in the metabox.
 *
 * @return void
 */
function centershop_ajax_resolve_facebook_page_id() {
	check_ajax_referer( 'centershop_fb_page_id', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Du har ikke tilladelse til at hente Page ID.', 'centershop_txtdomain' ) ), 403 );
	}

	$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
	if ( $url === '' && isset( $_POST['url'] ) ) {
		$url = sanitize_text_field( wp_unslash( $_POST['url'] ) );
	}

	if ( $url === '' ) {
		wp_send_json_error( array( 'message' => __( 'Indtast først en Facebook-adresse.', 'centershop_txtdomain' ) ) );
	}

	$resolved = centershop_resolve_facebook_page_id( $url );
	if ( is_wp_error( $resolved ) ) {
		wp_send_json_error( array( 'message' => $resolved->get_error_message() ) );
	}

	if ( $resolved === '' ) {
		wp_send_json_error( array( 'message' => __( 'Adressen indeholder ikke et Page ID. Tilføj Facebook App-oplysninger under Indstillinger, eller indtast ID’et manuelt.', 'centershop_txtdomain' ) ) );
	}

	wp_send_json_success(
		array(
			'id'     => $resolved,
			'source' => centershop_extract_facebook_page_id_from_url( $url ) === $resolved ? 'url' : 'graph',
		)
	);
}

/**
 * Show the save-time lookup notice on the shop edit screen.
 *
 * @return void
 */
function centershop_facebook_page_id_admin_notice() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->base !== 'post' || $screen->post_type !== 'butiksside' ) {
		return;
	}

	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	if ( ! $post_id ) {
		return;
	}

	$user_id = get_current_user_id();
	$key     = 'centershop_fb_page_id_notice_' . $user_id . '_' . $post_id;
	$notice  = get_transient( $key );
	if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
		return;
	}

	delete_transient( $key );

	$type = in_array( $notice['type'], array( 'error', 'warning', 'success', 'info' ), true ) ? $notice['type'] : 'warning';
	printf(
		'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
		esc_attr( $type ),
		esc_html( $notice['message'] )
	);
}

/**
 * Re-resolve page ID after REST updates to shop meta.
 *
 * @param WP_Post         $post     Shop post.
 * @param WP_REST_Request $request  Request.
 * @param bool            $creating Whether the post was just created.
 * @return void
 */
function centershop_rest_sync_facebook_page_id( $post, $request, $creating = false ) {
	unset( $creating );

	if ( ! $post || empty( $post->ID ) ) {
		return;
	}

	$url       = get_post_meta( $post->ID, 'butik_payed_fb', true );
	$posted_id = '';

	if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
		$meta = $request->get_param( 'meta' );
		if ( is_array( $meta ) && array_key_exists( 'butik_payed_fb_id', $meta ) ) {
			$posted_id = $meta['butik_payed_fb_id'];
		}
	}

	if ( $posted_id === '' ) {
		$posted_id = get_post_meta( $post->ID, 'butik_payed_fb_id', true );
	}

	centershop_sync_shop_facebook_page_id( $post->ID, $url, $posted_id );
}

add_action( 'wp_ajax_centershop_resolve_facebook_page_id', 'centershop_ajax_resolve_facebook_page_id' );
add_action( 'admin_notices', 'centershop_facebook_page_id_admin_notice' );
add_action( 'rest_after_insert_butiksside', 'centershop_rest_sync_facebook_page_id', 10, 3 );
