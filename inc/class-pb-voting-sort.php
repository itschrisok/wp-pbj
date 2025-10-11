

<?php
/**
 * Voting Sort class for Project Baldwin.
 * Handles sorting of ranked voting participants.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PB_Voting_Sort {
    /**
     * Apply the requested sort mode to the ranked participants for a round.
     *
     * @param string $round_ref
     * @param string $mode Sort mode: recent|highest|lowest|tiebreaker
     * @return array
     */
    public static function apply($round_ref, $mode = 'recent') {
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

    /**
     * Sort by most recent vote (last_vote_at desc), fallback to rank.
     */
    public static function sort_recent(array $rows) {
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
 