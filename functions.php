<?php

// Enqueue stylesheet
function pb_enqueue_assets() {
    wp_enqueue_style('pb-style', get_stylesheet_uri());
    // Enqueue cards.css with version based on file modification time
    $cards_css_path = get_template_directory() . '/template-parts/css/cards.css';
    $cards_css_uri = get_template_directory_uri() . '/template-parts/css/cards.css';
    $cards_css_ver = file_exists($cards_css_path) ? filemtime($cards_css_path) : null;
    wp_enqueue_style('pb-cards', $cards_css_uri, array(), $cards_css_ver);
}
add_action('wp_enqueue_scripts', 'pb_enqueue_assets');

// Theme supports
function pb_theme_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
}
add_action('after_setup_theme', 'pb_theme_setup');

// Load modular functionality.
require_once get_template_directory() . '/inc/class-pb-voting-service.php';
require_once get_template_directory() . '/inc/class-pb-voting-sort.php';
PB_Voting_Service::init();
// ------------------------
// Project Baldwin Custom Post Types
// ------------------------
function pb_register_location_post_type() {
    register_post_type('location', [
        'labels' => [
            'name' => 'Locations',
            'singular_name' => 'Location',
            'add_new_item' => 'Add New Location',
            'edit_item' => 'Edit Location',
            'view_item' => 'View Location',
            'all_items' => 'All Locations',
        ],
        'public' => true,
        'publicly_queryable' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-location-alt',
        'has_archive' => true,
        'hierarchical' => true,
        'rewrite' => ['slug' => 'locations'],
        'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'excerpt'],
        'show_in_rest' => true,
    ]);
}
add_action('init', 'pb_register_location_post_type');

function pb_register_sub_post_types() {
    $post_types = [
        'business' => ['Businesses', 'Business', 'dashicons-store'],
        'person'   => ['People', 'Person', 'dashicons-admin-users'],
        'event'    => ['Events', 'Event', 'dashicons-calendar-alt'],
    ];

    foreach ($post_types as $slug => [$name, $singular, $icon]) {
        register_post_type($slug, [
            'labels' => [
                'name' => $name,
                'singular_name' => $singular,
                'add_new_item' => "Add New $singular",
                'edit_item' => "Edit $singular",
                'view_item' => "View $singular",
                'all_items' => "All $name",
            ],
            'public' => true,
            'publicly_queryable' => true,
            'show_in_menu' => true,
            'menu_icon' => $icon,
            'has_archive' => true,
            'hierarchical' => false,
            'rewrite' => ['slug' => $slug . 's'],
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'excerpt'],
            'show_in_rest' => true,
        ]);
    }
}
add_action('init', 'pb_register_sub_post_types');

// ------------------------
// Project Baldwin Video Post Type
// ------------------------
/**
 * Registers the 'video' custom post type.
 */
function pb_register_video_post_type() {
    register_post_type('video', [
        'labels' => [
            'name' => 'Videos',
            'singular_name' => 'Video',
            'add_new_item' => 'Add New Video',
            'edit_item' => 'Edit Video',
            'view_item' => 'View Video',
            'all_items' => 'All Videos',
        ],
        'public' => true,
        'publicly_queryable' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-video-alt3',
        'has_archive' => true,
        'hierarchical' => false,
        'rewrite' => ['slug' => 'videos'],
        'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'excerpt'],
        'show_in_rest' => true,
    ]);
}
add_action('init', 'pb_register_video_post_type');

// ------------------------
// Taxonomy: Category (Shared across multiple post types)
// ------------------------
function pb_register_category_taxonomy() {
    $post_types = ['location', 'business', 'person', 'event'];
    register_taxonomy('pb_category', $post_types, [
        'labels' => [
            'name' => 'Categories',
            'singular_name' => 'Category',
            'search_items' => 'Search Categories',
            'all_items' => 'All Categories',
            'edit_item' => 'Edit Category',
            'update_item' => 'Update Category',
            'add_new_item' => 'Add New Category',
            'new_item_name' => 'New Category Name',
            'menu_name' => 'Category',
        ],
        'hierarchical' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'public' => true,
        'rewrite' => ['slug' => 'category'],
    ]);
}
add_action('init', 'pb_register_category_taxonomy');

// ------------------------
// Project Baldwin Voting Round Post Type
// ------------------------
/**
 * Registers the 'voting_round' custom post type.
 */
