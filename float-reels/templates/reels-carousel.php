<?php
/**
 * float Reels — Homepage Carousel Partial
 *
 * Renders a horizontal Swiper carousel of reels + a full-screen popup viewer.
 * Call via the helper function from any theme template:
 *
 *   <?php if ( function_exists( 'float_reels_carousel' ) ) float_reels_carousel(); ?>
 *
 * Or with a city filter:
 *
 *   <?php float_reels_carousel( 'berlin' ); ?>
 *
 * The city_slug is passed via query var 'float_reels_city_slug' by the wrapper
 * function defined in float-reels.php.
 *
 * @package floatReels
 */

// ── City context ──────────────────────────────────────────────────────────────
// Accept either the query var (set by float_reels_carousel()) or the global
// $float_city_slug used by the float News theme.
$float_reels_city = get_query_var( 'float_reels_city_slug', '' );

if ( empty( $float_reels_city ) && isset( $GLOBALS['float_city_slug'] ) ) {
	$float_reels_city = $GLOBALS['float_city_slug'];
}

$float_reels_city = sanitize_key( $float_reels_city );

// ── Build WP_Query args ───────────────────────────────────────────────────────
$float_reels_query_args = array(
	'post_type'           => 'reel',
	'post_status'         => 'publish',
	'posts_per_page'      => -1,
	'orderby'             => 'date',
	'order'               => 'DESC',
	'no_found_rows'       => true,
	'ignore_sticky_posts' => true,
);

// Only apply city filter when a slug is provided AND the taxonomy exists.
if ( $float_reels_city && taxonomy_exists( 'city' ) ) {
	$float_reels_query_args['tax_query'] = array(
		array(
			'taxonomy' => 'city',
			'field'    => 'slug',
			'terms'    => $float_reels_city,
		),
	);
}

// ── Collect reel data ─────────────────────────────────────────────────────────
$float_reels_data  = array();
$float_reels_query = new WP_Query( $float_reels_query_args );

if ( $float_reels_query->have_posts() ) {
	while ( $float_reels_query->have_posts() ) {
		$float_reels_query->the_post();
		$float_reel_video_id = get_field( 'reel_video' );
		$float_reels_data[]  = array(
			'id'        => get_the_ID(),
			'top_title' => get_field( 'top_title' ),
			'title'     => get_field( 'reel_title' ) ?: get_the_title(),
			'video_url' => $float_reel_video_id ? wp_get_attachment_url( $float_reel_video_id ) : '',
			'thumbnail' => get_the_post_thumbnail_url( get_the_ID(), 'large' ) ?: '',
		);
	}
	wp_reset_postdata();
}

if ( empty( $float_reels_data ) ) {
	return; // Nothing to display.
}
?>

