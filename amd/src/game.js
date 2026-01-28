/**
 * Blackjack game JavaScript.
 *
 * @package block_blackjack
 */
define(['jquery', 'core/ajax', 'core/notification'], function($, ajax, notification) {

    var Game = {
        blockid: null,

        /**
         * Initialize the game.
         * @param {number} blockid
         */
        init: function(blockid) {
            this.blockid = blockid;
            this.bindEvents();
            this.loadState();
        },

        /**
         * Bind button events.
         */
        bindEvents: function() {
            var self = this;
            var container = $('#block-blackjack-' + this.blockid);

            container.on('click', '.blackjack-deal', function(e) {
                e.preventDefault();
                var bet = container.find('.blackjack-bet').val() || 10;
                self.newGame(parseInt(bet, 10));
            });

            container.on('click', '.blackjack-hit', function(e) {
                e.preventDefault();
                self.hit();
            });

            container.on('click', '.blackjack-stand', function(e) {
                e.preventDefault();
                self.stand();
            });

            container.on('click', '.blackjack-double', function(e) {
                e.preventDefault();
                self.doubleDown();
            });

            container.on('click', '.blackjack-split', function(e) {
                e.preventDefault();
                self.split();
            });
        },

        /**
         * Load current game state.
         */
        loadState: function() {
            var self = this;
            ajax.call([{
                methodname: 'block_blackjack_get_state',
                args: {}
            }])[0].done(function(response) {
                self.updateUI(response);
            }).fail(notification.exception);
        },

        /**
         * Start a new game.
         * @param {number} bet
         */
        newGame: function(bet) {
            var self = this;
            ajax.call([{
                methodname: 'block_blackjack_new_game',
                args: {bet: bet}
            }])[0].done(function(response) {
                self.updateUI(response);
            }).fail(notification.exception);
        },

        /**
         * Player hits.
         */
        hit: function() {
            var self = this;
            ajax.call([{
                methodname: 'block_blackjack_hit',
                args: {}
            }])[0].done(function(response) {
                self.updateUI(response);
            }).fail(notification.exception);
        },

        /**
         * Player stands.
         */
        stand: function() {
            var self = this;
            ajax.call([{
                methodname: 'block_blackjack_stand',
                args: {}
            }])[0].done(function(response) {
                self.updateUI(response);
            }).fail(notification.exception);
        },

        /**
         * Player doubles down.
         */
        doubleDown: function() {
            var self = this;
            ajax.call([{
                methodname: 'block_blackjack_double_down',
                args: {}
            }])[0].done(function(response) {
                self.updateUI(response);
            }).fail(notification.exception);
        },

        /**
         * Player splits their pair.
         */
        split: function() {
            var self = this;
            ajax.call([{
                methodname: 'block_blackjack_split',
                args: {}
            }])[0].done(function(response) {
                self.updateUI(response);
            }).fail(notification.exception);
        },

        /**
         * Update the UI based on game state.
         * @param {object} state
         */
        updateUI: function(state) {
            var self = this;
            var container = $('#block-blackjack-' + this.blockid);

            // Update points display.
            container.find('.blackjack-points').text(state.points || 0);

            if (!state.success) {
                container.find('.blackjack-message').text(state.message || '').show();
                return;
            }

            // Show/hide appropriate sections.
            if (state.gameover === true && !state.playercards) {
                // No active game.
                container.find('.blackjack-game-area').hide();
                container.find('.blackjack-start-area').show();
                container.find('.blackjack-message').text('').hide();
                container.find('.blackjack-split-hand').hide();
            } else {
                container.find('.blackjack-game-area').show();
                container.find('.blackjack-start-area').hide();

                // Update dealer cards.
                this.renderCards(container.find('.blackjack-dealer-cards'), state.dealercards);
                container.find('.blackjack-dealer-score').text(state.dealerscore);

                // Update hand 1 cards.
                this.renderCards(container.find('.blackjack-player-cards'), state.playercards);
                container.find('.blackjack-player-score').text(state.playerscore);

                // Handle split hands.
                if (state.issplit) {
                    container.find('.blackjack-split-hand').show();
                    this.renderCards(container.find('.blackjack-split-cards'), state.splitcards);
                    container.find('.blackjack-split-score').text(state.splitscore);

                    // Highlight current hand.
                    container.find('.blackjack-player-hand').removeClass('active-hand');
                    container.find('.blackjack-split-hand').removeClass('active-hand');

                    if (!state.gameover) {
                        if (state.currenthand === 1) {
                            container.find('.blackjack-player-hand').addClass('active-hand');
                        } else {
                            container.find('.blackjack-split-hand').addClass('active-hand');
                        }
                    }

                    // Update bet displays.
                    container.find('.blackjack-current-bet').text(state.bet);
                    container.find('.blackjack-split-bet-amount').text(state.splitbet);
                    container.find('.blackjack-split-bet').show();

                    // Show hand results if game over.
                    if (state.gameover) {
                        var hand1Result = self.formatHandResult(state.hand1result, state.payout1);
                        var hand2Result = self.formatHandResult(state.hand2result, state.payout2);
                        container.find('.blackjack-hand1-result').html(hand1Result).show();
                        container.find('.blackjack-hand2-result').html(hand2Result).show();
                    } else {
                        container.find('.blackjack-hand1-result').hide();
                        container.find('.blackjack-hand2-result').hide();
                    }
                } else {
                    container.find('.blackjack-split-hand').hide();
                    container.find('.blackjack-split-bet').hide();
                    container.find('.blackjack-hand1-result').hide();
                    container.find('.blackjack-hand2-result').hide();
                    container.find('.blackjack-player-hand').removeClass('active-hand');

                    // Update bet display.
                    container.find('.blackjack-current-bet').text(state.bet);
                }

                // Show/hide action buttons.
                if (state.gameover) {
                    container.find('.blackjack-actions').hide();
                    container.find('.blackjack-result-area').show();
                    container.find('.blackjack-message').text(state.message).show();

                    // Show payout.
                    var payoutText = '';
                    if (state.payout > 0) {
                        payoutText = '+' + state.payout;
                        container.find('.blackjack-payout').removeClass('loss').addClass('win');
                        // Trigger confetti on win!
                        this.showConfetti(container);
                    } else if (state.payout < 0) {
                        payoutText = state.payout;
                        container.find('.blackjack-payout').removeClass('win').addClass('loss');
                    } else {
                        payoutText = '0';
                        container.find('.blackjack-payout').removeClass('win loss');
                    }
                    container.find('.blackjack-payout').text(payoutText);
                } else {
                    container.find('.blackjack-actions').show();
                    container.find('.blackjack-result-area').hide();
                    container.find('.blackjack-message').text('').hide();

                    // Show/hide double down button.
                    if (state.candoubledown) {
                        container.find('.blackjack-double').show();
                    } else {
                        container.find('.blackjack-double').hide();
                    }

                    // Show/hide split button.
                    if (state.cansplit) {
                        container.find('.blackjack-split').show();
                    } else {
                        container.find('.blackjack-split').hide();
                    }
                }
            }
        },

        /**
         * Format hand result for display.
         * @param {string} result
         * @param {number} payout
         * @return {string}
         */
        formatHandResult: function(result, payout) {
            var className = '';
            var payoutStr = '';
            if (payout > 0) {
                className = 'win';
                payoutStr = '+' + payout;
            } else if (payout < 0) {
                className = 'loss';
                payoutStr = payout;
            } else {
                payoutStr = '0';
            }
            return '<span class="hand-result ' + className + '">' + payoutStr + '</span>';
        },

        /**
         * Render cards into a container.
         * @param {jQuery} container
         * @param {array} cards
         */
        renderCards: function(container, cards) {
            container.empty();
            if (!cards) {
                return;
            }
            cards.forEach(function(card) {
                var cardClass = 'blackjack-card';
                if (card.suit === 'hidden') {
                    cardClass += ' card-hidden';
                } else {
                    cardClass += ' card-' + card.color;
                }
                var cardHtml = '<div class="' + cardClass + '">' +
                    '<span class="card-value">' + card.value + '</span>' +
                    '<span class="card-suit">' + card.symbol + '</span>' +
                    '</div>';
                container.append(cardHtml);
            });
        },

        /**
         * Show confetti celebration effect.
         * @param {jQuery} container
         */
        showConfetti: function(container) {
            var confettiContainer = container.find('.blackjack-confetti');
            if (confettiContainer.length === 0) {
                confettiContainer = $('<div class="blackjack-confetti"></div>');
                container.append(confettiContainer);
            }

            confettiContainer.empty();

            var colors = ['#f44336', '#e91e63', '#9c27b0', '#673ab7', '#3f51b5',
                          '#2196f3', '#03a9f4', '#00bcd4', '#009688', '#4caf50',
                          '#8bc34a', '#cddc39', '#ffeb3b', '#ffc107', '#ff9800', '#ff5722'];

            // Create confetti pieces.
            for (var i = 0; i < 50; i++) {
                var confetti = $('<div class="confetti-piece"></div>');
                var color = colors[Math.floor(Math.random() * colors.length)];
                var left = Math.random() * 100;
                var delay = Math.random() * 0.5;
                var duration = 1 + Math.random() * 1;
                var size = 5 + Math.random() * 10;

                confetti.css({
                    'background-color': color,
                    'left': left + '%',
                    'animation-delay': delay + 's',
                    'animation-duration': duration + 's',
                    'width': size + 'px',
                    'height': size + 'px'
                });

                confettiContainer.append(confetti);
            }

            // Remove confetti after animation.
            setTimeout(function() {
                confettiContainer.empty();
            }, 3000);
        }
    };

    return {
        init: function(blockid) {
            Game.init(blockid);
        }
    };
});
