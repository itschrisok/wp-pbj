<?php
get_header();

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();
		?>

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<?php
				if ( has_post_thumbnail() ) {
					the_post_thumbnail( 'large' );
				}
				the_title( '<h1 class="entry-title">', '</h1>' );
				?>
			</header>

			<?php
			$ref_id = get_post_meta(get_the_ID(), '_pb_ref_id', true);
			if ($ref_id) :
			?>
			    <div class="person-reference-id"><strong>Reference ID:</strong> <?php echo esc_html($ref_id); ?></div>
			<?php endif; ?>

			<?php if ( get_post_meta( get_the_ID(), '_pb_in_voting', true ) ) : ?>
				<div class="pb-vote-callout">🔔 <?php esc_html_e( 'This person is active in a voting round.', 'projectbaldwin' ); ?></div>
			<?php endif; ?>

			<div class="entry-content">
				<?php the_content(); ?>
			</div>

			<div class="person-meta">
				<?php
				// Website
				$website = get_post_meta( get_the_ID(), '_pb_website', true );
				if ( $website ) {
					echo '<p><strong>Website:</strong> <a href="' . esc_url( $website ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $website ) . '</a></p>';
				}

				// Social links
				$social_facebook = get_post_meta( get_the_ID(), '_pb_social_facebook', true );
				$social_x = get_post_meta( get_the_ID(), '_pb_social_x', true );
				$social_tiktok = get_post_meta( get_the_ID(), '_pb_social_tiktok', true );
				$social_youtube = get_post_meta( get_the_ID(), '_pb_social_youtube', true );

				$social_links = array(
					'Facebook' => $social_facebook,
					'X'        => $social_x,
					'TikTok'   => $social_tiktok,
					'YouTube'  => $social_youtube,
				);

				$has_social = false;
				foreach ( $social_links as $label => $url ) {
					if ( $url ) {
						$has_social = true;
						break;
					}
				}

				if ( $has_social ) {
					echo '<div class="person-social-links"><strong>Social Links:</strong><ul>';
					foreach ( $social_links as $label => $url ) {
						if ( $url ) {
							echo '<li><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ) . '</a></li>';
						}
					}
					echo '</ul></div>';
				}

				// Category taxonomy pb_category
				$categories = get_the_terms( get_the_ID(), 'pb_category' );
				if ( $categories && ! is_wp_error( $categories ) ) {
					$category_links = array();
					foreach ( $categories as $category ) {
						$category_links[] = '<a href="' . esc_url( get_term_link( $category ) ) . '">' . esc_html( $category->name ) . '</a>';
					}
					echo '<p><strong>Category:</strong> ' . implode( ', ', $category_links ) . '</p>';
				}

				// Related Businesses
				$related_businesses = get_post_meta( get_the_ID(), '_pb_related_businesses', true );
				if ( ! empty( $related_businesses ) && is_array( $related_businesses ) ) {
					echo '<div class="related-businesses"><h2>Related Businesses</h2><ul>';
					foreach ( $related_businesses as $business_id ) {
						$business_title = get_the_title( $business_id );
						$business_link = get_permalink( $business_id );
						if ( $business_title && $business_link ) {
							echo '<li><a href="' . esc_url( $business_link ) . '">' . esc_html( $business_title ) . '</a></li>';
						}
					}
					echo '</ul></div>';
				}

				// Related Events
				$related_events = get_post_meta( get_the_ID(), '_pb_related_events', true );
				if ( ! empty( $related_events ) && is_array( $related_events ) ) {
					echo '<div class="related-events"><h2>Related Events</h2><ul>';
					foreach ( $related_events as $event_id ) {
						$event_title = get_the_title( $event_id );
						$event_link = get_permalink( $event_id );
						if ( $event_title && $event_link ) {
							echo '<li><a href="' . esc_url( $event_link ) . '">' . esc_html( $event_title ) . '</a></li>';
						}
					}
					echo '</ul></div>';
				}

				// Related Locations
				$related_locations = get_post_meta( get_the_ID(), '_pb_related_locations', true );
				if ( ! empty( $related_locations ) && is_array( $related_locations ) ) {
					echo '<div class="related-locations"><h2>Related Locations</h2><ul>';
					foreach ( $related_locations as $location_id ) {
						$location_title = get_the_title( $location_id );
						$location_link = get_permalink( $location_id );
						if ( $location_title && $location_link ) {
							echo '<li><a href="' . esc_url( $location_link ) . '">' . esc_html( $location_title ) . '</a></li>';
						}
					}
					echo '</ul></div>';
				}

				// Featured Videos
				$featured_videos = get_post_meta( get_the_ID(), '_pb_featured_videos', true );
				if ( ! empty( $featured_videos ) && is_array( $featured_videos ) ) {
					// Limit to 3
					$featured_videos = array_slice( $featured_videos, 0, 3 );
					echo '<div class="featured-videos"><h2>Featured Videos</h2><ul>';
					foreach ( $featured_videos as $video_id ) {
						$video_title = get_the_title( $video_id );
						$video_link = get_permalink( $video_id );
						echo '<li>';
						if ( $video_title && $video_link ) {
							echo '<a href="' . esc_url( $video_link ) . '">' . esc_html( $video_title ) . '</a>';
						}
						// Try to get the featured video embed
						$embed = get_post_meta( $video_id, '_pb_featured_video', true );
						if ( $embed ) {
							echo '<div class="featured-video-embed">';
							// If it's a URL, try oEmbed
							if ( filter_var( $embed, FILTER_VALIDATE_URL ) ) {
								$oembed = wp_oembed_get( $embed );
								if ( $oembed ) {
									echo $oembed;
								} else {
									// fallback, just output the link
									echo '<a href="' . esc_url( $embed ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $embed ) . '</a>';
								}
							} elseif ( is_numeric( $embed ) ) {
								// Assume it's an attachment ID to a video file
								$video_url = wp_get_attachment_url( $embed );
								if ( $video_url ) {
									echo '<video controls width="480"><source src="' . esc_url( $video_url ) . '"></video>';
								}
							}
							echo '</div>';
						}
						echo '</li>';
					}
					echo '</ul></div>';
				}
				?>
			</div>
		</article>

		<?php
	endwhile;
endif;

get_footer();
?>
