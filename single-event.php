<?php
/**
 * The template for displaying single Event posts
 *
 * @package ProjectBaldwin
 */

get_header();
?>

<main id="primary" class="site-main">
  <?php
  if ( have_posts() ) :
    while ( have_posts() ) : the_post();
      // Meta fields
      $address = get_post_meta( get_the_ID(), '_pb_address', true );
      $hours = get_post_meta( get_the_ID(), '_pb_hours', true );
      $phone = get_post_meta( get_the_ID(), '_pb_phone', true );
      $website = get_post_meta( get_the_ID(), '_pb_website', true );
      $ticket_link = get_post_meta( get_the_ID(), '_pb_ticket_link', true );
      $ticket_enabled = !empty($ticket_link);
      $social_facebook = get_post_meta( get_the_ID(), '_pb_social_facebook', true );
      $social_x = get_post_meta( get_the_ID(), '_pb_social_x', true );
      $social_tiktok = get_post_meta( get_the_ID(), '_pb_social_tiktok', true );
      $social_youtube = get_post_meta( get_the_ID(), '_pb_social_youtube', true );
      $gallery = get_post_meta( get_the_ID(), '_pb_image_gallery', true );

      $related_people = get_post_meta( get_the_ID(), '_pb_related_people', true );
      $related_businesses = get_post_meta( get_the_ID(), '_pb_related_businesses', true );
      $related_locations = get_post_meta( get_the_ID(), '_pb_related_locations', true );
  ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
      <header class="entry-header">
        <?php if ( has_post_thumbnail() ) : ?>
          <div class="event-featured-image">
            <?php the_post_thumbnail( 'large' ); ?>
          </div>
        <?php endif; ?>
        <h1 class="entry-title"><?php the_title(); ?></h1>
      </header>

      <?php
      $ref_id = get_post_meta(get_the_ID(), '_pb_ref_id', true);
      if ($ref_id) :
      ?>
          <div class="event-reference-id"><strong>Reference ID:</strong> <?php echo esc_html($ref_id); ?></div>
      <?php endif; ?>

      <?php if ( get_post_meta( get_the_ID(), '_pb_in_voting', true ) ) : ?>
        <div class="pb-vote-callout">🔔 <?php esc_html_e( 'This event is active in a voting round.', 'projectbaldwin' ); ?></div>
      <?php endif; ?>

      <div class="event-meta">
        <?php if ( $address ) : ?>
          <div class="event-meta-item"><strong>Address:</strong> <?php echo esc_html( $address ); ?></div>
        <?php endif; ?>
        <?php if ( $hours ) : ?>
          <div class="event-meta-item"><strong>Hours:</strong> <?php echo esc_html( $hours ); ?></div>
        <?php endif; ?>
        <?php if ( $phone ) : ?>
          <div class="event-meta-item"><strong>Phone:</strong> <?php echo esc_html( $phone ); ?></div>
        <?php endif; ?>
        <?php if ( $website ) : ?>
          <div class="event-meta-item"><strong>Website:</strong>
            <a href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $website ); ?></a>
          </div>
        <?php endif; ?>
        <?php if ( $ticket_enabled ) : ?>
          <div class="event-meta-item"><strong>Tickets:</strong>
            <a href="<?php echo esc_url( $ticket_link ); ?>" target="_blank" rel="noopener">Buy Tickets</a>
          </div>
        <?php endif; ?>
        <?php if ( $social_facebook || $social_x || $social_tiktok || $social_youtube ) : ?>
          <div class="event-meta-item event-social-links">
            <strong>Social:</strong>
            <?php if ( $social_facebook ) : ?>
              <a href="<?php echo esc_url( $social_facebook ); ?>" target="_blank" rel="noopener">Facebook</a>
            <?php endif; ?>
            <?php if ( $social_x ) : ?>
              <a href="<?php echo esc_url( $social_x ); ?>" target="_blank" rel="noopener">X</a>
            <?php endif; ?>
            <?php if ( $social_tiktok ) : ?>
              <a href="<?php echo esc_url( $social_tiktok ); ?>" target="_blank" rel="noopener">TikTok</a>
            <?php endif; ?>
            <?php if ( $social_youtube ) : ?>
              <a href="<?php echo esc_url( $social_youtube ); ?>" target="_blank" rel="noopener">YouTube</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <?php
        // Gallery
        if ( $gallery ) :
          $ids = array_filter( array_map( 'trim', explode( ',', $gallery ) ) );
          if ( !empty($ids) ) :
        ?>
          <div class="event-meta-item event-gallery">
            <strong>Gallery:</strong>
            <div class="event-gallery-thumbs">
              <?php foreach ( $ids as $img_id ) : ?>
                <a href="<?php echo esc_url( wp_get_attachment_url( $img_id ) ); ?>" target="_blank">
                  <?php echo wp_get_attachment_image( $img_id, 'thumbnail' ); ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php
          endif;
        endif;
        ?>
      </div>

      <div class="entry-content">
        <?php the_content(); ?>
      </div>

      <footer class="entry-footer">
        <?php
        // Categories (taxonomy: pb_category)
        $terms = get_the_terms( get_the_ID(), 'pb_category' );
        if ( $terms && ! is_wp_error( $terms ) ) :
        ?>
          <div class="event-categories">
            <strong>Categories:</strong>
            <?php
            $cats = array();
            foreach ( $terms as $term ) {
              $cats[] = '<a href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a>';
            }
            echo implode( ', ', $cats );
            ?>
          </div>
        <?php endif; ?>

        <?php
        // Related People
        if ( !empty($related_people) && is_array($related_people) ) :
        ?>
          <div class="event-related-people">
            <strong>Related People:</strong>
            <ul>
              <?php
              foreach ( $related_people as $person_id ) {
                $title = get_the_title( $person_id );
                $link = get_permalink( $person_id );
                if ( $title && $link ) {
                  echo '<li><a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a></li>';
                }
              }
              ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php
        // Related Businesses
        if ( !empty($related_businesses) && is_array($related_businesses) ) :
        ?>
          <div class="event-related-businesses">
            <strong>Related Businesses:</strong>
            <ul>
              <?php
              foreach ( $related_businesses as $biz_id ) {
                $title = get_the_title( $biz_id );
                $link = get_permalink( $biz_id );
                if ( $title && $link ) {
                  echo '<li><a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a></li>';
                }
              }
              ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php
        // Related Locations
        if ( !empty($related_locations) && is_array($related_locations) ) :
        ?>
          <div class="event-related-locations">
            <strong>Related Locations:</strong>
            <ul>
              <?php
              foreach ( $related_locations as $loc_id ) {
                $title = get_the_title( $loc_id );
                $link = get_permalink( $loc_id );
                if ( $title && $link ) {
                  echo '<li><a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a></li>';
                }
              }
              ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php
        // Featured Videos
        $featured_videos = get_post_meta( get_the_ID(), '_pb_featured_videos', true );
        if ( !empty($featured_videos) && is_array($featured_videos) ) :
          $featured_videos = array_slice($featured_videos, 0, 3);
        ?>
          <div class="event-featured-videos">
            <strong>Featured Videos</strong>
            <?php foreach ( $featured_videos as $video_id ) :
              $video_title = get_the_title( $video_id );
              $video_link = get_permalink( $video_id );
              $video_meta = get_post_meta( $video_id, '_pb_featured_video', true );
              if ( $video_title && $video_link ) :
            ?>
              <div class="featured-video-item">
                <h4><a href="<?php echo esc_url( $video_link ); ?>"><?php echo esc_html( $video_title ); ?></a></h4>
                <?php
                if ( !empty($video_meta) && is_array($video_meta) ) {
                  if ( isset($video_meta['type']) ) {
                    if ( $video_meta['type'] === 'url' && !empty($video_meta['url']) ) {
                      echo wp_oembed_get( esc_url( $video_meta['url'] ) );
                    } elseif ( $video_meta['type'] === 'file' && !empty($video_meta['file']) ) {
                      $video_url = wp_get_attachment_url( intval($video_meta['file']) );
                      if ( $video_url ) {
                        ?>
                        <video controls preload="metadata" width="100%">
                          <source src="<?php echo esc_url( $video_url ); ?>" type="video/mp4" />
                          Your browser does not support the video tag.
                        </video>
                        <?php
                      }
                    }
                  }
                }
                ?>
              </div>
            <?php endif; endforeach; ?>
          </div>
        <?php endif; ?>
      </footer>
    </article>
  <?php
    endwhile;
  endif;
  ?>
</main>

<?php
get_footer();
?>
