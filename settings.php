<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Newblock block caps.
 *
 * @package    block_studentdash
 * @copyright  N/a <>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$settings->add(new admin_setting_configtext('studentdash/servername', 'Server Name', '', ''));

$settings->add(new admin_setting_configtext('studentdash/dbname', 'Database Name', '', ''));

$options = array('', "access","ado_access", "ado", "ado_mssql", "borland_ibase", "csv", "db2", "fbsql", "firebird", "ibase", "informix72", "informix", "mssql", "mssql_n", "mssqlnative", "mysql", "mysqli", "mysqlt", "oci805", "oci8", "oci8po", "odbc", "odbc_mssql", "odbc_oracle", "oracle", "postgres64", "postgres7", "postgres", "proxy", "sqlanywhere", "sybase", "vfp");
$options = array_combine($options, $options);
$settings->add(new admin_setting_configselect('studentdash/dbtype', 'Database driver', get_string('dbtype_desc', 'block_studentdash'), 'defaultval', $options));

$settings->add(new admin_setting_configtext('studentdash/uid', 'User ID', '', ''));

$settings->add(new admin_setting_configpasswordunmask('studentdash/pwd', 'Password', '', ''));
