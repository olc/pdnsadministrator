<?php
/**
 * PDNS-Admin
 * Copyright (c) 2006-2008 Roger Libiez http://www.iguanadons.net
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

if (!defined('PDNSADMIN')) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

require_once $set['include_path'] . '/global.php';
require_once $set['include_path'] . '/lib/xmlparser.php';
require_once $set['include_path'] . '/lib/packageutil.php';

class upgrade extends pdnsadmin
{
	function upgrade_console( $step )
	{
		switch($step)
		{
			default:
				echo "<form action='{$this->self}?mode=upgrade&amp;step=2' method='post'>
				<table border='0' cellpadding='4' cellspacing='0'>\n";

				check_writeable_files();

				echo "<tr><td colspan='2' align='center'><b>To determine what version you are running, check the bottom of your AdminCP page. Or check the CHANGES file and look for the latest revision mentioned there.</b></td></tr>
				<tr><td colspan='2' align='center'><b>Upgrade from what version?</b></td></tr>
				    <tr>
				        <td><input type='radio' name='from' value='1.1.4' id='114' checked='checked' />
					<label for='114'>PDNS-Admin 1.1.4</label></td>
				    </tr>
				    <tr>
				        <td><input type='radio' name='from' value='1.1.3' id='113' />
					<label for='113'>PDNS-Admin 1.1.3</label></td>
				    </tr>
				    <tr>
				        <td><input type='radio' name='from' value='1.1.2' id='112' />
					<label for='112'>PDNS-Admin 1.1.2</label></td>
				    </tr>
				    <tr>
				        <td><input type='radio' name='from' value='1.1.1' id='111' />
					<label for='111'>PDNS-Admin 1.1.1</label></td>
				    </tr>
				    <tr>
				        <td><input type='radio' name='from' value='1.1' id='11' />
					<label for='11'>PDNS-Admin 1.1</label></td>
				    </tr>
				    <tr>
				        <td><input type='radio' name='from' value='1.0' id='10' />
					<label for='10'>PDNS-Admin 1.0</label></td>
				    </tr>
				    <tr>
				        <td colspan='2' align='center'><br /><input type='submit' value='Continue' /></td>
				    </tr>
				  </table>
				</form>\n";
			break;

			case 2:
				$db = new $this->modules['database']($this->sets['db_host'], $this->sets['db_user'], $this->sets['db_pass'], $this->sets['db_name'],
					$this->sets['db_port'], $this->sets['db_socket']);

				if ( !$db->connection )
				{
					echo 'Couldn\'t select database: <br />' . mysql_error();
					break;
				}
				$this->db = $db;

				$queries = array();
				$templates_add = false;
				$templates_update = false;
				$this->sets = $this->get_settings($this->sets);

				// Missing breaks are deliberate. Upgrades from older versions need to step through all of this.
				switch($this->post['from'])
				{
					case '1.0': // 1.0 to 1.1
						$templates_update = true;

					case '1.1': // 1.1 to 1.1.1

					case '1.1.1': // 1.1.1 to 1.1.2
						if( $templates_update !== true ) {
							$templates_update[] = 'ADMIN_COPYRIGHT';
							$templates_update[] = 'DOMAINS_EDIT';
							$templates_update[] = 'MAIN_COPYRIGHT';
						}

					case '1.1.2': // 1.1.2 to 1.1.3
						if( $templates_update !== true ) {
							$templates_update[] = 'ADMIN_INDEX';
							$templates_update[] = 'DOMAIN_LIST';
							$templates_update[] = 'MAIN';
						}

					case '1.1.3': // 1.1.3 to 1.1.4
						if( $templates_add !== true ) {
							$templates_add[] = 'USERS_MAIN';
							$templates_add[] = 'USERS_USER';
							$templates_add[] = 'USERS_PROFILE';
						}

						if( $templates_update !== true ) {
							$templates_update[] = 'ADMIN_EDIT_DB_SETTINGS';
							$templates_update[] = 'ADMIN_EDIT_BOARD_SETTINGS';
							$templates_update[] = 'MAIN_HEADER_MEMBER';
							$templates_update[] = 'DOMAIN_ITEM';
						}

						$queries[] = "ALTER TABLE users ADD user_domains int(10) unsigned NOT NULL default '0' AFTER user_email";
						$queries[] = 'ALTER TABLE users ADD user_lastlogonip varchar(255) NOT NULL AFTER user_lastlogon';

						$users = $this->db->query( 'SELECT user_id FROM users' );
						while( $user = $this->db->nqfetch( $users ) )
						{
							$domains = $this->db->fetch( 'SELECT COUNT(id) count FROM zones WHERE owner=%d', $user['user_id'] );

							$queries[] = "UPDATE users SET user_domains={$domains['count']} WHERE user_id={$user['user_id']}";
						}

						$this->sets['tertiary_nameserver'] = '';
						$this->sets['quaternary_nameserver'] = '';
						$this->sets['quinary_nameserver'] = '';
						$this->sets['senary_nameserver'] = '';
						$this->sets['septenary_nameserver'] = '';
						$this->sets['octonary_nameserver'] = '';

					case '1.1.4': // 1.1.4 to 1.1.5
						if( $templates_add !== true ) {
							$templates_add[] = 'ADMIN_SUPERMASTERS';
							$templates_add[] = 'ADMIN_SUPERMASTER_ADD';
							$templates_add[] = 'ADMIN_SUPERMASTER_ENTRY';
						}
						if( $templates_update !== true ) {
							$templates_update[] = 'MAIN';
							$templates_update[] = 'MAIN_HEADER_MEMBER';
							$templates_update[] = 'MAIN_HEADER_GUEST';
							$templates_update[] = 'MAIN_COPYRIGHT';
							$templates_update[] = 'ADMIN_COPYRIGHT';
							$templates_update[] = 'ADMIN_EDIT_BOARD_SETTINGS';
							$templates_update[] = 'DOMAIN_RECORD_EDIT';
							$templates_update[] = 'DOMAINS_ADD';
							$templates_update[] = 'DOMAINS_EDIT';
							$templates_update[] = 'DOMAINS_ADD_REVERSE';
							$templates_update[] = 'ADMIN_INDEX';
						}

						$this->sets['domains_per_page'] = 50;
						$this->sets['records_per_page'] = 50;
						$this->sets['soa_retry'] = 3600;
						$this->sets['soa_refresh'] = 10800;
						$this->sets['soa_expire'] = 432000;
						break;
				}

				if ( ( $templates_add || $templates_update ) && !is_readable('skin_default.xml')) {
					echo 'Templates can not be updated. skin_default.xml file is unreadable.';
					break;
				}

				execute_queries($queries, $this->db);

				if( $templates_add )
				{
					if( $templates_add === true )
						$templates_add = null;

					$xmlInfo = new xmlparser();
					$xmlInfo->parse('skin_default.xml');
					$templatesNode = $xmlInfo->GetNodeByPath('QSFMOD/TEMPLATES');
					packageutil::insert_templates('default', $this->db, $templatesNode, $templates_add);
					unset($templatesNode);
					$xmlInfo = null;
				}

				if( $templates_update )
				{
					$temps = array();

					if( $templates_update === true )
						$temps = null;
					else
					{
						foreach( $templates_update as $update )
						{
							if( $templates_add === false || !in_array( $update, $templates_add ) )
								$temps[] = $update;
						}
					}
					$xmlInfo = new xmlparser();
					$xmlInfo->parse('skin_default.xml');
					$templatesNode = $xmlInfo->GetNodeByPath('QSFMOD/TEMPLATES');
					packageutil::insert_templates('default', $this->db, $templatesNode, $temps);
					unset($templatesNode);
					$xmlInfo = null;
				}

				$this->write_sets();

				echo "Upgrade complete. You can <a href=\"../index.php\">return to your site</a> now.<br />";
				echo "Please DELETE THE INSTALL DIRECTORY NOW for security purposes!!";
				break;
		}
	}
}
?>