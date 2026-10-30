<?php
/**
 * Plugin Name:       Gator Gate Pre for bbPress
 * Plugin URI:        https://tinfoilwho.com/signal/forum/gator-gate-plugins/
 * Description:       Simple, robust preformatted code block support for the bbPress visual editor. Per-forum checkboxes control the visual editor and Code tab. Other plugins can hide or force the Pre button with the gator_gate_pre_show_pre_button filter (optionally targeting a specific forum ID).
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Tin Who
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gator-gate-pre
 * 
 * AI-generated/AI-assisted code provided AS-IS.
 * User assumes all risk and responsibility for use.
 * @package Gator_Gate_Pre
 *
 * Controlling the Pre button from another plugin
 * ----------------------------------------------
 * The Pre button is on by default when the visual editor is enabled for a forum.
 * Other plugins can override it:
 *
 *   // Hide the Pre button everywhere
 *   add_filter( 'gator_gate_pre_show_pre_button', '__return_false' );
 *
 *   // Force the Pre button on everywhere
 *   add_filter( 'gator_gate_pre_show_pre_button', '__return_true' );
 *
 *   // Target a specific forum ID
 *   add_filter( 'gator_gate_pre_show_pre_button', function( $show, $forum_id ) {
 *       if ( 123 === (int) $forum_id ) {
 *           return false; // hide on forum 123
 *       }
 *       return $show;
 *   }, 10, 2 );
 */

defined( 'ABSPATH' ) || exit;

define( 'GATOR_GATE_PRE_VERSION', '1.0.0' );
define( 'GATOR_GATE_PRE_FILE', __FILE__ );
define( 'GATOR_GATE_PRE_PATH', plugin_dir_path( __FILE__ ) );
define( 'GATOR_GATE_PRE_URL', plugin_dir_url( __FILE__ ) );

// Line spacing is now controlled per-forum via checkbox (saved to forum meta).
// The old constant has been removed.

/**
 * Load the plugin after bbPress is available.
 */
function gator_gate_pre_init() {
    if ( ! class_exists( 'bbPress' ) ) {
        add_action( 'admin_notices', 'gator_gate_pre_missing_bbpress_notice' );
        return;
    }

    require_once GATOR_GATE_PRE_PATH . 'includes/class-gator-gate-pre.php';
    Gator_Gate_Pre::instance();
}
add_action( 'plugins_loaded', 'gator_gate_pre_init' );

/**
 * Admin notice when bbPress is missing.
 */
function gator_gate_pre_missing_bbpress_notice() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }
    echo '<div class="notice notice-error"><p>';
    esc_html_e( 'Gator Gate Pre for bbPress requires bbPress to be installed and active.', 'gator-gate-pre' );
    echo '</p></div>';
}
