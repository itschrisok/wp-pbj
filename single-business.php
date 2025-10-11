<?php
/**
 * Template for displaying single Business posts
 */
get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <?php
        if ( have_posts() ) :
            while ( have_posts() ) : the_post();
                // Gather meta fields
                $address = get_post_meta( get_the_ID(), '_pb_address', true );
                $latitude = get_post_meta( get_the_ID(), '_pb_latitude', true );
                $longitude = get_post_meta( get_the_ID(), '_pb_longitude', true );
                $phone = get_post_meta( get_the_ID(), '_pb_phone', true );
                $hours = get_post_meta( get_the_ID(), '_pb_hours', true );
                $website = get_post_meta( get_the_ID(), '_pb_website', true );
                // Social links: check individual meta fields
                $social_facebook = get_post_meta( get_the_ID(), '_pb_social_facebook', true );
                $social_x = get_post_meta( get_the_ID(), '_pb_social_x', true );
                $social_tiktok = get_post_meta( get_the_ID(), '_pb_social_tiktok', true );
                $social_youtube = get_post_meta( get_the_ID(), '_pb_social_youtube', true );
                // Gallery: get comma-separated string, explode to array
                $gallery_raw = get_post_meta( get_the_ID(), '_pb_image_gallery', true );
                $gallery = array_filter( array_map( 'trim', explode( ',', $gallery_raw ) ) );
                $related_people = get_post_meta( get_the_ID(), '_pb_related_people', true );
                $related_events = get_post_meta( get_the_ID(), '_pb_related_events', true );
                $categories = get_the_terms( get_the_ID(), 'pb_category' );
        ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <header class="entry-header">
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                    <?php if (get_post_meta(get_the_ID(), '_pb_in_voting', true)) : ?>
                        <div class="pb-vote-callout">🔔 <?php esc_html_e('This business is active in a voting round.', 'projectbaldwin'); ?></div>
                    <?php endif; ?>
                    <?php if ( has_post_thumbnail() ) : ?>
                        <div class="business-featured-image">
                            <?php the_post_thumbnail( 'large' ); ?>
                        </div>
                    <?php endif; ?>
                </header>

                <?php
                $ref_id = get_post_meta(get_the_ID(), '_pb_ref_id', true);
                if ($ref_id) :
                ?>
                    <div class="business-reference-id"><strong>Reference ID:</strong> <?php echo esc_html($ref_id); ?></div>
                <?php endif; ?>

                <div class="business-card-widget">
                    <h2 class="business-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <?php if ( has_post_thumbnail() ) : ?>
                        <div class="business-card-image">
                            <a href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'thumbnail' ); ?></a>
                        </div>
                    <?php endif; ?>
                    <div class="business-card-meta">
                        <?php if ( $address ) : ?>
                            <div class="business-card-address"><?php echo esc_html( $address ); ?></div>
                        <?php endif; ?>
                        <?php if ( $phone ) : ?>
                            <div class="business-card-phone"><?php echo esc_html( $phone ); ?></div>
                        <?php endif; ?>
                        <?php if ( $website ) : ?>
                            <div class="business-card-website"><a href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $website ); ?></a></div>
                        <?php endif; ?>
                    </div>
                    <?php if ( $categories && !is_wp_error( $categories ) ) : ?>
                        <div class="business-card-categories">
                            <?php
                            $count = 0;
                            foreach ( $categories as $cat ) :
                                if ( $count >= 2 ) break;
                                ?>
                                <span class="business-card-category-tag"><?php echo esc_html( $cat->name ); ?></span>
                                <?php
                                $count++;
                            endforeach;
                            ?>
                        </div>
                    <?php endif; ?>
                </div>

                <section class="business-meta">
                    <?php if ( $address ) : ?>
                        <div class="business-address"><strong>Address:</strong> <?php echo esc_html( $address ); ?></div>
                    <?php endif; ?>
                    <?php if ( $latitude && $longitude ) : ?>
                        <div class="business-latlng">
                            <strong>Coordinates:</strong> <?php echo esc_html( $latitude ); ?>, <?php echo esc_html( $longitude ); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ( $phone ) : ?>
                        <div class="business-phone"><strong>Phone:</strong> <?php echo esc_html( $phone ); ?></div>
                    <?php endif; ?>
                    <?php if ( $hours ) : ?>
                        <div class="business-hours"><strong>Hours:</strong> <?php echo esc_html( $hours ); ?></div>
                    <?php endif; ?>
                    <?php if ( $website ) : ?>
                        <div class="business-website"><strong>Website:</strong> <a href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $website ); ?></a></div>
                    <?php endif; ?>
                    <?php
                    // Social links: check if any present
                    if ( $social_facebook || $social_x || $social_tiktok || $social_youtube ) :
                    ?>
                        <div class="business-social">
                            <strong>Social:</strong>
                            <ul>
                                <?php if ( $social_facebook ) : ?>
                                    <li>
                                        <a href="<?php echo esc_url( $social_facebook ); ?>" target="_blank" rel="noopener">Facebook</a>
                                    </li>
                                <?php endif; ?>
                                <?php if ( $social_x ) : ?>
                                    <li>
                                        <a href="<?php echo esc_url( $social_x ); ?>" target="_blank" rel="noopener">X</a>
                                    </li>
                                <?php endif; ?>
                                <?php if ( $social_tiktok ) : ?>
                                    <li>
                                        <a href="<?php echo esc_url( $social_tiktok ); ?>" target="_blank" rel="noopener">TikTok</a>
                                    </li>
                                <?php endif; ?>
                                <?php if ( $social_youtube ) : ?>
                                    <li>
                                        <a href="<?php echo esc_url( $social_youtube ); ?>" target="_blank" rel="noopener">YouTube</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="business-taxonomies">
                    <?php
                    if ( $categories && !is_wp_error( $categories ) ) :
                    ?>
                        <div class="business-categories">
                            <strong>Categories:</strong>
                            <?php foreach ( $categories as $cat ) : ?>
                                <span class="business-category"><?php echo esc_html( $cat->name ); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <?php if ( !empty( $gallery ) && is_array( $gallery ) ) : ?>
                    <section class="business-gallery">
                        <h2>Gallery</h2>
                        <div class="gallery-grid" style="display: flex; flex-wrap: wrap; gap: 10px;">
                            <?php foreach ( $gallery as $img_id ) :
                                $img = wp_get_attachment_image( $img_id, 'medium', false, [ 'style' => 'max-width:150px;height:auto;' ] );
                                if ( $img ) :
                                    echo '<div class="gallery-item">' . $img . '</div>';
                                endif;
                            endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ( !empty( $related_people ) && is_array( $related_people ) ) : ?>
                    <section class="business-related-people">
                        <h2>Related People</h2>
                        <ul>
                            <?php foreach ( $related_people as $person_id ) :
                                $person_post = get_post( $person_id );
                                if ( $person_post && $person_post->post_status === 'publish' ) : ?>
                                    <li>
                                        <a href="<?php echo get_permalink( $person_id ); ?>">
                                            <?php echo esc_html( get_the_title( $person_id ) ); ?>
                                        </a>
                                    </li>
                                <?php endif;
                            endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>

                <?php if ( !empty( $related_events ) && is_array( $related_events ) ) : ?>
                    <section class="business-related-events">
                        <h2>Related Events</h2>
                        <ul>
                            <?php foreach ( $related_events as $event_id ) :
                                $event_post = get_post( $event_id );
                                if ( $event_post && $event_post->post_status === 'publish' ) : ?>
                                    <li>
                                        <a href="<?php echo get_permalink( $event_id ); ?>">
                                            <?php echo esc_html( get_the_title( $event_id ) ); ?>
                                        </a>
                                    </li>
                                <?php endif;
                            endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>

                <?php
                // Featured Videos Section
                $featured_videos = get_post_meta( get_the_ID(), '_pb_featured_videos', true );
                if ( !empty( $featured_videos ) && is_array( $featured_videos ) ) :
                    // Limit to 3 videos
                    $video_ids = array_slice( $featured_videos, 0, 3 );
                    ?>
                    <section class="business-featured-videos">
                        <h2>Featured Videos</h2>
                        <div class="featured-videos-list">
                        <?php foreach ( $video_ids as $vid ) :
                            $video_post = get_post( $vid );
                            if ( $video_post && $video_post->post_status === 'publish' ) :
                                $video_title = get_the_title( $vid );
                                $video_link = get_permalink( $vid );
                                ?>
                                <div class="featured-video-item" style="margin-bottom: 2em;">
                                    <h3><a href="<?php echo esc_url( $video_link ); ?>"><?php echo esc_html( $video_title ); ?></a></h3>
                                    <?php
                                    $video_meta = get_post_meta( $vid, '_pb_featured_video', true );
                                    if ( !empty( $video_meta ) && is_array( $video_meta ) ) {
                                        $type = isset( $video_meta['type'] ) ? $video_meta['type'] : '';
                                        if ( $type === 'url' && !empty( $video_meta['url'] ) ) {
                                            // oEmbed
                                            $embed = wp_oembed_get( $video_meta['url'] );
                                            if ( $embed ) {
                                                echo '<div class="featured-video-embed">' . $embed . '</div>';
                                            }
                                        } elseif ( $type === 'file' && !empty( $video_meta['file'] ) ) {
                                            // File attachment
                                            $attachment_url = wp_get_attachment_url( $video_meta['file'] );
                                            if ( $attachment_url ) {
                                                ?>
                                                <div class="featured-video-file">
                                                    <video controls style="max-width:100%;height:auto;">
                                                        <source src="<?php echo esc_url( $attachment_url ); ?>">
                                                        Your browser does not support the video tag.
                                                    </video>
                                                </div>
                                                <?php
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                            <?php endif;
                        endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="business-content entry-content">
                    <?php the_content(); ?>
                </section>
            </article>
        <?php
            endwhile;
        endif;
        ?>
    </main>
</div>

<?php get_footer(); ?>