function pb_register_voting_round_post_type() {
    register_post_type('voting_round', [
        'labels' => [
            'name' => 'Voting Rounds',
            'singular_name' => 'Voting Round',
            'add_new_item' => 'Add New Voting Round',
            'edit_item' => 'Edit Voting Round',
            'view_item' => 'View Voting Round',
            'all_items' => 'All Voting Rounds',
        ],
        'public' => true,
        'publicly_queryable' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-awards',
        'has_archive' => true,
        'hierarchical' => false,
        'rewrite' => ['slug' => 'voting-rounds'],
        'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'excerpt'],
        'show_in_rest' => true,
    ]);
}
add_action('init', 'pb_register_voting_round_post_type');
// ------------------------
// Disable Gutenberg for specific post types
// ------------------------
add_filter('use_block_editor_for_post_type', function ($enabled, $post_type) {
    if (in_array($post_type, ['business', 'person', 'event', 'video', 'location', 'voting_round'])) {
        return false;
    }
    return $enabled;
}, 10, 2);
// ------------------------
// Meta Boxes: Event Info
// ------------------------
function pb_add_event_meta_box() {
    add_meta_box(
        'pb_event_info',
        'Event Info',
        'pb_render_event_info_box',
        'event',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'pb_add_event_meta_box');

function pb_render_event_info_box($post) {
    // Address
    $address = get_post_meta($post->ID, '_pb_address', true);
    echo "<p><label for='pb_address'><strong>Address</strong></label><br/>";
    echo "<input type='text' name='pb_address' id='pb_address' value='" . esc_attr($address) . "' class='widefat' /></p>";

    // Latitude/Longitude (optional, can be entered manually)
    $lat = get_post_meta($post->ID, '_pb_latitude', true);
    $lng = get_post_meta($post->ID, '_pb_longitude', true);
    echo "<p><label for='pb_latitude'><strong>Latitude</strong></label><br/>";
    echo "<input type='text' name='pb_latitude' id='pb_latitude' value='" . esc_attr($lat) . "' class='widefat' /></p>";
    echo "<p><label for='pb_longitude'><strong>Longitude</strong></label><br/>";
    echo "<input type='text' name='pb_longitude' id='pb_longitude' value='" . esc_attr($lng) . "' class='widefat' /></p>";

    // Hours
    $hours = get_post_meta($post->ID, '_pb_hours', true);
    echo "<p><label for='pb_hours'><strong>Hours</strong></label><br/>";
    echo "<input type='text' name='pb_hours' id='pb_hours' value='" . esc_attr($hours) . "' class='widefat' /></p>";

    // Phone
    $phone = get_post_meta($post->ID, '_pb_phone', true);
    echo "<p><label for='pb_phone'><strong>Phone</strong></label><br/>";
    echo "<input type='text' name='pb_phone' id='pb_phone' value='" . esc_attr($phone) . "' class='widefat' /></p>";

    // Website
    $website = get_post_meta($post->ID, '_pb_website', true);
    echo "<p><label for='pb_website'><strong>Website</strong></label><br/>";
    echo "<input type='url' name='pb_website' id='pb_website' value='" . esc_attr($website) . "' class='widefat' /></p>";

    // Social Links
    $social_fields = ['facebook' => 'Facebook', 'x' => 'X (formerly Twitter)', 'tiktok' => 'TikTok', 'youtube' => 'YouTube'];
    foreach ($social_fields as $skey => $slabel) {
        $val = get_post_meta($post->ID, '_pb_social_' . $skey, true);
        $enabled = !empty($val);
        echo "<p><label><input type='checkbox' name='pb_social_enable_$skey' id='pb_social_enable_$skey' " . checked($enabled, true, false) . "> Enable $slabel</label></p>";
        echo "<div id='pb_social_input_$skey' style='margin-bottom:10px;" . ($enabled ? "" : "display:none;") . "'>";
        echo "<label for='pb_social_$skey'><strong>$slabel URL</strong></label><br/>";
        echo "<input type='url' name='pb_social_$skey' id='pb_social_$skey' value='" . esc_attr($val) . "' class='widefat' /></div>";
    }

    // Ticket Link
    $ticket = get_post_meta($post->ID, '_pb_ticket_link', true);
    $ticket_enabled = !empty($ticket);
    echo "<p><label><input type='checkbox' name='pb_ticket_enable' id='pb_ticket_enable' " . checked($ticket_enabled, true, false) . "> Enable Ticket Link</label></p>";
    echo "<div id='pb_ticket_input' style='margin-bottom:10px;" . ($ticket_enabled ? "" : "display:none;") . "'>";
    echo "<label for='pb_ticket_link'><strong>Ticket URL</strong></label><br/>";
    echo "<input type='url' name='pb_ticket_link' id='pb_ticket_link' value='" . esc_attr($ticket) . "' class='widefat' /></div>";

    ?>
    <script>
    jQuery(document).ready(function($){
        ['facebook','x','tiktok','youtube'].forEach(function(key){
            $('#pb_social_enable_' + key).on('change', function(){
                $('#pb_social_input_' + key).toggle(this.checked);
            });
        });
        $('#pb_ticket_enable').on('change', function(){
            $('#pb_ticket_input').toggle(this.checked);
        });
    });
    </script>
    <?php

    // Category taxonomy multi-select
    $taxonomy = 'pb_category';
    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
    $selected_terms = wp_get_post_terms($post->ID, $taxonomy, ['fields' => 'ids']);
    echo "<p><label for='pb_category'><strong>Category</strong></label><br/>";
    echo "<select name='pb_category[]' id='pb_category' multiple class='widefat searchable-multi' data-searchable='true'>";
    foreach ($terms as $term) {
        $selected = in_array($term->term_id, $selected_terms) ? 'selected' : '';
        echo "<option value='{$term->term_id}' $selected>{$term->name}</option>";
    }
    echo "</select></p>";

    // Related People
    $people = get_posts(['post_type' => 'person', 'numberposts' => -1]);
    $saved_people = get_post_meta($post->ID, '_pb_related_people', true) ?: [];
    echo "<p><label for='pb_related_people'><strong>Related People</strong></label><br/>";
    echo "<select name='pb_related_people[]' id='pb_related_people' multiple class='widefat searchable-multi' data-searchable='true'>";
    foreach ($people as $person) {
        $selected = in_array($person->ID, $saved_people) ? 'selected' : '';
        echo "<option value='{$person->ID}' $selected>{$person->post_title}</option>";
    }
    echo "</select></p>";

    // Related Businesses
    $businesses = get_posts(['post_type' => 'business', 'numberposts' => -1]);
    $saved_businesses = get_post_meta($post->ID, '_pb_related_businesses', true) ?: [];
    echo "<p><label for='pb_related_businesses'><strong>Related Businesses</strong></label><br/>";
    echo "<select name='pb_related_businesses[]' id='pb_related_businesses' multiple class='widefat searchable-multi' data-searchable='true'>";
    foreach ($businesses as $business) {
        $selected = in_array($business->ID, $saved_businesses) ? 'selected' : '';
        echo "<option value='{$business->ID}' $selected>{$business->post_title}</option>";
    }
    echo "</select></p>";

    // Featured Videos section
    // Get all videos, then filter to those related to this event
    $all_videos = get_posts([
        'post_type' => 'video',
        'numberposts' => -1,
    ]);
    $related_videos = [];
    foreach ($all_videos as $video) {
        $related_events = get_post_meta($video->ID, '_pb_related_events', true);
        if (!is_array($related_events)) {
            if (is_string($related_events) && strlen($related_events) > 0 && strpos($related_events, ',') !== false) {
                $related_events = array_map('intval', explode(',', $related_events));
            } elseif (is_numeric($related_events)) {
                $related_events = [(int)$related_events];
            } else {
                $related_events = [];
            }
        }
        if (in_array($post->ID, $related_events)) {
            $related_videos[] = $video;
        }
    }
    $featured_videos = get_post_meta($post->ID, '_pb_featured_videos', true);
    $featured_videos = is_array($featured_videos) ? $featured_videos : [];
    echo "<p><label for='pb_featured_videos'><strong>Featured Videos (up to 3)</strong></label><br/>";
    echo "<select name='pb_featured_videos[]' id='pb_featured_videos' multiple class='widefat searchable-multi' data-searchable='true' size='4'>";
    foreach ($related_videos as $video) {
        $selected = in_array($video->ID, $featured_videos) ? 'selected' : '';
        echo "<option value='{$video->ID}' $selected>{$video->post_title}</option>";
    }
    echo "</select></p>";

    // Gallery (multi-image)
    $gallery_ids = get_post_meta($post->ID, '_pb_image_gallery', true);
    $gallery_ids = is_array($gallery_ids) ? $gallery_ids : (is_string($gallery_ids) ? explode(',', $gallery_ids) : []);
    $gallery_ids = array_filter(array_map('intval', $gallery_ids));
    echo "<p><label><strong>Gallery Images</strong></label><br/>";
    echo "<button type='button' class='button' id='pb_upload_gallery_images'>Upload Gallery Images</button></p>";
    echo "<input type='hidden' name='pb_image_gallery' id='pb_image_gallery' value='" . esc_attr(implode(',', $gallery_ids)) . "' />";
    echo "<div id='pb_image_gallery_preview' style='margin-bottom: 10px;'>";
    foreach ($gallery_ids as $img_id) {
        $img_url = wp_get_attachment_image_url($img_id, 'thumbnail');
        if ($img_url) {
            echo "<span class='pb-gallery-thumb' data-id='{$img_id}' style='display:inline-block;position:relative;margin:0 10px 10px 0;'>";
            echo "<img src='" . esc_url($img_url) . "' style='width:80px;height:80px;object-fit:cover;border:1px solid #ccc;' />";
            echo "<span class='pb-remove-gallery-image' style='position:absolute;top:0;right:0;background:#fff;color:#d00;border-radius:50%;cursor:pointer;font-weight:bold;padding:2px 6px;'>×</span>";
            echo "</span>";
        }
    }
    echo "</div>";
    ?>
    <script>
    jQuery(document).ready(function($){
        var pb_gallery_frame;
        $('#pb_upload_gallery_images').on('click', function(e){
            e.preventDefault();
            if (pb_gallery_frame) {
                pb_gallery_frame.open();
                return;
            }
            pb_gallery_frame = wp.media({
                title: 'Select Gallery Images',
                button: { text: 'Add to Gallery' },
                multiple: true
            });
            pb_gallery_frame.on('select', function(){
                var selection = pb_gallery_frame.state().get('selection');
                var ids = $('#pb_image_gallery').val() ? $('#pb_image_gallery').val().split(',').filter(Boolean).map(Number) : [];
                selection.each(function(attachment){
                    var id = attachment.id;
                    if (ids.indexOf(id) === -1) {
                        ids.push(id);
                        var thumb = attachment.attributes.sizes && attachment.attributes.sizes.thumbnail ? attachment.attributes.sizes.thumbnail.url : attachment.attributes.url;
                        $('#pb_image_gallery_preview').append(
                            "<span class='pb-gallery-thumb' data-id='"+id+"' style='display:inline-block;position:relative;margin:0 10px 10px 0;'>" +
                            "<img src='"+thumb+"' style='width:80px;height:80px;object-fit:cover;border:1px solid #ccc;' />" +
                            "<span class='pb-remove-gallery-image' style='position:absolute;top:0;right:0;background:#fff;color:#d00;border-radius:50%;cursor:pointer;font-weight:bold;padding:2px 6px;'>×</span>" +
                            "</span>"
                        );
                    }
                });
                $('#pb_image_gallery').val(ids.join(','));
            });
            pb_gallery_frame.open();
        });
        // Remove image from gallery
        $('#pb_image_gallery_preview').on('click', '.pb-remove-gallery-image', function(e){
            e.preventDefault();
            var $thumb = $(this).closest('.pb-gallery-thumb');
            var id = $thumb.data('id');
            $thumb.remove();
            var ids = $('#pb_image_gallery').val() ? $('#pb_image_gallery').val().split(',').filter(Boolean).map(Number) : [];
            ids = ids.filter(function(val){ return val !== id; });
            $('#pb_image_gallery').val(ids.join(','));
        });
    });
    </script>
    <?php
}

function pb_save_event_meta($post_id) {
    // Save Address, Hours, Phone, Website
    $fields = ['address','hours','phone','website'];
    foreach ($fields as $field) {
        if (isset($_POST['pb_' . $field])) {
            $val = $field === 'website' ? esc_url_raw($_POST['pb_' . $field]) : sanitize_text_field($_POST['pb_' . $field]);
            update_post_meta($post_id, '_pb_' . $field, $val);
        }
    }
    // Save latitude and longitude
    if (isset($_POST['pb_latitude'])) {
        update_post_meta($post_id, '_pb_latitude', sanitize_text_field($_POST['pb_latitude']));
    }
    if (isset($_POST['pb_longitude'])) {
        update_post_meta($post_id, '_pb_longitude', sanitize_text_field($_POST['pb_longitude']));
    }
    // Ticket Link
    if (isset($_POST['pb_ticket_enable']) && !empty($_POST['pb_ticket_link'])) {
        update_post_meta($post_id, '_pb_ticket_link', esc_url_raw($_POST['pb_ticket_link']));
    } else {
        delete_post_meta($post_id, '_pb_ticket_link');
    }
    // Socials
    $social_fields = ['facebook','x','tiktok','youtube'];
    foreach ($social_fields as $field) {
        if (isset($_POST['pb_social_enable_' . $field]) && !empty($_POST['pb_social_' . $field])) {
            update_post_meta($post_id, '_pb_social_' . $field, esc_url_raw($_POST['pb_social_' . $field]));
        } else {
            delete_post_meta($post_id, '_pb_social_' . $field);
        }
    }
    // Category taxonomy
    if (isset($_POST['pb_category']) && is_array($_POST['pb_category'])) {
        $category_ids = array_map('intval', $_POST['pb_category']);
        wp_set_object_terms($post_id, $category_ids, 'pb_category');
    }
    // Related People
    if (isset($_POST['pb_related_people'])) {
        update_post_meta($post_id, '_pb_related_people', array_map('intval', $_POST['pb_related_people']));
    }
    // Related Businesses
    if (isset($_POST['pb_related_businesses'])) {
        update_post_meta($post_id, '_pb_related_businesses', array_map('intval', $_POST['pb_related_businesses']));
    }
    // Gallery
    if (isset($_POST['pb_image_gallery'])) {
        $ids = array_filter(array_map('intval', explode(',', $_POST['pb_image_gallery'])));
        update_post_meta($post_id, '_pb_image_gallery', implode(',', $ids));
    } else {
        delete_post_meta($post_id, '_pb_image_gallery');
    }
    // Save featured videos (up to 3)
    if (isset($_POST['pb_featured_videos']) && is_array($_POST['pb_featured_videos'])) {
        $videos = array_map('intval', $_POST['pb_featured_videos']);
        $videos = array_slice(array_unique($videos), 0, 3);
        update_post_meta($post_id, '_pb_featured_videos', $videos);
    } else {
        delete_post_meta($post_id, '_pb_featured_videos');
    }
}
add_action('save_post_event', 'pb_save_event_meta');

// ------------------------
// Taxonomy: Location Zone (Hierarchical like Categories)
// ------------------------
function pb_register_location_taxonomy() {
    register_taxonomy('location_zone', ['person', 'business', 'event', 'video'], [
        'labels' => [
            'name' => 'Locations',
            'singular_name' => 'Location',
            'add_new_item' => 'Add New Location',
            'edit_item' => 'Edit Location',
        ],
        'hierarchical' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'locations'],
    ]);
}
add_action('init', 'pb_register_location_taxonomy');

