<?php
/**
 * Unit tests for block_blackjack game logic.
 *
 * @package block_blackjack
 */

defined('MOODLE_INTERNAL') || die();

use block_blackjack\game;

/**
 * Test cases for the blackjack game logic class.
 *
 * @package block_blackjack
 * @group block_blackjack
 */
class block_blackjack_game_test extends advanced_testcase {

    /**
     * Test deck creation.
     */
    public function test_create_deck() {
        $deck = game::create_deck();

        // Deck should have 52 cards.
        $this->assertCount(52, $deck);

        // Count suits and values.
        $suits = [];
        $values = [];
        foreach ($deck as $card) {
            $suits[$card['suit']] = ($suits[$card['suit']] ?? 0) + 1;
            $values[$card['value']] = ($values[$card['value']] ?? 0) + 1;
        }

        // Each suit should have 13 cards.
        $this->assertCount(4, $suits);
        foreach ($suits as $count) {
            $this->assertEquals(13, $count);
        }

        // Each value should appear 4 times (once per suit).
        $this->assertCount(13, $values);
        foreach ($values as $count) {
            $this->assertEquals(4, $count);
        }
    }

    /**
     * Test deck is shuffled (randomized).
     */
    public function test_deck_is_shuffled() {
        $deck1 = game::create_deck();
        $deck2 = game::create_deck();

        // Two decks should not be in the same order (extremely unlikely).
        // We'll check if at least some cards are in different positions.
        $differences = 0;
        for ($i = 0; $i < 52; $i++) {
            if ($deck1[$i] !== $deck2[$i]) {
                $differences++;
            }
        }

        // With shuffling, we expect many differences.
        $this->assertGreaterThan(10, $differences);
    }

    /**
     * Data provider for score calculation tests.
     */
    public function score_provider(): array {
        return [
            'simple hand' => [
                [['value' => '5', 'suit' => 'hearts'], ['value' => '3', 'suit' => 'spades']],
                8
            ],
            'face cards' => [
                [['value' => 'K', 'suit' => 'hearts'], ['value' => 'Q', 'suit' => 'spades']],
                20
            ],
            'ace as 11' => [
                [['value' => 'A', 'suit' => 'hearts'], ['value' => '8', 'suit' => 'spades']],
                19
            ],
            'ace as 1 to avoid bust' => [
                [['value' => 'A', 'suit' => 'hearts'], ['value' => '8', 'suit' => 'spades'], ['value' => '5', 'suit' => 'clubs']],
                14
            ],
            'blackjack' => [
                [['value' => 'A', 'suit' => 'hearts'], ['value' => 'K', 'suit' => 'spades']],
                21
            ],
            'multiple aces' => [
                [['value' => 'A', 'suit' => 'hearts'], ['value' => 'A', 'suit' => 'spades'], ['value' => '9', 'suit' => 'clubs']],
                21
            ],
            'bust hand' => [
                [['value' => 'K', 'suit' => 'hearts'], ['value' => 'Q', 'suit' => 'spades'], ['value' => '5', 'suit' => 'clubs']],
                25
            ],
            'all aces' => [
                [['value' => 'A', 'suit' => 'hearts'], ['value' => 'A', 'suit' => 'spades'], ['value' => 'A', 'suit' => 'clubs'], ['value' => 'A', 'suit' => 'diamonds']],
                14
            ],
        ];
    }

    /**
     * Test score calculation.
     *
     * @dataProvider score_provider
     */
    public function test_calculate_score(array $hand, int $expected) {
        $this->assertEquals($expected, game::calculate_score($hand));
    }

    /**
     * Test blackjack detection.
     */
    public function test_is_blackjack() {
        // Ace + face card = blackjack.
        $blackjack = [
            ['value' => 'A', 'suit' => 'hearts'],
            ['value' => 'K', 'suit' => 'spades']
        ];
        $this->assertTrue(game::is_blackjack($blackjack));

        // Ace + 10 = blackjack.
        $blackjack2 = [
            ['value' => 'A', 'suit' => 'hearts'],
            ['value' => '10', 'suit' => 'spades']
        ];
        $this->assertTrue(game::is_blackjack($blackjack2));

        // 21 with 3 cards is not blackjack.
        $not_blackjack = [
            ['value' => '7', 'suit' => 'hearts'],
            ['value' => '7', 'suit' => 'spades'],
            ['value' => '7', 'suit' => 'clubs']
        ];
        $this->assertFalse(game::is_blackjack($not_blackjack));

        // 20 is not blackjack.
        $twenty = [
            ['value' => 'K', 'suit' => 'hearts'],
            ['value' => 'Q', 'suit' => 'spades']
        ];
        $this->assertFalse(game::is_blackjack($twenty));
    }