<!-- REELS (horizontal carousel + full-screen popup) -->
<section class="section section--reels col-eight" aria-label="<?php esc_attr_e( 'Reels', 'float-reels' ); ?>">

	<header class="section__header container flex space-between">
		<p class="hero-card__label tag">
			<span class="chip chip--assist" aria-label="<?php esc_attr_e( 'Reels section', 'float-reels' ); ?>">
				<span class="chip__icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#fff"><path d="M672-192v-576q29 0 50.5 21.16t21.5 50.88v432.24Q744-234 722.5-213T672-192ZM168-96q-29.7 0-50.85-21.15Q96-138.3 96-168v-624q0-29.7 21.15-50.85Q138.3-864 168-864h360q29 0 50.5 21.15T600-792v624q0 29.7-21.5 50.85Q557-96 528-96H168Zm648-144v-480q22 0 35 23t13 45v344q0 22-13 45t-35 23Zm-648 72h360v-624H168v624Zm0-624v624-624Z"/></svg>
				</span>
				<span class="chip__text"><?php esc_html_e( 'Reels', 'float-reels' ); ?></span>
			</span>
		</p>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'reel' ) ); ?>" class="header__action flex align-center">
			<span class="action--assist" aria-label="<?php esc_attr_e( 'All Reels', 'float-reels' ); ?>"></span>
			<span class="action__text label-large"><?php esc_html_e( 'All reels', 'float-reels' ); ?></span>
			<span class="action__icon" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#4A5561"><path d="M522-480 333-669l51-51 240 240-240 240-51-51 189-189Z"/></svg>
			</span>
		</a>
	</header>

	<!-- ── Horizontal carousel ─────────────────────────────────────────────── -->
	<div class="reels-carousel swiper" role="region" aria-label="<?php esc_attr_e( 'Reels carousel', 'float-reels' ); ?>">
		<div class="swiper-wrapper reels__list" role="list">

			<?php foreach ( $float_reels_data as $float_reel_index => $float_reel ) :
				$float_reel_uid = 'reel-' . ( $float_reel_index + 1 ) . '-title';
			?>
			<div
				class="swiper-slide reels__item"
				role="listitem"
				data-reel-index="<?php echo esc_attr( $float_reel_index ); ?>"
				tabindex="0"
				aria-label="<?php echo esc_attr( $float_reel['title'] ); ?>"
			>
				<article class="reel-card" aria-labelledby="<?php echo esc_attr( $float_reel_uid ); ?>">
					<div class="reel-card__media">

						<video
							class="reel-card__video"
							src="<?php echo esc_url( $float_reel['video_url'] ); ?>#t=0.001"
							muted
							playsinline
							loop
							preload="metadata"
						></video>

						<!-- Play overlay -->
						<span class="video__play" aria-hidden="true">
							<span class="video__play-icon">
								<svg fill="none" height="14" viewBox="0 0 11 14" width="11" xmlns="http://www.w3.org/2000/svg"><path d="m0 14v-14l11 7z" fill="#eef2f6"/></svg>
							</span>
						</span>

						<!-- Bottom overlay -->
						<span class="reel-card__overlay" aria-hidden="true">
							<?php if ( $float_reel['top_title'] ) : ?>
							<span class="reel-card__kicker top-title"><?php echo esc_html( $float_reel['top_title'] ); ?></span>
							<?php endif; ?>
							<h3 class="reel-card__title text-600" id="<?php echo esc_attr( $float_reel_uid ); ?>"><?php echo esc_html( $float_reel['title'] ); ?></h3>
						</span>
					</div>

					<!-- Accessible text (visually hidden) -->
					<div class="a11y-visually-hidden">
						<?php if ( $float_reel['top_title'] ) : ?>
						<p class="reel-card__kicker"><?php echo esc_html( $float_reel['top_title'] ); ?></p>
						<?php endif; ?>
						<h3><?php echo esc_html( $float_reel['title'] ); ?></h3>
					</div>
				</article>
			</div>
			<?php endforeach; ?>

		</div>
	</div>

	<!-- ── Reels popup (full-screen vertical/horizontal Swiper) ──────────── -->
	<div
		id="reels-popup"
		class="reels-popup"
		hidden
		role="dialog"
		aria-modal="true"
		aria-label="<?php esc_attr_e( 'Reel viewer', 'float-reels' ); ?>"
	>
		<!-- Close button -->
		<button class="reels-popup__close" aria-label="<?php esc_attr_e( 'Close', 'float-reels' ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffffff">
				<path d="M256-192 192-256l224-224-224-224 64-64 224 224 224-224 64 64-224 224 224 224-64 64-224-224-224 224Z"/>
			</svg>
		</button>

		<!-- Mute / unmute button -->
		<button class="reels-popup__mute" data-muted="true" aria-label="<?php esc_attr_e( 'Unmute', 'float-reels' ); ?>">
			<svg class="reels-popup__icon-muted" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffffff" aria-hidden="true">
				<path d="M792-56 671-177q-29 18-61.5 28T544-139v-81q19-4 37-10.5t35-16.5L544-330v174L360-332H184v-256h110L56-816l57-57 736 736-57 57Zm-8-232-58-58q17-31 25.5-65t8.5-69q0-97-56-176t-144-106v-84q124 28 202 125.5T840-575q0 53-14.5 102T784-288ZM660-412l-56-56v-130q37 29 58.5 71.5T684-434q0 20-3 38.5T660-412ZM544-541 406-679l138-138v276Z"/>
			</svg>
			<svg class="reels-popup__icon-unmuted" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffffff" aria-hidden="true">
				<path d="M560-131v-82q90-26 145-100t55-168q0-94-55-168T560-749v-82q124 28 202 125.5T840-481q0 127-78 224.5T560-131ZM120-360v-240h160l200-200v640L280-360H120Zm440 40v-322q47 22 73.5 66t26.5 96q0 51-26.5 94.5T560-320ZM400-606l-86 86H200v120h114l86 86v-292Zm-50 146Z"/>
			</svg>
		</button>

		<!-- Prev / Next (desktop) -->
		<button class="reels-popup__nav reels-popup__nav--prev" aria-label="<?php esc_attr_e( 'Previous reel', 'float-reels' ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#ffffff" aria-hidden="true">
				<path d="M560-240 320-480l240-240 56 56-184 184 184 184-56 56Z"/>
			</svg>
		</button>
		<button class="reels-popup__nav reels-popup__nav--next" aria-label="<?php esc_attr_e( 'Next reel', 'float-reels' ); ?>">
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
				<path d="M10 6L16 12L10 18L8.6 16.6L13.2 12L8.6 7.4L10 6Z" fill="white"/>
			</svg>
		</button>

		<!-- Swiper -->
		<div class="reels-popup__swiper swiper">
			<div class="swiper-wrapper">

				<?php foreach ( $float_reels_data as $float_popup_reel ) : ?>
				<div class="swiper-slide reels-popup__slide">

					<!-- Blurred background (desktop only) -->
					<div class="reels-popup__bg" aria-hidden="true"<?php if ( $float_popup_reel['thumbnail'] ) : ?> style="background-image:url('<?php echo esc_url( $float_popup_reel['thumbnail'] ); ?>')"<?php endif; ?>></div>

					<!-- Video + overlay -->
					<div class="reels-popup__video-container">
						<video
							class="reels-popup__video"
							src="<?php echo esc_url( $float_popup_reel['video_url'] ); ?>#t=0.001"
							muted
							playsinline
							preload="metadata"
						></video>

						<div class="reels-popup__overlay" aria-hidden="true">
							<?php if ( $float_popup_reel['top_title'] ) : ?>
							<span class="reels-popup__kicker"><?php echo esc_html( $float_popup_reel['top_title'] ); ?></span>
							<?php endif; ?>
							<p class="reels-popup__title"><?php echo esc_html( $float_popup_reel['title'] ); ?></p>
						</div>
					</div>

				</div>
				<?php endforeach; ?>

			</div>
		</div>
	</div>

</section>
