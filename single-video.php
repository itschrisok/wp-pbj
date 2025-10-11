<?php
get_header();

if ( have_posts() ) :
    while ( have_posts() ) : the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="entry-header">
                <?php
                $featured_video = get_post_meta( get_the_ID(), '_pb_featured_video', true );
                $video_rendered = false;
                if ( is_array( $featured_video ) && ! empty( $featured_video['type'] ) ) {
                    echo '<h2>Watch Video</h2>';
                    if ( $featured_video['type'] === 'url' && ! empty( $featured_video['url'] ) ) {
                        // Try to embed via wp_oembed_get()
                        $embed_code = wp_oembed_get( $featured_video['url'] );
                        if ( $embed_code ) {
                            echo $embed_code;
                        } else {
                            // If no embed code, output video tag
                            echo '<video controls>';
                            echo '<source src="' . esc_url( $featured_video['url'] ) . '" type="video/mp4">';
                            echo 'Your browser does not support the video tag.';
                            echo '</video>';
                        }
                        $video_rendered = true;
                    } elseif ( $featured_video['type'] === 'file' && ! empty( $featured_video['file_id'] ) ) {
                        $attachment_url = wp_get_attachment_url( $featured_video['file_id'] );
                        if ( $attachment_url ) {
                            echo '<video controls>';
                            echo '<source src="' . esc_url( $attachment_url ) . '" type="video/mp4">';
                            echo 'Your browser does not support the video tag.';
                            echo '</video>';
                            $video_rendered = true;
                        }
                    }
                } elseif ( ! empty( $featured_video ) && is_string( $featured_video ) ) {
                    // Legacy: treat as direct URL
                    echo '<h2>Watch Video</h2>';
                    $embed_code = wp_oembed_get( $featured_video );
                    if ( $embed_code ) {
                        echo $embed_code;
                    } else {
                        echo '<video controls>';
                        echo '<source src="' . esc_url( $featured_video ) . '" type="video/mp4">';
                        echo 'Your browser does not support the video tag.';
                        echo '</video>';
                    }
                    $video_rendered = true;
                }
                if ( ! $video_rendered ) {
                    if ( has_post_thumbnail() ) {
                        the_post_thumbnail( 'large' );
                    } else {
                        // Placeholder for video embed later
                        echo '<div class="video-placeholder">[Video embed goes here]</div>';
                    }
                }
                the_title( '<h1 class="entry-title">', '</h1>' );
                ?>
            </header>

            <?php
            $ref_id = get_post_meta(get_the_ID(), '_pb_ref_id', true);
            if ($ref_id) :
            ?>
                <div class="video-reference-id"><strong>Reference ID:</strong> <?php echo esc_html($ref_id); ?></div>
            <?php endif; ?>

            <div class="entry-content">
                <?php the_content(); ?>
            </div>

            <footer class="entry-footer">
                <?php
                // CTA Link
                $cta_link = get_post_meta( get_the_ID(), '_pb_cta_link', true );
                if ( $cta_link ) {
                    echo '<p class="cta-link"><a href="' . esc_url( $cta_link ) . '" class="button" target="_blank" rel="noopener noreferrer">Call to Action</a></p>';
                }

                // Helper function to display related posts
                function pb_display_related_items( $meta_key, $title ) {
                    $related_items = get_post_meta( get_the_ID(), $meta_key, true );
                    if ( ! empty( $related_items ) && is_array( $related_items ) ) {
                        echo '<section class="related-' . esc_attr( $meta_key ) . '">';
                        echo '<h2>' . esc_html( $title ) . '</h2>';
                        echo '<ul>';
                        foreach ( $related_items as $item_id ) {
                            $item_title = get_the_title( $item_id );
                            $item_link = get_permalink( $item_id );
                            if ( $item_title && $item_link ) {
                                echo '<li><a href="' . esc_url( $item_link ) . '">' . esc_html( $item_title ) . '</a></li>';
                            }
                        }
                        echo '</ul>';
                        echo '</section>';
                    }
                }

                // Related People
                pb_display_related_items( '_pb_related_people', 'Related People' );

                // Related Businesses
                pb_display_related_items( '_pb_related_businesses', 'Related Businesses' );

                // Related Events
                pb_display_related_items( '_pb_related_events', 'Related Events' );

                // Related Locations
                pb_display_related_items( '_pb_related_locations', 'Related Locations' );
                ?>
            </footer>
        </article>
    <?php endwhile;
endif;

get_footer();
?>