    /**
     * Test bust detection.
     */
    public function test_is_bust() {
        $bust = [
            ['value' => 'K', 'suit' => 'hearts'],
            ['value' => 'Q', 'suit' => 'spades'],
            ['value' => '5', 'suit' => 'clubs']
        ];
        $this->assertTrue(game::is_bust($bust));

        $not_bust = [
            ['value' => 'K', 'suit' => 'hearts'],
            ['value' => 'Q', 'suit' => 'spades']
        ];
        $this->assertFalse(game::is_bust($not_bust));

        $exactly_21 = [
            ['value' => 'A', 'suit' => 'hearts'],
            ['value' => 'K', 'suit' => 'spades']
        ];
        $this->assertFalse(game::is_bust($exactly_21));
    }

    /**
     * Test new game creation.
     */
    public function test_new_game() {
        $gamestate = game::new_game();

        // Player should have 2 cards.
        $this->assertCount(2, $gamestate['playerhand']);

        // Dealer should have 2 cards.
        $this->assertCount(2, $gamestate['dealerhand']);

        // Deck should have 48 cards remaining.
        $this->assertCount(48, $gamestate['deck']);

        // Status should be 'playing'.
        $this->assertEquals('playing', $gamestate['status']);

        // Default bet should be 10.
        $this->assertEquals(10, $gamestate['bet']);
    }

    /**
     * Test hit action.
     */
    public function test_hit() {
        $gamestate = game::new_game();
        $initial_card_count = count($gamestate['playerhand']);
        $initial_deck_count = count($gamestate['deck']);

        $gamestate = game::hit($gamestate);

        // Player should have one more card.
        $this->assertCount($initial_card_count + 1, $gamestate['playerhand']);

        // Deck should have one less card.
        $this->assertCount($initial_deck_count - 1, $gamestate['deck']);
    }

    /**
     * Test hit resulting in bust.
     */
    public function test_hit_bust() {
        // Create a game state where player will bust.
        $gamestate = [
            'deck' => [['value' => 'K', 'suit' => 'clubs']],
            'playerhand' => [
                ['value' => 'K', 'suit' => 'hearts'],
                ['value' => 'Q', 'suit' => 'spades']
            ],
            'dealerhand' => [
                ['value' => '7', 'suit' => 'hearts'],
                ['value' => '8', 'suit' => 'spades']
            ],
            'status' => 'playing',
            'bet' => 10
        ];

        $gamestate = game::hit($gamestate);

        $this->assertEquals('player_bust', $gamestate['status']);
        $this->assertEquals('lose', $gamestate['result']);
        $this->assertEquals(-10, $gamestate['payout']);
    }

    /**
     * Test stand action - player wins.
     */
    public function test_stand_player_wins() {
        $gamestate = [
            'deck' => array_fill(0, 10, ['value' => '2', 'suit' => 'clubs']),
            'playerhand' => [
                ['value' => 'K', 'suit' => 'hearts'],
                ['value' => '9', 'suit' => 'spades']
            ],
            'dealerhand' => [
                ['value' => '8', 'suit' => 'hearts'],
                ['value' => '9', 'suit' => 'spades']
            ],
            'status' => 'playing',
            'bet' => 10
        ];

        $gamestate = game::stand($gamestate);

        $this->assertEquals('player_wins', $gamestate['status']);
        $this->assertEquals('win', $gamestate['result']);
        $this->assertEquals(10, $gamestate['payout']);
    }

