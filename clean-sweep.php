<?php
/**
 * Plugin Name: Clean Sweep
 * Description: Bulk clean unused themes, plugins, orphan thumbnails, post revisions, transients, and more with automatic backups.
 * Version: 1.0.0
 * Author: mrshahbazdev
 * Author URI: https://github.com/mrshahbazdev
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: clean-sweep
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CLEAN_SWEEP_VERSION', '1.0.0' );
define( 'CLEAN_SWEEP_FILE', __FILE__ );
define( 'CLEAN_SWEEP_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLEAN_SWEEP_URL', plugin_dir_url( __FILE__ ) );

require_once CLEAN_SWEEP_DIR . 'includes/class-clean-sweep-admin.php';

add_action( 'admin_menu', array( 'Clean_Sweep_Admin', 'add_menu' ) );
add_action( 'admin_init', array( 'Clean_Sweep_Admin', 'handle_actions' ) );
add_action( 'admin_enqueue_scripts', array( 'Clean_Sweep_Admin', 'enqueue_assets' ) );
