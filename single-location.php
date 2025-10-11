<?php
/**
 * Template for displaying single Location posts
 */

get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <?php while ( have_posts() ) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <header class="entry-header">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <div class="location-featured-image">
                            <?php the_post_thumbnail('large'); ?>
                        </div>
                    <?php endif; ?>
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                </header>

                <?php
                $ref_id = get_post_meta(get_the_ID(), '_pb_ref_id', true);
                if ($ref_id) :
                ?>
                    <div class="location-reference-id"><strong>Reference ID:</strong> <?php echo esc_html($ref_id); ?></div>
                <?php endif; ?>

                <div class="entry-content">
                    <?php the_content(); ?>
                </div>

                <aside class="location-meta">
                    <h2>Location Details</h2>
                    <ul>
                        <?php
                        $address = get_post_meta( get_the_ID(), '_pb_address', true );
                        $latitude = get_post_meta( get_the_ID(), '_pb_latitude', true );
                        $longitude = get_post_meta( get_the_ID(), '_pb_longitude', true );
                        $website = get_post_meta( get_the_ID(), '_pb_website', true );
                        ?>
                        <?php if ( $address ) : ?>
                            <li><strong>Address:</strong> <?php echo esc_html( $address ); ?></li>
                        <?php endif; ?>
                        <?php if ( $latitude && $longitude ) : ?>
                            <li><strong>Coordinates:</strong> <?php echo esc_html( $latitude ); ?>, <?php echo esc_html( $longitude ); ?></li>
                        <?php endif; ?>
                        <?php if ( $website ) : ?>
                            <li><strong>Website:</strong> <a href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $website ); ?></a></li>
                        <?php endif; ?>
                    </ul>
                    <?php
                    // Social links
                    $socials = [
                        'facebook' => get_post_meta( get_the_ID(), '_pb_social_facebook', true ),
                        'x' => get_post_meta( get_the_ID(), '_pb_social_x', true ),
                        'tiktok' => get_post_meta( get_the_ID(), '_pb_social_tiktok', true ),
                        'youtube' => get_post_meta( get_the_ID(), '_pb_social_youtube', true ),
                    ];
                    $has_social = array_filter($socials);
                    ?>
                    <?php if ( $has_social ) : ?>
                        <div class="location-social-links">
                            <strong>Social:</strong>
                            <ul>
                                <?php if ( $socials['facebook'] ) : ?>
                                    <li><a href="<?php echo esc_url( $socials['facebook'] ); ?>" target="_blank" rel="noopener">Facebook</a></li>
                                <?php endif; ?>
                                <?php if ( $socials['x'] ) : ?>
                                    <li><a href="<?php echo esc_url( $socials['x'] ); ?>" target="_blank" rel="noopener">X</a></li>
                                <?php endif; ?>
                                <?php if ( $socials['tiktok'] ) : ?>
                                    <li><a href="<?php echo esc_url( $socials['tiktok'] ); ?>" target="_blank" rel="noopener">TikTok</a></li>
                                <?php endif; ?>
                                <?php if ( $socials['youtube'] ) : ?>
                                    <li><a href="<?php echo esc_url( $socials['youtube'] ); ?>" target="_blank" rel="noopener">YouTube</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </aside>

                <?php
                // Gallery
                $gallery = get_post_meta( get_the_ID(), '_pb_image_gallery', true );
                if ( $gallery ) :
                    $gallery_ids = array_filter( array_map('trim', explode(',', $gallery)) );
                    if ( $gallery_ids ) :
                ?>
                    <section class="location-gallery">
                        <h2>Gallery</h2>
                        <div class="gallery-thumbnails">
                            <?php foreach ( $gallery_ids as $img_id ) : ?>
                                <a href="<?php echo esc_url( wp_get_attachment_url( $img_id ) ); ?>" target="_blank">
                                    <?php echo wp_get_attachment_image( $img_id, 'thumbnail' ); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php
                    endif;
                endif;
                ?>

                <?php
                // Categories (taxonomy: pb_category)
                $categories = get_the_terms( get_the_ID(), 'pb_category' );
                if ( $categories && ! is_wp_error( $categories ) ) :
                ?>
                    <section class="location-categories">
                        <strong>Categories:</strong>
                        <ul>
                            <?php foreach ( $categories as $cat ) : ?>
                                <li>
                                    <a href="<?php echo esc_url( get_term_link( $cat ) ); ?>">
                                        <?php echo esc_html( $cat->name ); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>

                <section class="location-related">
                    <?php
                    // Related People
                    $related_people = get_post_meta( get_the_ID(), '_pb_related_people', true );
                    if ( !empty($related_people) ) {
                        // Can be array or comma-separated
                        if ( !is_array($related_people) ) {
                            $related_people = array_filter( array_map('intval', explode(',', $related_people)) );
                        }
                        if ( $related_people ) :
                    ?>
                        <div class="related-people">
                            <h2>Related People</h2>
                            <ul>
                                <?php foreach ( $related_people as $person_id ) : ?>
                                    <?php if ( get_post_status($person_id) ) : ?>
                                        <li>
                                            <a href="<?php echo esc_url( get_permalink($person_id) ); ?>">
                                                <?php echo esc_html( get_the_title($person_id) ); ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php
                        endif;
                    }
                    // Related Businesses
                    $related_businesses = get_post_meta( get_the_ID(), '_pb_related_businesses', true );
                    if ( !empty($related_businesses) ) {
                        if ( !is_array($related_businesses) ) {
                            $related_businesses = array_filter( array_map('intval', explode(',', $related_businesses)) );
                        }
                        if ( $related_businesses ) :
                    ?>
                        <div class="related-businesses">
                            <h2>Related Businesses</h2>
                            <ul>
                                <?php foreach ( $related_businesses as $biz_id ) : ?>
                                    <?php if ( get_post_status($biz_id) ) : ?>
                                        <li>
                                            <a href="<?php echo esc_url( get_permalink($biz_id) ); ?>">
                                                <?php echo esc_html( get_the_title($biz_id) ); ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php
                        endif;
                    }
                    // Related Events
                    $related_events = get_post_meta( get_the_ID(), '_pb_related_events', true );
                    if ( !empty($related_events) ) {
                        if ( !is_array($related_events) ) {
                            $related_events = array_filter( array_map('intval', explode(',', $related_events)) );
                        }
                        if ( $related_events ) :
                    ?>
                        <div class="related-events">
                            <h2>Related Events</h2>
                            <ul>
                                <?php foreach ( $related_events as $event_id ) : ?>
                                    <?php if ( get_post_status($event_id) ) : ?>
                                        <li>
                                            <a href="<?php echo esc_url( get_permalink($event_id) ); ?>">
                                                <?php echo esc_html( get_the_title($event_id) ); ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php
                        endif;
                    }
                    ?>
                </section>

                <?php
                // Featured Videos
                $featured_videos = get_post_meta( get_the_ID(), '_pb_featured_videos', true );
                if ( !empty($featured_videos) && is_array($featured_videos) ) :
                    $featured_videos = array_slice($featured_videos, 0, 3);
                ?>
                    <section class="featured-videos">
                        <h2>Featured Videos</h2>
                        <?php foreach ( $featured_videos as $video_id ) : ?>
                            <?php
                            $video_post = get_post($video_id);
                            if ( $video_post && get_post_status($video_post) ) :
                                $video_title = get_the_title($video_post);
                                $video_link = get_permalink($video_post);
                                $featured_video_meta = get_post_meta( $video_id, '_pb_featured_video', true );
                            ?>
                                <div class="featured-video-item">
                                    <h3><a href="<?php echo esc_url($video_link); ?>"><?php echo esc_html($video_title); ?></a></h3>
                                    <?php if ( !empty($featured_video_meta) && is_array($featured_video_meta) ) : ?>
                                        <?php
                                        $type = isset($featured_video_meta['type']) ? $featured_video_meta['type'] : '';
                                        $value = isset($featured_video_meta['value']) ? $featured_video_meta['value'] : '';
                                        if ( $type === 'url' && $value ) :
                                            echo wp_oembed_get( esc_url($value) );
                                        elseif ( $type === 'file' && $value ) :
                                            $video_url = wp_get_attachment_url( intval($value) );
                                            if ( $video_url ) :
                                        ?>
                                            <video controls>
                                                <source src="<?php echo esc_url($video_url); ?>" type="video/mp4">
                                                Your browser does not support the video tag.
                                            </video>
                                        <?php
                                            endif;
                                        endif;
                                        ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>
            </article>
        <?php endwhile; ?>
    </main>
</div>

<?php get_footer(); ?>