    /**
     * Test stand action - dealer wins.
     */
    public function test_stand_dealer_wins() {
        $gamestate = [
            'deck' => array_fill(0, 10, ['value' => '2', 'suit' => 'clubs']),
            'playerhand' => [
                ['value' => '8', 'suit' => 'hearts'],
                ['value' => '9', 'suit' => 'spades']
            ],
            'dealerhand' => [
                ['value' => 'K', 'suit' => 'hearts'],
                ['value' => '9', 'suit' => 'spades']
            ],
            'status' => 'playing',
            'bet' => 10
        ];

        $gamestate = game::stand($gamestate);

        $this->assertEquals('dealer_wins', $gamestate['status']);
        $this->assertEquals('lose', $gamestate['result']);
        $this->assertEquals(-10, $gamestate['payout']);
    }

    /**
     * Test stand action - push (tie).
     */
    public function test_stand_push() {
        $gamestate = [
            'deck' => array_fill(0, 10, ['value' => '2', 'suit' => 'clubs']),
            'playerhand' => [
                ['value' => 'K', 'suit' => 'hearts'],
                ['value' => '8', 'suit' => 'spades']
            ],
            'dealerhand' => [
                ['value' => 'Q', 'suit' => 'hearts'],
                ['value' => '8', 'suit' => 'spades']
            ],
            'status' => 'playing',
            'bet' => 10
        ];

        $gamestate = game::stand($gamestate);

        $this->assertEquals('push', $gamestate['status']);
        $this->assertEquals('push', $gamestate['result']);
        $this->assertEquals(0, $gamestate['payout']);
    }

    /**
     * Test stand action - dealer busts.
     */
    public function test_stand_dealer_busts() {
        $gamestate = [
            'deck' => [['value' => 'K', 'suit' => 'clubs']],
            'playerhand' => [
                ['value' => '8', 'suit' => 'hearts'],
                ['value' => '9', 'suit' => 'spades']
            ],
            'dealerhand' => [
                ['value' => '6', 'suit' => 'hearts'],
                ['value' => '9', 'suit' => 'spades']
            ],
            'status' => 'playing',
            'bet' => 10
        ];

        $gamestate = game::stand($gamestate);

        $this->assertEquals('dealer_bust', $gamestate['status']);
        $this->assertEquals('win', $gamestate['result']);
        $this->assertEquals(10, $gamestate['payout']);
    }

    /**
     * Test can_double_down.
     */
    public function test_can_double_down() {
        // Can double with 2 cards.
        $gamestate = game::new_game();
        $this->assertTrue(game::can_double_down($gamestate));

        // Cannot double after hitting.
        $gamestate = game::hit($gamestate);
        $this->assertFalse(game::can_double_down($gamestate));

        // Cannot double if game is over.
        $gamestate['status'] = 'player_wins';
        $this->assertFalse(game::can_double_down($gamestate));
    }

    /**
     * Test double down action.
     */
    public function test_double_down() {
        $gamestate = [
            'deck' => [['value' => '5', 'suit' => 'clubs']] + array_fill(1, 10, ['value' => '2', 'suit' => 'clubs']),
            'playerhand' => [
                ['value' => '5', 'suit' => 'hearts'],
                ['value' => '6', 'suit' => 'spades']
            ],
            'dealerhand' => [
                ['value' => '8', 'suit' => 'hearts'],
                ['value' => '9', 'suit' => 'spades']
            ],
            'status' => 'playing',
            'bet' => 10
        ];

        $gamestate = game::double_down($gamestate);

        // Bet should be doubled.
        $this->assertEquals(20, $gamestate['bet']);

        // Player should have 3 cards.
        $this->assertCount(3, $gamestate['playerhand']);

        // Game should be over (stand is automatic).
        $this->assertNotEquals('playing', $gamestate['status']);
    }

