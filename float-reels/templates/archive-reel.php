<?php
/**
 * float Reels — Archive Template
 *
 * Displays all published reels in a 2-column (mobile) / 4-column (desktop) grid.
 * Videos play / pause on click via the float-reels.js script.
 *
 * URL: /reels/  (managed by WordPress via has_archive => 'reels')
 *
 * This template is used automatically when the active theme does NOT provide
 * its own archive-reel.php. It calls get_header() / get_footer() so it works
 * with any standard WordPress theme.
 *
 * @package floatReels
 */

$float_reels_archive_query = new WP_Query( array(
	'post_type'           => 'reel',
	'post_status'         => 'publish',
	'posts_per_page'      => -1,
	'orderby'             => 'date',
	'order'               => 'DESC',
	'no_found_rows'       => true,
	'ignore_sticky_posts' => true,
) );
?>

<?php get_header(); ?>

<main id="main" class="main page-wrapper" tabindex="-1">

	<header class="page__header container">
		<h1 class="page__title"><?php esc_html_e( 'Reels', 'float-reels' ); ?></h1>
	</header>

	<!-- REELS ARCHIVE GRID -->
	<section class="section reels-archive" aria-label="<?php esc_attr_e( 'All reels', 'float-reels' ); ?>">

		<?php if ( $float_reels_archive_query->have_posts() ) : ?>

		<ul class="reels-archive__grid container" role="list">

			<?php $float_reel_i = 1;
			while ( $float_reels_archive_query->have_posts() ) : $float_reels_archive_query->the_post();
				$float_reel_id        = get_the_ID();
				$float_reel_top_title = get_field( 'top_title' );
				$float_reel_title     = get_field( 'reel_title' ) ?: get_the_title();
				$float_reel_video_id  = get_field( 'reel_video' );
				$float_reel_video_url = $float_reel_video_id ? wp_get_attachment_url( $float_reel_video_id ) : '';
				$float_reel_uid       = 'reel-archive-' . $float_reel_i . '-title';
			?>

			<li class="reels-archive__item">
				<article class="reel-card" aria-labelledby="<?php echo esc_attr( $float_reel_uid ); ?>" data-reel-archive>

					<div class="reel-card__media">

						<video
							class="reel-card__video"
							src="<?php echo esc_url( $float_reel_video_url ); ?>"
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
							<?php if ( $float_reel_top_title ) : ?>
							<span class="reel-card__kicker top-title"><?php echo esc_html( $float_reel_top_title ); ?></span>
							<?php endif; ?>
							<h2 class="reel-card__title text-600" id="<?php echo esc_attr( $float_reel_uid ); ?>"><?php echo esc_html( $float_reel_title ); ?></h2>
						</span>

					</div>

					<!-- Accessible text (visually hidden) -->
					<div class="a11y-visually-hidden">
						<?php if ( $float_reel_top_title ) : ?>
						<p><?php echo esc_html( $float_reel_top_title ); ?></p>
						<?php endif; ?>
						<h2><?php echo esc_html( $float_reel_title ); ?></h2>
					</div>

				</article>
			</li>

			<?php $float_reel_i++; endwhile; wp_reset_postdata(); ?>

		</ul>

		<?php else : ?>
		<p class="container"><?php esc_html_e( 'No reels found.', 'float-reels' ); ?></p>
		<?php endif; ?>

	</section>

</main>

<?php get_footer(); ?>
