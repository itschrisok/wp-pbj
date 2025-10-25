<?php
/**
 * PB_Voting_Service: Main voting service bootstrap for Project Baldwin.
 *
 * Responsibilities:
 * - Registers custom meta fields, REST API endpoints, and admin pages for voting rounds.
 * - Handles participant selection, vote tallying, round state, and result snapshots.
 * - Interacts with PB_Voting_Sort for sorting/ranking logic.
 * - Directly interacts with the WordPress database for vote storage and retrieval.
 * PB_Voting_Service: Main voting service bootstrap for Project Baldwin.
 *
 * This file defines the PB_Voting_Service class, which orchestrates all voting-related
 * functionality for Project Baldwin. Its responsibilities include registering custom meta fields,
 * handling REST API endpoints, managing voting rounds and their participants, tallying votes,
 * and providing admin UI for voting round management. It serves as the primary integration point
 * for voting, working closely with PB_Voting_Sort for ranking and sorting logic, and interacts
 * directly with the WordPress database for vote storage.
 * Voting service bootstrap for Project Baldwin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PB_Voting_Service {
    // ============================================================
    // Initialization & Setup
    // ============================================================
    // Registers hooks, meta fields, REST endpoints, and admin pages.

    private const REF_META_KEY = '_pb_ref_id';
    private const IN_VOTING_META_KEY = '_pb_in_voting';
    private const ROUND_REF_META = '_pb_round_ref';
    private const ROUND_STATE_META = '_pb_round_state';
    private const ROUND_START_META = '_pb_round_start';
    private const ROUND_DURATION_META = '_pb_round_duration';
    private const ROUND_CATEGORY_META = '_pb_round_category_ids';
    private const ROUND_SOURCE_META = '_pb_round_source';
    private const ROUND_MANUAL_META = '_pb_round_manual_participants';
    private const ROUND_PARTICIPANTS_META = '_pb_round_participants';

    private static $eligible_post_types = [
        'card',
        'pack',
        'location',
        'business',
        'person',
        'event',
    ];

    private static $round_states = [
        'nomination',
        'final',
        'custom',
    ];

    /**
     * Entrypoint: Registers all hooks for voting service.
     */
    public static function init() {
        add_action('init', [__CLASS__, 'register_meta']);
        add_action('add_meta_boxes', [__CLASS__, 'register_meta_boxes']);
        add_action('save_post', [__CLASS__, 'maybe_assign_reference_id'], 10, 2);
        add_action('save_post', [__CLASS__, 'persist_in_voting_flag'], 10, 2);
        add_action('save_post_voting_round', [__CLASS__, 'save_voting_round_meta'], 10, 2);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        add_action('admin_menu', [__CLASS__, 'register_admin_pages']);
        add_action('admin_post_pb_end_voting_round', [__CLASS__, 'handle_end_voting_round']);
        add_action('admin_post_pb_bulk_voting_round_action', [__CLASS__, 'handle_bulk_voting_round_action']);
    }

    // ============================================================
    // Meta Field Registration
    // ============================================================
    // Registers meta fields for all votable post types and voting rounds.

    public static function register_meta() {
        foreach (self::get_available_votable_types() as $post_type) {
            register_post_meta($post_type, self::REF_META_KEY, [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => [__CLASS__, 'can_edit_post'],
                'sanitize_callback' => 'sanitize_text_field',
            ]);

            register_post_meta($post_type, self::IN_VOTING_META_KEY, [
                'type' => 'boolean',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => [__CLASS__, 'can_edit_post'],
            ]);
        }

        register_post_meta('voting_round', self::ROUND_STATE_META, [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => [__CLASS__, 'can_edit_post'],
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_post_meta('voting_round', self::ROUND_REF_META, [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => [__CLASS__, 'can_edit_post'],
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_post_meta('voting_round', self::ROUND_START_META, [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => [__CLASS__, 'can_edit_post'],
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_post_meta('voting_round', self::ROUND_DURATION_META, [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => [__CLASS__, 'can_edit_post'],
            'sanitize_callback' => 'absint',
        ]);

        register_post_meta('voting_round', self::ROUND_CATEGORY_META, [
            'type' => 'array',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => [__CLASS__, 'can_edit_post'],
            'sanitize_callback' => [__CLASS__, 'sanitize_int_array'],
        ]);

        register_post_meta('voting_round', self::ROUND_SOURCE_META, [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => [__CLASS__, 'can_edit_post'],
        ]);

        register_post_meta('voting_round', self::ROUND_MANUAL_META, [
            'type' => 'array',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => [__CLASS__, 'can_edit_post'],
            'sanitize_callback' => [__CLASS__, 'sanitize_int_array'],
        ]);

        register_post_meta('voting_round', self::ROUND_PARTICIPANTS_META, [
            'type' => 'array',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => [__CLASS__, 'can_edit_post'],
            'sanitize_callback' => [__CLASS__, 'sanitize_int_array'],
        ]);

        // Additional meta for enhanced admin controls and frontend awareness.
        // - _pb_total_nominees: nomination round finalist count
        // - _pb_total_places: number of places to display post-round
        // - _pb_round_end: explicit end datetime, derived or manually set
        register_post_meta('voting_round', '_pb_total_nominees', [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => [__CLASS__, 'can_edit_post'],
            'sanitize_callback' => 'absint',
        ]);

        register_post_meta('voting_round', '_pb_total_places', [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => [__CLASS__, 'can_edit_post'],
            'sanitize_callback' => 'absint',
        ]);

        register_post_meta('voting_round', '_pb_round_end', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => [__CLASS__, 'can_edit_post'],
            'sanitize_callback' => 'sanitize_text_field',
        ]);
    }

    // ============================================================
    // Admin Meta Boxes
    // ============================================================
    // Adds meta boxes to post edit screens for voting settings and round configuration.

    public static function register_meta_boxes() {
        foreach (self::get_available_votable_types() as $post_type) {
            add_meta_box(
                'pb_voting_status',
                __('Voting Settings', 'projectbaldwin'),
                [__CLASS__, 'render_in_voting_meta_box'],
                $post_type,
                'side',
                'default'
            );
        }

        add_meta_box(
            'pb_voting_round_settings',
            __('Voting Round Settings', 'projectbaldwin'),
            [__CLASS__, 'render_voting_round_meta_box'],
            'voting_round',
            'normal',
            'high'
        );
    }

    public static function render_in_voting_meta_box($post) {
        wp_nonce_field('pb_in_voting_meta', 'pb_in_voting_nonce');
        $is_enabled = (bool) get_post_meta($post->ID, self::IN_VOTING_META_KEY, true);
        ?>
        <p>
            <label>
                <input type="checkbox" name="pb_in_voting" value="1" <?php checked($is_enabled); ?> />
                <?php esc_html_e('Eligible for active voting round', 'projectbaldwin'); ?>
            </label>
        </p>
        <?php

        $reference = get_post_meta($post->ID, self::REF_META_KEY, true);
        if ($reference) {
            echo '<p><strong>' . esc_html__('Reference ID:', 'projectbaldwin') . '</strong> ' . esc_html($reference) . '</p>';
        }
    }

    public static function render_voting_round_meta_box($post) {
        // ==========================
        // VOTING ROUND META BOX UI
        // ==========================
        // This section structures the admin UI for nomination, final, and custom voting rounds.
        // It includes timing, round-specific settings, and participant summaries.
        // Contextual visibility is controlled via inline JS toggling.

        wp_nonce_field('pb_voting_round_meta', 'pb_voting_round_nonce');
        $pb_rest_nonce = wp_create_nonce('wp_rest');
        $pb_rest_root  = rest_url('pb/v1/');

        $round_state          = get_post_meta($post->ID, self::ROUND_STATE_META, true);
        if (!$round_state || !in_array($round_state, self::$round_states, true)) {
            $round_state = 'custom';
        }
        $is_nomination = ($round_state === 'nomination');
        $is_final      = ($round_state === 'final');
        $is_custom     = ($round_state === 'custom');
        $selected_cats        = (array) get_post_meta($post->ID, self::ROUND_CATEGORY_META, true);
        $manual_participants  = (array) get_post_meta($post->ID, self::ROUND_MANUAL_META, true);
        $cached_participants  = array_map('intval', (array) get_post_meta($post->ID, self::ROUND_PARTICIPANTS_META, true));

        $manual_participant_options = self::fetch_manual_participant_options();
        $manual_option_ids = array_map(static function($row) {
            return isset($row['ID']) ? (int) $row['ID'] : 0;
        }, $manual_participant_options);
        $manual_option_ids = array_filter($manual_option_ids);

        $missing_manual_ids = array_diff(array_map('intval', $manual_participants), $manual_option_ids);
        if (!empty($missing_manual_ids)) {
            foreach (self::get_participant_posts($missing_manual_ids) as $extra_post) {
                $manual_participant_options[] = [
                    'ID'    => $extra_post->ID,
                    'label' => sprintf('%s — %s', get_the_title($extra_post), ucfirst($extra_post->post_type)),
                ];
            }
        }

        $selected_participant_ids = $cached_participants;
        if (empty($selected_participant_ids)) {
            if ($round_state === 'custom' && !empty($manual_participants)) {
                $selected_participant_ids = $manual_participants;
            } elseif (!empty($selected_cats)) {
                $selected_participant_ids = self::fetch_nomination_participants($selected_cats);
            }
        }
        $selected_participant_ids   = array_values(array_unique(array_map('intval', $selected_participant_ids)));
        $selected_participant_posts = self::get_participant_posts($selected_participant_ids);
?>
<div class="pb-voting-round-meta">

  <!-- === Section: Timing Fields === -->
  <h3>Timing</h3>
  <p><label for="pb_round_start"><strong>Start Date/Time</strong></label><br>
    <input type="datetime-local" name="pb_round_start" id="pb_round_start" value="<?php echo esc_attr(get_post_meta($post->ID, '_pb_round_start', true)); ?>" class="widefat">
  </p>
  <p><label for="pb_round_end"><strong>End Date/Time</strong></label><br>
    <input type="datetime-local" name="pb_round_end" id="pb_round_end" value="<?php echo esc_attr(get_post_meta($post->ID, '_pb_round_end', true)); ?>" class="widefat">
  </p>
  <p><label for="pb_round_duration"><strong>Duration (Days)</strong></label><br>
    <?php
      $start = strtotime(get_post_meta($post->ID, '_pb_round_start', true));
      $end = strtotime(get_post_meta($post->ID, '_pb_round_end', true));
      $duration = ($start && $end) ? round(($end - $start) / 86400, 2) : '';
    ?>
    <input type="number" name="pb_round_duration" id="pb_round_duration" value="<?php echo esc_attr($duration); ?>" class="widefat" readonly>
    <em>This field is automatically calculated from Start and End times.</em>
  </p>

  <!-- === Section: Round Type === -->
  <h3>Round Type</h3>
  <label><input type="radio" name="pb_round_state" value="nomination" <?php checked($round_state, 'nomination'); ?>> Nomination</label><br>
  <label><input type="radio" name="pb_round_state" value="final" <?php checked($round_state, 'final'); ?>> Final</label><br>
  <label><input type="radio" name="pb_round_state" value="custom" <?php checked($round_state, 'custom'); ?>> Custom</label>

  <!-- === Section: Nomination Fields === -->
  <fieldset class="pb-field-group pb-field-nomination" style="margin-top:10px;" <?php disabled(!$is_nomination); ?>>
    <h3>Nomination Round Settings</h3>
    <p><label><strong>Categories</strong></label></p>
    <div class="pb-checkbox-list">
      <?php
      // Use custom taxonomy for Project Baldwin categories
      $terms = get_terms(['taxonomy' => 'pb_category', 'hide_empty' => false]);
      foreach ($terms as $term) {
        echo '<label><input type="checkbox" name="pb_round_categories[]" value="' . esc_attr($term->term_id) . '" ' . checked(in_array($term->term_id, $selected_cats), true, false) . '> ' . esc_html($term->name) . '</label><br>';
      }
      ?>
    </div>
    <p><label for="pb_total_nominees"><strong>Total Nominees</strong></label><br>
      <input type="number" name="pb_total_nominees" id="pb_total_nominees" value="<?php echo esc_attr(get_post_meta($post->ID, '_pb_total_nominees', true)); ?>" min="1" step="1" class="small-text">
    </p>
  </fieldset>

  <!-- === Section: Final Fields === -->
  <fieldset class="pb-field-group pb-field-final" style="margin-top:10px;" <?php disabled(!$is_final); ?>>
    <h3>Final Round Settings</h3>
    <p><label for="pb_round_source"><strong>Final Round Source</strong></label><br>
      <input type="text" name="pb_round_source" id="pb_round_source" value="<?php echo esc_attr(get_post_meta($post->ID, '_pb_round_source', true)); ?>" placeholder="Search or enter reference ID" class="widefat">
    </p>
    <p><label for="pb_total_places"><strong>Total Places</strong></label><br>
      <input type="number" name="pb_total_places" id="pb_total_places" value="<?php echo esc_attr(get_post_meta($post->ID, '_pb_total_places', true)); ?>" min="1" step="1" class="small-text">
    </p>
  </fieldset>

  <!-- === Section: Custom Fields === -->
  <fieldset class="pb-field-group pb-field-custom" style="margin-top:10px;" <?php disabled(!$is_custom); ?>>
    <h3>Custom Round Settings</h3>
    <?php $custom_select_mode = get_post_meta($post->ID, '_pb_round_custom_select_mode', true); ?>
    <label><input type="checkbox" id="pb_custom_select_toggle" name="pb_custom_select_mode" value="category" <?php checked($custom_select_mode, 'category'); ?>> Select by Category instead of Custom Participants</label>

      <div class="pb-custom-by-category" style="margin-top:10px;">
      <p><label><strong>Categories</strong></label></p>
      <div class="pb-checkbox-list">
        <?php
        // Use custom taxonomy for Project Baldwin categories
        foreach ($terms as $term) {
          echo '<label><input type="checkbox" name="pb_round_custom_categories[]" value="' . esc_attr($term->term_id) . '" ' . checked(in_array($term->term_id, $selected_cats), true, false) . '> ' . esc_html($term->name) . '</label><br>';
        }
        ?>
      </div>
    </div>

    <div class="pb-custom-by-manual" style="margin-top:10px;">
      <p><label><strong>Custom Participants</strong></label></p>
      <input type="text" id="pb-participant-search" placeholder="Search participants..." style="width:100%;margin-bottom:5px;">
      <div id="pb-manual-participants" class="pb-checkbox-list" style="max-height:250px; overflow-y:auto;">
        <?php
        foreach ($manual_participant_options as $option) {
            $participant_id = isset($option['ID']) ? (int) $option['ID'] : 0;
            if (!$participant_id) {
                continue;
            }
            $label = isset($option['label']) ? $option['label'] : sprintf(__('Participant #%d', 'projectbaldwin'), $participant_id);
            $is_selected = in_array($participant_id, $manual_participants, true);
            echo '<label><input type="checkbox" name="pb_round_manual_participants[]" value="' . esc_attr($participant_id) . '" ' . checked($is_selected, true, false) . '> ' . esc_html($label) . '</label><br>';
        }
        if (empty($manual_participant_options)) {
            echo '<em>' . esc_html__('No participants available to select.', 'projectbaldwin') . '</em>';
        } else {
            echo '<p class="description" style="margin-top:8px;">' . esc_html__('Showing up to 200 recently added participants. Use search to filter the list.', 'projectbaldwin') . '</p>';
        }
        ?>
      </div>
    </div>

    <p><label for="pb_total_places_custom"><strong>Total Places</strong></label><br>
      <input type="number" name="pb_total_places" id="pb_total_places_custom" value="<?php echo esc_attr(get_post_meta($post->ID, '_pb_total_places', true)); ?>" min="1" step="1" class="small-text">
    </p>
  </fieldset>

  <!-- === Section: Round Reference and Selected Participants (Always Visible) === -->
  <!--
    This section always shows the round reference and allows admins to review and adjust the cached participant list.
    The "Refresh Participants" button will later trigger a REST API call to recalculate participants.
  -->
  <h3>
    Round Reference &amp; Selected Participants
    <button type="button" id="pb-refresh-participants" class="button" style="float:right;">Refresh Participants</button>
  </h3>
  <p><strong>Round Reference:</strong>
    <?php
    $ref = get_post_meta($post->ID, '_pb_round_ref', true);
    echo $ref ? esc_html($ref) : '<em>Will generate on save</em>';
    ?>
  </p>
  <p><strong>Selected Participants:</strong></p>
  <div id="pb-selected-participants" class="pb-checkbox-list" style="max-height:250px; overflow-y:auto;">
    <?php if (!empty($selected_participant_posts)) : ?>
        <?php foreach ($selected_participant_posts as $participant_post) : ?>
            <label>
                <input type="checkbox"
                       name="pb_round_participants[]"
                       value="<?php echo esc_attr($participant_post->ID); ?>"
                       <?php checked(in_array($participant_post->ID, $selected_participant_ids, true), true); ?>>
                <?php echo esc_html(get_the_title($participant_post)); ?>
                <span class="description">(<?php echo esc_html($participant_post->post_type); ?>)</span>
            </label><br>
        <?php endforeach; ?>
    <?php else : ?>
        <em><?php esc_html_e('No participants selected yet. Save or refresh to populate.', 'projectbaldwin'); ?></em>
    <?php endif; ?>
  </div>
  <p><em>Use this list to verify or adjust cached participants before publishing.</em></p>

<script>
// === Admin: Refresh participants via REST ===
(function($){
  var restRoot = <?php echo wp_json_encode( $pb_rest_root ); ?>;
  var restNonce = <?php echo wp_json_encode( $pb_rest_nonce ); ?>;
  var roundId = <?php echo (int) $post->ID; ?>;

  function renderSelectedParticipants(payload) {
    var box = $('#pb-selected-participants');
    if (!box.length) return;

    var ids = [];
    var details = [];

    if (Array.isArray(payload)) {
      ids = payload;
    } else if (payload) {
      if (Array.isArray(payload.participants)) {
        ids = payload.participants;
      }
      if (Array.isArray(payload.details)) {
        details = payload.details;
      }
    }

    if (!ids.length) {
      box.html('<em>No participants cached yet.</em>');
      return;
    }

    var detailMap = {};
    details.forEach(function(detail){
      if (detail && detail.ID) {
        detailMap[detail.ID] = detail;
      }
    });

    var html = '';
    ids.forEach(function(pid){
      var detail = detailMap[pid] || null;
      var labelText = detail && detail.title ? detail.title : null;
      var typeText = detail && detail.type ? detail.type : '';
      if (!labelText) {
        var $label = $('input[name="pb_round_manual_participants[]"][value="'+pid+'"]').closest('label');
        labelText = $label.length ? $label.text().trim() : ('ID ' + pid);
      }
      var safeLabel = $('<div/>').text(labelText).html();
      var safeType = typeText ? $('<div/>').text(typeText).html() : '';

      html += '<label><input type="checkbox" name="pb_round_participants[]" value="'+pid+'" checked> ' + safeLabel;
      if (safeType) {
        html += ' <span class="description">(' + safeType + ')</span>';
      }
      html += '</label><br>';
    });

    box.html(html);
  }

  $('#pb-refresh-participants').on('click', function(){
    var $btn = $(this);
    $btn.prop('disabled', true).text('Refreshing...');
    fetch(restRoot + 'round/' + roundId + '/refresh', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': restNonce
      },
      body: JSON.stringify({})
    }).then(function(res){
      if (!res.ok) throw new Error('Refresh failed');
      return res.json();
    }).then(function(data){
      if (data) {
        renderSelectedParticipants(data);
      }
    }).catch(function(err){
      console.error(err);
      alert('Unable to refresh participants.');
    }).finally(function(){
      $btn.prop('disabled', false).text('Refresh Participants');
    });
  });
})(jQuery);
</script>
</div>

// Unified admin JS for round-type toggling, custom mode, and participant search.
<script>
(function($){
  function setGroupState($group, enabled) {
    if (!$group || !$group.length) {
      return;
    }
    $group.toggle(enabled);
    $group.prop('disabled', !enabled);
    $group.find(':input').prop('disabled', !enabled);
  }

  // === Toggle Round Type Fields ===
  function toggleFields() {
    var val = $('input[name="pb_round_state"]:checked').val();
    setGroupState($('.pb-field-nomination'), val === 'nomination');
    setGroupState($('.pb-field-final'), val === 'final');
    setGroupState($('.pb-field-custom'), val === 'custom');
  }

  // === Toggle Custom Selection Mode ===
  function toggleCustomMode() {
    var byCategory = $('#pb_custom_select_toggle').is(':checked');
    setGroupState($('.pb-custom-by-category'), byCategory);
    setGroupState($('.pb-custom-by-manual'), !byCategory);
  }

  // === Participant Search Filter ===
  function setupParticipantSearch() {
    $('#pb-participant-search').on('input', function() {
      var filter = $(this).val().toLowerCase();
      $('.pb-custom-by-manual .pb-checkbox-list label').each(function() {
        var text = $(this).text().toLowerCase();
        $(this).toggle(text.indexOf(filter) > -1);
      });
    });
  }

  // === Init on Document Ready ===
  $(document).ready(function(){
    toggleFields();
    toggleCustomMode();
    setupParticipantSearch();
    // Bind events
    $(document).on('change', 'input[name="pb_round_state"]', toggleFields);
    $(document).on('change', '#pb_custom_select_toggle', toggleCustomMode);
  });
})(jQuery);
</script>
<?php
    }

    // ============================================================
    // Save Post Hooks
    // ============================================================
    // Handles persistence of meta fields when posts (including rounds) are saved.

    public static function maybe_assign_reference_id($post_id, $post) {
        // Assigns a human-readable reference ID to a votable post if missing.
        if (!self::should_handle_post($post_id, $post)) {
            return;
        }

        $current = get_post_meta($post_id, self::REF_META_KEY, true);
        if (!$current) {
            $reference = self::generate_human_ref($post->post_type, $post_id);
            update_post_meta($post_id, self::REF_META_KEY, $reference);
        }
    }

    public static function persist_in_voting_flag($post_id, $post) {
        // Persists the "in voting" eligibility flag for a post.
        if (!self::should_handle_post($post_id, $post)) {
            return;
        }

        if (!isset($_POST['pb_in_voting_nonce']) || !wp_verify_nonce($_POST['pb_in_voting_nonce'], 'pb_in_voting_meta')) {
            return;
        }

        $value = isset($_POST['pb_in_voting']) ? 1 : 0;
        update_post_meta($post_id, self::IN_VOTING_META_KEY, $value);
    }

    public static function save_voting_round_meta($post_id, $post) {
        // Saves voting round meta fields and recalculates participants list.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if (!isset($_POST['pb_voting_round_nonce']) || !wp_verify_nonce($_POST['pb_voting_round_nonce'], 'pb_voting_round_meta')) {
            return;
        }

        $state = isset($_POST['pb_round_state']) && in_array($_POST['pb_round_state'], self::$round_states, true)
            ? $_POST['pb_round_state']
            : 'custom';

        $start = isset($_POST['pb_round_start']) ? sanitize_text_field($_POST['pb_round_start']) : '';
        $duration = isset($_POST['pb_round_duration']) ? intval($_POST['pb_round_duration']) : 0;
        $end = isset($_POST['pb_round_end']) ? sanitize_text_field($_POST['pb_round_end']) : '';
        $categories = isset($_POST['pb_round_categories']) ? self::sanitize_int_array($_POST['pb_round_categories']) : [];
        $manual = isset($_POST['pb_round_manual_participants']) ? self::sanitize_int_array($_POST['pb_round_manual_participants']) : [];
        $source_round = isset($_POST['pb_round_source']) ? (int) $_POST['pb_round_source'] : 0;

        if (count($categories) > 12) {
            $categories = array_slice($categories, 0, 12);
        }

        $round_ref = get_post_meta($post_id, self::ROUND_REF_META, true);
        if (!$round_ref) {
            $round_ref = self::generate_human_ref('round', $post_id);
        }

        // Synchronization logic for start, end, duration
        $start_ts = $start ? strtotime($start) : false;
        $end_ts = $end ? strtotime($end) : false;
        // If both start and end are present, recalc duration (in days)
        if ($start_ts && $end_ts) {
            $new_duration = max(0, ceil(($end_ts - $start_ts) / DAY_IN_SECONDS));
            $duration = $new_duration;
        }
        // If start and duration are present but end is empty, calculate end
        if ($start_ts && $duration && !$end) {
            $end_ts = $start_ts + ($duration * DAY_IN_SECONDS);
            $end = date('Y-m-d\TH:i', $end_ts);
        }
        // Save end value (may be empty string)
        update_post_meta($post_id, '_pb_round_end', $end);

        update_post_meta($post_id, self::ROUND_STATE_META, $state);
        update_post_meta($post_id, self::ROUND_START_META, $start);
        update_post_meta($post_id, self::ROUND_DURATION_META, $duration);
        update_post_meta($post_id, self::ROUND_CATEGORY_META, $categories);
        update_post_meta($post_id, self::ROUND_SOURCE_META, $source_round);
        update_post_meta($post_id, self::ROUND_MANUAL_META, $manual);
        update_post_meta($post_id, self::ROUND_REF_META, $round_ref);

        // Save total nominees and places for nomination/final/custom rounds.
        if (isset($_POST['pb_total_nominees'])) {
            update_post_meta($post_id, '_pb_total_nominees', absint($_POST['pb_total_nominees']));
        }
        if (isset($_POST['pb_total_places'])) {
            update_post_meta($post_id, '_pb_total_places', absint($_POST['pb_total_places']));
        }

        $participants = self::calculate_participants($state, $categories, $manual, $source_round);
        update_post_meta($post_id, self::ROUND_PARTICIPANTS_META, $participants);

        // Save manually adjusted participant selections from the always-visible section.
        // If the admin checks/unchecks participant boxes, this will persist the chosen list.
        if (isset($_POST['pb_round_participants'])) {
            update_post_meta($post_id, self::ROUND_PARTICIPANTS_META, array_map('absint', $_POST['pb_round_participants']));
        }
    }

    // ============================================================
    // Participant Calculation Logic
    // ============================================================
    // Determines which posts participate in a round, based on round type.

    private static function calculate_participants($state, array $categories, array $manual, $source_round_id) {
        // Returns an array of participant post IDs for the round.
        switch ($state) {
            case 'nomination':
                return self::fetch_nomination_participants($categories);
            case 'final':
                return self::fetch_finalists($source_round_id);
            case 'custom':
            default:
                return array_values(array_unique(array_filter($manual)));
        }
    }

private static function fetch_nomination_participants(array $categories) {
    if (empty($categories)) return [];

    $taxonomy = taxonomy_exists('pb_category') ? 'pb_category' :
                (taxonomy_exists('connections') ? 'connections' : '');
    if (!$taxonomy) return [];

    $post_types = self::get_available_votable_types();
    if (empty($post_types)) return [];

    $query = new WP_Query([
        'post_type'      => $post_types,
        'post_status'    => 'publish',
        'meta_key'       => self::IN_VOTING_META_KEY,
        'meta_value'     => '1',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'tax_query'      => [[
            'taxonomy' => $taxonomy,
            'field'    => 'term_id',
            'terms'    => $categories,
        ]],
    ]);

    return $query->posts ?: [];
}

    private static function fetch_finalists($source_round_id) {
        // Returns top finalist post IDs from a previous round.
        if (!$source_round_id) {
            return [];
        }

        $winners = get_post_meta($source_round_id, '_pb_round_results', true);
        $winners = is_array($winners) ? array_slice(array_map('intval', $winners), 0, 5) : [];

        if (!empty($winners)) {
            return $winners;
        }

        $previous_participants = get_post_meta($source_round_id, self::ROUND_PARTICIPANTS_META, true);
        $previous_participants = is_array($previous_participants) ? array_slice($previous_participants, 0, 5) : [];

        return array_values(array_unique(array_filter($previous_participants)));
    }

    private static function fetch_manual_participant_options() {
        // Returns an array of eligible posts for manual participant selection.
        $post_types = self::get_available_votable_types();
        if (empty($post_types)) {
            return [];
        }

        $posts = get_posts([
            'post_type' => $post_types,
            'post_status' => ['publish', 'draft'],
            'numberposts' => 200,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $options = [];
        foreach ($posts as $post) {
            $options[] = [
                'ID' => $post->ID,
                'label' => sprintf('%s — %s', get_the_title($post), ucfirst($post->post_type)),
            ];
        }

        return $options;
    }

    private static function get_participant_posts(array $participant_ids) {
        $participant_ids = array_values(array_unique(array_filter(array_map('intval', $participant_ids))));
        if (empty($participant_ids)) {
            return [];
        }

        $post_types = self::get_available_votable_types();
        if (empty($post_types)) {
            return [];
        }

        return get_posts([
            'post_type'      => $post_types,
            'post_status'    => ['publish', 'draft'],
            'posts_per_page' => count($participant_ids),
            'post__in'       => $participant_ids,
            'orderby'        => 'post__in',
        ]);
    }

    private static function format_participant_details(array $participant_ids) {
        $posts = self::get_participant_posts($participant_ids);
        $details = [];
        foreach ($posts as $participant) {
            $details[] = [
                'ID'    => $participant->ID,
                'title' => get_the_title($participant),
                'type'  => $participant->post_type,
            ];
        }

        return $details;
    }

    // ============================================================
    // Helper Functions & Utilities
    // ============================================================
    // Internal utilities for permission checks, sanitization, reference generation, etc.

    private static function should_handle_post($post_id, $post) {
        // Determines if the post should be handled by voting logic.
        if (!$post instanceof WP_Post) {
            return false;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }

        if (wp_is_post_revision($post_id)) {
            return false;
        }

        if (!in_array($post->post_type, self::get_available_votable_types(), true)) {
            return false;
        }

        return current_user_can('edit_post', $post_id);
    }

    public static function can_edit_post($allowed, $meta_key, $post_id, ...$args) {
        return current_user_can('edit_post', $post_id);
    }

    public static function sanitize_int_array($value) {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $value)));
    }

    /**
     * Generate a human-readable reference string.
     *
     * @param string $post_type
     * @param int    $post_id
     * @return string
     */
    private static function generate_human_ref($post_type, $post_id) {
        $random = strtolower(wp_generate_password(8, false, false));
        return "{$post_type}_{$post_id}_{$random}";
    }

    // ============================================================
    // Ranking & Snapshot Helpers
    // ============================================================
    // Handles ranking, sorting, and snapshotting of round results.

    /**
     * Get ranked participants for a round reference, with live votes and rank.
     *
     * @param string $round_ref
     * @return array Array of arrays: [ 'post_id', 'post_ref', 'votes', 'rank' ]
     */
    public static function get_ranked_participants($round_ref) {
        // Returns an array of ranked participants (post_id, post_ref, votes, rank, last_vote_at).
        $round_id = self::get_round_id_by_reference($round_ref);
        if (!$round_id) {
            return [];
        }
        $participants = (array) get_post_meta($round_id, self::ROUND_PARTICIPANTS_META, true);
        if (empty($participants)) {
            return [];
        }
        // Map post_id => post_ref
        $post_refs = [];
        foreach ($participants as $pid) {
            $ref = get_post_meta($pid, self::REF_META_KEY, true);
            if ($ref) {
                $post_refs[$pid] = $ref;
            }
        }
        // Get vote totals by post_ref
        $vote_records = self::get_vote_records($round_ref, array_values($post_refs));
        // Build array for ranking
        $rank_rows = [];
        foreach ($post_refs as $pid => $ref) {
            $record = $vote_records[$ref] ?? null;
            $votes = $record['votes'] ?? 0;
            // Set last_vote_at to null if not present or empty
            $last_vote_at = !empty($record['last_vote_at']) ? $record['last_vote_at'] : null;
            $post_obj = get_post($pid);
            $rank_rows[] = [
                'post_id' => $pid,
                'post_ref' => $ref,
                'votes' => $votes,
                'last_vote_at' => $last_vote_at,
                'post_title' => $post_obj ? get_the_title($post_obj) : '',
                'post_type' => $post_obj ? $post_obj->post_type : '',
            ];
        }
        // Sort descending by votes, then by post_id for stable order
        usort($rank_rows, function($a, $b) {
            if ($a['votes'] === $b['votes']) {
                return $a['post_id'] <=> $b['post_id'];
            }
            return $b['votes'] <=> $a['votes'];
        });
        // Assign rank (1-based, shared rank for ties)
        // TODO: Move rank assignment fully to PB_Voting_Sort class for single responsibility.
        $current_rank = 0;
        $prev_votes = null;
        foreach ($rank_rows as $i => &$row) {
            if ($prev_votes !== $row['votes']) {
                $current_rank = $i + 1;
                $prev_votes = $row['votes'];
            }
            $row['rank'] = $current_rank;
        }
        unset($row);
        return $rank_rows;
    }

    /**
     * Get sorted participants for a round reference, using a sort mode.
     *
     * @param string $round_ref
     * @param string $mode Sort mode: recent|highest|lowest|tiebreaker
     * @return array
     */
    public static function get_sorted_participants($round_ref, $mode = 'recent') {
        // Delegates sorting to PB_Voting_Sort class.
        if (!class_exists('PB_Voting_Sort')) {
            require_once get_template_directory() . '/inc/class-pb-voting-sort.php';
        }
        return PB_Voting_Sort::apply($round_ref, $mode);
    }

    /**
     * Store a snapshot of round results (ranked) in meta.
     *
     * @param int $round_id
     * @param string $round_ref
     * @return void
     */
    public static function snapshot_round_results($round_id, $round_ref) {
        // Saves a snapshot of round results (for post-round archival).
        $ranked = self::get_ranked_participants($round_ref);
        // Store just the post_ids in order as legacy, but also full ranking
        $result_ids = array_map(function($row) { return $row['post_id']; }, $ranked);
        update_post_meta($round_id, '_pb_round_results', $result_ids);
        update_post_meta($round_id, '_pb_round_rankings', $ranked);
    }

    public static function get_available_votable_types() {
        return array_values(array_filter(self::$eligible_post_types, 'post_type_exists'));
    }

    // ============================================================
    // REST API Routes
    // ============================================================
    // Registers custom REST API endpoints for voting and round result queries.

    public static function register_rest_routes() {
        register_rest_route('pb/v1', '/vote', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_vote_submission'],
            'permission_callback' => '__return_true',
            'args' => [
                'round_ref' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'post_ref' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('pb/v1', '/round/(?P<round_ref>[^/]+)/totals', [
            'methods' => 'GET',
            // REST: Return ranked results for round
            'callback' => [__CLASS__, 'handle_round_totals'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('pb/v1', '/round/(?P<round_id>\d+)/refresh', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'refresh_round_participants'],
            'permission_callback' => function() { return current_user_can('edit_posts'); },
]);
    }

    // ============================================================
    // Vote Handling (REST)
    // ============================================================
    // Handles REST vote submissions, validates participants, and updates vote counts.

    public static function handle_vote_submission(WP_REST_Request $request) {
        // Handles a POST vote submission via REST API.
        global $wpdb;

        // Ensure vote table exists (create if not)
        self::maybe_create_vote_table();

        $round_ref = $request->get_param('round_ref');
        $post_ref = $request->get_param('post_ref');

        $round_id = self::get_round_id_by_reference($round_ref);
        if (!$round_id) {
            return new WP_Error('pb_invalid_round', __('Voting round not found.', 'projectbaldwin'), ['status' => 404]);
        }

        $post_id = self::get_post_id_by_reference($post_ref);
        if (!$post_id) {
            return new WP_Error('pb_invalid_post', __('Participant not found.', 'projectbaldwin'), ['status' => 404]);
        }

        $participants = (array) get_post_meta($round_id, self::ROUND_PARTICIPANTS_META, true);
        if (!in_array($post_id, $participants, true)) {
            return new WP_Error('pb_not_in_round', __('Participant is not registered in this voting round.', 'projectbaldwin'), ['status' => 400]);
        }

        $table = self::get_vote_table();

        // Ensure table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return new WP_Error('pb_missing_table', __('Vote table not found. Please contact the site administrator.', 'projectbaldwin'), ['status' => 500]);
        }

        $now_gmt = current_time('mysql', true);
        $inserted = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (round_ref, post_ref, votes, last_vote_at)
                 VALUES (%s, %s, 1, %s)
                 ON DUPLICATE KEY UPDATE votes = votes + 1, last_vote_at = VALUES(last_vote_at)",
                $round_ref,
                $post_ref,
                $now_gmt
            )
        );

        if ($inserted === false) {
            global $wpdb;
            return new WP_Error(
                'pb_vote_error',
                sprintf(__('Unable to record vote. Database error: %s', 'projectbaldwin'), $wpdb->last_error),
                ['status' => 500]
            );
        }

        $totals = self::get_vote_totals($round_ref, [$post_ref]);
        $total_for_post = isset($totals[$post_ref]) ? (int) $totals[$post_ref] : 0;

        // Find rank and votes for this post in this round
        $ranked = self::get_sorted_participants($round_ref, 'recent');
        $rank = null;
        $votes = null;
        $last_vote_at = $now_gmt;
        foreach ($ranked as $row) {
            if ($row['post_ref'] === $post_ref) {
                $rank = $row['rank'];
                $votes = $row['votes'];
                // Use the consistent last_vote_at, which is always set
                $last_vote_at = $row['last_vote_at'];
                break;
            }
        }

        return rest_ensure_response([
            'round_ref' => $round_ref,
            'post_ref' => $post_ref,
            'total' => $total_for_post,
            'votes' => $votes,
            'rank' => $rank,
            'last_vote_at' => $last_vote_at,
        ]);
    }

    /**
     * REST: Return ranked or sorted results for round
     */
    public static function handle_round_totals(WP_REST_Request $request) {
        // Returns sorted/ranked results for a voting round, for frontend live updates.
        $round_ref = $request->get_param('round_ref');
        $sort_mode = $request->get_param('sort');
        $sort_mode = is_string($sort_mode) ? strtolower($sort_mode) : 'recent';
        $sorted = self::get_sorted_participants($round_ref, $sort_mode);
        return rest_ensure_response([
            'round_ref' => $round_ref,
            'results' => $sorted,
        ]);
    }
    public static function refresh_round_participants(WP_REST_Request $request) {
        $round_id = (int) $request->get_param('round_id');
        if (!$round_id || get_post_type($round_id) !== 'voting_round') {
            return new WP_Error('pb_invalid_round', __('Invalid round.', 'projectbaldwin'), ['status' => 400]);
        }
        if (!current_user_can('edit_post', $round_id)) {
            return new WP_Error('pb_forbidden', __('Insufficient permissions.', 'projectbaldwin'), ['status' => 403]);
        }

        $state        = get_post_meta($round_id, self::ROUND_STATE_META, true);
        $categories   = (array) get_post_meta($round_id, self::ROUND_CATEGORY_META, true);
        $manual       = (array) get_post_meta($round_id, self::ROUND_MANUAL_META, true);
        $source_round = (int) get_post_meta($round_id, self::ROUND_SOURCE_META, true);

        $participants = self::calculate_participants($state ?: 'custom', $categories, $manual, $source_round);
        update_post_meta($round_id, self::ROUND_PARTICIPANTS_META, $participants);

        return rest_ensure_response([
            'round_id'     => $round_id,
            'participants' => array_map('intval', $participants),
            'details'      => self::format_participant_details($participants),
        ]);
    } 

    // ============================================================
    // Database Accessors & Vote Storage
    // ============================================================
    // Handles direct DB access for votes, including table creation and query helpers.

    private static function get_vote_records($round_ref, array $limit_posts = []) {
        // Returns an array of vote records for a round, optionally limited to certain posts.
        global $wpdb;

        $table = self::get_vote_table();
        $where = ['round_ref' => $round_ref];
        $values = [$round_ref];

        $sql = "SELECT post_ref, votes, last_vote_at FROM {$table} WHERE round_ref = %s";
        if (!empty($limit_posts)) {
            $placeholders = implode(',', array_fill(0, count($limit_posts), '%s'));
            $sql .= " AND post_ref IN ({$placeholders})";
            $values = array_merge($values, $limit_posts);
        }

        $prepared = $wpdb->prepare($sql, $values);
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        $records = [];
        foreach ($rows as $row) {
            $records[$row['post_ref']] = [
                'votes' => (int) $row['votes'],
                'last_vote_at' => self::normalize_gmt_datetime($row['last_vote_at'] ?? ''),
            ];
        }
        return $records;
    }

    private static function normalize_gmt_datetime($mysql_datetime) {
        // Converts MySQL datetime to ISO8601 in GMT, or null if invalid.
        if (empty($mysql_datetime)) {
            return null;
        }

        $timestamp = strtotime($mysql_datetime . ' UTC');
        if ($timestamp === false) {
            return null;
        }

        return gmdate('c', $timestamp);
    }

    private static function get_vote_totals($round_ref, array $limit_posts = []) {
        // Returns an array of post_ref => total votes for a round.
        $records = self::get_vote_records($round_ref, $limit_posts);

        $totals = [];
        foreach ($records as $post_ref => $data) {
            $totals[$post_ref] = $data['votes'];
        }

        return $totals;
    }

    public static function get_round_vote_totals($round_ref) {
        return self::get_vote_totals($round_ref);
    }

    public static function get_post_reference($post_id) {
        return get_post_meta($post_id, self::REF_META_KEY, true);
    }

    private static function get_vote_table() {
        global $wpdb;
        return $wpdb->prefix . 'pb_votes';
    }

    /**
     * Ensures the vote table exists with (round_ref, post_ref) as unique/primary key.
     */
    private static function maybe_create_vote_table() {
        // Creates the votes table if it does not exist.
        global $wpdb;
        $table = self::get_vote_table();
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            round_ref varchar(100) NOT NULL,
            post_ref varchar(100) NOT NULL,
            votes int(11) NOT NULL DEFAULT 0,
            last_vote_at datetime NOT NULL,
            PRIMARY KEY (round_ref, post_ref)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private static function get_round_id_by_reference($round_ref) {
        // Looks up a voting round post ID by its reference string.
        $round = get_posts([
            'post_type' => 'voting_round',
            'post_status' => ['publish', 'draft'],
            'meta_key' => self::ROUND_REF_META,
            'meta_value' => $round_ref,
            'fields' => 'ids',
            'numberposts' => 1,
        ]);

        return $round ? (int) $round[0] : 0;
    }

    private static function get_post_id_by_reference($post_ref) {
        // Looks up a participant post ID by its reference string.
        $post = get_posts([
            'post_type' => self::get_available_votable_types(),
            'post_status' => ['publish', 'draft'],
            'meta_key' => self::REF_META_KEY,
            'meta_value' => $post_ref,
            'fields' => 'ids',
            'numberposts' => 1,
        ]);

        return $post ? (int) $post[0] : 0;
    }
    // ============================================================
    // Admin Pages (Overview & End Round)
    // ============================================================
    // Adds admin submenu for overview/active voting rounds and handles "end round" actions.

    public static function register_admin_pages() {
        add_submenu_page(
            'edit.php?post_type=voting_round',
            __('Voting Rounds Overview', 'projectbaldwin'),
            __('Active Rounds', 'projectbaldwin'),
            'manage_options',
            'active_voting_rounds',
            [__CLASS__, 'render_active_rounds_page']
        );
    }

    public static function render_active_rounds_page() {
        // Renders the admin page listing all voting rounds with status and actions.
        $now = current_time('timestamp');
        $rounds = get_posts([
            'post_type'   => 'voting_round',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby'     => 'meta_value',
            'meta_key'    => self::ROUND_START_META,
            'order'       => 'ASC',
        ]);
        $rest_nonce = wp_create_nonce('wp_rest');
        $rest_root  = rest_url('pb/v1/');

        echo '<div class="wrap"><h1>' . esc_html__('Voting Rounds Overview', 'projectbaldwin') . '</h1>';

        // Admin notice if present
        if (!empty($_GET['pb_notice'])) {
            $notice = sanitize_text_field($_GET['pb_notice']);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($notice) . '</p></div>';
        }

        if (empty($rounds)) {
            echo '<p>' . esc_html__('No voting rounds found.', 'projectbaldwin') . '</p></div>';
            return;
        }

        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pb-rounds-bulk-form">
            <?php wp_nonce_field('pb_bulk_rounds', 'pb_bulk_nonce'); ?>
            <input type="hidden" name="action" value="pb_bulk_voting_round_action" />
            <div class="pb-bulk-controls">
                <select name="pb_bulk_action" required>
                    <option value=""><?php esc_html_e('Bulk actions', 'projectbaldwin'); ?></option>
                    <option value="end_now"><?php esc_html_e('End selected now', 'projectbaldwin'); ?></option>
                    <option value="archive"><?php esc_html_e('Archive (set to draft)', 'projectbaldwin'); ?></option>
                </select>
                <button type="submit" class="button"><?php esc_html_e('Apply', 'projectbaldwin'); ?></button>
            </div>
            <table class="widefat fixed striped pb-rounds-table">
                <thead>
                    <tr>
                        <th class="pb-col-select"><input type="checkbox" class="pb-select-all" /></th>
                        <th><?php esc_html_e('Title', 'projectbaldwin'); ?></th>
                        <th><?php esc_html_e('Start', 'projectbaldwin'); ?></th>
                        <th><?php esc_html_e('End', 'projectbaldwin'); ?></th>
                        <th><?php esc_html_e('Status', 'projectbaldwin'); ?></th>
                        <th><?php esc_html_e('Time Remaining', 'projectbaldwin'); ?></th>
                        <th><?php esc_html_e('Live Votes & Leaders', 'projectbaldwin'); ?></th>
                        <th><?php esc_html_e('Actions', 'projectbaldwin'); ?></th>
                    </tr>
                </thead>
                <tbody>

        foreach ($rounds as $round) {
            $start = strtotime(get_post_meta($round->ID, self::ROUND_START_META, true));
            $duration = (int) get_post_meta($round->ID, self::ROUND_DURATION_META, true);
            $end_meta = get_post_meta($round->ID, '_pb_round_end', true);
            if (!empty($end_meta)) {
                $end = strtotime($end_meta);
            } else {
                $end = $start + ($duration * DAY_IN_SECONDS);
            }
            $round_ref = get_post_meta($round->ID, self::ROUND_REF_META, true);

            // All display logic uses $end above
            if ($now < $start) {
                $status = '<span class="pb-status-badge pb-status-upcoming">' . esc_html__('Upcoming', 'projectbaldwin') . '</span>';
                $time_remaining = 'Starts in ' . human_time_diff($now, $start);
            } elseif ($now >= $start && $now <= $end) {
                $status = '<span class="pb-status-badge pb-status-active">' . esc_html__('Active', 'projectbaldwin') . '</span>';
                $time_remaining = human_time_diff($now, $end) . ' left';
            } else {
                $status = '<span class="pb-status-badge pb-status-expired">' . esc_html__('Expired', 'projectbaldwin') . '</span>';
                $time_remaining = 'Ended ' . human_time_diff($end, $now) . ' ago';
            }

            $row_attrs = '';
            if ($round_ref) {
                $row_attrs = ' data-round-ref="' . esc_attr($round_ref) . '" data-round-id="' . esc_attr($round->ID) . '"';
            }

            echo '<tr class="pb-round-row"' . $row_attrs . '>';
            echo '<td class="pb-col-select"><input type="checkbox" name="round_ids[]" value="' . esc_attr($round->ID) . '" /></td>';
            echo '<td><a href="' . esc_url(get_edit_post_link($round->ID)) . '">' . esc_html(get_the_title($round)) . '</a></td>';
            echo '<td>' . esc_html(date('Y-m-d H:i', $start)) . '</td>';
            echo '<td>' . esc_html(date('Y-m-d H:i', $end)) . '</td>';
            echo '<td>' . $status . '</td>';
            echo '<td>' . esc_html($time_remaining) . '</td>';
            echo '<td>';
            if ($round_ref) {
                echo '<div class="pb-round-live" data-round-ref="' . esc_attr($round_ref) . '">' . esc_html__('Loading…', 'projectbaldwin') . '</div>';
            } else {
                echo '<em>' . esc_html__('Waiting for reference', 'projectbaldwin') . '</em>';
            }
            echo '</td>';
            // Actions column
            echo '<td class="pb-round-actions">';
            $permalink = get_permalink($round->ID);
            if ($permalink) {
                echo '<a class="button button-small" href="' . esc_url($permalink) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('View Round', 'projectbaldwin') . '</a> ';
            }
            if ($now >= $start && $now <= $end) {
                $url = admin_url('admin-post.php?action=pb_end_voting_round&round_id=' . $round->ID . '&_wpnonce=' . wp_create_nonce('pb_end_round_' . $round->ID));
                echo '<a class="button button-small button-danger" href="' . esc_url($url) . '" onclick="return confirm(\'' . esc_js(__('Are you sure you want to end this round now?', 'projectbaldwin')) . '\');">' . esc_html__('End Now', 'projectbaldwin') . '</a>';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</form>';
        ?>
        <style>
            .pb-status-badge {
                display:inline-block;
                padding:2px 8px;
                border-radius:12px;
                font-size:12px;
                font-weight:600;
                line-height:1.5;
                text-transform:uppercase;
            }
            .pb-status-active {background:#e6f4ea;color:#137333;}
            .pb-status-upcoming {background:#fff4e5;color:#c47f00;}
            .pb-status-expired {background:#fdecea;color:#c5221f;}
            .pb-round-live {font-size:13px;line-height:1.5;}
            .pb-round-live strong {display:block;font-size:14px;}
            .pb-round-live ul {margin:6px 0 0 16px;padding:0;}
            .pb-round-live li {margin:0;padding:0;}
            .pb-round-actions .button-danger {
                background:#c5221f;
                border-color:#c5221f;
                color:#fff;
            }
            .pb-round-actions .button-danger:hover {
                background:#a31812;
                border-color:#a31812;
            }
            .pb-rounds-bulk-form {
                margin-top:15px;
            }
            .pb-bulk-controls {
                margin-bottom:10px;
            }
            .pb-bulk-controls select {
                min-width:200px;
            }
            .pb-col-select {
                width:36px;
            }
            .pb-col-select input[type="checkbox"] {
                margin:0 auto;
                display:block;
            }
        </style>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            var liveCells = document.querySelectorAll('.pb-round-live[data-round-ref]');
            if (!liveCells.length) {
                return;
            }
            var restRoot = <?php echo wp_json_encode($rest_root); ?>;
            var nonce = <?php echo wp_json_encode($rest_nonce); ?>;
            var noVotesText = <?php echo wp_json_encode(__('No votes yet', 'projectbaldwin')); ?>;
            var votesLabel = <?php echo wp_json_encode(__('votes total', 'projectbaldwin')); ?>;
            var masterCheckbox = document.querySelector('.pb-select-all');
            if (masterCheckbox) {
                masterCheckbox.addEventListener('change', function(){
                    var checkboxes = document.querySelectorAll('.pb-col-select input[type="checkbox"][name="round_ids[]"]');
                    checkboxes.forEach(function(cb){ cb.checked = masterCheckbox.checked; });
                });
            }

            liveCells.forEach(function(cell){
                var ref = cell.getAttribute('data-round-ref');
                if (!ref) {
                    cell.textContent = noVotesText;
                    return;
                }
                fetch(restRoot + 'round/' + encodeURIComponent(ref) + '/totals?sort=highest', {
                    headers: { 'X-WP-Nonce': nonce }
                }).then(function(response){
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                }).then(function(payload){
                    if (!payload || !Array.isArray(payload.results) || !payload.results.length) {
                        cell.textContent = noVotesText;
                        return;
                    }
                    var totalVotes = 0;
                    var leaders = [];
                    payload.results.forEach(function(result, index){
                        var votes = parseInt(result.votes, 10) || 0;
                        totalVotes += votes;
                        if (leaders.length < 3) {
                            var label = result.post_title || result.post_ref || ('ID ' + (result.post_id || ''));
                            leaders.push((index + 1) + '. ' + label + ' (' + votes + ')');
                        }
                    });
                    var html = '<strong>' + totalVotes + ' ' + votesLabel + '</strong>';
                    if (leaders.length) {
                        html += '<ul><li>' + leaders.join('</li><li>') + '</li></ul>';
                    }
                    cell.innerHTML = html;
                }).catch(function(){
                    cell.textContent = '<?php echo esc_js(__('Unable to load totals', 'projectbaldwin')); ?>';
                });
            });
        });
        </script>
        <?php
        echo '</div>';
    }

    /**
     * Handles the admin action to end a voting round early.
     */
    public static function handle_end_voting_round() {
        // Handles the admin "End Now" action for a voting round.
        if (!isset($_GET['round_id'], $_GET['_wpnonce'])) {
            wp_die(__('Missing parameters.', 'projectbaldwin'));
        }
        $round_id = intval($_GET['round_id']);
        if (!current_user_can('edit_post', $round_id)) {
            wp_die(__('You do not have permission to end this voting round.', 'projectbaldwin'));
        }
        $nonce_action = 'pb_end_round_' . $round_id;
        if (!wp_verify_nonce($_GET['_wpnonce'], $nonce_action)) {
            wp_die(__('Nonce verification failed.', 'projectbaldwin'));
        }

        $start_str = get_post_meta($round_id, self::ROUND_START_META, true);
        $start_ts = strtotime($start_str);
        $now_ts = current_time('timestamp');
        if (!$start_ts || $now_ts < $start_ts) {
            // Invalid start or not actually started
            $redirect = add_query_arg('pb_notice', urlencode(__('Round cannot be ended at this time.', 'projectbaldwin')), admin_url('edit.php?post_type=voting_round&page=active_voting_rounds'));
            wp_safe_redirect($redirect);
            exit;
        }
        // Set the end timestamp to now, and adjust duration accordingly so that end = now
        $duration_days = max(1, ceil(($now_ts - $start_ts) / DAY_IN_SECONDS));
        update_post_meta($round_id, self::ROUND_DURATION_META, $duration_days);

        // Snapshot final standings
        $round_ref = get_post_meta($round_id, self::ROUND_REF_META, true);
        if ($round_ref) {
            self::snapshot_round_results($round_id, $round_ref);
        }

        $redirect = add_query_arg('pb_notice', urlencode(__('Voting round ended early.', 'projectbaldwin')), admin_url('edit.php?post_type=voting_round&page=active_voting_rounds'));
        wp_safe_redirect($redirect);
        exit;
    }

    public static function handle_bulk_voting_round_action() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to manage voting rounds.', 'projectbaldwin'));
        }
        if (!isset($_POST['pb_bulk_nonce']) || !wp_verify_nonce($_POST['pb_bulk_nonce'], 'pb_bulk_rounds')) {
            wp_die(__('Nonce verification failed.', 'projectbaldwin'));
        }
        $action = isset($_POST['pb_bulk_action']) ? sanitize_key($_POST['pb_bulk_action']) : '';
        $round_ids = isset($_POST['round_ids']) ? array_map('intval', (array) $_POST['round_ids']) : [];

        if (!$action || empty($round_ids)) {
            $redirect = add_query_arg('pb_notice', urlencode(__('No action performed. Select at least one round and an action.', 'projectbaldwin')), admin_url('edit.php?post_type=voting_round&page=active_voting_rounds'));
            wp_safe_redirect($redirect);
            exit;
        }

        $success = 0;
        $failed = 0;
        foreach ($round_ids as $round_id) {
            if (get_post_type($round_id) !== 'voting_round') {
                $failed++;
                continue;
            }
            if ($action === 'end_now') {
                if (self::force_end_round($round_id)) {
                    $success++;
                } else {
                    $failed++;
                }
            } elseif ($action === 'archive') {
                $archive = wp_update_post([
                    'ID' => $round_id,
                    'post_status' => 'draft',
                ], true);
                if (!is_wp_error($archive)) {
                    $success++;
                } else {
                    $failed++;
                }
            }
        }

        $message = sprintf(
            /* translators: 1: number succeeded, 2: number failed */
            __('Bulk action complete. %1$d succeeded, %2$d failed.', 'projectbaldwin'),
            $success,
            $failed
        );

        $redirect = add_query_arg('pb_notice', urlencode($message), admin_url('edit.php?post_type=voting_round&page=active_voting_rounds'));
        wp_safe_redirect($redirect);
        exit;
    }

    private static function force_end_round($round_id) {
        $start_str = get_post_meta($round_id, self::ROUND_START_META, true);
        $start_ts = strtotime($start_str);
        $now_ts = current_time('timestamp');
        if (!$start_ts || $now_ts < $start_ts) {
            return false;
        }

        $duration_days = max(1, ceil(($now_ts - $start_ts) / DAY_IN_SECONDS));
        update_post_meta($round_id, self::ROUND_DURATION_META, $duration_days);
        update_post_meta($round_id, '_pb_round_end', date('Y-m-d\TH:i', $now_ts));

        $round_ref = get_post_meta($round_id, self::ROUND_REF_META, true);
        if ($round_ref) {
            self::snapshot_round_results($round_id, $round_ref);
        }

        return true;
    }
}
