<?php
/**
 * Voting service bootstrap for Project Baldwin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PB_Voting_Service {
    // ------------------------
    // Initialization & Setup
    // ------------------------
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

    public static function init() {
        add_action('init', [__CLASS__, 'register_meta']);
        add_action('add_meta_boxes', [__CLASS__, 'register_meta_boxes']);
        add_action('save_post', [__CLASS__, 'maybe_assign_reference_id'], 10, 2);
        add_action('save_post', [__CLASS__, 'persist_in_voting_flag'], 10, 2);
        add_action('save_post_voting_round', [__CLASS__, 'save_voting_round_meta'], 10, 2);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        add_action('admin_menu', [__CLASS__, 'register_admin_pages']);
        add_action('admin_post_pb_end_voting_round', [__CLASS__, 'handle_end_voting_round']);
    }

    // ------------------------
    // Register Meta Fields
    // ------------------------
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
    }

    // ------------------------
    // Admin Meta Boxes
    // ------------------------
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
        wp_nonce_field('pb_voting_round_meta', 'pb_voting_round_nonce');

        $state = get_post_meta($post->ID, self::ROUND_STATE_META, true) ?: 'custom';
        $start = get_post_meta($post->ID, self::ROUND_START_META, true);
        $duration = get_post_meta($post->ID, self::ROUND_DURATION_META, true);
        $end = get_post_meta($post->ID, '_pb_round_end', true);
        $selected_categories = (array) get_post_meta($post->ID, self::ROUND_CATEGORY_META, true);
        $source_round = (int) get_post_meta($post->ID, self::ROUND_SOURCE_META, true);
        $manual_participants = (array) get_post_meta($post->ID, self::ROUND_MANUAL_META, true);
        $participants = (array) get_post_meta($post->ID, self::ROUND_PARTICIPANTS_META, true);
        $round_ref = get_post_meta($post->ID, self::ROUND_REF_META, true);

        $categories = get_terms([
            'taxonomy' => 'pb_category',
            'hide_empty' => false,
        ]);

        $rounds = get_posts([
            'post_type' => 'voting_round',
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
            'exclude' => [$post->ID],
        ]);

        $eligible_posts = self::fetch_manual_participant_options();
        ?>
        <p>
            <strong><?php esc_html_e('Round Type', 'projectbaldwin'); ?></strong><br />
            <?php foreach (self::$round_states as $option) : ?>
                <label style="margin-right:15px;">
                    <input type="radio" name="pb_round_state" value="<?php echo esc_attr($option); ?>" <?php checked($state, $option); ?> />
                    <?php echo esc_html(ucfirst($option)); ?>
                </label>
            <?php endforeach; ?>
        </p>

        <p>
            <label for="pb_round_start"><strong><?php esc_html_e('Start (date and time, site timezone)', 'projectbaldwin'); ?></strong></label><br />
            <input type="datetime-local" class="widefat" name="pb_round_start" id="pb_round_start" value="<?php echo esc_attr($start); ?>" />
        </p>

        <p>
            <label for="pb_round_duration"><strong><?php esc_html_e('Duration (days)', 'projectbaldwin'); ?></strong></label><br />
            <input type="number" class="widefat" name="pb_round_duration" id="pb_round_duration" value="<?php echo esc_attr($duration); ?>" min="1" step="1" />
        </p>

        <p>
            <label for="pb_round_end"><strong><?php esc_html_e('End (date and time, site timezone)', 'projectbaldwin'); ?></strong></label><br />
            <input type="datetime-local" class="widefat" name="pb_round_end" id="pb_round_end" value="<?php echo esc_attr($end); ?>" />
        </p>

        <div style="margin-bottom:20px;">
            <p><strong><?php esc_html_e('Nomination Categories (up to 12)', 'projectbaldwin'); ?></strong></p>
            <select name="pb_round_categories[]" multiple class="widefat" size="6">
                <?php foreach ($categories as $term) : ?>
                    <option value="<?php echo esc_attr($term->term_id); ?>" <?php selected(in_array($term->term_id, $selected_categories, true)); ?>>
                        <?php echo esc_html($term->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small><?php esc_html_e('Used only when Nomination is selected.', 'projectbaldwin'); ?></small>
        </div>

        <div style="margin-bottom:20px;">
            <p><strong><?php esc_html_e('Final Round Source', 'projectbaldwin'); ?></strong></p>
            <select name="pb_round_source" class="widefat">
                <option value="">— <?php esc_html_e('Select previous round', 'projectbaldwin'); ?> —</option>
                <?php foreach ($rounds as $round) : ?>
                    <?php $ref = get_post_meta($round->ID, self::ROUND_PARTICIPANTS_META, true); ?>
                    <option value="<?php echo esc_attr($round->ID); ?>" <?php selected($source_round, $round->ID); ?>>
                        <?php echo esc_html(get_the_title($round)); ?><?php echo $ref ? ' (' . esc_html(count((array) $ref)) . ')' : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small><?php esc_html_e('Used when Final is selected to pull top participants.', 'projectbaldwin'); ?></small>
        </div>

        <div style="margin-bottom:20px;">
            <p><strong><?php esc_html_e('Custom Participant Override', 'projectbaldwin'); ?></strong></p>
            <select name="pb_round_manual_participants[]" multiple class="widefat" size="8">
                <?php foreach ($eligible_posts as $row) : ?>
                    <option value="<?php echo esc_attr($row['ID']); ?>" <?php selected(in_array($row['ID'], $manual_participants, true)); ?>>
                        <?php echo esc_html($row['label']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small><?php esc_html_e('Used when Custom is selected to define participants explicitly.', 'projectbaldwin'); ?></small>
        </div>

        <?php if (!empty($participants)) : ?>
            <div class="notice notice-info" style="padding:12px;margin:0;background:#f0f6fc;border:1px solid #c8d7eb;">
                <strong><?php esc_html_e('Cached Participants', 'projectbaldwin'); ?>:</strong>
                <ul style="margin:8px 0 0 18px;">
                    <?php foreach ($participants as $participant_id) :
                        $participant = get_post($participant_id);
                        if (!$participant) {
                            continue;
                        }
                        echo '<li>' . esc_html(get_the_title($participant)) . ' (' . esc_html($participant->post_type) . ')</li>';
                    endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <p><strong><?php esc_html_e('Round Reference', 'projectbaldwin'); ?>:</strong> <?php echo esc_html($round_ref ?: __('(will generate on save)', 'projectbaldwin')); ?></p>
        <?php
    }

    // ------------------------
    // Save Post Hooks
    // ------------------------
    public static function maybe_assign_reference_id($post_id, $post) {
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

        $participants = self::calculate_participants($state, $categories, $manual, $source_round);
        update_post_meta($post_id, self::ROUND_PARTICIPANTS_META, $participants);
    }

    // ------------------------
    // Participant Logic (Nomination, Final, Custom)
    // ------------------------
    private static function calculate_participants($state, array $categories, array $manual, $source_round_id) {
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
        if (empty($categories)) {
            return [];
        }

        $post_types = self::get_available_votable_types();
        if (empty($post_types)) {
            return [];
        }

        $query = new WP_Query([
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => [
                [
                    'taxonomy' => 'pb_category',
                    'field' => 'term_id',
                    'terms' => $categories,
                ],
            ],
        ]);

        return $query->posts ?: [];
    }

    private static function fetch_finalists($source_round_id) {
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

    // ------------------------
    // Helper Functions
    // ------------------------
    private static function should_handle_post($post_id, $post) {
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

    // ------------------------
    // Ranking & Snapshot Helpers
    // ------------------------

    /**
     * Get ranked participants for a round reference, with live votes and rank.
     *
     * @param string $round_ref
     * @return array Array of arrays: [ 'post_id', 'post_ref', 'votes', 'rank' ]
     */
    public static function get_ranked_participants($round_ref) {
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
            $rank_rows[] = [
                'post_id' => $pid,
                'post_ref' => $ref,
                'votes' => $votes,
                'last_vote_at' => $last_vote_at,
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
        // Delegate to PB_Voting_Sort for sorting logic
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
        $ranked = self::get_ranked_participants($round_ref);
        // Store just the post_ids in order as legacy, but also full ranking
        $result_ids = array_map(function($row) { return $row['post_id']; }, $ranked);
        update_post_meta($round_id, '_pb_round_results', $result_ids);
        update_post_meta($round_id, '_pb_round_rankings', $ranked);
    }

    public static function get_available_votable_types() {
        return array_values(array_filter(self::$eligible_post_types, 'post_type_exists'));
    }

    // ------------------------
    // REST API Routes
    // ------------------------
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
    }

    // ------------------------
    // Vote Handling
    // ------------------------
    public static function handle_vote_submission(WP_REST_Request $request) {
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
        $round_ref = $request->get_param('round_ref');
        $sort_mode = $request->get_param('sort');
        $sort_mode = is_string($sort_mode) ? strtolower($sort_mode) : 'recent';
        $sorted = self::get_sorted_participants($round_ref, $sort_mode);
        return rest_ensure_response([
            'round_ref' => $round_ref,
            'results' => $sorted,
        ]);
    }

    // ------------------------
    // Database Accessors
    // ------------------------
    private static function get_vote_records($round_ref, array $limit_posts = []) {
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
    // ------------------------
    // Admin Pages (Active/Overview Rounds)
    // ------------------------
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
        $now = current_time('timestamp');
        $rounds = get_posts([
            'post_type'   => 'voting_round',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby'     => 'meta_value',
            'meta_key'    => self::ROUND_START_META,
            'order'       => 'ASC',
        ]);

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

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Title</th><th>Start</th><th>End</th><th>Status</th><th>Time Remaining</th><th>Actions</th></tr></thead><tbody>';

        foreach ($rounds as $round) {
            $start = strtotime(get_post_meta($round->ID, self::ROUND_START_META, true));
            $duration = (int) get_post_meta($round->ID, self::ROUND_DURATION_META, true);
            $end_meta = get_post_meta($round->ID, '_pb_round_end', true);
            if (!empty($end_meta)) {
                $end = strtotime($end_meta);
            } else {
                $end = $start + ($duration * DAY_IN_SECONDS);
            }

            // All display logic uses $end above
            if ($now < $start) {
                $status = '<span style="color:#e0a800; font-weight:bold;">Upcoming</span>';
                $time_remaining = 'Starts in ' . human_time_diff($now, $start);
            } elseif ($now >= $start && $now <= $end) {
                $status = '<span style="color:green; font-weight:bold;">Active</span>';
                $time_remaining = human_time_diff($now, $end) . ' left';
            } else {
                $status = '<span style="color:red; font-weight:bold;">Expired</span>';
                $time_remaining = 'Ended ' . human_time_diff($end, $now) . ' ago';
            }

            echo '<tr>';
            echo '<td><a href="' . esc_url(get_edit_post_link($round->ID)) . '">' . esc_html(get_the_title($round)) . '</a></td>';
            echo '<td>' . esc_html(date('Y-m-d H:i', $start)) . '</td>';
            echo '<td>' . esc_html(date('Y-m-d H:i', $end)) . '</td>';
            echo '<td>' . $status . '</td>';
            echo '<td>' . esc_html($time_remaining) . '</td>';
            // Actions column
            echo '<td>';
            if ($now >= $start && $now <= $end) {
                $url = admin_url('admin-post.php?action=pb_end_voting_round&round_id=' . $round->ID . '&_wpnonce=' . wp_create_nonce('pb_end_round_' . $round->ID));
                echo '<a href="' . esc_url($url) . '" style="color:#dc3232;font-weight:bold;" onclick="return confirm(\'Are you sure you want to end this round now?\');">' . esc_html__('End Now', 'projectbaldwin') . '</a>';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    /**
     * Handles the admin action to end a voting round early.
     */
    public static function handle_end_voting_round() {
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
}