// ------------------------
// Auto-link location_zone taxonomy terms to matching Location posts
// ------------------------

/**
 * Fetches a location post matching the given taxonomy term slug.
 *
 * @param string $term_slug The slug of the taxonomy term.
 * @return WP_Post|null The matching location post or null if not found.
 */
function pb_get_location_post_by_tax_term($term_slug) {
    $args = [
        'post_type' => 'location',
        'name' => $term_slug,
        'post_status' => 'publish',
        'numberposts' => 1,
    ];
    $posts = get_posts($args);
    return $posts ? $posts[0] : null;
}

/**
 * Overrides location_zone term links to redirect to the matching Location post permalink.
 */
add_filter('term_link', function ($url, $term, $taxonomy) {
    if ($taxonomy === 'location_zone') {
        $location = pb_get_location_post_by_tax_term($term->slug);
        if ($location) {
            return get_permalink($location->ID);
        }
    }
    return $url;
}, 10, 3);

// ------------------------
// Meta Boxes: Business Info
// ------------------------
function pb_add_business_meta_box() {
    add_meta_box(
        'pb_business_info',
        'Business Info',
        'pb_render_business_info_box',
        'business',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'pb_add_business_meta_box');

function pb_render_business_info_box($post) {
    $fields = [
        'address' => 'Address',
        'hours' => 'Hours',
        'phone' => 'Phone Number',
    ];

    foreach ($fields as $key => $label) {
        $value = get_post_meta($post->ID, '_pb_' . $key, true);
        echo "<p><label for='pb_$key'><strong>$label</strong></label><br/>";
        echo "<input type='text' name='pb_$key' id='pb_$key' value='" . esc_attr($value) . "' class='widefat' /></p>";
        // After address field, add Latitude and Longitude fields
        if ($key === 'address') {
            $lat = get_post_meta($post->ID, '_pb_latitude', true);
            $lng = get_post_meta($post->ID, '_pb_longitude', true);
            echo "<p><label for='pb_latitude'><strong>Latitude</strong></label><br/>";
            echo "<input type='text' name='pb_latitude' id='pb_latitude' value='" . esc_attr($lat) . "' class='widefat' readonly /></p>";
            echo "<p><label for='pb_longitude'><strong>Longitude</strong></label><br/>";
            echo "<input type='text' name='pb_longitude' id='pb_longitude' value='" . esc_attr($lng) . "' class='widefat' readonly /></p>";
            ?>
            <script>
            jQuery(document).ready(function($){
                $('#pb_address').on('change', function() {
                    var address = $(this).val();
                    if (!address) return;
                    $.getJSON('https://maps.googleapis.com/maps/api/geocode/json', {
                        address: address,
                        key: 'YOUR_GOOGLE_MAPS_API_KEY' // Replace with your real API key
                    }, function(data) {
                        if (data.status === 'OK') {
                            var loc = data.results[0].geometry.location;
                            $('#pb_latitude').val(loc.lat);
                            $('#pb_longitude').val(loc.lng);
                        }
                    });
                });
            });
            </script>
            <?php
        }
        // After phone field, add Website and Social Links
        if ($key === 'phone') {
            // Website field
            $website = get_post_meta($post->ID, '_pb_website', true);
            echo "<p><label for='pb_website'><strong>Website</strong></label><br/>";
            echo "<input type='url' name='pb_website' id='pb_website' value='" . esc_attr($website) . "' class='widefat' /></p>";

            // Social Links
            $social_fields = ['facebook' => 'Facebook', 'x' => 'X (formerly Twitter)', 'tiktok' => 'TikTok', 'youtube' => 'YouTube'];
            foreach ($social_fields as $skey => $slabel) {
                $val = get_post_meta($post->ID, '_pb_social_' . $skey, true);
                $enabled = !empty($val);
                echo "<p><label><input type='checkbox' name='pb_social_enable_$skey' id='pb_social_enable_$skey' " . checked($enabled, true, false) . "> Enable $slabel</label></p>";
                echo "<div id='pb_social_input_$skey' style='margin-bottom:10px;" . ($enabled ? "" : "display:none;") . "'>";
                echo "<label for='pb_social_$skey'><strong>$slabel URL</strong></label><br/>";
                echo "<input type='url' name='pb_social_$skey' id='pb_social_$skey' value='" . esc_attr($val) . "' class='widefat' /></div>";
            }
            ?>
            <script>
            jQuery(document).ready(function($){
                ['facebook','x','tiktok','youtube'].forEach(function(key){
                    $('#pb_social_enable_' + key).on('change', function(){
                        $('#pb_social_input_' + key).toggle(this.checked);
                    });
                });
            });
            </script>
            <?php
        }
    }

    // Category taxonomy multi-select
    $taxonomy = 'pb_category';
    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
    ]);
    $selected_terms = wp_get_post_terms($post->ID, $taxonomy, ['fields' => 'ids']);

    echo "<p><label for='pb_category'><strong>Category</strong></label><br/>";
    echo "<select name='pb_category[]' id='pb_category' multiple class='widefat searchable-multi' data-searchable='true'>";
    foreach ($terms as $term) {
        $selected = in_array($term->term_id, $selected_terms) ? 'selected' : '';
        echo "<option value='{$term->term_id}' $selected>{$term->name}</option>";
    }
    echo "</select></p>";

    // People dropdown
    $people = get_posts(['post_type' => 'person', 'numberposts' => -1]);
    $saved_people = get_post_meta($post->ID, '_pb_related_people', true) ?: [];

    echo "<p><label for='pb_related_people'><strong>Related People</strong></label><br/>";
    echo "<select name='pb_related_people[]' id='pb_related_people' multiple class='widefat searchable-multi' data-searchable='true'>";
    foreach ($people as $person) {
        $selected = in_array($person->ID, $saved_people) ? 'selected' : '';
        echo "<option value='{$person->ID}' $selected>{$person->post_title}</option>";
    }
    echo "</select></p>";

    // Events dropdown
    $events = get_posts(['post_type' => 'event', 'numberposts' => -1]);
    $saved_events = get_post_meta($post->ID, '_pb_related_events', true) ?: [];

    echo "<p><label for='pb_related_events'><strong>Related Events</strong></label><br/>";
    echo "<select name='pb_related_events[]' id='pb_related_events' multiple class='widefat searchable-multi' data-searchable='true'>";
    foreach ($events as $event) {
        $selected = in_array($event->ID, $saved_events) ? 'selected' : '';
        echo "<option value='{$event->ID}' $selected>{$event->post_title}</option>";
    }
    echo "</select></p>";

    // Featured Videos section
    // Get all videos, then filter to those related to this business
    $all_videos = get_posts([
        'post_type' => 'video',
        'numberposts' => -1,
    ]);
    $related_videos = [];
    foreach ($all_videos as $video) {
        $related_businesses = get_post_meta($video->ID, '_pb_related_businesses', true);
        if (!is_array($related_businesses)) {
            if (is_string($related_businesses) && strlen($related_businesses) > 0 && strpos($related_businesses, ',') !== false) {
                $related_businesses = array_map('intval', explode(',', $related_businesses));
            } elseif (is_numeric($related_businesses)) {
                $related_businesses = [(int)$related_businesses];
            } else {
                $related_businesses = [];
            }
        }
        if (in_array($post->ID, $related_businesses)) {
            $related_videos[] = $video;
        }
    }
    $featured_videos = get_post_meta($post->ID, '_pb_featured_videos', true);
    $featured_videos = is_array($featured_videos) ? $featured_videos : [];
    echo "<p><label for='pb_featured_videos'><strong>Featured Videos (up to 3)</strong></label><br/>";
    echo "<select name='pb_featured_videos[]' id='pb_featured_videos' multiple class='widefat searchable-multi' data-searchable='true' size='4'>";
    foreach ($related_videos as $video) {
        $selected = in_array($video->ID, $featured_videos) ? 'selected' : '';
        echo "<option value='{$video->ID}' $selected>{$video->post_title}</option>";
    }
    echo "</select></p>";

    // Gallery image uploader (multi-image)
    $gallery_ids = get_post_meta($post->ID, '_pb_image_gallery', true);
    $gallery_ids = is_array($gallery_ids) ? $gallery_ids : (is_string($gallery_ids) ? explode(',', $gallery_ids) : []);
    $gallery_ids = array_filter(array_map('intval', $gallery_ids));
    echo "<p><label><strong>Gallery Images</strong></label><br/>";
    echo "<button type='button' class='button' id='pb_upload_gallery_images'>Upload Gallery Images</button></p>";
    echo "<input type='hidden' name='pb_image_gallery' id='pb_image_gallery' value='" . esc_attr(implode(',', $gallery_ids)) . "' />";
    echo "<div id='pb_image_gallery_preview' style='margin-bottom: 10px;'>";
    foreach ($gallery_ids as $img_id) {
        $img_url = wp_get_attachment_image_url($img_id, 'thumbnail');
        if ($img_url) {
            echo "<span class='pb-gallery-thumb' data-id='{$img_id}' style='display:inline-block;position:relative;margin:0 10px 10px 0;'>";
            echo "<img src='" . esc_url($img_url) . "' style='width:80px;height:80px;object-fit:cover;border:1px solid #ccc;' />";
            echo "<span class='pb-remove-gallery-image' style='position:absolute;top:0;right:0;background:#fff;color:#d00;border-radius:50%;cursor:pointer;font-weight:bold;padding:2px 6px;'>×</span>";
            echo "</span>";
        }
    }
    echo "</div>";
    ?>
    <script>
    jQuery(document).ready(function($){
        var pb_gallery_frame;
        $('#pb_upload_gallery_images').on('click', function(e){
            e.preventDefault();
            if (pb_gallery_frame) {
                pb_gallery_frame.open();
                return;
            }
            pb_gallery_frame = wp.media({
                title: 'Select Gallery Images',
                button: { text: 'Add to Gallery' },
                multiple: true
            });
            pb_gallery_frame.on('select', function(){
                var selection = pb_gallery_frame.state().get('selection');
                var ids = $('#pb_image_gallery').val() ? $('#pb_image_gallery').val().split(',').filter(Boolean).map(Number) : [];
                selection.each(function(attachment){
                    var id = attachment.id;
                    if (ids.indexOf(id) === -1) {
                        ids.push(id);
                        var thumb = attachment.attributes.sizes && attachment.attributes.sizes.thumbnail ? attachment.attributes.sizes.thumbnail.url : attachment.attributes.url;
                        $('#pb_image_gallery_preview').append(
                            "<span class='pb-gallery-thumb' data-id='"+id+"' style='display:inline-block;position:relative;margin:0 10px 10px 0;'>" +
                            "<img src='"+thumb+"' style='width:80px;height:80px;object-fit:cover;border:1px solid #ccc;' />" +
                            "<span class='pb-remove-gallery-image' style='position:absolute;top:0;right:0;background:#fff;color:#d00;border-radius:50%;cursor:pointer;font-weight:bold;padding:2px 6px;'>×</span>" +
                            "</span>"
                        );
                    }
                });
                $('#pb_image_gallery').val(ids.join(','));
            });
            pb_gallery_frame.open();
        });
        // Remove image from gallery
        $('#pb_image_gallery_preview').on('click', '.pb-remove-gallery-image', function(e){
            e.preventDefault();
            var $thumb = $(this).closest('.pb-gallery-thumb');
            var id = $thumb.data('id');
            $thumb.remove();
            var ids = $('#pb_image_gallery').val() ? $('#pb_image_gallery').val().split(',').filter(Boolean).map(Number) : [];
            ids = ids.filter(function(val){ return val !== id; });
            $('#pb_image_gallery').val(ids.join(','));
        });
    });
    </script>
    <?php
}

