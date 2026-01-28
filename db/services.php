<?php
/**
 * Web services for block_blackjack.
 *
 * @package block_blackjack
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_blackjack_new_game' => [
        'classname' => 'block_blackjack\external',
        'methodname' => 'new_game',
        'description' => 'Start a new blackjack game',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'block_blackjack_hit' => [
        'classname' => 'block_blackjack\external',
        'methodname' => 'hit',
        'description' => 'Player hits (takes another card)',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'block_blackjack_stand' => [
        'classname' => 'block_blackjack\external',
        'methodname' => 'stand',
        'description' => 'Player stands (dealer plays)',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'block_blackjack_double_down' => [
        'classname' => 'block_blackjack\external',
        'methodname' => 'double_down',
        'description' => 'Player doubles down (double bet, one card, then stand)',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true
    ],
    'block_blackjack_get_state' => [
        'classname' => 'block_blackjack\external',
        'methodname' => 'get_state',
        'description' => 'Get current game state',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true
    ]
];
