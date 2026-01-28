<?php
/**
 * External API for blackjack game.
 *
 * @package block_blackjack
 */

namespace block_blackjack;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;

class external extends external_api {

    /**
     * Parameters for new_game.
     */
    public static function new_game_parameters(): external_function_parameters {
        return new external_function_parameters([
            'bet' => new external_value(PARAM_INT, 'Bet amount', VALUE_DEFAULT, 10)
        ]);
    }

    /**
     * Start a new game.
     *
     * @param int $bet
     * @return array
     */
    public static function new_game(int $bet = 10): array {
        global $USER;

        $params = self::validate_parameters(self::new_game_parameters(), ['bet' => $bet]);
        $bet = max(1, min(100, $params['bet'])); // Limit bet between 1 and 100.

        $userpoints = game::get_user_points($USER->id);

        if ($userpoints->points < $bet) {
            return [
                'success' => false,
                'message' => get_string('notenoughpoints', 'block_blackjack'),
                'points' => $userpoints->points
            ];
        }

        $gamestate = game::new_game();
        $gamestate['bet'] = $bet;

        // Check for immediate blackjack.
        if (game::is_blackjack($gamestate['playerhand'])) {
            $gamestate['status'] = 'blackjack';
            $gamestate['result'] = 'win';
            $gamestate['payout'] = (int)($bet * 1.5); // Blackjack pays 3:2.
            $newpoints = game::update_user_points($USER->id, $gamestate['payout']);
            return self::format_game_response($gamestate, $newpoints, true);
        }

        // Store game state in session.
        $_SESSION['blackjack_game'] = $gamestate;

        return self::format_game_response($gamestate, $userpoints->points, false);
    }

    /**
     * Returns for new_game.
     */
    public static function new_game_returns(): external_single_structure {
        return self::game_response_structure();
    }

    /**
     * Parameters for hit.
     */
    public static function hit_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Player hits.
     *
     * @return array
     */
    public static function hit(): array {
        global $USER;

        if (empty($_SESSION['blackjack_game'])) {
            return ['success' => false, 'message' => get_string('nogameactive', 'block_blackjack')];
        }

        $gamestate = $_SESSION['blackjack_game'];
        $gamestate = game::hit($gamestate);
        $_SESSION['blackjack_game'] = $gamestate;

        $points = game::get_user_points($USER->id)->points;

        // If game ended, update points.
        if ($gamestate['status'] !== 'playing') {
            $points = game::update_user_points($USER->id, $gamestate['payout']);
            unset($_SESSION['blackjack_game']);
        }

        return self::format_game_response($gamestate, $points, false);
    }

    /**
     * Returns for hit.
     */
    public static function hit_returns(): external_single_structure {
        return self::game_response_structure();
    }

    /**
     * Parameters for stand.
     */
    public static function stand_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Player stands.
     *
     * @return array
     */
    public static function stand(): array {
        global $USER;

        if (empty($_SESSION['blackjack_game'])) {
            return ['success' => false, 'message' => get_string('nogameactive', 'block_blackjack')];
        }

        $gamestate = $_SESSION['blackjack_game'];
        $gamestate = game::stand($gamestate);
        $_SESSION['blackjack_game'] = $gamestate;

        $points = game::get_user_points($USER->id)->points;

        // Check if game is finished.
        if ($gamestate['status'] !== 'playing') {
            $points = game::update_user_points($USER->id, $gamestate['payout']);
            unset($_SESSION['blackjack_game']);
            return self::format_game_response($gamestate, $points, true);
        }

        return self::format_game_response($gamestate, $points, false);
    }

    /**
     * Returns for stand.
     */
    public static function stand_returns(): external_single_structure {
        return self::game_response_structure();
    }

    /**
     * Parameters for double_down.
     */
    public static function double_down_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Player doubles down.
     *
     * @return array
     */
    public static function double_down(): array {
        global $USER;

        if (empty($_SESSION['blackjack_game'])) {
            return ['success' => false, 'message' => get_string('nogameactive', 'block_blackjack')];
        }

        $gamestate = $_SESSION['blackjack_game'];

        // Check if double down is allowed.
        if (!game::can_double_down($gamestate)) {
            return ['success' => false, 'message' => get_string('cannotdoubledown', 'block_blackjack')];
        }

        // Check if user has enough points to double.
        $userpoints = game::get_user_points($USER->id);
        if ($userpoints->points < $gamestate['bet']) {
            return [
                'success' => false,
                'message' => get_string('notenoughpoints', 'block_blackjack'),
                'points' => $userpoints->points
            ];
        }

        $gamestate = game::double_down($gamestate);

        $points = game::update_user_points($USER->id, $gamestate['payout']);
        unset($_SESSION['blackjack_game']);

        return self::format_game_response($gamestate, $points, true);
    }

