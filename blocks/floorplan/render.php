<?php
/**
 * Floorplan block render.
 *
 * @var array $attributes Block attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$focus   = isset( $attributes['focus'] ) ? (string) $attributes['focus'] : '';
$shop_id = '';
$marker  = '';

if ( 0 === strpos( $focus, 'shop:' ) ) {
	$shop_id = (string) absint( substr( $focus, 5 ) );
} elseif ( 0 === strpos( $focus, 'marker:' ) ) {
	$marker = substr( $focus, 7 );
}

$height = isset( $attributes['height'] ) ? (int) $attributes['height'] : 560;
if ( $height < 240 ) {
	$height = 240;
} elseif ( $height > 900 ) {
	$height = 900;
}

$show_search = ! isset( $attributes['showSearch'] ) || $attributes['showSearch'];

$focus_zoom = isset( $attributes['focusZoom'] ) ? (int) $attributes['focusZoom'] : 150;
if ( $focus_zoom < 100 ) {
	$focus_zoom = 100;
} elseif ( $focus_zoom > 250 ) {
	$focus_zoom = 250;
}

echo CenterShop_FloorPlan::get_instance()->floorplan_shortcode(
	array(
		'shop_id'     => $shop_id,
		'marker'      => $marker,
		'show_search' => $show_search ? 'yes' : 'no',
		'height'      => $height . 'px',
		'focus_zoom'  => (string) $focus_zoom,
	)
);
