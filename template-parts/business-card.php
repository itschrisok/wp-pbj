<?php
function pb_render_business_card($post_id) {
    if (get_post_type($post_id) !== 'business') return;

    $title   = get_the_title($post_id);
    $link    = get_permalink($post_id);
    $excerpt = get_the_excerpt($post_id);

    // Meta
    $phone   = get_post_meta($post_id, '_pb_phone', true);
    $address = get_post_meta($post_id, '_pb_address', true);
    $website = get_post_meta($post_id, '_pb_website', true);

    // Thumbnail (fallback)
    $thumb = has_post_thumbnail($post_id)
        ? get_the_post_thumbnail($post_id, 'medium', ['class' => 'pb-card-thumb'])
        : '<div class="pb-card-thumb placeholder"></div>';
    $in_voting = (bool) get_post_meta($post_id, '_pb_in_voting', true);
    ?>

    <div class="pb-card business-card">
        <a href="<?php echo esc_url($link); ?>" class="pb-card-link">
            <?php echo $thumb; ?>
            <h3 class="pb-card-title"><?php echo esc_html($title); ?></h3>
            <p class="pb-card-excerpt"><?php echo esc_html($excerpt); ?></p>
        </a>
        <?php if ($in_voting) : ?>
            <span class="pb-card-vote-flag">🔔 <?php esc_html_e('Voting Now', 'projectbaldwin'); ?></span>
        <?php endif; ?>
        <?php if ($address): ?>
            <p class="pb-card-meta"><strong>Address:</strong> <?php echo esc_html($address); ?></p>
        <?php endif; ?>
        <?php if ($phone): ?>
            <p class="pb-card-meta"><strong>Phone:</strong> <?php echo esc_html($phone); ?></p>
        <?php endif; ?>
        <?php if ($website): ?>
            <a href="<?php echo esc_url($website); ?>" target="_blank" class="pb-card-btn">Visit Website</a>
        <?php endif; ?>
    </div>
    <?php
}
