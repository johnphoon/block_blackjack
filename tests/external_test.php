<?php
/**
 * Unit tests for block_blackjack external API.
 *
 * @package block_blackjack
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use block_blackjack\external;
use block_blackjack\game;

/**
 * Test cases for the blackjack external API.
 *
 * @package block_blackjack
 * @group block_blackjack
 */
class block_blackjack_external_test extends externallib_advanced_testcase {

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Clean up session after each test.
     */
    protected function tearDown(): void {
        if (isset($_SESSION['blackjack_game'])) {
            unset($_SESSION['blackjack_game']);
        }
        parent::tearDown();
    }

    /**
     * Test new game creation.
     */
    public function test_new_game() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = external::new_game(10);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['gameover']);
        $this->assertCount(2, $result['playercards']);
        $this->assertCount(2, $result['dealercards']);
        $this->assertEquals(10, $result['bet']);
        $this->assertEquals(100, $result['points']); // Starting points.
    }

    /**
     * Test new game with insufficient points.
     */
    public function test_new_game_insufficient_points() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Set user points to 5.
        game::update_user_points($user->id, -95);

        $result = external::new_game(10);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('enough points', $result['message']);
    }

    /**
     * Test hit action.
     */
    public function test_hit() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Start a new game first.
        external::new_game(10);

        $result = external::hit();

        $this->assertTrue($result['success']);
        $this->assertCount(3, $result['playercards']);
    }

    /**
     * Test hit without active game.
     */
    public function test_hit_no_game() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = external::hit();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No active game', $result['message']);
    }

    /**
     * Test stand action.
     */
    public function test_stand() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Start a new game first.
        external::new_game(10);

        $result = external::stand();

        $this->assertTrue($result['success']);
        $this->assertTrue($result['gameover']);
        $this->assertNotEmpty($result['message']);
    }

    /**
     * Test double down action.
     */
    public function test_double_down() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Start a new game.
        external::new_game(10);

        $result = external::double_down();

        $this->assertTrue($result['success']);
        $this->assertEquals(20, $result['bet']);
        $this->assertCount(3, $result['playercards']);
    }

    /**
     * Test double down with insufficient points.
     */
    public function test_double_down_insufficient_points() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Set user points to exactly 10.
        game::update_user_points($user->id, -90);

        // Start a game with 10 bet.
        external::new_game(10);

        // Try to double - should fail (need 10 more but have 0 left).
        $result = external::double_down();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('enough points', $result['message']);
    }

    /**
     * Test split action.
     */
    public function test_split() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Create a game state with a pair.
        $_SESSION['blackjack_game'] = [
            'deck' => game::create_deck(),
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

        $result = external::split();

        $this->assertTrue($result['success']);
        $this->assertTrue($result['issplit']);
        $this->assertEquals(1, $result['currenthand']);
        $this->assertCount(2, $result['playercards']);
        $this->assertCount(2, $result['splitcards']);
    }

    /**
     * Test split with non-pair.
     */
    public function test_split_non_pair() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Create a game state without a pair.
        $_SESSION['blackjack_game'] = [
            'deck' => game::create_deck(),
            'playerhand' => [
                ['value' => '8', 'suit' => 'hearts'],
                ['value' => '9', 'suit' => 'spades']
            ],
            'dealerhand' => [
                ['value' => '7', 'suit' => 'hearts'],
                ['value' => '9', 'suit' => 'spades']
            ],
            'status' => 'playing',
            'bet' => 10
        ];

        $result = external::split();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('pair', $result['message']);
    }

    /**
     * Test get_state with no active game.
     */
    public function test_get_state_no_game() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = external::get_state();

        $this->assertTrue($result['success']);
        $this->assertTrue($result['gameover']);
        $this->assertEquals(100, $result['points']);
    }

    /**
     * Test get_state with active game.
     */
    public function test_get_state_with_game() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Start a game.
        external::new_game(10);

        $result = external::get_state();

        $this->assertTrue($result['success']);
        $this->assertFalse($result['gameover']);
        $this->assertCount(2, $result['playercards']);
    }

    /**
     * Test that winning updates points correctly.
     */
    public function test_winning_updates_points() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Create a game where player will definitely win.
        $_SESSION['blackjack_game'] = [
            'deck' => array_fill(0, 10, ['value' => '6', 'suit' => 'clubs']),
            'playerhand' => [
                ['value' => 'K', 'suit' => 'hearts'],
                ['value' => 'K', 'suit' => 'spades']
            ],
            'dealerhand' => [
                ['value' => '6', 'suit' => 'hearts'],
                ['value' => '9', 'suit' => 'spades']
            ],
            'status' => 'playing',
            'bet' => 10
        ];

        $result = external::stand();

        $this->assertTrue($result['success']);
        $this->assertTrue($result['gameover']);
        $this->assertEquals('win', $result['result']);
        $this->assertEquals(10, $result['payout']);
        $this->assertEquals(110, $result['points']); // 100 + 10 win.
    }

    /**
     * Test that losing updates points correctly.
     */
    public function test_losing_updates_points() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Create a game where player will definitely lose.
        $_SESSION['blackjack_game'] = [
            'deck' => array_fill(0, 10, ['value' => '2', 'suit' => 'clubs']),
            'playerhand' => [
                ['value' => '8', 'suit' => 'hearts'],
                ['value' => '7', 'suit' => 'spades']
            ],
            'dealerhand' => [
                ['value' => 'K', 'suit' => 'hearts'],
                ['value' => '9', 'suit' => 'spades']
            ],
            'status' => 'playing',
            'bet' => 10
        ];

        $result = external::stand();

        $this->assertTrue($result['success']);
        $this->assertTrue($result['gameover']);
        $this->assertEquals('lose', $result['result']);
        $this->assertEquals(-10, $result['payout']);
        $this->assertEquals(90, $result['points']); // 100 - 10 loss.
    }

    /**
     * Test dealer card is hidden during play.
     */
    public function test_dealer_card_hidden() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        external::new_game(10);
        $result = external::get_state();

        // Second dealer card should be hidden.
        $this->assertEquals('?', $result['dealercards'][1]['value']);
        $this->assertEquals('hidden', $result['dealercards'][1]['suit']);
    }

    /**
     * Test response structure for split game.
     */
    public function test_split_response_structure() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Create a split game that's complete.
        $_SESSION['blackjack_game'] = [
            'deck' => array_fill(0, 10, ['value' => '2', 'suit' => 'clubs']),
            'playerhand' => [
                ['value' => '8', 'suit' => 'hearts'],
                ['value' => '9', 'suit' => 'spades']
            ],
            'splithand' => [
                ['value' => '8', 'suit' => 'diamonds'],
                ['value' => 'K', 'suit' => 'clubs']
            ],
            'dealerhand' => [
                ['value' => '7', 'suit' => 'hearts'],
                ['value' => '9', 'suit' => 'spades']
            ],
            'status' => 'playing',
            'bet' => 10,
            'splitbet' => 10,
            'is_split' => true,
            'current_hand' => 2,
            'hand1_status' => 'stand',
            'hand2_status' => 'playing'
        ];

        // Stand on hand 2 to complete the game.
        $result = external::stand();

        $this->assertTrue($result['success']);
        $this->assertTrue($result['issplit']);
        $this->assertArrayHasKey('splitcards', $result);
        $this->assertArrayHasKey('splitscore', $result);
        $this->assertArrayHasKey('hand1result', $result);
        $this->assertArrayHasKey('hand2result', $result);
        $this->assertArrayHasKey('payout1', $result);
        $this->assertArrayHasKey('payout2', $result);
    }
}
