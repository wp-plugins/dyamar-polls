/*

	DYAMAR Polls - interactive polls for WordPress web sites
	Copyright (C) 2014  DYAMAR Engineering <info@dyamar.com>

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

*/

function dyamar_polls_send_vote(poll_id, ajaxurl)
{
	var has_answer = false;
	var answer_ids = [];

	jQuery("#dyamar_poll_" + poll_id + " .dyamar-poll-answers input").each(function(index)
	{
		if (jQuery(this).prop('checked'))
		{
			has_answer = true;
			
			var elem_id = jQuery(this).attr('id');
			
			if (elem_id && (elem_id.length > 0))
			{
				answer_ids.push(elem_id.replace('dyamar_poll_answer_', ''));
			}
		}
	});
	
	if (!has_answer)
	{
		alert('Please specify at least one answer!');
		
		return;
	}
	
	var data = {
		'action': 'dyamar_polls_vote',
		'dyamar_poll_id': poll_id,
		'dyamar_poll_answer_ids' : answer_ids
	};

	jQuery.post(ajaxurl, data, function(response)
	{
		var result = jQuery.parseJSON(response);

		if (result['status'].indexOf('success') >= 0)
		{
			alert('Your vote has been successfully accepted!');

			var answers = result['answers'];

			if (answers instanceof Array)
			{
				var index = 0;
				var total_votes = 0;
	
				for (index = 0; index < answers.length; ++index)
				{
					total_votes += parseInt(answers[index]['votes']);
				}
				
				for (index = 0; index < answers.length; ++index)
				{
					var answer_id = answers[index]['answer_id'];
					
					if (answer_id > 0)
					{
						var percentage = 0;

						if (total_votes > 0)
						{
							percentage = Math.round((parseFloat(answers[index]['votes']) / (total_votes / 100.0)) * 100) / 100;
						}

						var poll_bar = jQuery("#dyamar_poll_" + poll_id + " #dyamar_poll_bar_" + answer_id);

						if (poll_bar && (poll_bar.length > 0))
						{
							var new_html = '';

							var votes = parseInt(answers[index]['votes']);

							if (votes == 1)
							{
								new_html += '<div class="dyamar-poll-info">' + percentage + '%, ' + votes + ' vote</div>';
							}
							else
							{
								new_html += '<div class="dyamar-poll-info">' + percentage + '%, ' + votes + ' votes</div>';
							}

							if (percentage <= 0)
							{
								new_html += '<div class="dyamar-poll-bar-bg" style="width:3px;"></div>';
							}
							else
							{
								new_html += '<div class="dyamar-poll-bar-bg" style="width:' + percentage + '%;"></div>';
							}
							
							poll_bar.html(new_html);
						}
					}
				}
			}

			var lifetime_elem = jQuery("#dyamar_poll_" + poll_id + "_lifetime");

			if (lifetime_elem && (lifetime_elem.length > 0))
			{
				var lifetime = parseInt(lifetime_elem.val());

				if (lifetime > 0)
				{
					var currentDate = new Date();
    				currentDate.setTime(currentDate.getTime() + lifetime * 1000);
    				var expires = "expires="+currentDate.toGMTString();
		    		document.cookie = "DYAMAR_POLL_" + poll_id + "_VOTED=YES; " + expires + "; path=/";

		    		jQuery("#dyamar_poll_" + poll_id + "_view_answers").hide();

		    		dyamar_polls_view_result(poll_id);
		    	}
		    }
		}
		else
		{
			alert('Failed to save results. Server is not responding. Please try again later.');
		}
	});
}

function dyamar_polls_view_result(poll_id)
{
	jQuery("#dyamar_poll_" + poll_id + " .dyamar-poll-content").hide();
	jQuery("#dyamar_poll_" + poll_id + " .dyamar-poll-result").show();

	return false;
}

function dyamar_polls_view_answers(poll_id)
{
	jQuery("#dyamar_poll_" + poll_id + " .dyamar-poll-result").hide();
	jQuery("#dyamar_poll_" + poll_id + " .dyamar-poll-content").show();
	
	return false;
}
