<?php
/**
 * Render callback for the read-also block.
 *
 * Shows a clickable card for a selected article, page, shop or event.
 * Events additionally display the event date, start time and place.
 *
 * @package JXW_Mall
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id   = isset( $attributes['postId'] ) ? absint( $attributes['postId'] ) : 0;
$post_type = isset( $attributes['postType'] ) ? sanitize_key( $attributes['postType'] ) : '';
$words     = isset( $attributes['excerptLength'] ) ? absint( $attributes['excerptLength'] ) : 30;

if ( ! $post_id ) {
	echo '<p>' . esc_html__( 'Intet indhold valgt.', 'centershop_txtdomain' ) . '</p>';
	return;
}

$the_post = get_post( $post_id );

if ( ! $the_post || 'publish' !== $the_post->post_status ) {
	echo '<p>' . esc_html__( 'Indhold ikke fundet.', 'centershop_txtdomain' ) . '</p>';
	return;
}

// Use the stored post type, fall back to the real post type of the loaded post.
if ( '' === $post_type ) {
	$post_type = $the_post->post_type;
}

$is_event = in_array( $post_type, array( 'event', 'tribe_events' ), true );

$permalink   = get_permalink( $the_post );
$title       = get_the_title( $the_post );
$thumbnail   = get_the_post_thumbnail( $the_post, 'medium_large' );

/**
 * Build a short plain-text excerpt of the first lines of the content.
 *
 * @param WP_Post $post  Post object.
 * @param int     $limit Number of words.
 * @return string
 */
function centershop_read_also_excerpt( $post, $limit ) {
	$raw = $post->post_excerpt ? $post->post_excerpt : $post->post_content;
	$raw = wp_strip_all_tags( wp_kses_post( $raw ) );
	$raw = trim( preg_replace( '/\s+/', ' ', $raw ) );

	if ( '' === $raw ) {
		return '';
	}

	$parts = explode( ' ', $raw );

	if ( count( $parts ) <= $limit ) {
		return $raw;
	}

	return implode( ' ', array_slice( $parts, 0, $limit ) ) . '…';
}

?>
<div <?php echo wp_kses_post( get_block_wrapper_attributes( array( 'class' => 'centershop-read-also-block' ) ) ); ?>>
	<a class="centershop-read-also-card" href="<?php echo esc_url( $permalink ); ?>">
		<?php if ( $thumbnail ) : ?>
			<div class="centershop-read-also-card__image-wrap">
				<?php echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_the_post_thumbnail returns safe HTML. ?>
			</div>
		<?php endif; ?>

		<div class="centershop-read-also-card__body">
			<h3 class="centershop-read-also-card__title"><?php echo esc_html( $title ); ?></h3>

			<?php if ( $is_event ) : ?>
				<?php
				$event_date = get_post_meta( $the_post->ID, '_event_date', true );
				if ( '' === $event_date ) {
					$event_date = get_post_meta( $the_post->ID, '_EventStartDate', true );
				}
				$start_time = get_post_meta( $the_post->ID, '_event_start_time', true );
				if ( '' === $start_time ) {
					$start_time = get_post_meta( $the_post->ID, '_EventStartTime', true );
				}
				$address = get_post_meta( $the_post->ID, '_event_address', true );
				if ( '' === $address ) {
					$address = get_post_meta( $the_post->ID, '_VenueAddress', true );
				}
				if ( '' === $address ) {
					$address = get_post_meta( $the_post->ID, '_EventVenue', true );
				}
				?>

				<?php if ( $event_date ) : ?>
					<?php
					$ts   = strtotime( $event_date );
					$when = $ts ? wp_date( 'j. F Y', $ts ) : esc_html( $event_date );
					if ( $start_time ) {
						$when .= ' ' . esc_html__( 'kl.', 'centershop_txtdomain' ) . ' ' . esc_html( $start_time );
					}
					?>
					<p class="centershop-read-also-card__meta">
						<?php
						/* translators: 1: event date and time. */
						printf( esc_html__( 'Begivenhed: %s', 'centershop_txtdomain' ), esc_html( $when ) );
						?>
					</p>
				<?php endif; ?>

				<?php if ( $address ) : ?>
					<p class="centershop-read-also-card__meta">
						<?php
						/* translators: 1: event address. */
						printf( esc_html__( 'Sted: %s', 'centershop_txtdomain' ), esc_html( $address ) );
						?>
					</p>
				<?php endif; ?>
			<?php else : ?>
				<p class="centershop-read-also-card__meta">
					<?php
					/* translators: 1: publish date. */
					printf(
						esc_html__( 'Udgivet: %s', 'centershop_txtdomain' ),
						esc_html( wp_date( 'j. F Y', strtotime( $the_post->post_date ) ) )
					);
					?>
				</p>

				<?php $excerpt = centershop_read_also_excerpt( $the_post, $words ); ?>
				<?php if ( $excerpt ) : ?>
					<p class="centershop-read-also-card__excerpt"><?php echo esc_html( $excerpt ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</a>
</div>