function pb_save_business_meta($post_id) {
    // Verify autosave, nonce, permissions here if needed (omitted for brevity)

    $simple_fields = ['address', 'hours', 'phone'];
    foreach ($simple_fields as $field) {
        if (isset($_POST['pb_' . $field])) {
            update_post_meta($post_id, '_pb_' . $field, sanitize_text_field($_POST['pb_' . $field]));
        }
    }
    // Save latitude and longitude
    if (isset($_POST['pb_latitude'])) {
        update_post_meta($post_id, '_pb_latitude', sanitize_text_field($_POST['pb_latitude']));
    }
    if (isset($_POST['pb_longitude'])) {
        update_post_meta($post_id, '_pb_longitude', sanitize_text_field($_POST['pb_longitude']));
    }
    // Website field
    if (isset($_POST['pb_website'])) {
        update_post_meta($post_id, '_pb_website', esc_url_raw($_POST['pb_website']));
    }
    // Social Links
    $social_fields = ['facebook', 'x', 'tiktok', 'youtube'];
    foreach ($social_fields as $field) {
        if (isset($_POST['pb_social_enable_' . $field]) && !empty($_POST['pb_social_' . $field])) {
            update_post_meta($post_id, '_pb_social_' . $field, esc_url_raw($_POST['pb_social_' . $field]));
        } else {
            delete_post_meta($post_id, '_pb_social_' . $field);
        }
    }

    // Save category taxonomy terms
    if (isset($_POST['pb_category']) && is_array($_POST['pb_category'])) {
        $category_ids = array_map('intval', $_POST['pb_category']);
        wp_set_object_terms($post_id, $category_ids, 'pb_category');
    }

    if (isset($_POST['pb_related_people'])) {
        $people = array_map('intval', $_POST['pb_related_people']);
        update_post_meta($post_id, '_pb_related_people', $people);
    }

    if (isset($_POST['pb_related_events'])) {
        $events = array_map('intval', $_POST['pb_related_events']);
        update_post_meta($post_id, '_pb_related_events', $events);
    }

    // Save gallery images (multi-image)
    if (isset($_POST['pb_image_gallery'])) {
        $ids = array_filter(array_map('intval', explode(',', $_POST['pb_image_gallery'])));
        update_post_meta($post_id, '_pb_image_gallery', implode(',', $ids));
    } else {
        delete_post_meta($post_id, '_pb_image_gallery');
    }
    // Save featured videos (up to 3)
    if (isset($_POST['pb_featured_videos']) && is_array($_POST['pb_featured_videos'])) {
        $videos = array_map('intval', $_POST['pb_featured_videos']);
        $videos = array_slice(array_unique($videos), 0, 3);
        update_post_meta($post_id, '_pb_featured_videos', $videos);
    } else {
        delete_post_meta($post_id, '_pb_featured_videos');
    }
}
add_action('save_post_business', 'pb_save_business_meta');

// ------------------------
// Admin Columns for Business
// ------------------------
function pb_add_business_columns($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['pb_phone'] = 'Phone Number';
            $new_columns['pb_category'] = 'Category';
            $new_columns['pb_location_zone'] = 'Location';
        }
    }
    return $new_columns;
}
add_filter('manage_business_posts_columns', 'pb_add_business_columns');

function pb_manage_business_columns($column, $post_id) {
    switch ($column) {
        case 'pb_phone':
            $phone = get_post_meta($post_id, '_pb_phone', true);
            echo esc_html($phone);
            break;
        case 'pb_category':
            $terms = get_the_terms($post_id, 'pb_category');
            if (!empty($terms) && !is_wp_error($terms)) {
                $term_links = [];
                foreach ($terms as $term) {
                    $link = esc_url(add_query_arg(['post_type' => 'business', 'pb_category' => $term->slug], 'edit.php'));
                    $term_links[] = "<a href='$link'>{$term->name}</a>";
                }
                echo implode(', ', $term_links);
            } else {
                echo '—';
            }
            break;
        case 'pb_location_zone':
            $terms = get_the_terms($post_id, 'location_zone');
            if (!empty($terms) && !is_wp_error($terms)) {
                $term_links = [];
                foreach ($terms as $term) {
                    $link = esc_url(add_query_arg(['post_type' => 'business', 'location_zone' => $term->slug], 'edit.php'));
                    $term_links[] = "<a href='$link'>{$term->name}</a>";
                }
                echo implode(', ', $term_links);
            } else {
                echo '—';
            }
            break;
    }
}
add_action('manage_business_posts_custom_column', 'pb_manage_business_columns', 10, 2);

// ------------------------
// Admin Filters for Business
// ------------------------
function pb_restrict_business_by_taxonomy() {
    global $typenow;
    if ($typenow === 'business') {
        $taxonomies = ['pb_category', 'location_zone'];
        foreach ($taxonomies as $tax_slug) {
            $taxonomy = get_taxonomy($tax_slug);
            $selected = isset($_GET[$tax_slug]) ? $_GET[$tax_slug] : '';
            wp_dropdown_categories([
                'show_option_all' => "Show All {$taxonomy->label}",
                'taxonomy'        => $tax_slug,
                'name'            => $tax_slug,
                'orderby'         => 'name',
                'selected'        => $selected,
                'show_count'      => true,
                'hide_empty'      => true,
            ]);
        }
    }
}
add_action('restrict_manage_posts', 'pb_restrict_business_by_taxonomy');

function pb_filter_business_by_taxonomy($query) {
    global $pagenow, $typenow;
    if ($typenow === 'business' && $pagenow === 'edit.php' && $query->is_main_query()) {
        $taxonomies = ['pb_category', 'location_zone'];
        foreach ($taxonomies as $tax_slug) {
            if (!empty($_GET[$tax_slug]) && is_numeric($_GET[$tax_slug])) {
                $term = get_term_by('id', intval($_GET[$tax_slug]), $tax_slug);
                if ($term) {
                    $query->set('tax_query', array_merge(
                        $query->get('tax_query') ?: [],
                        [[
                            'taxonomy' => $tax_slug,
                            'field'    => 'slug',
                            'terms'    => $term->slug,
                        ]]
                    ));
                }
            }
        }
    }
}
add_filter('pre_get_posts', 'pb_filter_business_by_taxonomy');

