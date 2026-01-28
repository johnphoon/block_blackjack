<?php
/**
 * Block blackjack main class.
 *
 * @package block_blackjack
 */

use block_blackjack\game;

class block_blackjack extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_blackjack');
    }

    public function applicable_formats() {
        return array('all' => true);
    }

    public function get_content() {
        global $OUTPUT, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->footer = '';

        $userpoints = game::get_user_points($USER->id);

        $data = [
            'blockid' => $this->instance->id,
            'points' => $userpoints->points
        ];

        $this->content->text = $OUTPUT->render_from_template('block_blackjack/game', $data);

        return $this->content;
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function get_required_javascript() {
        parent::get_required_javascript();
        $this->page->requires->css('/blocks/blackjack/styles.css');
    }
}
