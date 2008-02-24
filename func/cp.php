<?php
/**
 * PDNS-Admin
 * Copyright (c) 2006-2007 Roger Libiez http://www.iguanadons.net
 *
 * Based on Quicksilver Forums
 * Copyright (c) 2005 The Quicksilver Forums Development Team
 *  http://www.quicksilverforums.com/
 * 
 * Based on MercuryBoard
 * Copyright (c) 2001-2005 The Mercury Development Team
 *  http://www.mercuryboard.com/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 **/

if (!defined('QUICKSILVERFORUMS')) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

require_once $set['include_path'] . '/global.php';

/**
 * User Control Panel
 *
 * @author Jason Warner <jason@mercuryboard.com>
 * @since Beta 2.0
 **/
class cp extends qsfglobal
{
	function execute()
	{
		if (!isset($this->get['s'])) {
			$this->get['s'] = null;
		}

		if ($this->perms->is_guest) {
			return $this->message($this->lang->cp_cp, $this->lang->cp_login_first);
		}

		$class['cpass']   = 'tablelight';
		$class['prefs']   = 'tablelight';
		$class['profile'] = 'tablelight';

		switch($this->get['s'])
		{
		case 'cpass':
			$class['cpass'] = 'tabledark';
			$control_page = $this->edit_pass();
			break;

		case 'profile':
			$class['profile'] = 'tabledark';
			$control_page = $this->edit_profile();
			break;

		case 'prefs':
			$class['prefs'] = 'tabledark';
			$control_page = $this->edit_prefs();
			break;

		default:
			$this->set_title($this->lang->cp_cp);
			$this->tree($this->lang->cp_cp);

			$this->get['s'] = null;
			$control_page = eval($this->template('CP_HOME'));
		}

		return eval($this->template('CP_MAIN'));
	}

	function check_pass($passA, $passB, $old_pass)
	{
		if (md5($old_pass) != $this->user['user_password']) {
			return PASS_NOT_VERIFIED;
		}

		if (!preg_match('/[a-z0-9_\- ]+/i', $passA)) {
			return PASS_INVALID;
		}

		if ($passA != $passB) {
			return PASS_NO_MATCH;
		}

		return PASS_SUCCESS;
	}

	function edit_pass()
	{
		$this->set_title($this->lang->cp_changing_pass);
		$this->tree($this->lang->cp_cp, $this->self . '?a=cp');
		$this->tree($this->lang->cp_changing_pass);

		if (!isset($this->post['submit'])) {
			return eval($this->template('CP_PASS'));
		} else {
			$result = $this->check_pass($this->post['passA'], $this->post['passB'], $this->post['old_pass']);

			switch($result)
			{
			case PASS_NOT_VERIFIED:
				return $this->message($this->lang->cp_changing_pass, $this->lang->cp_old_notmatch);
				break;

			case PASS_INVALID:
				return $this->message($this->lang->cp_changing_pass, $this->lang->cp_pass_notvaid);
				break;

			case PASS_NO_MATCH:
				return $this->message($this->lang->cp_changing_pass, $this->lang->cp_new_notmatch);
				break;

			case PASS_SUCCESS:
				$hashed_pass = md5($this->post['passA']);
				$this->db->query("UPDATE users SET user_password='%s' WHERE user_id=%d", $hashed_pass, $this->user['user_id']);

				if( version_compare( PHP_VERSION, "5.2.0", "<" ) ) {
					setcookie($this->sets['cookie_prefix'] . 'pass', $hashed_pass, $this->time + $this->sets['logintime'], $this->sets['cookie_path'], $this->sets['cookie_domain'].'; HttpOnly', $this->sets['cookie_secure']);
				} else {
					setcookie($this->sets['cookie_prefix'] . 'pass', $hashed_pass, $this->time + $this->sets['logintime'], $this->sets['cookie_path'], $this->sets['cookie_domain'], $this->sets['cookie_secure'], true );
				}
				$_SESSION['pass'] = md5($hashed_pass . $this->ip);
				$this->user['user_password'] = $hashed_pass;

				return $this->message($this->lang->cp_changing_pass, sprintf($this->lang->cp_valided, $this->self));
				break;
			}
		}
	}

	function edit_prefs()
	{
		$this->set_title($this->lang->cp_preferences);
		$this->tree($this->lang->cp_cp, $this->self . '?a=cp');
		$this->tree($this->lang->cp_preferences);

		if (!isset($this->post['submit'])) {
			$skin_list  = $this->htmlwidgets->select_skins($this->skin);
			$lang_list  = $this->htmlwidgets->select_langs($this->user['user_language']);

			return eval($this->template('CP_PREFS'));
		} else {

			$this->post['user_language'] = preg_replace('/[^a-zA-Z0-9\-]/', '', $this->post['user_language']);

			$this->db->query("
				UPDATE users SET user_skin='%s', user_language='%s'
				WHERE user_id=%d",
				$this->post['user_skin'], $this->post['user_language'], $this->user['user_id']);

			return $this->message($this->lang->cp_updated_prefs, $this->lang->cp_been_updated_prefs);
		}
	}

	function edit_profile()
	{
		$this->set_title($this->lang->cp_editing_profile);
		$this->tree($this->lang->cp_cp, $this->self . '?a=cp');
		$this->tree($this->lang->cp_editing_profile);

		if (!isset($this->post['submit'])) {
			return eval($this->template('CP_PROFILE'));
		} else {
			$temp_email = $this->post['user_email'];
			if (!$this->validator->validate($temp_email, TYPE_EMAIL)) {
				return $this->message($this->lang->cp_err_updating, $this->lang->cp_email_invaid);
			}

			if ($this->db->fetch("SELECT user_email FROM users WHERE user_email='%s' AND user_id != %d",
				 $this->post['user_email'], $this->user['user_id']))
			{
				return $this->message($this->lang->cp_err_updating, $this->lang->cp_already_member);
			}

			$this->db->query("UPDATE users SET user_email='%s' WHERE user_id=%d",
				$this->post['user_email'], $this->user['user_id']);

			return $this->message($this->lang->cp_updated, $this->lang->cp_been_updated);
		}
	}
}
?>