    /**
     * Returns for double_down.
     */
    public static function double_down_returns(): external_single_structure {
        return self::game_response_structure();
    }

    /**
     * Parameters for split.
     */
    public static function split_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Player splits their hand.
     *
     * @return array
     */
    public static function split(): array {
        global $USER;

        if (empty($_SESSION['blackjack_game'])) {
            return ['success' => false, 'message' => get_string('nogameactive', 'block_blackjack')];
        }

        $gamestate = $_SESSION['blackjack_game'];

        // Check if split is allowed.
        if (!game::can_split($gamestate)) {
            return ['success' => false, 'message' => get_string('cannotsplit', 'block_blackjack')];
        }

        // Check if user has enough points to split (need to match original bet).
        $userpoints = game::get_user_points($USER->id);
        if ($userpoints->points < $gamestate['bet']) {
            return [
                'success' => false,
                'message' => get_string('notenoughpoints', 'block_blackjack'),
                'points' => $userpoints->points
            ];
        }

        $gamestate = game::split($gamestate);
        $_SESSION['blackjack_game'] = $gamestate;

        return self::format_game_response($gamestate, $userpoints->points, false);
    }

    /**
     * Returns for split.
     */
    public static function split_returns(): external_single_structure {
        return self::game_response_structure();
    }

    /**
     * Parameters for get_state.
     */
    public static function get_state_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Get current game state.
     *
     * @return array
     */
    public static function get_state(): array {
        global $USER;

        $points = game::get_user_points($USER->id)->points;

        if (empty($_SESSION['blackjack_game'])) {
            return [
                'success' => true,
                'gameover' => true,
                'points' => $points,
                'message' => ''
            ];
        }

        $gamestate = $_SESSION['blackjack_game'];
        $hidedealer = $gamestate['status'] === 'playing';

        return self::format_game_response($gamestate, $points, !$hidedealer);
    }

    /**
     * Returns for get_state.
     */
    public static function get_state_returns(): external_single_structure {
        return self::game_response_structure();
    }

    /**
     * Format game state for response.
     *
     * @param array $gamestate
     * @param int $points
     * @param bool $showdealerhand
     * @return array
     */
    private static function format_game_response(array $gamestate, int $points, bool $showdealerhand): array {
        $gameover = $gamestate['status'] !== 'playing';
        $issplit = !empty($gamestate['is_split']);

        $playercards = [];
        foreach ($gamestate['playerhand'] as $card) {
            $playercards[] = game::format_card($card);
        }

        $dealercards = [];
        foreach ($gamestate['dealerhand'] as $index => $card) {
            if (!$showdealerhand && !$gameover && $index > 0) {
                // Hide dealer's hole card.
                $dealercards[] = ['value' => '?', 'suit' => 'hidden', 'symbol' => '', 'color' => 'black'];
            } else {
                $dealercards[] = game::format_card($card);
            }
        }

        $playerscore = game::calculate_score($gamestate['playerhand']);
        $dealerscore = $showdealerhand || $gameover
            ? game::calculate_score($gamestate['dealerhand'])
            : game::calculate_score([$gamestate['dealerhand'][0]]); // Only show first card score.

        $message = '';
        if ($gameover) {
            if ($issplit) {
                $message = get_string('status_split_complete', 'block_blackjack');
            } else {
                $message = get_string('status_' . $gamestate['status'], 'block_blackjack');
            }
        }

        $response = [
            'success' => true,
            'gameover' => $gameover,
            'candoubledown' => game::can_double_down($gamestate),
            'cansplit' => game::can_split($gamestate),
            'issplit' => $issplit,
            'currenthand' => $gamestate['current_hand'] ?? 0,
            'playercards' => $playercards,
            'dealercards' => $dealercards,
            'playerscore' => $playerscore,
            'dealerscore' => $dealerscore,
            'points' => $points,
            'bet' => $gamestate['bet'],
            'payout' => $gamestate['payout'] ?? 0,
            'result' => $gamestate['result'] ?? '',
            'message' => $message
        ];

        // Add split hand info if applicable.
        if ($issplit) {
            $splitcards = [];
            foreach ($gamestate['splithand'] as $card) {
                $splitcards[] = game::format_card($card);
            }
            $response['splitcards'] = $splitcards;
            $response['splitscore'] = game::calculate_score($gamestate['splithand']);
            $response['splitbet'] = $gamestate['splitbet'] ?? $gamestate['bet'];
            $response['hand1status'] = $gamestate['hand1_status'] ?? 'playing';
            $response['hand2status'] = $gamestate['hand2_status'] ?? 'playing';

            if ($gameover) {
                $response['hand1result'] = $gamestate['hand1_result'] ?? '';
                $response['hand2result'] = $gamestate['hand2_result'] ?? '';
                $response['payout1'] = $gamestate['payout1'] ?? 0;
                $response['payout2'] = $gamestate['payout2'] ?? 0;
            }
        }

        return $response;
    }

