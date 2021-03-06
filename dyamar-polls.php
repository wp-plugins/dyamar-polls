<?php

/*
	Plugin Name: DYAMAR Polls
	Plugin URI: http://dyamar.com/wordpress/dyamar-polls
	Description: This plugin allows to add interactive polls on your WordPress web site. You can use shortcuts to place desired questions in a post or in any widget.
	Author: DYAMAR Engineering
	Author URI: http://dyamar.com/
	Text Domain: dyamar-polls
	Version: 1.2.0
	License: GNU General Public License v2 or later
	License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

define("DYAMAR_POLLS_VERSION", "1.2.0");
define("DYAMAR_POLLS_ADMIN_PAGE", "dyamar_polls");

// Add actions that are required by our plugin

add_action('init', 'dyamar_polls_init');
add_action('widgets_init', 'dyamar_widgets_init');
add_action('admin_menu', 'dyamar_register_polls_page');
add_action('admin_enqueue_scripts', 'dyamar_polls_admin_scripts');
add_action('wp_enqueue_scripts', 'dyamar_polls_enqueue_scripts');
add_action('wp_ajax_dyamar_polls_vote', 'dyamar_polls_vote');
add_action('wp_ajax_nopriv_dyamar_polls_vote', 'dyamar_polls_vote');
add_filter('widget_text', 'dyamar_polls_do_shortcode');
add_action('plugins_loaded', 'dyamar_polls_plugins_loaded');

register_activation_hook(__FILE__, 'dyamar_polls_activate');
register_uninstall_hook(__FILE__, 'dyamar_polls_uninstall');

// Classes

class DYAMARPollsWidget extends WP_Widget
{
	function __construct()
	{
		parent::__construct('dyamar-polls-widget', 'DYAMAR Polls', array('description' => __('Widget that displays AJAX polls.', 'dyamar-polls')));
	}

	function widget($args, $instance)
	{
		// Get poll
		if (empty($instance['poll_id']))
		{
			return;
		}
		
		$poll_id = intval($instance['poll_id']);

		$instance['title'] = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );

		echo $args['before_widget'];

		if ( !empty($instance['title']) )
		{
			echo $args['before_title'] . $instance['title'] . $args['after_title'];
		}

		$poll = dyamar_polls_get($poll_id);

		if (!empty($poll) && !empty($poll['poll']))
		{
			echo dyamar_polls_render($poll);
		}
		else
		{
			echo '<b>' . __('Error: no poll found with the specified ID', 'dyamar-polls') . '</b>';
		}

		echo $args['after_widget'];
	}

	function update($new_instance, $old_instance)
	{
		$instance = array();

		if (!empty($new_instance['title']))
		{
			$instance['title'] = strip_tags(stripslashes($new_instance['title']));
		}

		if (!empty($new_instance['poll_id']))
		{
			$instance['poll_id'] = (int)$new_instance['poll_id'];
		}

		return $instance;
	}

	function form($instance)
	{
		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		$poll_id = isset( $instance['poll_id'] ) ? $instance['poll_id'] : '';

		$polls = dyamar_polls_get_all();
		
		if ($polls && count($polls) > 0)
		{
?>
	<p>
		<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'dyamar-polls') ?></label>
		<input type="text" class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $title; ?>" />
	</p>
	<p>
		<?php _e('Please choose desired poll:', 'dyamar-polls') ?>
	</p>
	<p>
	<select style="width:100%;" id="<?php echo $this->get_field_id('poll_id'); ?>" name="<?php echo $this->get_field_name('poll_id'); ?>">
		<option value="0"><?php _e('&mdash; Select &mdash;', 'dyamar-polls') ?></option>
<?php

	foreach ($polls as $item)
	{
		echo '<option value="' . $item['id'] . '"'
			. selected( $poll_id, $item['id'], false )
			. '>'. esc_html(stripslashes($item['title'])) . '</option>';
	}
?>
	</select>
	</p>
<?php
		}
		else
		{
			echo '<p>'. sprintf(__('No polls have been created yet. <a href="%s">Create some</a>.', 'dyamar-polls'), admin_url('admin.php?page=' . DYAMAR_POLLS_ADMIN_PAGE)) . '</p>';
		}
	}
}

// Functions

function dyamar_polls_plugins_loaded()
{
	dyamar_polls_update_db();
}

function dyamar_polls_update_db()
{
	global $wpdb;

	$table_prefix = $wpdb->prefix . 'dyamar_';

	$installed_dyamar_polls_version = get_option("dyamar_polls_version");

	if ($installed_dyamar_polls_version != DYAMAR_POLLS_VERSION)
	{
		$columns = $wpdb->get_row('SHOW COLUMNS FROM `' . $table_prefix . 'polls` LIKE \'style\'');

		if (!$columns || !isset($columns->Field) || ($columns->Field != 'style'))
		{
			$wpdb->query('ALTER TABLE `' . $table_prefix . 'polls` ADD COLUMN `style` TEXT NOT NULL');
		}

		update_option("dyamar_polls_version", DYAMAR_POLLS_VERSION);
	}
}

function dyamar_polls_admin_scripts($name)
{
	// Register plugin stylesheet file
	if (stripos($name, DYAMAR_POLLS_ADMIN_PAGE) !== FALSE)
	{
		wp_register_style('dyamar_polls_admin_style', plugins_url('css/admin-style.css', __FILE__));
		wp_register_script('dyamar_polls_admin_script', plugins_url('js/management.js', __FILE__));
		
    	wp_enqueue_style('dyamar_polls_admin_style');
    	wp_enqueue_script('dyamar_polls_admin_script');
    }
}

function dyamar_polls_enqueue_scripts()
{
	if (is_archive() || is_single() || is_page() || is_home())
	{
		wp_enqueue_script('jquery');
		wp_enqueue_script('dyamar-polls', plugins_url( '/js/polls.js' , __FILE__ ), array(), DYAMAR_POLLS_VERSION);
		wp_enqueue_style('dyamar-polls', plugins_url( '/css/polls.css' , __FILE__ ), array(), DYAMAR_POLLS_VERSION);
	}
}

function dyamar_polls_activate()
{
	// Create required database structure

	global $wpdb;

	$table_prefix = $wpdb->prefix . 'dyamar_';
	
	$wpdb->query('
		CREATE TABLE IF NOT EXISTS `' . $table_prefix . 'polls` (
		  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  `title` varchar(100) NOT NULL,
		  `max_answers` int(11) NOT NULL,
		  `lifetime` bigint(20) unsigned NOT NULL,
		  `style` text NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
	');

	$wpdb->query('
		CREATE TABLE IF NOT EXISTS `' . $table_prefix . 'polls_answers` (
		  `answer_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		  `poll_id` bigint(20) unsigned NOT NULL,
		  `title` varchar(255) NOT NULL,
		  `votes` bigint(20) NOT NULL,
		  PRIMARY KEY (`answer_id`),
		  KEY `poll_id` (`poll_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
	');

	dyamar_polls_update_db();
}

function dyamar_polls_uninstall()
{
	// Remove all tables in case if we are uninstalling our plugin

	global $wpdb;

	$table_prefix = $wpdb->prefix . 'dyamar_';

	$wpdb->query('DROP TABLE IF EXISTS `' . $table_prefix . 'polls`;');
	$wpdb->query('DROP TABLE IF EXISTS `' . $table_prefix . 'polls_answers`;');

	delete_option("dyamar_polls_version");
}

function dyamar_polls_init()
{
	add_shortcode('dyamar_poll', 'dyamar_polls_show');
}

function dyamar_widgets_init()
{
	register_widget('DYAMARPollsWidget');
}

function dyamar_polls_vote()
{
	$result = array();

	if (	!empty($_POST['dyamar_poll_id']) &&
			!empty($_POST['dyamar_poll_answer_ids']) &&
			is_array($_POST['dyamar_poll_answer_ids']))
	{
		global $wpdb;

		$table_prefix = $wpdb->prefix . 'dyamar_';
		
		foreach ($_POST['dyamar_poll_answer_ids'] as $answer_id)
		{
			$wpdb->query('
				UPDATE `' . $table_prefix . 'polls_answers`
				SET `votes` = (votes+1)
				WHERE `poll_id` = ' . intval($_POST['dyamar_poll_id']) . ' AND `answer_id` = ' . intval($answer_id) . ';
			');
		}
		
		// Now we need to check if we has to delete some answers
		$result['status'] = 'success';
		
		$result['answers'] = $wpdb->get_results('
			SELECT votes, answer_id FROM `' . $table_prefix . 'polls_answers`
			WHERE `poll_id` = ' . intval($_POST['dyamar_poll_id']) . '
			ORDER BY answer_id ASC;
		', ARRAY_A);

		echo json_encode($result);
		
		exit;
	}

	$result['status'] = 'error';

	echo json_encode($result);

	exit;
}

function dyamar_polls_do_shortcode($text)
{
	if (stripos($text, 'dyamar_poll') !== FALSE)
	{
		return do_shortcode($text);
	}
	
	return $text;
}

function dyamar_polls_show($atts, $content = null)
{
	extract(shortcode_atts(array('id' => '0'), $atts));

	if (!empty($id))
	{
		$poll = dyamar_polls_get($id);
		
		if (!empty($poll) && !empty($poll['poll']))
		{
			return dyamar_polls_render($poll);
		}
	}

	return '<b>' . __('Error: no poll found with the specified ID', 'dyamar-polls') . '</b>';
}

function dyamar_polls_get($id)
{
	global $wpdb;

	$table_prefix = $wpdb->prefix . 'dyamar_';

	$poll['poll'] = $wpdb->get_row('SELECT * FROM `' . $table_prefix . 'polls` WHERE id = ' . intval($id) . ';', ARRAY_A);

	$poll['answers'] = $wpdb->get_results('
		SELECT * FROM `' . $table_prefix . 'polls_answers`
		WHERE poll_id = ' . intval($id) . '
		ORDER BY answer_id ASC;', ARRAY_A);

	return $poll;
}

function dyamar_polls_get_range($current_page, $items_per_page)
{
	global $wpdb;

	$table_prefix = $wpdb->prefix . 'dyamar_';

	return $wpdb->get_results('
		SELECT * FROM `' . $table_prefix . 'polls`
		ORDER BY id ASC
		LIMIT ' . $current_page * $items_per_page.', ' . $items_per_page . ';', ARRAY_A);
}

function dyamar_polls_get_all()
{
	global $wpdb;

	$table_prefix = $wpdb->prefix . 'dyamar_';

	return $wpdb->get_results('
		SELECT * FROM `' . $table_prefix . 'polls` ORDER BY id ASC;', ARRAY_A);
}

function dyamar_polls_get_all_count()
{
	global $wpdb;

	$table_prefix = $wpdb->prefix . 'dyamar_';

	return $wpdb->get_var('SELECT COUNT(id) FROM `' . $table_prefix . 'polls`;');
}

function dyamar_polls_render($poll)
{
	$result = '';

	$already_voted = false;

	if (!empty($poll['poll']['lifetime']) && (intval($poll['poll']['lifetime']) > 0))
	{
		$already_voted = trim(strtolower($_COOKIE['DYAMAR_POLL_' . $poll['poll']['id'] . '_VOTED'])) == 'yes';
	}

	$theme = "blue";

	if (!empty($poll['poll']['style']))
	{
		$styles = unserialize($poll['poll']['style']);

		if (is_array($styles) && !empty($styles['theme']))
		{
			$theme = $styles['theme'];
		}
	}

	$result .= '
<div id="dyamar_poll_' . $poll['poll']['id'] . '" class="dyamar-poll dyamar-poll-theme-' . $theme . ' dyamar-poll-' . $poll['poll']['id'] . '">
	<input type="hidden" id="dyamar_poll_' . $poll['poll']['id'] . '_lifetime" value="' . $poll['poll']['lifetime'] . '"/>
	<div class="dyamar-poll-title">
		<p>' . esc_html(stripslashes($poll['poll']['title'])) . '</p>
	</div>
	<div class="dyamar-poll-content"' . ($already_voted ? ' style="display:none;"' : '') . '>
		<div class="dyamar-poll-answers">
';

	$max_answers = $poll['poll']['max_answers'];

	foreach ($poll['answers'] as $answer)
	{
		if ($max_answers == 1)
		{
	$result .= '
			<p><label><input type="radio" id="dyamar_poll_answer_' . $answer['answer_id'] . '" name="dyamar_poll_answer"/>&nbsp;&nbsp;' . esc_html(stripslashes($answer['title'])) . '</label></p>
';
		}
		else
		{
	$result .= '
			<p><label><input type="checkbox" id="dyamar_poll_answer_' . $answer['answer_id'] . '"/>&nbsp;&nbsp;' . stripslashes(($answer['title'])) . '</label></p>
';
		}
	}

	$result .= '
		</div>';

	if (count($poll['answers']) > 0)
	{
		$result .= '
		<div class="dyamar-poll-actions">
			<p><button onclick="dyamar_polls_send_vote(' . $poll['poll']['id'] . ',\'' . admin_url('admin-ajax.php') . '\'' . ');">' . __('Vote!', 'dyamar-polls') . '</button></p>
		</div>';
	}

	$result .= '
		<div class="dyamar-poll-other">
			<p><a href="#" title="' . __('View results', 'dyamar-polls') . '" onclick="return dyamar_polls_view_result(' . $poll['poll']['id'] . ')">' . __('View results', 'dyamar-polls') . '</a></p>
		</div>
	</div>
	<div class="dyamar-poll-result"' . ($already_voted ? '': ' style="display:none;"') . '>
		<div class="dyamar-poll-data">
';

	// Get total number of votes
	$total_votes = 0;
	
	foreach ($poll['answers'] as $answer)
	{
		$total_votes += $answer['votes'];
	}

	// Show resutls
	$max_answers = $poll['poll']['max_answers'];

	foreach ($poll['answers'] as $answer)
	{
		$percentage = 0;

		if ($total_votes > 0)
		{
			$percentage = round($answer['votes'] / ($total_votes / 100.0), 2);
		}

	$result .= '
			<div class="dyamar-poll-line">
				<label class="dyamar-poll-label"><b>' . esc_html(stripslashes($answer['title'])) . '</b></label>
				<div id="dyamar_poll_bar_' . $answer['answer_id'] . '" class="dyamar-poll-bar">
';
		if ($answer['votes'] == 1)
		{
	$result .= '
					<div class="dyamar-poll-info">' . $percentage . '%, ' . $answer['votes'] . ' ' . __('vote', 'dyamar-polls') . '</div>
';
		}
		else
		{
	$result .= '
					<div class="dyamar-poll-info">' . $percentage . '%, ' . $answer['votes'] . ' ' . __('votes', 'dyamar-polls') . '</div>
';
		}

		if ($percentage <= 0)
		{
	$result .= '
					<div class="dyamar-poll-bar-bg" style="width:3px;"></div>
';
		}
		else
		{
	$result .= '
					<div class="dyamar-poll-bar-bg" style="width:' . $percentage . '%;"></div>
';
		}
	$result .= '
				</div>
			</div>
';
	}
	$result .= '
		</div>
		<div class="dyamar-poll-other">
			<p><a href="#" id="dyamar_poll_' . $poll['poll']['id'] . '_view_answers"' . ($already_voted ? ' style="display:none;"' : '') . ' title="' . __('View answers', 'dyamar-polls') . '" onclick="return dyamar_polls_view_answers(' . $poll['poll']['id'] . ')">' . __('View answers', 'dyamar-polls') . '</a></p>
		</div>
	</div>
</div>
';

	return $result;
}

function dyamar_polls_delete_poll($id)
{
	global $wpdb;

	$table_prefix = $wpdb->prefix . 'dyamar_';

	$wpdb->query('DELETE FROM `' . $table_prefix . 'polls` WHERE id = ' . intval($id). ';');
	$wpdb->query('DELETE FROM `' . $table_prefix . 'polls_answers` WHERE poll_id = ' . intval($id). ';');
}

function dyamar_polls_edit_poll()
{
	global $wpdb;

	$table_prefix = $wpdb->prefix . 'dyamar_';

	if (	!empty($_POST['dyamar_poll_id']) &&
			!empty($_POST['dyamar_poll_title']) &&
			!empty($_POST['dyamar_poll_theme']) &&
			!empty($_POST['dyamar_poll_answer_type']) &&
			!empty($_POST['dyamar_poll_answers']) &&
			is_array($_POST['dyamar_poll_answers']) &&
			(count($_POST['dyamar_poll_answers']) > 0)
		)
	{
		$max_answers = 0;
		$lifetime = 0;

		if ($_POST['dyamar_poll_answer_type'] === 'one')
		{
			$max_answers = 1;
		}
		else if ($_POST['dyamar_poll_answer_type'] === 'any')
		{
			$max_answers = 0;
		}

		$lifetime = intval($_POST['dyamar_poll_revote_time']);

		// Now we need to check if we has to delete some answers
		$answers = $wpdb->get_results('
			SELECT * FROM `' . $table_prefix . 'polls_answers`
			WHERE `poll_id` = ' . intval($_POST['dyamar_poll_id']) . ';
		', ARRAY_A);

		foreach ($answers as $answer)
		{
			$answer_id = $answer['answer_id'];

			if (!in_array($answer_id, $_POST['dyamar_poll_answer_ids']))
			{
				$wpdb->query('DELETE FROM `' . $table_prefix . 'polls_answers` WHERE answer_id = ' . intval($answer_id));
			}
		}

		// Update data in the database
		$styles = array();
		$styles['theme'] = esc_sql($_POST['dyamar_poll_theme']);

		$wpdb->query('
			UPDATE `' . $table_prefix . 'polls`
			SET `title` = \'' . esc_sql($_POST['dyamar_poll_title']) . '\', `max_answers` = ' . $max_answers . ', `lifetime` = ' . $lifetime . ', `style` = \'' . serialize($styles) . '\'
			WHERE `id` = ' . intval($_POST['dyamar_poll_id']) . ';
		');

		$new_poll_id = intval($_POST['dyamar_poll_id']);

		foreach ($_POST['dyamar_poll_answers'] as $key => $answer)
		{
			if (!empty($answer))
			{
				$votes = 0;

				if (!empty($_POST['dyamar_poll_answers_votes'][$key]))
				{
					$votes = intval($_POST['dyamar_poll_answers_votes'][$key]);
				}

				if (!empty($_POST['dyamar_poll_answer_ids'][$key]) && (intval($_POST['dyamar_poll_answer_ids'][$key]) > 0))
				{
					$wpdb->query('
						UPDATE `' . $table_prefix . 'polls_answers`
						SET `title` = \'' . esc_sql($answer) . '\', votes = ' . $votes. '
						WHERE `poll_id` = ' . intval($_POST['dyamar_poll_id']) . ' AND `answer_id` = ' . intval($_POST['dyamar_poll_answer_ids'][$key]) . ';
					');
				}
				else
				{
					$wpdb->query('
						INSERT INTO `' . $table_prefix . 'polls_answers`
						(`poll_id`, `title`, `votes`)
						VALUES
						(' . $new_poll_id . ', \'' . esc_sql($answer) . '\', ' . $votes. ');
					');
				}
			}
		}
	}
}

function dyamar_polls_insert_new_poll()
{
/*

Array
(
    [dyamar_poll_title] => 1312312
    [dyamar_poll_answer_type] => one
    [dyamar_poll_answers] => Array
        (
            [0] => 123123
            [1] => sdfsdf
            [2] => xxxx
        )
)

*/

	global $wpdb;

	$table_prefix = $wpdb->prefix . 'dyamar_';

	if (	!empty($_POST['dyamar_poll_title']) &&
			!empty($_POST['dyamar_poll_answer_type']) &&
			!empty($_POST['dyamar_poll_answers']) &&
			is_array($_POST['dyamar_poll_answers']) &&
			(count($_POST['dyamar_poll_answers']) > 0)
		)
	{
		$max_answers = 0;
		$lifetime = 0;

		if ($_POST['dyamar_poll_answer_type'] === 'one')
		{
			$max_answers = 1;
		}
		else if ($_POST['dyamar_poll_answer_type'] === 'any')
		{
			$max_answers = 0;
		}

		$lifetime = intval($_POST['dyamar_poll_revote_time']);

		$styles = array();
		$styles['theme'] = 'blue';
	
		if (!empty($_POST['dyamar_poll_theme']))
		{
			$styles['theme'] = esc_sql($_POST['dyamar_poll_theme']);
		}
	
		$wpdb->query('
			INSERT INTO `' . $table_prefix . 'polls`
			(`created`, `title`, `max_answers`, `lifetime`, `style`)
			VALUES
			(NOW(), \'' . esc_sql($_POST['dyamar_poll_title']) . '\', ' . $max_answers . ', ' . $lifetime . ', \'' . serialize($styles) . '\');
		');
		
		$new_poll_id = $wpdb->insert_id;

		foreach ($_POST['dyamar_poll_answers'] as $key => $answer)
		{
			if (!empty($answer))
			{
				$votes = 0;

				if (!empty($_POST['dyamar_poll_answers_votes'][$key]))
				{
					$votes = intval($_POST['dyamar_poll_answers_votes'][$key]);
				}
			
				$wpdb->query('
					INSERT INTO `' . $table_prefix . 'polls_answers`
					(`poll_id`, `title`, `votes`)
					VALUES
					(' . $new_poll_id . ', \'' . esc_sql($answer) . '\', ' . $votes. ');
				');
			}
		}
	}
}

function dyamar_register_polls_page()
{
    add_menu_page(
    	'DYAMAR Polls',
    	__('Polls', 'dyamar-polls'),
    	'manage_options',
    	DYAMAR_POLLS_ADMIN_PAGE,
    	'dyamar_polls_page',
    	plugins_url( 'images/menu-logo.png' , __FILE__), '50.9342'); 
}

function dyamar_polls_page()
{
	$request_uri = $_SERVER['REQUEST_URI'];
	$request_url = strtok($request_uri, '?');
	$request_main = add_query_arg(array('page' => DYAMAR_POLLS_ADMIN_PAGE), $request_url);
	
?>
<h1><?php _e('DYAMAR Polls'); ?></h1>
<?php
	// Save poll if that is required
	if (!empty($_GET['save_poll']))
	{
		if (!empty($_POST['dyamar_poll_id']))
		{
			dyamar_polls_edit_poll();
		}
		else
		{
			dyamar_polls_insert_new_poll();
		}
	}
	else if (!empty($_GET['delete']))
	{
		dyamar_polls_delete_poll($_GET['delete']);
	}

	$seconds_per_day = 86400;

	$revote_immediately = 0;
	$revote_1_day = $seconds_per_day;
	$revote_3_days = 3 * $seconds_per_day;
	$revote_1_week = 7 * $seconds_per_day;
	$revote_2_weeks = 2 * 7 * $seconds_per_day;
	$revote_1_month = 30 * $seconds_per_day;
	$revote_3_months = 91 * $seconds_per_day;
	$revote_6_months = 182 * $seconds_per_day;
	$revote_year = 365 * $seconds_per_day;

	// Render user interface
	if (!empty($_GET['add_poll']))
	{
?>
<h3><?php _e('Add new poll to your site.', 'dyamar-polls'); ?></h3>

<div class="dyamar-polls-main">
	<form method="post" action="<?php echo add_query_arg(array('save_poll' => 'yes'), $request_main); ?>">
		<div class="dyamar-polls-new">
			<p><label><?php _e('Title', 'dyamar-polls'); ?></label></p>
			<p><input type="text" class="dyamar-polls-title" name="dyamar_poll_title" id="dyamar_poll_title" size="50"/></p>
			<p><label><?php _e('Type', 'dyamar-polls'); ?></label></p>
			<p><label><input type="radio" name="dyamar_poll_answer_type" id="dyamar_poll_answer_single" value="one" checked="checked"/><?php _e('Only one answer is allowed', 'dyamar-polls'); ?></label></p>
			<p><label><input type="radio" name="dyamar_poll_answer_type" id="dyamar_poll_answer_multiple" value="any"/><?php _e('Multiple answers are allowed', 'dyamar-polls'); ?></label></p>
			<p><label><?php _e('Theme', 'dyamar-polls'); ?></label></p>
			<p>
				<select id="dyamar_poll_theme" name="dyamar_poll_theme">
					<option value="black"><?php _e('Black', 'dyamar-polls'); ?></option>
					<option value="blue" selected="selected"><?php _e('Blue', 'dyamar-polls'); ?></option>
					<option value="brown"><?php _e('Brown', 'dyamar-polls'); ?></option>
					<option value="gray"><?php _e('Gray', 'dyamar-polls'); ?></option>
					<option value="green"><?php _e('Green', 'dyamar-polls'); ?></option>
					<option value="pink"><?php _e('Pink', 'dyamar-polls'); ?></option>
					<option value="red"><?php _e('Red', 'dyamar-polls'); ?></option>
					<option value="yellow"><?php _e('Yellow', 'dyamar-polls'); ?></option>
				</select>
			</p>
			<p><label><?php _e('Revote is allowed every', 'dyamar-polls'); ?></label></p>
			<p>
				<select id="dyamar_poll_revote_time" name="dyamar_poll_revote_time">
					<option<?php echo ' value="' . $revote_immediately .'"'; ?>><?php _e('Immediately', 'dyamar-polls'); ?></option>
					<option<?php echo ' value="' . $revote_1_day .'"'; ?>><?php _e('1 day', 'dyamar-polls'); ?></option>
					<option<?php echo ' value="' . $revote_3_days .'"'; ?>><?php _e('3 days', 'dyamar-polls'); ?></option>
					<option<?php echo ' value="' . $revote_1_week .'"'; ?> selected="selected"><?php _e('1 week', 'dyamar-polls'); ?></option>
					<option<?php echo ' value="' . $revote_2_weeks .'"'; ?>><?php _e('2 weeks', 'dyamar-polls'); ?></option>
					<option<?php echo ' value="' . $revote_1_month .'"'; ?>><?php _e('1 month', 'dyamar-polls'); ?></option>
					<option<?php echo ' value="' . $revote_3_months .'"'; ?>><?php _e('3 months', 'dyamar-polls'); ?></option>
					<option<?php echo ' value="' . $revote_6_months .'"'; ?>><?php _e('6 months', 'dyamar-polls'); ?></option>
					<option<?php echo ' value="' . $revote_year .'"'; ?>><?php _e('Year', 'dyamar-polls'); ?></option>
				</select>
			</p>
			<p><label><?php _e('Answers', 'dyamar-polls'); ?></label></p>
			<div id="dyamar_poll_answers_list">
				<p><label class="dyamar-polls-elem">1. </label><span><input type="text" size="50" name="dyamar_poll_answers[]"/></span>&nbsp;&nbsp;<?php _e('Votes:', 'dyamar-polls'); ?><input type="text" size="5" name="dyamar_poll_answers_votes[]" value="0"/>&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" title="<?php _e('Delete', 'dyamar-polls'); ?>" onclick="return dyamar_polls_delete_answer(this);"><?php _e('Delete', 'dyamar-polls'); ?></a></p>
			</div>
			<p><button onclick="return dyamar_polls_add_answer();"><?php _e('Add Answer', 'dyamar-polls'); ?></button></p>
		</div>
		<button class="dyamar-polls-save"><?php _e('Save Poll', 'dyamar-polls'); ?></button><button onclick="return dymar_polls_cancel_button('<?php echo $request_main; ?>');"><?php _e('Cancel', 'dyamar-polls'); ?></button>
	</form>
</div>
<?php

	}
	else if (!empty($_GET['edit']))
	{
		// Loading existing poll if we are editing something
		$poll = dyamar_polls_get($_GET['edit']);

		$theme = "blue";

		if (!empty($poll['poll']['style']))
		{
			$styles = unserialize($poll['poll']['style']);

			if (is_array($styles) && !empty($styles['theme']))
			{
				$theme = $styles['theme'];
			}
		}

		if (!empty($poll) && is_array($poll))
		{
			$max_answers = $poll['poll']['max_answers'];
			
			$lifetime = $poll['poll']['lifetime'];
?>
<h3><?php _e('Edit your existing poll.', 'dyamar-polls'); ?></h3>

<div class="dyamar-polls-main">
	<form method="post" action="<?php echo add_query_arg(array('save_poll' => 'yes'), $request_main); ?>">
		<div class="dyamar-polls-new">
			<input type="hidden" name="dyamar_poll_id" id="dyamar_poll_id" value="<?php echo $poll['poll']['id']; ?>"/>
			<p><label><?php _e('Title', 'dyamar-polls'); ?></label></p>
			<p><input type="text" class="dyamar-polls-title" name="dyamar_poll_title" id="dyamar_poll_title" size="50" value="<?php echo esc_html(stripslashes($poll['poll']['title'])); ?>"/></p>
			<p><label><?php _e('Type', 'dyamar-polls'); ?></label></p>
			<p><label><input type="radio" name="dyamar_poll_answer_type" id="dyamar_poll_answer_single" value="one"<?php echo (($max_answers == 1) ? ' checked="checked"' : ''); ?>/><?php _e('Only one answer is allowed', 'dyamar-polls'); ?></label></p>
			<p><label><input type="radio" name="dyamar_poll_answer_type" id="dyamar_poll_answer_multiple" value="any"<?php echo (($max_answers == 0) ? ' checked="checked"' : ''); ?>/><?php _e('Multiple answers are allowed', 'dyamar-polls'); ?></label></p>
			<p><label><?php _e('Theme', 'dyamar-polls'); ?></label></p>
			<p>
				<select id="dyamar_poll_theme" name="dyamar_poll_theme">
					<option value="black"<?php echo ($theme == 'black' ? ' selected="selected"' : '');?>><?php _e('Black', 'dyamar-polls'); ?></option>
					<option value="blue"<?php echo ($theme == 'blue' ? ' selected="selected"' : '');?>><?php _e('Blue', 'dyamar-polls'); ?></option>
					<option value="brown"<?php echo ($theme == 'brown' ? ' selected="selected"' : '');?>><?php _e('Brown', 'dyamar-polls'); ?></option>
					<option value="gray"<?php echo ($theme == 'gray' ? ' selected="selected"' : '');?>><?php _e('Gray', 'dyamar-polls'); ?></option>
					<option value="green"<?php echo ($theme == 'green' ? ' selected="selected"' : '');?>><?php _e('Green', 'dyamar-polls'); ?></option>
					<option value="pink"<?php echo ($theme == 'pink' ? ' selected="selected"' : '');?>><?php _e('Pink', 'dyamar-polls'); ?></option>
					<option value="red"<?php echo ($theme == 'red' ? ' selected="selected"' : '');?>><?php _e('Red', 'dyamar-polls'); ?></option>
					<option value="yellow"<?php echo ($theme == 'yellow' ? ' selected="selected"' : '');?>><?php _e('Yellow', 'dyamar-polls'); ?></option>
				</select>
			</p>
			<p><label><?php _e('Revote is allowed every', 'dyamar-polls'); ?></label></p>
			<p>
				<select id="dyamar_poll_revote_time" name="dyamar_poll_revote_time">
					<option<?php echo ' value="' . $revote_immediately .'"'; echo ($lifetime == $revote_immediately ? ' selected="selected"' : '');?>><?php _e('Immediately', 'dyamar-polls'); ?></option>
					<option<?php echo ' value="' . $revote_1_day .'"'; echo ($lifetime == $revote_1_day ? ' selected="selected"' : '');?>><?php _e('1 day', 'dyamar-polls'); ?></option>
					<option<?php echo ' value="' . $revote_3_days .'"'; echo ($lifetime == $revote_3_days ? ' selected="selected"' : '');?>><?php _e('3 days', 'dyamar-polls'); ?></option>
					<option<?php echo ' value="' . $revote_1_week .'"'; echo ($lifetime == $revote_1_week ? ' selected="selected"' : '');?>><?php _e('1 week', 'dyamar-polls'); ?></option>
					<option<?php echo ' value="' . $revote_2_weeks .'"'; echo ($lifetime == $revote_2_weeks ? ' selected="selected"' : '');?>><?php _e('2 weeks', 'dyamar-polls'); ?></option>
					<option<?php echo ' value="' . $revote_1_month .'"'; echo ($lifetime == $revote_1_month ? ' selected="selected"' : '');?>><?php _e('1 month', 'dyamar-polls'); ?></option>
					<option<?php echo ' value="' . $revote_3_months .'"'; echo ($lifetime == $revote_3_months ? ' selected="selected"' : '');?>><?php _e('3 months', 'dyamar-polls'); ?></option>
					<option<?php echo ' value="' . $revote_6_months .'"'; echo ($lifetime == $revote_6_months ? ' selected="selected"' : '');?>><?php _e('6 months', 'dyamar-polls'); ?></option>
					<option<?php echo ' value="' . $revote_year .'"'; echo ($lifetime == $revote_year ? ' selected="selected"' : '');?>><?php _e('Year', 'dyamar-polls'); ?></option>
				</select>
			</p>
			<p><label><?php _e('Answers', 'dyamar-polls'); ?></label></p>
			<div id="dyamar_poll_answers_list">
<?php
		$index = 1;

		foreach ($poll['answers'] as $answer)
		{
?>
				<p><label class="dyamar-polls-elem"><?php echo $index; ?>. </label><input type="hidden" name="dyamar_poll_answer_ids[<?php echo $index; ?>]" value="<?php echo $answer['answer_id']; ?>"/><span><input type="text" size="50" name="dyamar_poll_answers[<?php echo $index; ?>]" value="<?php echo esc_html(stripslashes($answer['title'])); ?>"/></span>&nbsp;&nbsp;<?php _e('Votes:', 'dyamar-polls'); ?><input type="text" size="5" name="dyamar_poll_answers_votes[<?php echo $index; ?>]" value="<?php echo $answer['votes']; ?>"/>&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" title="<?php _e('Delete', 'dyamar-polls'); ?>" onclick="return dyamar_polls_delete_answer(this);"><?php _e('Delete', 'dyamar-polls'); ?></a></p>
<?php
			$index++;
		}
?>
			</div>
			<p><button onclick="return dyamar_polls_add_answer();"><?php _e('Add Answer', 'dyamar-polls'); ?></button></p>
		</div>
		<button class="dyamar-polls-save"><?php _e('Save Poll', 'dyamar-polls'); ?></button><button onclick="return dymar_polls_cancel_button('<?php echo $request_main; ?>');"><?php _e('Cancel', 'dyamar-polls'); ?></button>
	</form>
</div>
<?php
		}
		else
		{
?>
<h3><?php _e('Add new poll to your site.', 'dyamar-polls'); ?></h3>

<div class="dyamar-polls-main">
	<p><b><?php _e('Error: failed to get information from the database.', 'dyamar-polls'); ?></b></p>
</div>
<?php
		}
	}
	else
	{
		$total_items = dyamar_polls_get_all_count();

		$items_per_page = 5;
		$current_page = 1;
		$total_pages = floor($total_items / $items_per_page);

		if (($total_items % $items_per_page) > 0)
		{
			$total_pages++;
		}
		
		if (!empty($_GET['subpage']))
		{
			$current_page = intval($_GET['subpage']);
		}
		
		$polls = dyamar_polls_get_range($current_page - 1, $items_per_page);

		$pagelink_args = array(
			'base'         => $request_main . '%_%',
			'format'       => '&subpage=%#%',
			'total'        => $total_pages,
			'current'      => $current_page,
			'show_all'     => false,
			'end_size'     => 4,
			'mid_size'     => 4,
			'prev_next'    => true,
			'prev_text'    => __('« Previous', 'dyamar-polls'),
			'next_text'    => __('Next »', 'dyamar-polls'),
			'type'         => 'plain',
			'add_args'     => true,
			'add_fragment' => '',
			'before_page_number' => '',
			'after_page_number' => ''
		);

?>
<h3><?php _e('List of your interactive polls.', 'dyamar-polls'); ?></h3>

<div class="dyamar-polls-main">

<div class="dyamar-polls-info">
	<div class="dyamar-polls-widget">
		<div class="dyamar-polls-header"><?php _e('About', 'dyamar-polls'); ?></div>
		<div class="dyamar-polls-content">
		<p><?php _e('This plugin was developed by the <a href="http://dyamar.com?source=wp-dyamar-polls" target="_blank" title="DYAMAR">DYAMAR Engineering</a> company.', 'dyamar-polls'); ?></p>
		<p><?php _e('Version', 'dyamar-polls'); ?> <b><?php echo DYAMAR_POLLS_VERSION; ?></b></p>
		<p><?php _e('Link: <a href="http://dyamar.com?source=wp-dyamar-polls" target="_blank" title="DYAMAR">http://dyamar.com</a>', 'dyamar-polls'); ?></p>
		</div>
	</div>
	<div class="dyamar-polls-widget">
		<div class="dyamar-polls-header"><?php _e('Help', 'dyamar-polls'); ?></div>
		<div class="dyamar-polls-content">
		<p><?php _e('We are ready to help you! Our goal is to make high-quality products.', 'dyamar-polls'); ?></p>
		<p><?php _e('Please use <a href="http://dyamar.com/contact-us?source=wp-dyamar-polls" target="_blank" title="Contact form">this contact form</a> to send us your questions.', 'dyamar-polls'); ?></p>
		</div>
	</div>
</div>

<div class="dyamar-polls-list">
	<div class="dyamar-polls-list-content">
		<form method="post" action="<?php echo add_query_arg(array('add_poll' => 'yes'), $request_main); ?>">
		<button><?php _e('Add New Poll', 'dyamar-polls'); ?></button>
		<p><?php echo paginate_links($pagelink_args); ?></p>
		<table class="dyamar-polls-table">
			<tr>
				<th><?php _e('ID', 'dyamar-polls'); ?></th>
				<th><?php _e('Title', 'dyamar-polls'); ?></th>
				<th><?php _e('Created', 'dyamar-polls'); ?></th>
				<th><?php _e('Shortcode', 'dyamar-polls'); ?></th>
				<th><?php _e('Theme', 'dyamar-polls'); ?></th>
				<th><?php _e('Actions', 'dyamar-polls'); ?></th>
			</tr>
<?php

	if (empty($polls) || !is_array($polls) || (count($polls) <= 0))
	{
?>
			<tr>
				<td colspan="5"><p><?php _e('Currently, you do not have any active polls.', 'dyamar-polls'); ?></p></td>
			</tr>
<?php
	}
	else
	{
		foreach ($polls as $poll)
		{
			$theme = "blue";

			if (!empty($poll['style']))
			{
				$styles = unserialize($poll['style']);

				if (is_array($styles) && !empty($styles['theme']))
				{
					$theme = $styles['theme'];
				}
			}

?>
			<tr>
				<td class="dyamar-polls-id"><?php echo $poll['id']; ?></td>
				<td><?php echo esc_html(stripslashes($poll['title'])); ?></td>
				<td><?php echo $poll['created']; ?></td>
				<td class="dyamar-polls-shortcode"><b>[dyamar_poll id="<?php echo $poll['id']; ?>"]</b></td>
				<td class="dyamar-polls-theme"><?php echo ucwords($theme); ?></td>
				<td class="dyamar-polls-actions">
					<a href="<?php echo add_query_arg(array('edit' => $poll['id']), $request_main); ?>" title="<?php _e('Edit', 'dyamar-polls'); ?>"><?php _e('Edit', 'dyamar-polls'); ?></a>
					<a href="<?php echo add_query_arg(array('delete' => $poll['id']), $request_main); ?>" title="<?php _e('Delete', 'dyamar-polls'); ?>" onclick="return confirm('<?php _e('Are you sure?', 'dyamar-polls'); ?>');"><?php _e('Delete', 'dyamar-polls'); ?></a>
				</td>
			</tr>
<?php
		}
	}
?>
		</table>
		<p><?php echo paginate_links($pagelink_args); ?></p>
		<div class="dyamar-polls-hint"><?php _e('<b>Hint:</b> you can use generated <b>shortcodes</b> to place your polls in posts or widgets.', 'dyamar-polls'); ?></div>
		</form>
	</div>	
</div>

</div>
<?php
	}
}

?>
