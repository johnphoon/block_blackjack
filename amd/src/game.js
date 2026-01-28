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
         * Update the UI based on game state.
         * @param {object} state
         */
        updateUI: function(state) {
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
            } else {
                container.find('.blackjack-game-area').show();
                container.find('.blackjack-start-area').hide();

                // Update cards.
                this.renderCards(container.find('.blackjack-dealer-cards'), state.dealercards);
                this.renderCards(container.find('.blackjack-player-cards'), state.playercards);

                // Update scores.
                container.find('.blackjack-dealer-score').text(state.dealerscore);
                container.find('.blackjack-player-score').text(state.playerscore);

                // Update bet display.
                container.find('.blackjack-current-bet').text(state.bet);

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
                }
            }
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