// ------------------------
// Enqueue Select2 for Admin
// ------------------------
function pb_enqueue_admin_select2($hook) {
    // Load only on post edit screens
    if (in_array($hook, ['post-new.php', 'post.php'])) {
        wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);

        wp_add_inline_script('select2-js', "
            jQuery(document).ready(function($) {
                $('.searchable-multi').select2({
                    width: '100%',
                    placeholder: 'Search and select...'
                });
            });
        ");
    }
}
add_action('admin_enqueue_scripts', 'pb_enqueue_admin_select2');
// ------------------------
// Meta Boxes: Person Info
// ------------------------
function pb_add_person_meta_box() {
    add_meta_box(
        'pb_person_info',
        'Person Info',
        'pb_render_person_info_box',
        'person',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'pb_add_person_meta_box');

function pb_render_person_info_box($post) {
    // Website field
    $website = get_post_meta($post->ID, '_pb_website', true);
    echo "<p><label for='pb_website'><strong>Website</strong></label><br/>";
    echo "<input type='url' name='pb_website' id='pb_website' value='" . esc_attr($website) . "' class='widefat' /></p>";

    // Social Links
    $social_fields = ['facebook' => 'Facebook', 'x' => 'X (formerly Twitter)', 'tiktok' => 'TikTok', 'youtube' => 'YouTube'];
    foreach ($social_fields as $skey => $slabel) {
        $val = get_post_meta($post->ID, '_pb_social_' . $skey, true);
        $enabled = !empty($val);
        echo "<p><label><input type='checkbox' name='pb_social_enable_$skey' id='pb_social_enable_$skey' " . checked($enabled, true, false) . "> Enable $slabel</label></p>";
        echo "<div id='pb_social_input_$skey' style='margin-bottom:10px;" . ($enabled ? "" : "display:none;") . "'>";
        echo "<label for='pb_social_$skey'><strong>$slabel URL</strong></label><br/>";
        echo "<input type='url' name='pb_social_$skey' id='pb_social_$skey' value='" . esc_attr($val) . "' class='widefat' /></div>";
    }
    ?>
    <script>
    jQuery(document).ready(function($){
        ['facebook','x','tiktok','youtube'].forEach(function(key){
            $('#pb_social_enable_' + key).on('change', function(){
                $('#pb_social_input_' + key).toggle(this.checked);
            });
        });
    });
    </script>
    <?php

    // Category taxonomy multi-select
    $taxonomy = 'pb_category';
    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
    ]);
    $selected_terms = wp_get_post_terms($post->ID, $taxonomy, ['fields' => 'ids']);

    echo "<p><label for='pb_category'><strong>Category</strong></label><br/>";
    echo "<select name='pb_category[]' id='pb_category' multiple class='widefat searchable-multi' data-searchable='true'>";
    foreach ($terms as $term) {
        $selected = in_array($term->term_id, $selected_terms) ? 'selected' : '';
        echo "<option value='{$term->term_id}' $selected>{$term->name}</option>";
    }
    echo "</select></p>";

    // Businesses dropdown
    $businesses = get_posts(['post_type' => 'business', 'numberposts' => -1]);
    $saved_businesses = get_post_meta($post->ID, '_pb_related_businesses', true) ?: [];

    echo "<p><label for='pb_related_businesses'><strong>Related Businesses</strong></label><br/>";
    echo "<select name='pb_related_businesses[]' id='pb_related_businesses' multiple class='widefat searchable-multi' data-searchable='true'>";
    foreach ($businesses as $business) {
        $selected = in_array($business->ID, $saved_businesses) ? 'selected' : '';
        echo "<option value='{$business->ID}' $selected>{$business->post_title}</option>";
    }
    echo "</select></p>";

    // Events dropdown
    $events = get_posts(['post_type' => 'event', 'numberposts' => -1]);
    $saved_events = get_post_meta($post->ID, '_pb_related_events', true) ?: [];

    echo "<p><label for='pb_related_events'><strong>Related Events</strong></label><br/>";
    echo "<select name='pb_related_events[]' id='pb_related_events' multiple class='widefat searchable-multi' data-searchable='true'>";
    foreach ($events as $event) {
        $selected = in_array($event->ID, $saved_events) ? 'selected' : '';
        echo "<option value='{$event->ID}' $selected>{$event->post_title}</option>";
    }
    echo "</select></p>";

    // Featured Videos section
    // Get all videos, then filter to those related to this person
    $all_videos = get_posts([
        'post_type' => 'video',
        'numberposts' => -1,
    ]);
    $related_videos = [];
    foreach ($all_videos as $video) {
        $related_people = get_post_meta($video->ID, '_pb_related_people', true);
        if (!is_array($related_people)) {
            if (is_string($related_people) && strlen($related_people) > 0 && strpos($related_people, ',') !== false) {
                $related_people = array_map('intval', explode(',', $related_people));
            } elseif (is_numeric($related_people)) {
                $related_people = [(int)$related_people];
            } else {
                $related_people = [];
            }
        }
        if (in_array($post->ID, $related_people)) {
            $related_videos[] = $video;
        }
    }
    $featured_videos = get_post_meta($post->ID, '_pb_featured_videos', true);
    $featured_videos = is_array($featured_videos) ? $featured_videos : [];
    echo "<p><label for='pb_featured_videos'><strong>Featured Videos (up to 3)</strong></label><br/>";
    echo "<select name='pb_featured_videos[]' id='pb_featured_videos' multiple class='widefat searchable-multi' data-searchable='true' size='4'>";
    foreach ($related_videos as $video) {
        $selected = in_array($video->ID, $featured_videos) ? 'selected' : '';
        echo "<option value='{$video->ID}' $selected>{$video->post_title}</option>";
    }
    echo "</select></p>";
}

function pb_save_person_meta($post_id) {
    // Verify autosave, nonce, permissions here if needed (omitted for brevity)

    // Website field
    if (isset($_POST['pb_website'])) {
        update_post_meta($post_id, '_pb_website', esc_url_raw($_POST['pb_website']));
    }
    // Social Links
    $social_fields = ['facebook', 'x', 'tiktok', 'youtube'];
    foreach ($social_fields as $field) {
        if (isset($_POST['pb_social_enable_' . $field]) && !empty($_POST['pb_social_' . $field])) {
            update_post_meta($post_id, '_pb_social_' . $field, esc_url_raw($_POST['pb_social_' . $field]));
        } else {
            delete_post_meta($post_id, '_pb_social_' . $field);
        }
    }

    // Save category taxonomy terms
    if (isset($_POST['pb_category']) && is_array($_POST['pb_category'])) {
        $category_ids = array_map('intval', $_POST['pb_category']);
        wp_set_object_terms($post_id, $category_ids, 'pb_category');
    }

    // Save related businesses
    if (isset($_POST['pb_related_businesses'])) {
        $businesses = array_map('intval', $_POST['pb_related_businesses']);
        update_post_meta($post_id, '_pb_related_businesses', $businesses);
    }
    // Save related events
    if (isset($_POST['pb_related_events'])) {
        $events = array_map('intval', $_POST['pb_related_events']);
        update_post_meta($post_id, '_pb_related_events', $events);
    }

    // Save featured videos (up to 3)
    if (isset($_POST['pb_featured_videos']) && is_array($_POST['pb_featured_videos'])) {
        $videos = array_map('intval', $_POST['pb_featured_videos']);
        $videos = array_slice(array_unique($videos), 0, 3);
        update_post_meta($post_id, '_pb_featured_videos', $videos);
    } else {
        delete_post_meta($post_id, '_pb_featured_videos');
    }
}
add_action('save_post_person', 'pb_save_person_meta');
// ------------------------
// Admin Columns and Filters for Other Post Types
// ------------------------
function pb_add_common_columns($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['pb_category'] = 'Category';
            $new_columns['pb_location_zone'] = 'Location';
        }
    }
    return $new_columns;
}
add_filter('manage_person_posts_columns', 'pb_add_common_columns');
add_filter('manage_event_posts_columns', 'pb_add_common_columns');
add_filter('manage_location_posts_columns', 'pb_add_common_columns');
add_filter('manage_video_posts_columns', 'pb_add_common_columns');

function pb_manage_common_columns($column, $post_id) {
    switch ($column) {
        case 'pb_category':
            $terms = get_the_terms($post_id, 'pb_category');
            if (!empty($terms) && !is_wp_error($terms)) {
                echo implode(', ', wp_list_pluck($terms, 'name'));
            } else {
                echo '—';
            }
            break;
        case 'pb_location_zone':
            $terms = get_the_terms($post_id, 'location_zone');
            if (!empty($terms) && !is_wp_error($terms)) {
                echo implode(', ', wp_list_pluck($terms, 'name'));
            } else {
                echo '—';
            }
            break;
    }
}
add_action('manage_person_posts_custom_column', 'pb_manage_common_columns', 10, 2);
add_action('manage_event_posts_custom_column', 'pb_manage_common_columns', 10, 2);
add_action('manage_location_posts_custom_column', 'pb_manage_common_columns', 10, 2);
add_action('manage_video_posts_custom_column', 'pb_manage_common_columns', 10, 2);

