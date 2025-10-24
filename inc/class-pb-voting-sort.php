<?php
/**
 * PB_Voting_Sort: Handles sorting of ranked voting participants for Project Baldwin.
 *
 * Responsibilities:
 * - Sorts participant arrays by different strategies (recent, highest, lowest, tiebreaker).
 * - Interacts with PB_Voting_Service to obtain ranked participants.
 * - TODO: Consider moving rank assignment fully to this class for single responsibility.
 * PB_Voting_Sort: Handles sorting of ranked voting participants for Project Baldwin.
 *
 * This file defines the PB_Voting_Sort class, which provides multiple sorting strategies
 * for ranked voting participants, including recent, highest, lowest, and tiebreaker sorts.
 * It interacts with PB_Voting_Service to fetch ranked participants and is used by the REST
 * endpoints and admin UI for displaying sorted voting results.
 * TODO: Consider moving rank assignment fully to this class for single responsibility.
 * Voting Sort class for Project Baldwin.
 * Handles sorting of ranked voting participants.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PB_Voting_Sort {
    // ============================================================
    // Sorting Entrypoint
    // ============================================================

    /**
     * Apply the requested sort mode to the ranked participants for a round.
     *
     * @param string $round_ref
     * @param string $mode Sort mode: recent|highest|lowest|tiebreaker
     * @return array
     */
    public static function apply($round_ref, $mode = 'recent') {
        // Main entry: sorts ranked participants using the specified mode.
        $rank_rows = PB_Voting_Service::get_ranked_participants($round_ref);
        if (empty($rank_rows)) {
            return [];
        }
        $mode = is_string($mode) ? strtolower($mode) : 'recent';
        switch ($mode) {
            case 'recent':
                return self::sort_recent($rank_rows);
            case 'highest':
                return self::sort_highest($rank_rows);
            case 'lowest':
                return self::sort_lowest($rank_rows);
            case 'tiebreaker':
                return self::sort_tiebreaker($rank_rows);
            default:
                // Default to highest (rank order)
                return self::sort_highest($rank_rows);
        }
    }

    // ============================================================
    // Sorting Implementations
    // ============================================================

    /**
     * Sort by most recent vote (last_vote_at desc), fallback to rank.
     */
    public static function sort_recent(array $rows) {
        // Sorts by most recent vote (desc), fallback to rank.
        usort($rows, function($a, $b) {
            $a_time = !empty($a['last_vote_at']) ? strtotime($a['last_vote_at']) : -INF;
            $b_time = !empty($b['last_vote_at']) ? strtotime($b['last_vote_at']) : -INF;
            if ($a_time === $b_time) {
                // fallback to rank (lowest first)
                return $a['rank'] <=> $b['rank'];
            }
            return $b_time <=> $a_time;
        });
        return $rows;
    }

    /**
     * Sort by rank ascending (1 is highest), fallback to post_id.
     */
    public static function sort_highest(array $rows) {
        // Sorts by ascending rank (1 is highest), fallback to post_id.
        usort($rows, function($a, $b) {
            if ($a['rank'] === $b['rank']) {
                return $a['post_id'] <=> $b['post_id'];
            }
            return $a['rank'] <=> $b['rank'];
        });
        return $rows;
    }

    /**
     * Sort by rank descending (lowest is worst rank).
     */
    public static function sort_lowest(array $rows) {
        // Sorts by descending rank (lowest/worst first).
        usort($rows, function($a, $b) {
            if ($a['rank'] === $b['rank']) {
                return $a['post_id'] <=> $b['post_id'];
            }
            return $b['rank'] <=> $a['rank'];
        });
        return $rows;
    }

    /**
     * Group by votes, find ties, order: lowtie, hightie, midtie, neartie, other.
     * Within group, by most recent vote.
     */
    public static function sort_tiebreaker(array $rows) {
        // Groups by vote count, prioritizes ties, then sorts by recency within group.
        // Group by votes
        $voteMap = [];
        foreach ($rows as $row) {
            $voteMap[$row['votes']][] = $row;
        }
        $tieVotes = [];
        foreach ($voteMap as $votes => $group) {
            if (count($group) > 1) {
                $tieVotes[] = (int)$votes;
            }
        }
        $maxTie = !empty($tieVotes) ? max($tieVotes) : null;
        $minTie = !empty($tieVotes) ? min($tieVotes) : null;
        // Assign tieStatus
        foreach ($rows as &$row) {
            $votes = $row['votes'];
            if (in_array($votes, $tieVotes, true)) {
                if ($votes === $minTie) {
                    $row['tieStatus'] = 'lowtie';
                } elseif ($votes === $maxTie) {
                    $row['tieStatus'] = 'hightie';
                } else {
                    $row['tieStatus'] = 'midtie';
                }
            } else {
                $near = false;
                foreach ($tieVotes as $tv) {
                    if (abs($votes - $tv) <= 2) {
                        $near = true;
                        break;
                    }
                }
                $row['tieStatus'] = $near ? 'neartie' : 'other';
            }
        }
        unset($row);
        $orderMap = [ 'lowtie' => 0, 'hightie' => 1, 'midtie' => 1, 'neartie' => 2, 'other' => 3 ];
        usort($rows, function($a, $b) use ($orderMap) {
            $aStatus = isset($a['tieStatus']) ? $a['tieStatus'] : 'other';
            $bStatus = isset($b['tieStatus']) ? $b['tieStatus'] : 'other';
            if ($orderMap[$aStatus] !== $orderMap[$bStatus]) {
                return $orderMap[$aStatus] - $orderMap[$bStatus];
            }
            // Within group, by most recent vote
            $a_time = !empty($a['last_vote_at']) ? strtotime($a['last_vote_at']) : 0;
            $b_time = !empty($b['last_vote_at']) ? strtotime($b['last_vote_at']) : 0;
            return $b_time <=> $a_time;
        });
        return $rows;
    }
}