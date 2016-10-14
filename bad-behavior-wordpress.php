<?php
/**
 * Bad Behavior
 *
 * @author  Michael Hampton
 * @license LGPLv3
 * @link    https://github.com/blueblazeassociates/bad-behavior
 */

/*
 * Modified by Blue Blaze Associates, LLC
 *
 * Changes:
 * * Added file phpDoc comment.
 * * Tweaked WordPress plugin header.
 */

/*
 * Plugin Name:          Bad Behavior
 * Plugin URI:           https://github.com/blueblazeassociates/bad-behavior
 * Original Plugin URI:  http://bad-behavior.ioerror.us/
 * Description:          Deny automated spambots access to your PHP-based Web site.
 * Version:              2.2.19
 * Author:               Michael Hampton
 * Author URI:           http://bad-behavior.ioerror.us/
 * License:              LGPLv3
 * Bitbucket Plugin URI: https://github.com/blueblazeassociates/bad-behavior
 * Bitbucket Branch:     blueblaze
 */

/*
Bad Behavior - detects and blocks unwanted Web accesses
Copyright (C) 2005,2006,2007,2008,2009,2010,2011,2012 Michael Hampton

Bad Behavior is free software; you can redistribute it and/or modify it under
the terms of the GNU Lesser General Public License as published by the Free
Software Foundation; either version 3 of the License, or (at your option) any
later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public License along
with this program. If not, see <http://www.gnu.org/licenses/>.

Please report any problems to bad . bots AT ioerror DOT us
http://bad-behavior.ioerror.us/
*/

###############################################################################
###############################################################################

if (!defined('ABSPATH')) die("No cheating!");

global $bb2_result;

$bb2_mtime = explode(" ", microtime());
$bb2_timer_start = $bb2_mtime[1] + $bb2_mtime[0];

define('BB2_CWD', dirname(__FILE__));

// Bad Behavior callback functions.
require_once("bad-behavior-mysql.php");

// Return current time in the format preferred by your database.
function bb2_db_date() {
	return get_gmt_from_date(current_time('mysql'));
}

// Return affected rows from most recent query.
function bb2_db_affected_rows() {
	global $wpdb;

	return $wpdb->rows_affected;
}

// Escape a string for database usage
function bb2_db_escape($string) {
	return esc_sql($string);
}

// Return the number of rows in a particular query.
function bb2_db_num_rows($result) {
	if ($result !== FALSE)
		return count($result);
	return 0;
}

// Run a query and return the results, if any.
// Should return FALSE if an error occurred.
// Bad Behavior will use the return value here in other callbacks.
function bb2_db_query($query) {
	global $wpdb;

	$wpdb->hide_errors();
	$result = $wpdb->get_results($query, ARRAY_A);
	if ( defined('WP_DEBUG') and WP_DEBUG == true )
		$wpdb->show_errors();
	if ($wpdb->last_error) {
		return FALSE;
	}
	return $result;
}

// Return all rows in a particular query.
// Should contain an array of all rows generated by calling mysql_fetch_assoc()
// or equivalent and appending the result of each call to an array.
// For WP this is pretty much a no-op.
function bb2_db_rows($result) {
	return $result;
}

// Return emergency contact email address.
function bb2_email() {
	return get_bloginfo('admin_email');
}

// retrieve whitelist
function bb2_read_whitelist() {
	return get_option('bad_behavior_whitelist');
}

// retrieve settings from database
function bb2_read_settings() {
	global $wpdb;

	// Add in default settings when they aren't yet present in WP
	$settings = get_option('bad_behavior_settings');
	if (!$settings) $settings = array();
	return array_merge(array('log_table' => $wpdb->prefix . 'bad_behavior', 'display_stats' => false, 'strict' => false, 'verbose' => false, 'logging' => true, 'httpbl_key' => '', 'httpbl_threat' => '25', 'httpbl_maxage' => '30', 'offsite_forms' => false, 'eu_cookie' => false, 'reverse_proxy' => false, 'reverse_proxy_header' => 'X-Forwarded-For', 'reverse_proxy_addresses' => array(),), $settings);


}

// write settings to database
function bb2_write_settings($settings) {
	update_option('bad_behavior_settings', $settings);
}

// installation
function bb2_install() {
	$settings = bb2_read_settings();
	if (!$settings['logging']) return;
	bb2_db_query(bb2_table_structure($settings['log_table']));
}

// Cute timer display; screener
function bb2_insert_head() {
	global $bb2_timer_total;
	global $bb2_javascript;
	echo "\n<!-- Bad Behavior " . BB2_VERSION . " run time: " . number_format(1000 * $bb2_timer_total, 3) . " ms -->\n";
	echo $bb2_javascript;
}

function bb2_approved_callback($settings, $package) {
	global $bb2_package;

	// Save package for possible later use
	$bb2_package = $package;
}

// Capture missed spam and log it
function bb2_capture_spam($id, $comment) {
	global $bb2_package;

	// Capture only spam
	if ('spam' != $comment->comment_approved) return;

	// Don't capture if HTTP request no longer active
	if (array_key_exists("request_entity", $bb2_package) && array_key_exists("author", $bb2_package['request_entity']) && $bb2_package['request_entity']['author'] == $comment->comment_author) {
		bb2_db_query(bb2_insert(bb2_read_settings(), $bb2_package, "00000000"));
	}
}

// Display stats?
function bb2_insert_stats($force = false) {
	global $bb2_result;

	$settings = bb2_read_settings();

	if ($force || $settings['display_stats']) {
		$blocked = bb2_db_query("SELECT COUNT(*) FROM " . $settings['log_table'] . " WHERE `key` NOT LIKE '00000000'");
		if ($blocked !== FALSE) {
			echo sprintf('<p><a href="http://bad-behavior.ioerror.us/">%1$s</a> %2$s <strong>%3$s</strong> %4$s</p>', __('Bad Behavior'), __('has blocked'), $blocked[0]["COUNT(*)"], __('access attempts in the last 7 days.'));
		}
	}
	if (@!empty($bb2_result)) {
		echo sprintf("\n<!-- Bad Behavior result was %s! This request would have been blocked. -->\n", $bb2_result);
		unset($bb2_result);
	}
}

// Return the top-level relative path of wherever we are (for cookies)
function bb2_relative_path() {
	$url = parse_url(get_bloginfo('url'));
	if (array_key_exists('path', $url)) {
		return $url['path'] . '/';
	}
	return '/';
}

// FIXME: figure out what's wrong on 2.0 that this doesn't work
// register_activation_hook(__FILE__, 'bb2_install');
//add_action('activate_bb2/bad-behavior-wordpress.php', 'bb2_install');
add_action('wp_head', 'bb2_insert_head');
add_action('wp_footer', 'bb2_insert_stats');
add_action('wp_insert_comment', 'bb2_capture_spam', 99, 2);

// Calls inward to Bad Behavor itself.
require_once(BB2_CWD . "/bad-behavior/core.inc.php");
bb2_install();	// FIXME: see above

if (is_admin() || strstr($_SERVER['PHP_SELF'], 'wp-admin/')) {	// 1.5 kludge
	#wp_enqueue_script("admin-forms");
	require_once(BB2_CWD . "/bad-behavior-wordpress-admin.php");
}

$bb2_result = bb2_start(bb2_read_settings());

$bb2_mtime = explode(" ", microtime());
$bb2_timer_stop = $bb2_mtime[1] + $bb2_mtime[0];
$bb2_timer_total = $bb2_timer_stop - $bb2_timer_start;
