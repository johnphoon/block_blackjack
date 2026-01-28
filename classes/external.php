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

        $points = game::update_user_points($USER->id, $gamestate['payout']);
        unset($_SESSION['blackjack_game']);

        return self::format_game_response($gamestate, $points, true);
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
            $message = get_string('status_' . $gamestate['status'], 'block_blackjack');
        }

        return [
            'success' => true,
            'gameover' => $gameover,
            'candoubledown' => game::can_double_down($gamestate),
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
    }

    /**
     * Common response structure.
     */
    private static function game_response_structure(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation succeeded'),
            'gameover' => new external_value(PARAM_BOOL, 'Whether the game is over', VALUE_OPTIONAL),
            'candoubledown' => new external_value(PARAM_BOOL, 'Whether double down is allowed', VALUE_OPTIONAL),
            'playercards' => new external_multiple_structure(
                new external_single_structure([
                    'value' => new external_value(PARAM_TEXT, 'Card value'),
                    'suit' => new external_value(PARAM_TEXT, 'Card suit'),
                    'symbol' => new external_value(PARAM_TEXT, 'Suit symbol'),
                    'color' => new external_value(PARAM_TEXT, 'Card color')
                ]),
                'Player cards',
                VALUE_OPTIONAL
            ),
            'dealercards' => new external_multiple_structure(
                new external_single_structure([
                    'value' => new external_value(PARAM_TEXT, 'Card value'),
                    'suit' => new external_value(PARAM_TEXT, 'Card suit'),
                    'symbol' => new external_value(PARAM_TEXT, 'Suit symbol'),
                    'color' => new external_value(PARAM_TEXT, 'Card color')
                ]),
                'Dealer cards',
                VALUE_OPTIONAL
            ),
            'playerscore' => new external_value(PARAM_INT, 'Player score', VALUE_OPTIONAL),
            'dealerscore' => new external_value(PARAM_INT, 'Dealer score', VALUE_OPTIONAL),
            'points' => new external_value(PARAM_INT, 'User points', VALUE_OPTIONAL),
            'bet' => new external_value(PARAM_INT, 'Current bet', VALUE_OPTIONAL),
            'payout' => new external_value(PARAM_INT, 'Payout amount', VALUE_OPTIONAL),
            'result' => new external_value(PARAM_TEXT, 'Game result', VALUE_OPTIONAL),
            'message' => new external_value(PARAM_TEXT, 'Status message', VALUE_OPTIONAL)
        ]);
    }
}