function pb_restrict_common_by_taxonomy() {
    global $typenow;
    $taxonomies = ['pb_category', 'location_zone'];
    if (in_array($typenow, ['person','event','location','video'])) {
        foreach ($taxonomies as $tax_slug) {
            $taxonomy = get_taxonomy($tax_slug);
            $selected = isset($_GET[$tax_slug]) ? $_GET[$tax_slug] : '';
            wp_dropdown_categories([
                'show_option_all' => "Show All {$taxonomy->label}",
                'taxonomy'        => $tax_slug,
                'name'            => $tax_slug,
                'orderby'         => 'name',
                'selected'        => $selected,
                'show_count'      => true,
                'hide_empty'      => true,
            ]);
        }
    }
}
add_action('restrict_manage_posts', 'pb_restrict_common_by_taxonomy');

function pb_filter_common_by_taxonomy($query) {
    global $pagenow, $typenow;
    if (in_array($typenow, ['person','event','location','video']) && $pagenow === 'edit.php' && $query->is_main_query()) {
        $taxonomies = ['pb_category', 'location_zone'];
        foreach ($taxonomies as $tax_slug) {
            if (!empty($_GET[$tax_slug]) && is_numeric($_GET[$tax_slug])) {
                $term = get_term_by('id', intval($_GET[$tax_slug]), $tax_slug);
                if ($term) {
                    $query->set('tax_query', array_merge(
                        $query->get('tax_query') ?: [],
                        [[
                            'taxonomy' => $tax_slug,
                            'field'    => 'slug',
                            'terms'    => $term->slug,
                        ]]
                    ));
                }
            }
        }
    }
}
add_filter('pre_get_posts', 'pb_filter_common_by_taxonomy');

