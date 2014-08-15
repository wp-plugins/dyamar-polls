<?php

/*
	Plugin Name: DYAMAR Polls
	Plugin URI: http://dyamar.com/wordpress/dyamar-polls
	Description: This plugin allows to add interactive polls on your WordPress web site. You can use shortcuts to place desired questions in a post or in any widget.
	Author: DYAMAR Engineering
	Author URI: http://dyamar.com/
	Text Domain: dyamar-polls
	Version: 1.0.0
	License: GNU General Public License v2 or later
	License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

define("DYAMAR_POLLS_VERSION", "1.0.0");
define("DYAMAR_POLLS_ADMIN_PAGE", "dyamar_polls");

// Add actions that are required by our plugin

add_action('init', 'dyamar_polls_init');
add_action('admin_menu', 'dyamar_register_polls_page');
add_action('admin_enqueue_scripts', 'dyamar_polls_admin_scripts');
add_action('wp_enqueue_scripts', 'dyamar_polls_enqueue_scripts');
add_action('wp_ajax_dyamar_polls_vote', 'dyamar_polls_vote');
add_action('wp_ajax_nopriv_dyamar_polls_vote', 'dyamar_polls_vote');
add_filter('widget_text', 'dyamar_polls_do_shortcode');

register_activation_hook(__FILE__, 'dyamar_polls_activate');
register_uninstall_hook(__FILE__, 'dyamar_polls_uninstall');

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
		wp_enqueue_script('dyamar-polls', plugins_url( '/js/polls.js' , __FILE__ ), array(), DYAMAR_POLLS_VERSION, false);
		wp_enqueue_style('dyamar-polls', plugins_url( '/css/polls.css' , __FILE__ ), array(), DYAMAR_POLLS_VERSION, false);
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
}

function dyamar_polls_uninstall()
{
	// Remove all tables in case if we are uninstalling our plugin

	global $wpdb;

	$table_prefix = $wpdb->prefix . 'dyamar_';

	$wpdb->query('DROP TABLE IF EXISTS `' . $table_prefix . 'polls`;');
	$wpdb->query('DROP TABLE IF EXISTS `' . $table_prefix . 'polls_answers`;');
}

function dyamar_polls_init()
{
	add_shortcode('dyamar_poll', 'dyamar_polls_show');
}

function dyamar_polls_vote()
{
	if (!empty($_POST['poll_id']) && !empty($_POST['answer_ids']) && is_array($_POST['answer_ids']))
	{
		global $wpdb;

		$table_prefix = $wpdb->prefix . 'dyamar_';
		
		foreach ($_POST['answer_ids'] as $answer_id)
		{
			$wpdb->query('
				UPDATE `' . $table_prefix . 'polls_answers`
				SET `votes` = (votes+1)
				WHERE `poll_id` = ' . intval($_POST['poll_id']) . ' AND `answer_id` = ' . intval($answer_id) . ';
			');
		}
		
		// Now we need to check if we has to delete some answers
		$result['status'] = 'success';
		
		$result['answers'] = $wpdb->get_results('
			SELECT * FROM `' . $table_prefix . 'polls_answers`
			WHERE `poll_id` = ' . intval($_POST['poll_id']) . '
			ORDER BY answer_id ASC;
		', ARRAY_A);

		echo json_encode($result);
	}

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

	return '<b>Error: no poll found with the specified ID</b>';
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

function dyamar_polls_get_all($current_page, $items_per_page)
{
	global $wpdb;

	$table_prefix = $wpdb->prefix . 'dyamar_';

	return $wpdb->get_results('
		SELECT * FROM `' . $table_prefix . 'polls` 
		LIMIT ' . $current_page * $items_per_page.', ' . $items_per_page . ';', ARRAY_A);
}

function dyamar_polls_get_all_count()
{
	global $wpdb;

	$table_prefix = $wpdb->prefix . 'dyamar_';

	return $wpdb->get_var('SELECT COUNT(id) FROM `' . $table_prefix . 'polls`;');
}

function dyamar_polls_render($poll)
{
	$already_voted = trim(strtolower($_COOKIE['POLL_' . $poll['poll']['id'] . '_VOTED'])) == 'yes';

?>
<div id="dyamar_poll_<?php echo $poll['poll']['id']; ?>" class="dyamar-poll poll-<?php echo $poll['poll']['id']; ?>">
	<input type="hidden" id="poll_<?php echo $poll['poll']['id']; ?>_lifetime" value="<?php echo $poll['poll']['lifetime']; ?>"/>
	<div class="title">
		<p><?php echo $poll['poll']['title']; ?></p>
	</div>
	<div class="poll-content"<?php echo ($already_voted ? ' style="display:none;"' : ''); ?>>
		<div class="poll-answers">
<?php

	$max_answers = $poll['poll']['max_answers'];

	foreach ($poll['answers'] as $answer)
	{
		if ($max_answers == 1)
		{
?>
			<p><label><input type="radio" id="answer_<?php echo $answer['answer_id']; ?>" name="answer"/>&nbsp;&nbsp;<?php echo $answer['title']; ?></label></p>
<?php
		}
		else
		{
?>
			<p><label><input type="checkbox" id="answer_<?php echo $answer['answer_id']; ?>"/>&nbsp;&nbsp;<?php echo $answer['title']; ?></label></p>
<?php
		}
	}

?>
		</div>
		<div class="actions">
			<p><button onclick="dyamar_polls_send_vote(<?php echo $poll['poll']['id'] . ',\'' . admin_url('admin-ajax.php') . '\''; ?>);">Vote!</button></p>
		</div>
		<div class="other">
			<p><a href="#" title="View results" onclick="return dyamar_polls_view_result(<?php echo $poll['poll']['id']; ?>)">View results</a></p>
		</div>
	</div>
	<div class="poll-result"<?php echo ($already_voted ? '': ' style="display:none;"');?>>
		<div class="poll-data">
<?php

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
		
?>
			<div class="poll-info-line">
				<label class="poll-label"><b><?php echo $answer['title']; ?></b></label>
				<div id="poll_bar_<?php echo $answer['answer_id']; ?>" class="poll-bar">
<?php
		if ($answer['votes'] == 1)
		{
?>
					<div class="poll-info"><?php echo $percentage; ?>%, <?php echo $answer['votes']; ?> vote</div>
<?php
		}
		else
		{
?>
					<div class="poll-info"><?php echo $percentage; ?>%, <?php echo $answer['votes']; ?> votes</div>
<?php
		}
		
		if ($percentage <= 0)
		{
?>
					<div class="poll-percentage" style="width:3px;"></div>
<?php
		}
		else
		{
?>
					<div class="poll-percentage" style="width:<?php echo $percentage; ?>%;"></div>
<?php
		}
?>
				</div>
			</div>
<?php
	}

?>
		</div>
		<div class="other">
			<p><a href="#" id="poll_<?php echo $poll['poll']['id']; ?>_view_answers"<?php echo ($already_voted ? ' style="display:none;"' : ''); ?> title="View answers" onclick="return dyamar_polls_view_answers(<?php echo $poll['poll']['id']; ?>)">View answers</a></p>
		</div>
	</div>
</div>
<?php
}

function drupal_polls_delete_poll($id)
{
	global $wpdb;

	$table_prefix = $wpdb->prefix . 'dyamar_';

	$wpdb->query('DELETE FROM `' . $table_prefix . 'polls` WHERE id = ' . intval($id). ';');
	$wpdb->query('DELETE FROM `' . $table_prefix . 'polls_answers` WHERE poll_id = ' . intval($id). ';');
}

function drupal_polls_edit_poll()
{
	global $wpdb;

	$table_prefix = $wpdb->prefix . 'dyamar_';

	if (	!empty($_POST['poll_id']) &&
			!empty($_POST['title']) &&
			!empty($_POST['answer_type']) &&
			!empty($_POST['answers']) &&
			is_array($_POST['answers']) &&
			(count($_POST['answers']) > 0)
		)
	{
		$max_answers = 0;
		$lifetime = 0;

		if ($_POST['answer_type'] === 'one')
		{
			$max_answers = 1;
		}
		else if ($_POST['answer_type'] === 'any')
		{
			$max_answers = 0;
		}

		$lifetime = intval($_POST['revote_time']);

		// Now we need to check if we has to delete some answers
		$answers = $wpdb->get_results('
			SELECT * FROM `' . $table_prefix . 'polls_answers`
			WHERE `poll_id` = ' . intval($_POST['poll_id']) . ';
		', ARRAY_A);

		foreach ($answers as $answer)
		{
			$answer_id = $answer['answer_id'];

			if (!in_array($answer_id, $_POST['answer_ids']))
			{
				$wpdb->query('DELETE FROM `' . $table_prefix . 'polls_answers` WHERE answer_id = ' . intval($answer_id));
			}
		}

		// Update data in the database
		$wpdb->query('
			UPDATE `' . $table_prefix . 'polls`
			SET `title` = \'' . $_POST['title'] . '\', `max_answers` = ' . $max_answers . ', `lifetime` = ' . $lifetime . '
			WHERE `id` = ' . $_POST['poll_id'] . ';
		');
		
		$new_poll_id = $_POST['poll_id'];

		foreach ($_POST['answers'] as $key => $answer)
		{
			if (!empty($answer))
			{
				$votes = 0;

				if (!empty($_POST['answers_votes'][$key]))
				{
					$votes = intval($_POST['answers_votes'][$key]);
				}

				if (!empty($_POST['answer_ids'][$key]) && (intval($_POST['answer_ids'][$key]) > 0))
				{
					$wpdb->query('
						UPDATE `' . $table_prefix . 'polls_answers`
						SET `title` = \'' . $answer . '\', votes = ' . $votes. '
						WHERE `poll_id` = ' . intval($_POST['poll_id']) . ' AND `answer_id` = ' . intval($_POST['answer_ids'][$key]) . ';
					');
				}
				else
				{
					$wpdb->query('
						INSERT INTO `' . $table_prefix . 'polls_answers`
						(`poll_id`, `title`, `votes`)
						VALUES
						(' . $new_poll_id . ', \'' . $answer . '\', ' . $votes. ');
					');
				}
			}
		}
	}
}

function drupal_polls_insert_new_poll()
{
/*

Array
(
    [title] => 1312312
    [answer_type] => one
    [answers] => Array
        (
            [0] => 123123
            [1] => sdfsdf
            [2] => xxxx
        )

    [button_save] =>
)

*/

	global $wpdb;

	$table_prefix = $wpdb->prefix . 'dyamar_';

	if (	!empty($_POST['title']) &&
			!empty($_POST['answer_type']) &&
			!empty($_POST['answers']) &&
			is_array($_POST['answers']) &&
			(count($_POST['answers']) > 0)
		)
	{
		$max_answers = 0;
		$lifetime = 0;

		if ($_POST['answer_type'] === 'one')
		{
			$max_answers = 1;
		}
		else if ($_POST['answer_type'] === 'any')
		{
			$max_answers = 0;
		}

		$lifetime = intval($_POST['revote_time']);
	
		$wpdb->query('
			INSERT INTO `' . $table_prefix . 'polls`
			(`created`, `title`, `max_answers`, `lifetime`)
			VALUES
			(NOW(), \'' . $_POST['title'] . '\', ' . $max_answers . ', ' . $lifetime . ');
		');
		
		$new_poll_id = $wpdb->insert_id;

		foreach ($_POST['answers'] as $key => $answer)
		{
			if (!empty($answer))
			{
				$votes = 0;

				if (!empty($_POST['answers_votes'][$key]))
				{
					$votes = intval($_POST['answers_votes'][$key]);
				}
			
				$wpdb->query('
					INSERT INTO `' . $table_prefix . 'polls_answers`
					(`poll_id`, `title`, `votes`)
					VALUES
					(' . $new_poll_id . ', \'' . $answer . '\', ' . $votes. ');
				');
			}
		}
	}
}

function dyamar_register_polls_page()
{
    add_menu_page(
    	'DYAMAR Polls',
    	'Polls',
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
<h1>DYAMAR Polls</h1>
<?php
	// Save poll if that is required
	if (!empty($_GET['save_poll']))
	{
		if (!empty($_POST['poll_id']))
		{
			drupal_polls_edit_poll();
		}
		else
		{
			drupal_polls_insert_new_poll();
		}
	}
	else if (!empty($_GET['delete']))
	{
		drupal_polls_delete_poll($_GET['delete']);
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
<h3>Add new poll to your site.</h3>

<div class="main-area">
	<form method="post" action="<?php echo add_query_arg(array('save_poll' => 'yes'), $request_main); ?>">
		<div class="new-poll">
			<p><label>Title</label></p>
			<p><input type="text" name="title" id="title" size="50"/></p>
			<p><label>Type</label></p>
			<p><label><input type="radio" name="answer_type" id="answer_type" value="one" checked="checked"/>Only one answer is allowed</label></p>
			<p><label><input type="radio" name="answer_type" id="answer_type" value="any"/>Multiple answers are allowed</label></p>
			<p><label>Revote is allowed every:</label></p>
			<p>
				<select id="revote_time" name="revote_time">
					<option<?php echo ' value="' . $revote_immediately .'"'; ?>>Immediately</option>
					<option<?php echo ' value="' . $revote_1_day .'"'; ?>>1 day</option>
					<option<?php echo ' value="' . $revote_3_days .'"'; ?>>3 days</option>
					<option<?php echo ' value="' . $revote_1_week .'"'; ?> selected="selected">1 week</option>
					<option<?php echo ' value="' . $revote_2_weeks .'"'; ?>>2 weeks</option>
					<option<?php echo ' value="' . $revote_1_month .'"'; ?>>1 month</option>
					<option<?php echo ' value="' . $revote_3_months .'"'; ?>>3 months</option>
					<option<?php echo ' value="' . $revote_6_months .'"'; ?>>6 months</option>
					<option<?php echo ' value="' . $revote_year .'"'; ?>Year</option>
				</select>
			</p>
			<p><label>Answers</label></p>
			<div id="answers-list">
				<p><label class="elem-id">1. </label><span><input type="text" size="50" name="answers[]"/></span>&nbsp;&nbsp;Votes:<input type="text" size="5" name="answers_votes[]" value="0"/>&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" title="Delete" onclick="return dyamar_polls_delete_answer(this);">Delete</a></p>
			</div>
			<p><button name="button_add_answer" id="button_add_answer" onclick="return dyamar_polls_add_answer();">Add Answer</button></p>
		</div>
		<button name="button_save" id="button_save" class="poll-button-save">Save Poll</button><button onclick="return dymar_polls_cancel_button('<?php echo $request_main; ?>');">Cancel</button>
	</form>
</div>
<?php

	}
	else if (!empty($_GET['edit']))
	{
		// Loading existing poll if we are editing something
		$poll = dyamar_polls_get($_GET['edit']);

		if (!empty($poll) && is_array($poll))
		{
			$max_answers = $poll['poll']['max_answers'];
			
			$lifetime = $poll['poll']['lifetime'];

?>
<h3>Add new poll to your site.</h3>

<div class="main-area">
	<form method="post" action="<?php echo add_query_arg(array('save_poll' => 'yes'), $request_main); ?>">
		<div class="new-poll">
			<input type="hidden" name="poll_id" id="poll_id" value="<?php echo $poll['poll']['id']; ?>"/>
			<p><label>Title</label></p>
			<p><input type="text" name="title" id="title" size="50" value="<?php echo $poll['poll']['title']; ?>"/></p>
			<p><label>Type</label></p>
			<p><label><input type="radio" name="answer_type" id="answer_type" value="one"<?php echo (($max_answers == 1) ? ' checked="checked"' : ''); ?>/>Only one answer is allowed</label></p>
			<p><label><input type="radio" name="answer_type" id="answer_type" value="any"<?php echo (($max_answers == 0) ? ' checked="checked"' : ''); ?>/>Multiple answers are allowed</label></p>
			<p><label>Revote is allowed every:</label></p>
			<p>
				<select id="revote_time" name="revote_time">
					<option<?php echo ' value="' . $revote_immediately .'"'; echo ($lifetime == $revote_immediately ? ' selected="selected"' : '');?>>Immediately</option>
					<option<?php echo ' value="' . $revote_1_day .'"'; echo ($lifetime == $revote_1_day ? ' selected="selected"' : '');?>>1 day</option>
					<option<?php echo ' value="' . $revote_3_days .'"'; echo ($lifetime == $revote_3_days ? ' selected="selected"' : '');?>>3 days</option>
					<option<?php echo ' value="' . $revote_1_week .'"'; echo ($lifetime == $revote_1_week ? ' selected="selected"' : '');?>>1 week</option>
					<option<?php echo ' value="' . $revote_2_weeks .'"'; echo ($lifetime == $revote_2_weeks ? ' selected="selected"' : '');?>>2 weeks</option>
					<option<?php echo ' value="' . $revote_1_month .'"'; echo ($lifetime == $revote_1_month ? ' selected="selected"' : '');?>>1 month</option>
					<option<?php echo ' value="' . $revote_3_months .'"'; echo ($lifetime == $revote_3_months ? ' selected="selected"' : '');?>>3 months</option>
					<option<?php echo ' value="' . $revote_6_months .'"'; echo ($lifetime == $revote_6_months ? ' selected="selected"' : '');?>>6 months</option>
					<option<?php echo ' value="' . $revote_year .'"'; echo ($lifetime == $revote_year ? ' selected="selected"' : '');?>>Year</option>
				</select>
			</p>
			<p><label>Answers</label></p>
			<div id="answers-list">
<?php
		$index = 1;

		foreach ($poll['answers'] as $answer)
		{
?>
				<p><label class="elem-id"><?php echo $index; ?>. </label><input type="hidden" name="answer_ids[<?php echo $index; ?>]" value="<?php echo $answer['answer_id']; ?>"/><span><input type="text" size="50" name="answers[<?php echo $index; ?>]" value="<?php echo $answer['title']; ?>"/></span>&nbsp;&nbsp;Votes:<input type="text" size="5" name="answers_votes[<?php echo $index; ?>]" value="<?php echo $answer['votes']; ?>"/>&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" title="Delete" onclick="return dyamar_polls_delete_answer(this);">Delete</a></p>
<?php
			$index++;
		}
?>
			</div>
			<p><button name="button_add_answer" id="button_add_answer" onclick="return dyamar_polls_add_answer();">Add Answer</button></p>
		</div>
		<button name="button_save" id="button_save" class="poll-button-save">Save Poll</button><button onclick="return dymar_polls_cancel_button('<?php echo $request_main; ?>');">Cancel</button>
	</form>
</div>
<?php
		}
		else
		{
?>
<h3>Add new poll to your site.</h3>

<div class="main-area">
	<p><b>Error: failed to get information from the database</b></p>
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
		
		$polls = dyamar_polls_get_all($current_page - 1, $items_per_page);

		$pagelink_args = array(
			'base'         => $request_main . '%_%',
			'format'       => '&subpage=%#%',
			'total'        => $total_pages,
			'current'      => $current_page,
			'show_all'     => false,
			'end_size'     => 4,
			'mid_size'     => 4,
			'prev_next'    => true,
			'prev_text'    => __('« Previous'),
			'next_text'    => __('Next »'),
			'type'         => 'plain',
			'add_args'     => true,
			'add_fragment' => '',
			'before_page_number' => '',
			'after_page_number' => ''
		);

?>
<h3>List of your interactive polls.</h3>

<div class="main-area">

<div class="info-area">
	<div class="info-widget">
		<div class="info-header">About</div>
		<div class="info-content">
		<p>This plugin was developed by the <a href="http://dyamar.com?source=wp-dyamar-polls" target="_blank" title="DYAMAR">DYAMAR Engineering</a> company.</p>
		<p>Version <b><?php echo DYAMAR_POLLS_VERSION; ?></b></p>
		<p>Link: <a href="http://dyamar.com?source=wp-dyamar-polls" target="_blank" title="DYAMAR">http://dyamar.com</a></p>
		</div>
	</div>
	<div class="info-widget">
		<div class="info-header">Help</div>
		<div class="info-content">
		<p>We are ready to help you! Our goal is to make high-quality products.</p>
		<p>Please use <a href="http://dyamar.com/contact-us?source=wp-dyamar-polls" target="_blank" title="Contact form">this contact form</a> to send us your questions.</p>
		</div>
	</div>
</div>

<div class="list-area">
	<div class="list-content">
		<form method="post" action="<?php echo add_query_arg(array('add_poll' => 'yes'), $request_main); ?>">
		<button>Add New Poll</button>
		<p><?php echo paginate_links($pagelink_args); ?></p>
		<table class="data-table">
			<tr>
				<th>ID</th>
				<th>Title</th>
				<th>Created</th>
				<th>Shortcode</th>
				<th>Actions</th>
			</tr>
<?php

	if (empty($polls) || !is_array($polls) || (count($polls) <= 0))
	{
?>
			<tr>
				<td colspan="5"><p>Currently, you do not have any active polls.</p></td>
			</tr>
<?php
	}
	else
	{
		foreach ($polls as $poll)
		{
?>
			<tr>
				<td class="id"><?php echo $poll['id']; ?></td>
				<td><?php echo $poll['title']; ?></td>
				<td><?php echo $poll['created']; ?></td>
				<td class="shortcode"><b>[dyamar_poll id="<?php echo $poll['id']; ?>"]</b></td>
				<td class="actions">
					<a href="<?php echo add_query_arg(array('edit' => $poll['id']), $request_main); ?>" title="Edit">Edit</a>
					<a href="<?php echo add_query_arg(array('delete' => $poll['id']), $request_main); ?>" title="Delete" onclick="return confirm('Are you sure?');">Delete</a>
				</td>
			</tr>
<?php
		}
	}
?>
		</table>
		<p><?php echo paginate_links($pagelink_args); ?></p>
		<div class="poll-hint"><b>Hint:</b> you can use generated <b>shortcodes</b> to place your poll in posts or widgets</div>
		</form>
	</div>	
</div>

</div>
<?php
	}
}

?>
