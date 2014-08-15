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

function dyamar_polls_add_answer()
{
	var elem_count = jQuery("#answers-list span input").length;

	var answers_list = jQuery("#answers-list");

	if (answers_list && (answers_list.length > 0))
	{
		answers_list.append('<p><label class="elem-id">' + (elem_count + 1) + '. </label><input type="hidden" name="answer_ids[' + (elem_count + 1) + ']" value="0"/><span><input type="text" size="50" name="answers[' + (elem_count + 1) + ']"/></span>&nbsp;&nbsp;Votes:<input type="text" size="5" name="answers_votes[' + (elem_count + 1) + ']" value="0"/>&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" title="Delete" onclick="return dyamar_polls_delete_answer(this);">Delete</a></p>');
	}

	return false;
}

function dyamar_polls_delete_answer(source)
{
	var elem_count = jQuery("#answers-list span input").length;

	var parent = jQuery(source).parent();
	
	if (parent && (parent.length > 0) && (elem_count > 1))
	{
		parent.remove();
	}
	else
	{
		var input_element = parent.find("input");
	
		if (input_element && (input_element.length > 0))
		{
			parent.find("input").val('');
		}
	}

	var ids = jQuery("#answers-list .elem-id").each(function(index){
		jQuery(this).text((index + 1) + ". ");
	});
	
	return false;
}

function dymar_polls_cancel_button(polls_admin_url)
{
	location.href = polls_admin_url;
	
	return false;
}