// ------------------------
// Meta Boxes: Video Info
// ------------------------
function pb_add_video_meta_box() {
    add_meta_box(
        'pb_video_info',
        'Video Info',
        'pb_render_video_info_box',
        'video',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'pb_add_video_meta_box');

function pb_render_video_info_box($post) {
    // Related People
    $people = get_posts(['post_type' => 'person', 'numberposts' => -1]);
    $saved_people = get_post_meta($post->ID, '_pb_related_people', true) ?: [];
    echo "<p><label for='pb_related_people'><strong>Related People</strong></label><br/>";
    echo "<select name='pb_related_people[]' id='pb_related_people' multiple class='widefat searchable-multi' data-searchable='true'>";
    foreach ($people as $person) {
        $selected = in_array($person->ID, $saved_people) ? 'selected' : '';
        echo "<option value='{$person->ID}' $selected>{$person->post_title}</option>";
    }
    echo "</select></p>";

    // Related Events
    $events = get_posts(['post_type' => 'event', 'numberposts' => -1]);
    $saved_events = get_post_meta($post->ID, '_pb_related_events', true) ?: [];
    echo "<p><label for='pb_related_events'><strong>Related Events</strong></label><br/>";
    echo "<select name='pb_related_events[]' id='pb_related_events' multiple class='widefat searchable-multi' data-searchable='true'>";
    foreach ($events as $event) {
        $selected = in_array($event->ID, $saved_events) ? 'selected' : '';
        echo "<option value='{$event->ID}' $selected>{$event->post_title}</option>";
    }
    echo "</select></p>";

    // Related Businesses
    $businesses = get_posts(['post_type' => 'business', 'numberposts' => -1]);
    $saved_businesses = get_post_meta($post->ID, '_pb_related_businesses', true) ?: [];
    echo "<p><label for='pb_related_businesses'><strong>Related Businesses</strong></label><br/>";
    echo "<select name='pb_related_businesses[]' id='pb_related_businesses' multiple class='widefat searchable-multi' data-searchable='true'>";
    foreach ($businesses as $business) {
        $selected = in_array($business->ID, $saved_businesses) ? 'selected' : '';
        echo "<option value='{$business->ID}' $selected>{$business->post_title}</option>";
    }
    echo "</select></p>";

    // Related Locations
    $locations = get_posts(['post_type' => 'location', 'numberposts' => -1]);
    $saved_locations = get_post_meta($post->ID, '_pb_related_locations', true) ?: [];
    echo "<p><label for='pb_related_locations'><strong>Related Locations</strong></label><br/>";
    echo "<select name='pb_related_locations[]' id='pb_related_locations' multiple class='widefat searchable-multi' data-searchable='true'>";
    foreach ($locations as $location) {
        $selected = in_array($location->ID, $saved_locations) ? 'selected' : '';
        echo "<option value='{$location->ID}' $selected>{$location->post_title}</option>";
    }
    echo "</select></p>";

    // CTA Link
    $cta = get_post_meta($post->ID, '_pb_cta_link', true);
    echo "<p><label for='pb_cta_link'><strong>CTA Link (external URL)</strong></label><br/>";
    echo "<input type='url' name='pb_cta_link' id='pb_cta_link' value='" . esc_attr($cta) . "' class='widefat' /></p>";

    // Featured Video (Extended)
    $featured_video_data = get_post_meta($post->ID, '_pb_featured_video', true);
    $featured_enabled = !empty($featured_video_data) && is_array($featured_video_data) && !empty($featured_video_data['enabled']);
    $featured_type = !empty($featured_video_data['type']) ? $featured_video_data['type'] : 'url';
    $featured_url = !empty($featured_video_data['url']) ? $featured_video_data['url'] : '';
    $featured_file_id = !empty($featured_video_data['file_id']) ? intval($featured_video_data['file_id']) : 0;
    $featured_file_name = '';
    $featured_file_url = '';
    if ($featured_file_id) {
        $featured_file_url = wp_get_attachment_url($featured_file_id);
        $featured_file_name = get_the_title($featured_file_id);
    }
    echo "<p><label><input type='checkbox' name='pb_featured_video_enable' id='pb_featured_video_enable' " . checked($featured_enabled, true, false) . "> Enable Featured Video</label></p>";
    echo "<div id='pb_featured_video_input' style='margin-bottom:10px;" . ($featured_enabled ? "" : "display:none;") . "'>";
    echo "<strong>Featured Video Source:</strong><br/>";
    echo "<label><input type='radio' name='pb_featured_video_type' value='url' id='pb_featured_video_type_url' " . checked($featured_type, 'url', false) . "> Use external video link</label>&nbsp;";
    echo "<label><input type='radio' name='pb_featured_video_type' value='file' id='pb_featured_video_type_file' " . checked($featured_type, 'file', false) . "> Select from Media Library</label>";
    echo "<div id='pb_featured_video_url_wrap' style='margin-top:10px;" . ($featured_type === 'url' ? "" : "display:none;") . "'>";
    echo "<label for='pb_featured_video_url'><strong>Featured Video URL (YouTube, Vimeo, or MP4)</strong></label><br/>";
    echo "<input type='text' name='pb_featured_video_url' id='pb_featured_video_url' value='" . esc_attr($featured_url) . "' class='widefat' />";
    echo "</div>";
    echo "<div id='pb_featured_video_file_wrap' style='margin-top:10px;" . ($featured_type === 'file' ? "" : "display:none;") . "'>";
    echo "<input type='hidden' name='pb_featured_video_id' id='pb_featured_video_id' value='" . esc_attr($featured_file_id) . "' />";
    echo "<button type='button' class='button' id='pb_select_featured_video_file'>Select Video from Media Library</button>";
    echo "<span id='pb_featured_video_file_display' style='margin-left:10px;'>";
    if ($featured_file_id && $featured_file_name) {
        echo "<span class='pb-featured-video-file-name'>" . esc_html($featured_file_name) . "</span>";
        if ($featured_file_url) {
            echo " (<a href='" . esc_url($featured_file_url) . "' target='_blank'>Preview</a>)";
        }
        echo " <span class='pb-remove-featured-video-file' style='color:#d00;cursor:pointer;font-weight:bold;' title='Remove'>&times;</span>";
    }
    echo "</span>";
    echo "</div>";
    echo "</div>";
    ?>
    <script>
    jQuery(document).ready(function($){
        function toggleFeaturedVideoInput() {
            $('#pb_featured_video_input').toggle($('#pb_featured_video_enable').is(':checked'));
        }
        $('#pb_featured_video_enable').on('change', toggleFeaturedVideoInput);
        // Radio toggle for type
        function toggleVideoSourceType() {
            var type = $('input[name="pb_featured_video_type"]:checked').val();
            $('#pb_featured_video_url_wrap').toggle(type === 'url');
            $('#pb_featured_video_file_wrap').toggle(type === 'file');
        }
        $('input[name="pb_featured_video_type"]').on('change', toggleVideoSourceType);
        toggleVideoSourceType();

        // Media library video selector
        var pb_video_frame;
        $('#pb_select_featured_video_file').on('click', function(e){
            e.preventDefault();
            if (pb_video_frame) {
                pb_video_frame.open();
                return;
            }
            pb_video_frame = wp.media({
                title: 'Select Video',
                button: { text: 'Use this video' },
                library: { type: 'video' },
                multiple: false
            });
            pb_video_frame.on('select', function(){
                var attachment = pb_video_frame.state().get('selection').first().toJSON();
                $('#pb_featured_video_id').val(attachment.id);
                $('#pb_featured_video_file_display').html(
                    "<span class='pb-featured-video-file-name'>" + attachment.title + "</span>" +
                    " (<a href='" + attachment.url + "' target='_blank'>Preview</a>) " +
                    "<span class='pb-remove-featured-video-file' style='color:#d00;cursor:pointer;font-weight:bold;' title='Remove'>&times;</span>"
                );
            });
            pb_video_frame.open();
        });
        // Remove file
        $('#pb_featured_video_file_wrap').on('click', '.pb-remove-featured-video-file', function(e){
            e.preventDefault();
            $('#pb_featured_video_id').val('');
            $('#pb_featured_video_file_display').empty();
        });
    });
    </script>
    <?php
}

function pb_save_video_meta($post_id) {
    // Related People
    if (isset($_POST['pb_related_people'])) {
        update_post_meta($post_id, '_pb_related_people', array_map('intval', $_POST['pb_related_people']));
    }
    // Related Events
    if (isset($_POST['pb_related_events'])) {
        update_post_meta($post_id, '_pb_related_events', array_map('intval', $_POST['pb_related_events']));
    }
    // Related Businesses
    if (isset($_POST['pb_related_businesses'])) {
        update_post_meta($post_id, '_pb_related_businesses', array_map('intval', $_POST['pb_related_businesses']));
    }
    // Related Locations
    if (isset($_POST['pb_related_locations'])) {
        update_post_meta($post_id, '_pb_related_locations', array_map('intval', $_POST['pb_related_locations']));
    }
    // CTA Link
    if (isset($_POST['pb_cta_link'])) {
        update_post_meta($post_id, '_pb_cta_link', esc_url_raw($_POST['pb_cta_link']));
    }
    // Featured Video (extended)
    if (isset($_POST['pb_featured_video_enable'])) {
        $type = isset($_POST['pb_featured_video_type']) && $_POST['pb_featured_video_type'] === 'file' ? 'file' : 'url';
        if ($type === 'url' && !empty($_POST['pb_featured_video_url'])) {
            $url = sanitize_text_field($_POST['pb_featured_video_url']);
            update_post_meta($post_id, '_pb_featured_video', [
                'enabled' => 1,
                'type' => 'url',
                'url' => $url,
            ]);
        } elseif ($type === 'file' && !empty($_POST['pb_featured_video_id'])) {
            $file_id = intval($_POST['pb_featured_video_id']);
            update_post_meta($post_id, '_pb_featured_video', [
                'enabled' => 1,
                'type' => 'file',
                'file_id' => $file_id,
            ]);
        } else {
            // If enabled but no data, remove meta
            delete_post_meta($post_id, '_pb_featured_video');
        }
    } else {
        delete_post_meta($post_id, '_pb_featured_video');
    }
}
add_action('save_post_video', 'pb_save_video_meta');
// ------------------------
// Meta Boxes: Location Info
// ------------------------
function pb_add_location_meta_box() {
    add_meta_box(
        'pb_location_info',
        'Location Info',
        'pb_render_location_info_box',
        'location',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'pb_add_location_meta_box');

function pb_render_location_info_box($post) {
    // Address
    $address = get_post_meta($post->ID, '_pb_address', true);
    echo "<p><label for='pb_address'><strong>Address</strong></label><br/>";
    echo "<input type='text' name='pb_address' id='pb_address' value='" . esc_attr($address) . "' class='widefat' /></p>";

    // Latitude/Longitude
    $lat = get_post_meta($post->ID, '_pb_latitude', true);
    $lng = get_post_meta($post->ID, '_pb_longitude', true);
    echo "<p><label for='pb_latitude'><strong>Latitude</strong></label><br/>";
    echo "<input type='text' name='pb_latitude' id='pb_latitude' value='" . esc_attr($lat) . "' class='widefat' /></p>";
    echo "<p><label for='pb_longitude'><strong>Longitude</strong></label><br/>";
    echo "<input type='text' name='pb_longitude' id='pb_longitude' value='" . esc_attr($lng) . "' class='widefat' /></p>";

    // Website
    $website = get_post_meta($post->ID, '_pb_website', true);
    echo "<p><label for='pb_website'><strong>Website</strong></label><br/>";
    echo "<input type='url' name='pb_website' id='pb_website' value='" . esc_attr($website) . "' class='widefat' /></p>";

    // Social Links
    $social_fields = ['facebook' => 'Facebook', 'x' => 'X (formerly Twitter)', 'tiktok' => 'TikTok', 'youtube' => 'YouTube'];
    foreach ($social_fields as $skey => $slabel) {
        $val = get_post_meta($post->ID, '_pb_social_' . $skey, true);
        $enabled = !empty($val);
        echo "<p><label><input type='checkbox' name='pb_social_enable_$skey' id='pb_social_enable_$skey' " . checked($enabled, true, false) . "> Enable $slabel</label></p>";
        echo "<div id='pb_social_input_$skey' style='margin-bottom:10px;" . ($enabled ? "" : "display:none;") . "'>";
        echo "<label for='pb_social_$skey'><strong>$slabel URL</strong></label><br/>";
        echo "<input type='url' name='pb_social_$skey' id='pb_social_$skey' value='" . esc_attr($val) . "' class='widefat' /></div>";
    }
    ?>
    <script>
    jQuery(document).ready(function($){
        ['facebook','x','tiktok','youtube'].forEach(function(key){
            $('#pb_social_enable_' + key).on('change', function(){
                $('#pb_social_input_' + key).toggle(this.checked);
            });
        });
    });
    </script>
    <?php

    // Category taxonomy multi-select
    $taxonomy = 'pb_category';
    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
    ]);
    $selected_terms = wp_get_post_terms($post->ID, $taxonomy, ['fields' => 'ids']);
    echo "<p><label for='pb_category'><strong>Category</strong></label><br/>";
    echo "<select name='pb_category[]' id='pb_category' multiple class='widefat searchable-multi' data-searchable='true'>";
    foreach ($terms as $term) {
        $selected = in_array($term->term_id, $selected_terms) ? 'selected' : '';
        echo "<option value='{$term->term_id}' $selected>{$term->name}</option>";
    }
    echo "</select></p>";

    // Businesses dropdown
    $businesses = get_posts(['post_type' => 'business', 'numberposts' => -1]);
    $saved_businesses = get_post_meta($post->ID, '_pb_related_businesses', true) ?: [];
    echo "<p><label for='pb_related_businesses'><strong>Related Businesses</strong></label><br/>";
    echo "<select name='pb_related_businesses[]' id='pb_related_businesses' multiple class='widefat searchable-multi' data-searchable='true'>";
    foreach ($businesses as $business) {
        $selected = in_array($business->ID, $saved_businesses) ? 'selected' : '';
        echo "<option value='{$business->ID}' $selected>{$business->post_title}</option>";
    }
    echo "</select></p>";

    // Events dropdown
    $events = get_posts(['post_type' => 'event', 'numberposts' => -1]);
    $saved_events = get_post_meta($post->ID, '_pb_related_events', true) ?: [];
    echo "<p><label for='pb_related_events'><strong>Related Events</strong></label><br/>";
    echo "<select name='pb_related_events[]' id='pb_related_events' multiple class='widefat searchable-multi' data-searchable='true'>";
    foreach ($events as $event) {
        $selected = in_array($event->ID, $saved_events) ? 'selected' : '';
        echo "<option value='{$event->ID}' $selected>{$event->post_title}</option>";
    }
    echo "</select></p>";

    // Featured Videos section
    // Get all videos, then filter to those related to this location
    $all_videos = get_posts([
        'post_type' => 'video',
        'numberposts' => -1,
    ]);
    $related_videos = [];
    foreach ($all_videos as $video) {
        $related_locations = get_post_meta($video->ID, '_pb_related_locations', true);
        if (!is_array($related_locations)) {
            if (is_string($related_locations) && strlen($related_locations) > 0 && strpos($related_locations, ',') !== false) {
                $related_locations = array_map('intval', explode(',', $related_locations));
            } elseif (is_numeric($related_locations)) {
                $related_locations = [(int)$related_locations];
            } else {
                $related_locations = [];
            }
        }
        if (in_array($post->ID, $related_locations)) {
            $related_videos[] = $video;
        }
    }
    $featured_videos = get_post_meta($post->ID, '_pb_featured_videos', true);
    $featured_videos = is_array($featured_videos) ? $featured_videos : [];
    echo "<p><label for='pb_featured_videos'><strong>Featured Videos (up to 3)</strong></label><br/>";
    echo "<select name='pb_featured_videos[]' id='pb_featured_videos' multiple class='widefat searchable-multi' data-searchable='true' size='4'>";
    foreach ($related_videos as $video) {
        $selected = in_array($video->ID, $featured_videos) ? 'selected' : '';
        echo "<option value='{$video->ID}' $selected>{$video->post_title}</option>";
    }
    echo "</select></p>";
}