    /**
     * Common response structure.
     */
    private static function game_response_structure(): external_single_structure {
        $cardstructure = new external_single_structure([
            'value' => new external_value(PARAM_TEXT, 'Card value'),
            'suit' => new external_value(PARAM_TEXT, 'Card suit'),
            'symbol' => new external_value(PARAM_TEXT, 'Suit symbol'),
            'color' => new external_value(PARAM_TEXT, 'Card color')
        ]);

        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation succeeded'),
            'gameover' => new external_value(PARAM_BOOL, 'Whether the game is over', VALUE_OPTIONAL),
            'candoubledown' => new external_value(PARAM_BOOL, 'Whether double down is allowed', VALUE_OPTIONAL),
            'cansplit' => new external_value(PARAM_BOOL, 'Whether split is allowed', VALUE_OPTIONAL),
            'issplit' => new external_value(PARAM_BOOL, 'Whether this is a split game', VALUE_OPTIONAL),
            'currenthand' => new external_value(PARAM_INT, 'Current hand being played (1 or 2)', VALUE_OPTIONAL),
            'playercards' => new external_multiple_structure($cardstructure, 'Player cards (hand 1)', VALUE_OPTIONAL),
            'dealercards' => new external_multiple_structure($cardstructure, 'Dealer cards', VALUE_OPTIONAL),
            'splitcards' => new external_multiple_structure($cardstructure, 'Split hand cards (hand 2)', VALUE_OPTIONAL),
            'playerscore' => new external_value(PARAM_INT, 'Player score (hand 1)', VALUE_OPTIONAL),
            'splitscore' => new external_value(PARAM_INT, 'Split hand score (hand 2)', VALUE_OPTIONAL),
            'dealerscore' => new external_value(PARAM_INT, 'Dealer score', VALUE_OPTIONAL),
            'points' => new external_value(PARAM_INT, 'User points', VALUE_OPTIONAL),
            'bet' => new external_value(PARAM_INT, 'Current bet (hand 1)', VALUE_OPTIONAL),
            'splitbet' => new external_value(PARAM_INT, 'Split hand bet (hand 2)', VALUE_OPTIONAL),
            'payout' => new external_value(PARAM_INT, 'Total payout amount', VALUE_OPTIONAL),
            'payout1' => new external_value(PARAM_INT, 'Hand 1 payout', VALUE_OPTIONAL),
            'payout2' => new external_value(PARAM_INT, 'Hand 2 payout', VALUE_OPTIONAL),
            'result' => new external_value(PARAM_TEXT, 'Game result', VALUE_OPTIONAL),
            'hand1status' => new external_value(PARAM_TEXT, 'Hand 1 status', VALUE_OPTIONAL),
            'hand2status' => new external_value(PARAM_TEXT, 'Hand 2 status', VALUE_OPTIONAL),
            'hand1result' => new external_value(PARAM_TEXT, 'Hand 1 result', VALUE_OPTIONAL),
            'hand2result' => new external_value(PARAM_TEXT, 'Hand 2 result', VALUE_OPTIONAL),
            'message' => new external_value(PARAM_TEXT, 'Status message', VALUE_OPTIONAL)
        ]);
    }
}
