<?php
/**
 * @package Blog Feed Finder
 */
/*
Plugin Name: Blog Feed Finder
Plugin URI: http://github.com/oeru/bff
Description: Provides a widget that helps a user figure out the valid URL for
    their personal course blog feed
Version: 0.0.1
Author: Dave Lane
Author URI: https://oeru.org, http://WikiEducator.org/User:Davelane
License: AGPLv3 or later
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU Affero General Public License
as published by the Free Software Foundation; either version 3
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
Affero GNU General Public License for more details:
https://www.gnu.org/licenses/agpl-3.0.en.html

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

define( 'BFF_VERSION', '0.0.1' );
// plugin computer name
define('BFF_NAME', 'BFF');
// current version
// the path to this file
define('BFF_FILE', __FILE__);
// absolute URL for this plugin, including site name, e.g.
// https://sitename.nz/wp-content/plugins/
define('BFF_URL', plugins_url("/", __FILE__));
// absolute server path to this plugin
define('BFF_PATH', plugin_dir_path(__FILE__));
// module details
define('BFF_SLUG', 'blog-feed-finder');
define('BFF_TITLE', 'Blog Feed Finder');
define('BFF_MENU', 'BFF');
define('BFF_SHORTCODE', 'bff_form');
define('BFF_ID', 'blog-feed-finder');
define('BFF_CLASS', 'bff-form');
// admin details
define('BFF_ADMIN_SLUG', 'BFF_settings');
define('BFF_ADMIN_TITLE', 'Blog Feed Finder Settings');
define('BFF_ADMIN_MENU', 'BFF Settings');
// other useful parameters
define('BFF_MAX_FILE_READ_CHAR', 100000);
// turn on debugging with true, off with false
define('BFF_DEBUG', true);
define('LOG_STREAM', getenv('LOG_STREAM'));

// include the dependencies
require BFF_PATH . '/bff-app.php';

if ( function_exists( 'add_action' ) ) {
    // this starts everything up!
    add_action('plugins_loaded', array(BFFForm::get_instance(), 'init'));
} else {
	echo 'This only works as a WordPress plugin.';
	exit;
}