function pb_save_location_meta($post_id) {
    // Address
    if (isset($_POST['pb_address'])) {
        update_post_meta($post_id, '_pb_address', sanitize_text_field($_POST['pb_address']));
    }
    // Latitude/Longitude
    if (isset($_POST['pb_latitude'])) {
        update_post_meta($post_id, '_pb_latitude', sanitize_text_field($_POST['pb_latitude']));
    }
    if (isset($_POST['pb_longitude'])) {
        update_post_meta($post_id, '_pb_longitude', sanitize_text_field($_POST['pb_longitude']));
    }
    // Website
    if (isset($_POST['pb_website'])) {
        update_post_meta($post_id, '_pb_website', esc_url_raw($_POST['pb_website']));
    }
    // Social Links
    $social_fields = ['facebook', 'x', 'tiktok', 'youtube'];
    foreach ($social_fields as $field) {
        if (isset($_POST['pb_social_enable_' . $field]) && !empty($_POST['pb_social_' . $field])) {
            update_post_meta($post_id, '_pb_social_' . $field, esc_url_raw($_POST['pb_social_' . $field]));
        } else {
            delete_post_meta($post_id, '_pb_social_' . $field);
        }
    }
    // Category taxonomy
    if (isset($_POST['pb_category']) && is_array($_POST['pb_category'])) {
        $category_ids = array_map('intval', $_POST['pb_category']);
        wp_set_object_terms($post_id, $category_ids, 'pb_category');
    }
    // Related Businesses
    if (isset($_POST['pb_related_businesses'])) {
        update_post_meta($post_id, '_pb_related_businesses', array_map('intval', $_POST['pb_related_businesses']));
    }
    // Related Events
    if (isset($_POST['pb_related_events'])) {
        update_post_meta($post_id, '_pb_related_events', array_map('intval', $_POST['pb_related_events']));
    }
    // Save featured videos (up to 3)
    if (isset($_POST['pb_featured_videos']) && is_array($_POST['pb_featured_videos'])) {
        $videos = array_map('intval', $_POST['pb_featured_videos']);
        $videos = array_slice(array_unique($videos), 0, 3);
        update_post_meta($post_id, '_pb_featured_videos', $videos);
    } else {
        delete_post_meta($post_id, '_pb_featured_videos');
    }
}

add_action('save_post_location', 'pb_save_location_meta');

// ------------------------
// Business Card Rendering, Hook, and Shortcode
// ------------------------

/**
 * Renders a simple business card for a business post.
 *
 * @param int $post_id
 */
if (!function_exists('pb_render_business_card')) {
function pb_render_business_card($post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'business') {
        return;
    }
    ?>
    <div class="pb-business-card" style="border:1px solid #ccc;padding:1em;margin:1em 0;max-width:400px;">
        <h3 class="pb-business-card-title"><?php echo esc_html(get_the_title($post)); ?></h3>
        <div class="pb-business-card-excerpt">
            <?php echo esc_html(get_the_excerpt($post)); ?>
        </div>
    </div>
    <?php
}
}

/**
 * Action handler for rendering business card via hook.
 *
 * @param int|null $post_id If null, uses current post in loop.
 */
function pb_render_business_card_hook($post_id = null) {
    if (empty($post_id)) {
        global $post;
        if (!empty($post) && $post->post_type === 'business') {
            $post_id = $post->ID;
        }
    }
    if ($post_id) {
        pb_render_business_card($post_id);
    }
}
add_action('pb_show_business_card', 'pb_render_business_card_hook');

/**
 * Shortcode for displaying a business card: [business_card id="123"]
 *
 * @param array $atts
 * @return string
 */
function pb_business_card_shortcode($atts) {
    $atts = shortcode_atts([
        'id' => '',
    ], $atts, 'business_card');
    $post_id = intval($atts['id']);
    if (!$post_id) {
        global $post;
        if (!empty($post) && $post->post_type === 'business') {
            $post_id = $post->ID;
        }
    }
    if (!$post_id) {
        return '';
    }
    ob_start();
    pb_render_business_card($post_id);
    return ob_get_clean();
}
add_shortcode('business_card', 'pb_business_card_shortcode');

// ------------------------
// Voting table install/upgrade
// ------------------------
function pb_install_votes_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'hqb_pb_votes';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        round_ref varchar(64) NOT NULL,
        post_ref varchar(64) NOT NULL,
        votes bigint(20) unsigned NOT NULL DEFAULT 0,
        last_vote_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY round_post_unique (round_ref, post_ref)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

add_action('after_switch_theme', 'pb_install_votes_table');

// ------------------------
// Custom Branding: Replace WordPress with Project Baldwin
// ------------------------

// Change WordPress admin bar logo and link
function pb_custom_admin_bar_logo($wp_admin_bar) {
    // Remove WP logo
    $wp_admin_bar->remove_node('wp-logo');

    // Add Project Baldwin logo/text
    $wp_admin_bar->add_node([
        'id'    => 'pb-logo',
        'title' => '<span class="ab-icon" style="background-image:url(' . get_stylesheet_directory_uri() . '/images/pb-logo.png);background-size:contain;"></span><span class="ab-label">Project Baldwin</span>',
        'href'  => home_url(),
        'meta'  => [
            'title' => __('Project Baldwin Dashboard'),
        ],
    ]);

    // Add Admin Panel submenu node as a child of pb-logo
    $wp_admin_bar->add_node([
        'id'     => 'pb-logo-admin',
        'parent' => 'pb-logo',
        'title'  => __('Admin Panel', 'projectbaldwin-theme'),
        'href'   => admin_url(),
        'meta'   => [
            'title' => __('Go to WordPress Admin Panel', 'projectbaldwin-theme'),
            // Optionally, add custom class for styling if needed
            'class' => 'pb-logo-admin-submenu',
        ],
    ]);
}
add_action('admin_bar_menu', 'pb_custom_admin_bar_logo', 11);

// Change login logo
function pb_custom_login_logo() {
    ?>
    <style type="text/css">
        #login h1 a {
            background-image: url('<?php echo get_stylesheet_directory_uri(); ?>/images/pb-logo.png');
            background-size: contain;
            width: 200px;
            height: 100px;
        }
    </style>
    <?php
}
add_action('login_enqueue_scripts', 'pb_custom_login_logo');

// Change login logo link & title
function pb_login_logo_url() {
    return home_url();
}
add_filter('login_headerurl', 'pb_login_logo_url');

function pb_login_logo_url_title() {
    return 'Project Baldwin';
}
add_filter('login_headertitle', 'pb_login_logo_url_title');

// Change admin footer text
function pb_custom_admin_footer() {
    echo 'Project Baldwin © ' . date('Y');
}
add_filter('admin_footer_text', 'pb_custom_admin_footer');

// Remove "My WordPress" from admin bar
function pb_remove_site_name_admin_bar() {
    global $wp_admin_bar;
    $wp_admin_bar->remove_node('site-name');
}
add_action('wp_before_admin_bar_render', 'pb_remove_site_name_admin_bar');

// ------------------------
// Remove SiteGround Starter dashboard override
// ------------------------
function pb_remove_siteground_dashboard() {
    remove_menu_page('siteground-dashboard');
    remove_submenu_page('index.php', 'siteground-dashboard');
}
add_action('admin_menu', 'pb_remove_siteground_dashboard', 999);

function pb_redirect_siteground_dashboard() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'dashboard_page_siteground-dashboard') {
        wp_redirect(admin_url('index.php'));
        exit;
    }
}
add_action('current_screen', 'pb_redirect_siteground_dashboard');

// ------------------------
// Project Baldwin Dashboard Overview
// ------------------------

// Remove default dashboard widgets
function pb_remove_default_dashboard_widgets() {
    remove_action('welcome_panel', 'wp_welcome_panel');
    remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
    remove_meta_box('dashboard_activity', 'dashboard', 'normal');
    remove_meta_box('dashboard_primary', 'dashboard', 'side');
    remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
}
add_action('wp_dashboard_setup', 'pb_remove_default_dashboard_widgets');

// Add Project Baldwin custom dashboard widget
function pb_add_dashboard_widget() {
    wp_add_dashboard_widget('pb_dashboard_widget', 'Project Baldwin Overview', 'pb_render_dashboard_widget');
}
add_action('wp_dashboard_setup', 'pb_add_dashboard_widget');

// Render the widget content
function pb_render_dashboard_widget() {
    $post_types = array('business','person','event','location','video','card','pack','voting_round');
    $labels     = array();
    $counts     = array();

    foreach ($post_types as $pt) {
        if (post_type_exists($pt)) {
            $obj   = get_post_type_object($pt);
            $count = wp_count_posts($pt)->publish;
            $labels[] = $obj->labels->singular_name;
            $counts[] = (int)$count;
        }
    }
    ?>
    <canvas id="pbDashboardChart" style="max-width:100%;height:400px;"></canvas>
    <div style="margin-top:10px;">
        <button id="pbToggleChart" class="button">Toggle Chart Type</button>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('pbDashboardChart').getContext('2d');
        let currentType = 'bar';
        let chart = new Chart(ctx, {
            type: currentType,
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Post Count',
                    data: <?php echo json_encode($counts); ?>,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(255, 99, 132, 0.6)',
                        'rgba(255, 206, 86, 0.6)',
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(153, 102, 255, 0.6)',
                        'rgba(255, 159, 64, 0.6)',
                        'rgba(199, 199, 199, 0.6)',
                        'rgba(100, 181, 246, 0.6)'
                    ],
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        precision: 0
                    }
                }
            }
        });

        document.getElementById('pbToggleChart').addEventListener('click', function() {
            chart.destroy();
            currentType = (currentType === 'bar') ? 'pie' : 'bar';
            chart = new Chart(ctx, {
                type: currentType,
                data: {
                    labels: <?php echo json_encode($labels); ?>,
                    datasets: [{
                        label: 'Post Count',
                        data: <?php echo json_encode($counts); ?>,
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.6)',
                            'rgba(255, 99, 132, 0.6)',
                            'rgba(255, 206, 86, 0.6)',
                            'rgba(75, 192, 192, 0.6)',
                            'rgba(153, 102, 255, 0.6)',
                            'rgba(255, 159, 64, 0.6)',
                            'rgba(199, 199, 199, 0.6)',
                            'rgba(100, 181, 246, 0.6)'
                        ],
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: currentType === 'bar' ? { y: { beginAtZero: true, precision: 0 } } : {}
                }
            });
        });
    });
    </script>
    <?php
}