<?php
/**
 * Blackjack game logic.
 *
 * @package block_blackjack
 */

namespace block_blackjack;

defined('MOODLE_INTERNAL') || die();

class game {

    private const SUITS = ['hearts', 'diamonds', 'clubs', 'spades'];
    private const VALUES = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];

    /**
     * Create a new shuffled deck.
     *
     * @return array
     */
    public static function create_deck(): array {
        $deck = [];
        foreach (self::SUITS as $suit) {
            foreach (self::VALUES as $value) {
                $deck[] = ['suit' => $suit, 'value' => $value];
            }
        }
        shuffle($deck);
        return $deck;
    }

    /**
     * Calculate the score of a hand.
     *
     * @param array $hand
     * @return int
     */
    public static function calculate_score(array $hand): int {
        $score = 0;
        $aces = 0;

        foreach ($hand as $card) {
            $value = $card['value'];
            if ($value === 'A') {
                $aces++;
                $score += 11;
            } else if (in_array($value, ['K', 'Q', 'J'])) {
                $score += 10;
            } else {
                $score += (int)$value;
            }
        }

        // Adjust for aces if score is over 21.
        while ($score > 21 && $aces > 0) {
            $score -= 10;
            $aces--;
        }

        return $score;
    }

    /**
     * Check if a hand is a blackjack (21 with 2 cards).
     *
     * @param array $hand
     * @return bool
     */
    public static function is_blackjack(array $hand): bool {
        return count($hand) === 2 && self::calculate_score($hand) === 21;
    }

    /**
     * Check if a hand is busted (over 21).
     *
     * @param array $hand
     * @return bool
     */
    public static function is_bust(array $hand): bool {
        return self::calculate_score($hand) > 21;
    }

    /**
     * Get suit symbol for display.
     *
     * @param string $suit
     * @return string
     */
    public static function get_suit_symbol(string $suit): string {
        $symbols = [
            'hearts' => '♥',
            'diamonds' => '♦',
            'clubs' => '♣',
            'spades' => '♠'
        ];
        return $symbols[$suit] ?? '';
    }

    /**
     * Get suit color.
     *
     * @param string $suit
     * @return string
     */
    public static function get_suit_color(string $suit): string {
        return in_array($suit, ['hearts', 'diamonds']) ? 'red' : 'black';
    }

    /**
     * Format a card for display.
     *
     * @param array $card
     * @return array
     */
    public static function format_card(array $card): array {
        return [
            'value' => $card['value'],
            'suit' => $card['suit'],
            'symbol' => self::get_suit_symbol($card['suit']),
            'color' => self::get_suit_color($card['suit'])
        ];
    }

    /**
     * Start a new game.
     *
     * @return array Game state
     */
    public static function new_game(): array {
        $deck = self::create_deck();
        $playerhand = [array_pop($deck), array_pop($deck)];
        $dealerhand = [array_pop($deck), array_pop($deck)];

        return [
            'deck' => $deck,
            'playerhand' => $playerhand,
            'dealerhand' => $dealerhand,
            'status' => 'playing',
            'bet' => 10
        ];
    }

    /**
     * Player hits (takes another card).
     *
     * @param array $gamestate
     * @return array Updated game state
     */
    public static function hit(array $gamestate): array {
        if ($gamestate['status'] !== 'playing') {
            return $gamestate;
        }

        // Handle split hands.
        if (!empty($gamestate['is_split'])) {
            $currenthand = $gamestate['current_hand'] ?? 1;

            if ($currenthand === 1) {
                $gamestate['playerhand'][] = array_pop($gamestate['deck']);
                if (self::is_bust($gamestate['playerhand'])) {
                    $gamestate['hand1_status'] = 'bust';
                    // Move to second hand.
                    $gamestate['current_hand'] = 2;
                }
            } else {
                $gamestate['splithand'][] = array_pop($gamestate['deck']);
                if (self::is_bust($gamestate['splithand'])) {
                    $gamestate['hand2_status'] = 'bust';
                    // Both hands done, dealer plays.
                    return self::finish_split_game($gamestate);
                }
            }
            return $gamestate;
        }

        // Normal (non-split) play.
        $gamestate['playerhand'][] = array_pop($gamestate['deck']);

        if (self::is_bust($gamestate['playerhand'])) {
            $gamestate['status'] = 'player_bust';
            $gamestate['result'] = 'lose';
            $gamestate['payout'] = -$gamestate['bet'];
        }

        return $gamestate;
    }

    /**
     * Player doubles down (double bet, one card, then stand).
     *
     * @param array $gamestate
     * @return array Updated game state
     */
    public static function double_down(array $gamestate): array {
        if ($gamestate['status'] !== 'playing') {
            return $gamestate;
        }

        // Handle split hands.
        if (!empty($gamestate['is_split'])) {
            $currenthand = $gamestate['current_hand'] ?? 1;

            if ($currenthand === 1) {
                $gamestate['bet'] *= 2;
                $gamestate['playerhand'][] = array_pop($gamestate['deck']);

                if (self::is_bust($gamestate['playerhand'])) {
                    $gamestate['hand1_status'] = 'bust';
                    $gamestate['current_hand'] = 2;
                    return $gamestate;
                }

                // Stand on this hand, move to next.
                $gamestate['hand1_status'] = 'stand';
                $gamestate['current_hand'] = 2;
                return $gamestate;
            } else {
                $gamestate['splitbet'] *= 2;
                $gamestate['splithand'][] = array_pop($gamestate['deck']);

                if (self::is_bust($gamestate['splithand'])) {
                    $gamestate['hand2_status'] = 'bust';
                    return self::finish_split_game($gamestate);
                }

                // Stand on this hand, finish game.
                $gamestate['hand2_status'] = 'stand';
                return self::finish_split_game($gamestate);
            }
        }

        // Normal (non-split) play.
        $gamestate['bet'] *= 2;
        $gamestate['doubled'] = true;

        // Take exactly one card.
        $gamestate['playerhand'][] = array_pop($gamestate['deck']);

        // Check for bust.
        if (self::is_bust($gamestate['playerhand'])) {
            $gamestate['status'] = 'player_bust';
            $gamestate['result'] = 'lose';
            $gamestate['payout'] = -$gamestate['bet'];
            return $gamestate;
        }

        // Automatically stand.
        return self::stand($gamestate);
    }

    /**
     * Check if double down is allowed (only on first two cards).
     *
     * @param array $gamestate
     * @return bool
     */
    public static function can_double_down(array $gamestate): bool {
        $hand = self::get_current_hand($gamestate);
        return $gamestate['status'] === 'playing' && count($hand) === 2;
    }

    /**
     * Get the card's numeric value for split comparison.
     *
     * @param array $card
     * @return int
     */
    public static function get_card_rank(array $card): int {
        $value = $card['value'];
        if (in_array($value, ['K', 'Q', 'J'])) {
            return 10;
        }
        if ($value === 'A') {
            return 11;
        }
        return (int)$value;
    }

    /**
     * Check if split is allowed (two cards of same value, first action only).
     *
     * @param array $gamestate
     * @return bool
     */
    public static function can_split(array $gamestate): bool {
        if ($gamestate['status'] !== 'playing') {
            return false;
        }

        // Can't split if already split.
        if (!empty($gamestate['is_split'])) {
            return false;
        }

        $hand = $gamestate['playerhand'];
        if (count($hand) !== 2) {
            return false;
        }

        // Check if both cards have the same rank.
        return self::get_card_rank($hand[0]) === self::get_card_rank($hand[1]);
    }

    /**
     * Split the hand into two hands.
     *
     * @param array $gamestate
     * @return array Updated game state
     */
    public static function split(array $gamestate): array {
        if (!self::can_split($gamestate)) {
            return $gamestate;
        }

        // Create two hands from the pair.
        $card1 = $gamestate['playerhand'][0];
        $card2 = $gamestate['playerhand'][1];

        // Each hand gets one of the original cards plus a new card.
        $gamestate['playerhand'] = [$card1, array_pop($gamestate['deck'])];
        $gamestate['splithand'] = [$card2, array_pop($gamestate['deck'])];

        // Mark as split and set current hand.
        $gamestate['is_split'] = true;
        $gamestate['current_hand'] = 1; // 1 = first hand, 2 = second hand.
        $gamestate['hand1_status'] = 'playing';
        $gamestate['hand2_status'] = 'playing';

        // Second bet for split hand (same as original).
        $gamestate['splitbet'] = $gamestate['bet'];

        return $gamestate;
    }

    /**
     * Get the current hand being played.
     *
     * @param array $gamestate
     * @return array
     */
    public static function get_current_hand(array $gamestate): array {
        if (!empty($gamestate['is_split']) && ($gamestate['current_hand'] ?? 1) === 2) {
            return $gamestate['splithand'];
        }
        return $gamestate['playerhand'];
    }

    /**
     * Player stands (dealer plays).
     *
     * @param array $gamestate
     * @return array Updated game state
     */
    public static function stand(array $gamestate): array {
        if ($gamestate['status'] !== 'playing') {
            return $gamestate;
        }

        // Handle split hands.
        if (!empty($gamestate['is_split'])) {
            $currenthand = $gamestate['current_hand'] ?? 1;

            if ($currenthand === 1) {
                $gamestate['hand1_status'] = 'stand';
                $gamestate['current_hand'] = 2;
                return $gamestate;
            } else {
                $gamestate['hand2_status'] = 'stand';
                return self::finish_split_game($gamestate);
            }
        }

        // Normal (non-split) play - dealer draws.
        return self::dealer_play($gamestate);
    }

    /**
     * Dealer plays their hand.
     *
     * @param array $gamestate
     * @return array Updated game state
     */
    private static function dealer_play(array $gamestate): array {
        // Dealer draws until 17 or higher.
        while (self::calculate_score($gamestate['dealerhand']) < 17) {
            $gamestate['dealerhand'][] = array_pop($gamestate['deck']);
        }

        $playerscore = self::calculate_score($gamestate['playerhand']);
        $dealerscore = self::calculate_score($gamestate['dealerhand']);

        if (self::is_bust($gamestate['dealerhand'])) {
            $gamestate['status'] = 'dealer_bust';
            $gamestate['result'] = 'win';
            $gamestate['payout'] = $gamestate['bet'];
        } else if ($playerscore > $dealerscore) {
            $gamestate['status'] = 'player_wins';
            $gamestate['result'] = 'win';
            $gamestate['payout'] = $gamestate['bet'];
        } else if ($dealerscore > $playerscore) {
            $gamestate['status'] = 'dealer_wins';
            $gamestate['result'] = 'lose';
            $gamestate['payout'] = -$gamestate['bet'];
        } else {
            $gamestate['status'] = 'push';
            $gamestate['result'] = 'push';
            $gamestate['payout'] = 0;
        }

        return $gamestate;
    }

    /**
     * Finish a split game - dealer plays and results calculated for both hands.
     *
     * @param array $gamestate
     * @return array Updated game state
     */
    private static function finish_split_game(array $gamestate): array {
        // Dealer draws until 17 or higher.
        while (self::calculate_score($gamestate['dealerhand']) < 17) {
            $gamestate['dealerhand'][] = array_pop($gamestate['deck']);
        }

        $dealerscore = self::calculate_score($gamestate['dealerhand']);
        $dealerbust = self::is_bust($gamestate['dealerhand']);

        // Calculate result for hand 1.
        $payout1 = 0;
        if ($gamestate['hand1_status'] === 'bust') {
            $gamestate['hand1_result'] = 'lose';
            $payout1 = -$gamestate['bet'];
        } else {
            $score1 = self::calculate_score($gamestate['playerhand']);
            if ($dealerbust || $score1 > $dealerscore) {
                $gamestate['hand1_result'] = 'win';
                $payout1 = $gamestate['bet'];
            } else if ($score1 < $dealerscore) {
                $gamestate['hand1_result'] = 'lose';
                $payout1 = -$gamestate['bet'];
            } else {
                $gamestate['hand1_result'] = 'push';
                $payout1 = 0;
            }
        }

        // Calculate result for hand 2.
        $payout2 = 0;
        if ($gamestate['hand2_status'] === 'bust') {
            $gamestate['hand2_result'] = 'lose';
            $payout2 = -$gamestate['splitbet'];
        } else {
            $score2 = self::calculate_score($gamestate['splithand']);
            if ($dealerbust || $score2 > $dealerscore) {
                $gamestate['hand2_result'] = 'win';
                $payout2 = $gamestate['splitbet'];
            } else if ($score2 < $dealerscore) {
                $gamestate['hand2_result'] = 'lose';
                $payout2 = -$gamestate['splitbet'];
            } else {
                $gamestate['hand2_result'] = 'push';
                $payout2 = 0;
            }
        }

        // Set overall status.
        $gamestate['status'] = 'split_complete';
        $gamestate['payout'] = $payout1 + $payout2;
        $gamestate['payout1'] = $payout1;
        $gamestate['payout2'] = $payout2;

        // Determine overall result message.
        if ($payout1 + $payout2 > 0) {
            $gamestate['result'] = 'win';
        } else if ($payout1 + $payout2 < 0) {
            $gamestate['result'] = 'lose';
        } else {
            $gamestate['result'] = 'push';
        }

        return $gamestate;
    }

    /**
     * Get or create user points record.
     *
     * @param int $userid
     * @return object
     */
    public static function get_user_points(int $userid): object {
        global $DB;

        $record = $DB->get_record('block_blackjack', ['userid' => $userid]);
        if (!$record) {
            $record = new \stdClass();
            $record->userid = $userid;
            $record->points = 100; // Starting points.
            $record->id = $DB->insert_record('block_blackjack', $record);
        }

        return $record;
    }

    /**
     * Update user points.
     *
     * @param int $userid
     * @param int $change Points to add (positive) or subtract (negative)
     * @return int New points total
     */
    public static function update_user_points(int $userid, int $change): int {
        global $DB;

        $record = self::get_user_points($userid);
        $record->points += $change;

        // Don't go below 0.
        if ($record->points < 0) {
            $record->points = 0;
        }

        $DB->update_record('block_blackjack', $record);

        return $record->points;
    }
}