    /**
     * Test can_split.
     */
    public function test_can_split() {
        // Can split with a pair.
        $gamestate = [
            'playerhand' => [
                ['value' => '8', 'suit' => 'hearts'],
                ['value' => '8', 'suit' => 'spades']
            ],
            'dealerhand' => [
                ['value' => '7', 'suit' => 'hearts'],
                ['value' => '9', 'suit' => 'spades']
            ],
            'status' => 'playing',
            'bet' => 10
        ];
        $this->assertTrue(game::can_split($gamestate));

        // Can split face cards (same rank = 10).
        $gamestate['playerhand'] = [
            ['value' => 'K', 'suit' => 'hearts'],
            ['value' => 'Q', 'suit' => 'spades']
        ];
        $this->assertTrue(game::can_split($gamestate));

        // Cannot split non-pair.
        $gamestate['playerhand'] = [
            ['value' => '8', 'suit' => 'hearts'],
            ['value' => '9', 'suit' => 'spades']
        ];
        $this->assertFalse(game::can_split($gamestate));

        // Cannot split with more than 2 cards.
        $gamestate['playerhand'] = [
            ['value' => '8', 'suit' => 'hearts'],
            ['value' => '8', 'suit' => 'spades'],
            ['value' => '3', 'suit' => 'clubs']
        ];
        $this->assertFalse(game::can_split($gamestate));

        // Cannot split if already split.
        $gamestate['playerhand'] = [
            ['value' => '8', 'suit' => 'hearts'],
            ['value' => '8', 'suit' => 'spades']
        ];
        $gamestate['is_split'] = true;
        $this->assertFalse(game::can_split($gamestate));
    }

    /**
     * Test split action.
     */
    public function test_split() {
        $gamestate = [
            'deck' => [
                ['value' => '5', 'suit' => 'clubs'],
                ['value' => '6', 'suit' => 'diamonds']
            ] + array_fill(2, 10, ['value' => '2', 'suit' => 'clubs']),
            'playerhand' => [
                ['value' => '8', 'suit' => 'hearts'],
                ['value' => '8', 'suit' => 'spades']
            ],
            'dealerhand' => [
                ['value' => '7', 'suit' => 'hearts'],
                ['value' => '9', 'suit' => 'spades']
            ],
            'status' => 'playing',
            'bet' => 10
        ];

        $gamestate = game::split($gamestate);

        // Should be marked as split.
        $this->assertTrue($gamestate['is_split']);

        // Current hand should be 1.
        $this->assertEquals(1, $gamestate['current_hand']);

        // Both hands should have 2 cards each.
        $this->assertCount(2, $gamestate['playerhand']);
        $this->assertCount(2, $gamestate['splithand']);

        // Original cards should be split.
        $this->assertEquals('8', $gamestate['playerhand'][0]['value']);
        $this->assertEquals('8', $gamestate['splithand'][0]['value']);

        // Split bet should equal original bet.
        $this->assertEquals(10, $gamestate['splitbet']);

        // Both hands should be playing.
        $this->assertEquals('playing', $gamestate['hand1_status']);
        $this->assertEquals('playing', $gamestate['hand2_status']);
    }

    /**
     * Test user points management.
     */
    public function test_user_points() {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // New user should get 100 starting points.
        $record = game::get_user_points($user->id);
        $this->assertEquals(100, $record->points);

        // Update points (win).
        $newpoints = game::update_user_points($user->id, 50);
        $this->assertEquals(150, $newpoints);

        // Update points (lose).
        $newpoints = game::update_user_points($user->id, -30);
        $this->assertEquals(120, $newpoints);

        // Points should not go below 0.
        $newpoints = game::update_user_points($user->id, -200);
        $this->assertEquals(0, $newpoints);
    }

    /**
     * Test card formatting.
     */
    public function test_format_card() {
        $card = ['value' => 'K', 'suit' => 'hearts'];
        $formatted = game::format_card($card);

        $this->assertEquals('K', $formatted['value']);
        $this->assertEquals('hearts', $formatted['suit']);
        $this->assertEquals('♥', $formatted['symbol']);
        $this->assertEquals('red', $formatted['color']);

        $card = ['value' => 'A', 'suit' => 'spades'];
        $formatted = game::format_card($card);

        $this->assertEquals('A', $formatted['value']);
        $this->assertEquals('spades', $formatted['suit']);
        $this->assertEquals('♠', $formatted['symbol']);
        $this->assertEquals('black', $formatted['color']);
    }

