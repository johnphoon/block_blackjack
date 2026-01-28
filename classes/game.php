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

        // Double the bet.
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
        return $gamestate['status'] === 'playing' && count($gamestate['playerhand']) === 2;
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
