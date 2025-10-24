<?php
/**
 * Template: single-voting_round.php
 *
 * Responsibility:
 * - Displays a single Voting Round, including round info, participants, and voting UI.
 * - Interacts with PB_Voting_Service for participant, reference, and vote data.
 * - Uses REST API endpoints for live vote updates and sorting.
 * Template for displaying a single Voting Round.
 */

if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_script('wp-api');

get_header();
?>

<div id="primary" class="content-area voting-round">
    <main id="main" class="site-main">
        <?php if (have_posts()) : ?>
            <?php while (have_posts()) : the_post();
                // --- Voting Round Data Load ---
                $round_id = get_the_ID();
                $round_ref = get_post_meta($round_id, '_pb_round_ref', true);
                $participants = (array) get_post_meta($round_id, '_pb_round_participants', true);
                $participant_posts = [];
                // Load participant post objects for display
                if (!empty($participants)) {
                    $participant_posts = get_posts([
                        'post_type' => PB_Voting_Service::get_available_votable_types(),
                        'post__in' => $participants,
                        'orderby' => 'post__in',
                        'posts_per_page' => count($participants),
                    ]);
                }
                // Fetch current vote totals for each participant
                $vote_totals = [];
                if ($round_ref) {
                    $vote_totals = PB_Voting_Service::get_round_vote_totals($round_ref);
                }
                $rest_nonce = wp_create_nonce('wp_rest');
                ?>

                <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                    <header class="entry-header">
                        <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
                        <?php if ($round_ref) : ?>
                            <p class="round-reference"><strong><?php esc_html_e('Reference:', 'projectbaldwin'); ?></strong> <?php echo esc_html($round_ref); ?></p>
                        <?php endif; ?>
                    </header>

                    <?php
                    // --- Voting round time info ---
                    $round_start = get_post_meta($round_id, '_pb_round_start', true);
                    $round_duration = (int) get_post_meta($round_id, '_pb_round_duration', true);
                    $round_end_meta = get_post_meta($round_id, '_pb_round_end', true);
                    $round_end = '';
                    $start_dt = $round_start ? strtotime($round_start) : false;
                    if ($round_end_meta) {
                        // Use explicit round end if present
                        $round_end = strtotime($round_end_meta);
                    } elseif ($start_dt && $round_duration) {
                        // Fallback to start + duration
                        $round_end = $start_dt + ($round_duration * DAY_IN_SECONDS);
                    }
                    ?>
                    <?php if ($round_start && $round_end): ?>
                    <div class="pb-round-times" data-end="<?php echo esc_attr($round_end); ?>">
                        <p><strong><?php esc_html_e('Starts:', 'projectbaldwin'); ?></strong> <?php echo esc_html(date_i18n('Y-m-d H:i', $start_dt)); ?></p>
                        <p><strong><?php esc_html_e('Ends:', 'projectbaldwin'); ?></strong> <?php echo esc_html(date_i18n('Y-m-d H:i', $round_end)); ?></p>
                        <p><strong><?php esc_html_e('Time Remaining:', 'projectbaldwin'); ?></strong> <span id="pb-countdown"></span></p>
                    </div>
                    <?php endif; ?>

                    <div class="entry-content">
                        <?php the_content(); ?>
                    </div>

                    <?php if (empty($participant_posts)) : ?>
                        <p class="notice notice-warning"><?php esc_html_e('No participants are currently linked to this round.', 'projectbaldwin'); ?></p>
                    <?php else : ?>
                        <!-- Participants Section: Displays each participant and handles voting UI -->
                        <section class="pb-round-participants" data-round-ref="<?php echo esc_attr($round_ref); ?>">
                            <h2><?php esc_html_e('Participants', 'projectbaldwin'); ?></h2>
                            <label for="pb-sort-participants" style="display:block;margin-bottom:0.5em;">
                                <strong><?php esc_html_e('Sort by:', 'projectbaldwin'); ?></strong>
                                <select id="pb-sort-participants" style="margin-left:0.5em;">
                                    <option value="recent"><?php esc_html_e('Recent', 'projectbaldwin'); ?></option>
                                    <option value="highest"><?php esc_html_e('Highest', 'projectbaldwin'); ?></option>
                                    <option value="lowest"><?php esc_html_e('Lowest', 'projectbaldwin'); ?></option>
                                    <option value="tiebreaker"><?php esc_html_e('Tie Breaker 🎉', 'projectbaldwin'); ?></option>
                                </select>
                            </label>
                            <div id="pb-participants-grid-container">
                                <div class="pb-participants-grid">
                                   <?php foreach ($participant_posts as $participant_post) :
                                        $participant_ref = PB_Voting_Service::get_post_reference($participant_post->ID);
                                        $total_votes = isset($vote_totals[$participant_ref]) ? (int) $vote_totals[$participant_ref] : 0;
                                        ?>
                                        <article class="pb-participant" data-post-ref="<?php echo esc_attr($participant_ref); ?>">
                                            <h3><a href="<?php echo esc_url(get_permalink($participant_post)); ?>"><?php echo esc_html(get_the_title($participant_post)); ?></a></h3>
                                            <div class="pb-participant-ref">
                                                <strong><?php esc_html_e('Reference ID:', 'projectbaldwin'); ?></strong>
                                                <?php echo esc_html($participant_ref); ?>
                                            </div>
                                            <div class="pb-participant-excerpt"><?php echo esc_html(get_the_excerpt($participant_post)); ?></div>
                                            <div class="pb-participant-meta">
                                                <span class="pb-participant-type"><?php echo esc_html(ucfirst($participant_post->post_type)); ?></span>
                                                <span class="pb-participant-total" data-total><?php printf(esc_html__('%d votes', 'projectbaldwin'), $total_votes); ?></span>
                                                <span class="pb-participant-rank" data-rank></span>
                                            </div>
                                            <button class="pb-vote-button" type="button" data-round-ref="<?php echo esc_attr($round_ref); ?>" data-post-ref="<?php echo esc_attr($participant_ref); ?>">
                                                <?php esc_html_e('Vote', 'projectbaldwin'); ?>
                                            </button>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </section>
                    <?php endif; ?>
                </article>

                <script>
                (function(){
                    // --- Voting Round JS: Handles voting, sorting, and live updating UI ---
                    const restRoot = <?php echo wp_json_encode(rest_url('pb/v1/')); ?>;
                    const nonce = <?php echo wp_json_encode($rest_nonce); ?>;
                    const roundRef = <?php echo wp_json_encode($round_ref); ?>;

                    // Update vote total in DOM
                    function updateVoteTotal(container, total) {
                        const totalEl = container.querySelector('[data-total]');
                        if (totalEl) {
                            totalEl.textContent = total + ' <?php echo esc_js(__('votes', 'projectbaldwin')); ?>';
                        }
                    }
                    // Update rank in DOM
                    function updateRank(container, rank) {
                        const rankEl = container.querySelector('[data-rank]');
                        if (rankEl) {
                            if (rank !== null && rank !== undefined) {
                                rankEl.textContent = 'Rank: #' + rank;
                            } else {
                                rankEl.textContent = '';
                            }
                        }
                    }

                    // Reorder DOM articles to match API order from backend
                    function reorderParticipantsGrid(results) {
                        var gridContainer = document.getElementById('pb-participants-grid-container');
                        if (!gridContainer) return;
                        var grid = gridContainer.querySelector('.pb-participants-grid');
                        if (!grid) return;
                        if (!Array.isArray(results)) return;
                        // Map post_ref to article
                        var articleMap = {};
                        var articles = grid.querySelectorAll('.pb-participant');
                        articles.forEach(function(article){
                            var postRef = article.getAttribute('data-post-ref');
                            if (postRef) articleMap[postRef] = article;
                        });
                        // Append in API order
                        results.forEach(function(r){
                            if (r && r.post_ref && articleMap[r.post_ref]) {
                                grid.appendChild(articleMap[r.post_ref]);
                            }
                        });
                    }

                    // Fetch updated totals and ranks from backend and update DOM
                    function refreshTotalsAndRanks() {
                        var sortSelect = document.getElementById('pb-sort-participants');
                        var sortValue = sortSelect ? sortSelect.value : 'recent';
                        var url = restRoot + 'round/' + encodeURIComponent(roundRef) + '/totals?sort=' + encodeURIComponent(sortValue);
                        fetch(url, {
                            headers: {}
                        }).then(function(response){
                            if (!response.ok) throw new Error('Failed to fetch totals');
                            return response.json();
                        }).then(function(data){
                            if (!Array.isArray(data.results)) return;
                            data.results.forEach(function(result){
                                if (!result || !result.post_ref) return;
                                const participant = document.querySelector('.pb-participant[data-post-ref="' + result.post_ref + '"]');
                                if (participant) {
                                    updateVoteTotal(participant, result.votes);
                                    updateRank(participant, result.rank);
                                    // Always set last_vote_at if present
                                    if (result.last_vote_at) {
                                        participant.setAttribute('data-last-vote-at', result.last_vote_at);
                                    }
                                }
                            });
                            // Only reorder according to backend order; no local sorting
                            reorderParticipantsGrid(data.results);
                        }).catch(console.error);
                    }

                    // Voting button handler
                    document.addEventListener('click', function(evt) {
                        const target = evt.target.closest('.pb-vote-button');
                        if (!target) return;

                        const postRef = target.getAttribute('data-post-ref');
                        const roundRefLocal = target.getAttribute('data-round-ref');
                        if (!postRef || !roundRefLocal) return;

                        target.disabled = true;

                        fetch(restRoot + 'vote', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                post_ref: postRef,
                                round_ref: roundRefLocal
                            })
                        }).then(function(response){
                            if (!response.ok) throw new Error('Vote failed');
                            return response.json();
                        }).then(function(data){
                            const container = target.closest('.pb-participant');
                            if (container) {
                                if (typeof data.votes === 'number') {
                                    updateVoteTotal(container, data.votes);
                                }
                                if (typeof data.rank === 'number') {
                                    updateRank(container, data.rank);
                                }
                                // Update last_vote_at immediately after voting
                                if (data.last_vote_at) {
                                    container.setAttribute('data-last-vote-at', data.last_vote_at);
                                } else {
                                    // Fallback: use current timestamp
                                    container.setAttribute('data-last-vote-at', new Date().toISOString());
                                }
                            }
                            // Refresh totals and ranks immediately after updating DOM
                            refreshTotalsAndRanks();
                        }).catch(function(error){
                            console.error(error);
                            alert('<?php echo esc_js(__('There was a problem submitting your vote. Please try again.', 'projectbaldwin')); ?>');
                        }).finally(function(){
                            target.disabled = false;
                        });
                    });

                    // Restore sort mode from localStorage if present
                    var sortSelect = document.getElementById('pb-sort-participants');
                    if (sortSelect) {
                        var savedSortMode = localStorage.getItem('pbSortMode');
                        if (savedSortMode && sortSelect.value !== savedSortMode) {
                            sortSelect.value = savedSortMode;
                        }
                        sortSelect.addEventListener('change', function() {
                            localStorage.setItem('pbSortMode', this.value);
                            refreshTotalsAndRanks();
                        });
                    }
                    refreshTotalsAndRanks();
                    setInterval(refreshTotalsAndRanks, 2000);

                    // Countdown timer for round end
                    const countdownEl = document.getElementById('pb-countdown');
                    if (countdownEl) {
                        const endTs = parseInt(document.querySelector('.pb-round-times').getAttribute('data-end')) * 1000;
                        function updateCountdown() {
                            const now = new Date().getTime();
                            let distance = endTs - now;
                            if (distance < 0) distance = 0;
                            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                            countdownEl.textContent = days + 'd:' + ('0' + hours).slice(-2) + ':' + ('0' + minutes).slice(-2) + ':' + ('0' + seconds).slice(-2);
                        }
                        updateCountdown();
                        setInterval(updateCountdown, 1000);
                    }
                })();
                </script>
            <?php endwhile; ?>
        <?php else : ?>
            <p><?php esc_html_e('Voting round not found.', 'projectbaldwin'); ?></p>
        <?php endif; ?>
    </main>
</div>

<?php
get_footer();