    /**
     * Test get_card_rank.
     */
    public function test_get_card_rank() {
        $this->assertEquals(10, game::get_card_rank(['value' => 'K', 'suit' => 'hearts']));
        $this->assertEquals(10, game::get_card_rank(['value' => 'Q', 'suit' => 'hearts']));
        $this->assertEquals(10, game::get_card_rank(['value' => 'J', 'suit' => 'hearts']));
        $this->assertEquals(10, game::get_card_rank(['value' => '10', 'suit' => 'hearts']));
        $this->assertEquals(11, game::get_card_rank(['value' => 'A', 'suit' => 'hearts']));
        $this->assertEquals(5, game::get_card_rank(['value' => '5', 'suit' => 'hearts']));
        $this->assertEquals(2, game::get_card_rank(['value' => '2', 'suit' => 'hearts']));
    }

    /**
     * Test split game flow - playing both hands.
     */
    public function test_split_game_flow() {
        $gamestate = [
            'deck' => [
                ['value' => '3', 'suit' => 'clubs'],    // Card for split hand 2.
                ['value' => '4', 'suit' => 'diamonds'], // Card for split hand 1.
                ['value' => '5', 'suit' => 'hearts'],   // Hit card for hand 1.
                ['value' => '6', 'suit' => 'spades'],   // Dealer draw.
            ],
            'playerhand' => [
                ['value' => '7', 'suit' => 'hearts'],
                ['value' => '7', 'suit' => 'spades']
            ],
            'dealerhand' => [
                ['value' => '6', 'suit' => 'hearts'],
                ['value' => '9', 'suit' => 'spades']
            ],
            'status' => 'playing',
            'bet' => 10
        ];

        // Split the hand.
        $gamestate = game::split($gamestate);
        $this->assertEquals(1, $gamestate['current_hand']);

        // Hit on hand 1.
        $gamestate = game::hit($gamestate);
        $this->assertCount(3, $gamestate['playerhand']);
        $this->assertEquals(1, $gamestate['current_hand']); // Still on hand 1.

        // Stand on hand 1 - should move to hand 2.
        $gamestate = game::stand($gamestate);
        $this->assertEquals(2, $gamestate['current_hand']);
        $this->assertEquals('stand', $gamestate['hand1_status']);

        // Stand on hand 2 - should finish game.
        $gamestate = game::stand($gamestate);
        $this->assertEquals('split_complete', $gamestate['status']);
        $this->assertArrayHasKey('payout', $gamestate);
        $this->assertArrayHasKey('payout1', $gamestate);
        $this->assertArrayHasKey('payout2', $gamestate);
    }

    /**
     * Test double down during split game.
     */
    public function test_double_down_during_split() {
        $gamestate = [
            'deck' => [
                ['value' => '3', 'suit' => 'clubs'],
                ['value' => '4', 'suit' => 'diamonds'],
                ['value' => '5', 'suit' => 'hearts'],
            ] + array_fill(3, 10, ['value' => '2', 'suit' => 'clubs']),
            'playerhand' => [
                ['value' => '5', 'suit' => 'hearts'],
                ['value' => '4', 'suit' => 'diamonds']
            ],
            'splithand' => [
                ['value' => '5', 'suit' => 'spades'],
                ['value' => '3', 'suit' => 'clubs']
            ],
            'dealerhand' => [
                ['value' => '6', 'suit' => 'hearts'],
                ['value' => '9', 'suit' => 'spades']
            ],
            'status' => 'playing',
            'bet' => 10,
            'splitbet' => 10,
            'is_split' => true,
            'current_hand' => 1,
            'hand1_status' => 'playing',
            'hand2_status' => 'playing'
        ];

        // Double down on hand 1.
        $gamestate = game::double_down($gamestate);

        // Bet should be doubled for hand 1.
        $this->assertEquals(20, $gamestate['bet']);

        // Should have moved to hand 2.
        $this->assertEquals(2, $gamestate['current_hand']);

        // Hand 1 should be standing.
        $this->assertEquals('stand', $gamestate['hand1_status']);

        // Game should still be playing (hand 2 not done yet).
        $this->assertEquals('playing', $gamestate['status']);

        // Double down on hand 2.
        $gamestate = game::double_down($gamestate);

        // Split bet should be doubled.
        $this->assertEquals(20, $gamestate['splitbet']);

        // Game should be complete.
        $this->assertEquals('split_complete', $gamestate['status']);
    }
}
