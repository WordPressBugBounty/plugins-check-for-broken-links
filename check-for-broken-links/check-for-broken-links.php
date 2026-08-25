<?php
/**
 * Plugin Name: Check for Broken Links - Broken Link Checker & 404 Monitor
 * Description: Scan your site for broken links and 404 errors to improve SEO and user experience.
 * Version: 3.0.6
 * Author: Norse Digital Group LLC
 * Author URI: https://brokenlinkchecker.io/
 * Requires at least: 6.0
 * Requires PHP: 7.2
 * Tested up to: 7.1
 *
 * Text Domain: check-for-broken-links
 * Domain Path: /languages/
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package WPCBL_Check_Broken_Links
 * @author Norse Digital Group LLC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/*
 * Globals constants.
 */
define( 'WPCBL_CHECK_BROKEN_LINKS_PLUGIN_NAME', 'Check for Broken Links' );
define( 'WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION', '3.0.6' );
define( 'WPCBL_CHECK_BROKEN_LINKS_MIN_PHP_VER', '7.2' );
define( 'WPCBL_CHECK_BROKEN_LINKS_MIN_WP_VER', '6.0' );
define( 'WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH', __DIR__ );
define( 'WPCBL_CHECK_BROKEN_LINKS_ROOT_URL', plugin_dir_url( __FILE__ ) );
define( 'WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH', __DIR__ . '/templates/' );
define( 'WPCBL_CHECK_BROKEN_LINKS_UPGRADE_URL', 'https://brokenlinkchecker.io/pricing' );

if ( ! class_exists( 'WPCBL_Check_Broken_Links' ) ) :

	/**
	 * The main class.
	 *
	 * @since 1.0.0
	 */
	class WPCBL_Check_Broken_Links {
		/**
		 * Plugin version.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $version = '3.0.5';

		/**
		 * Database version.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		private static $db_version = '1.0.0';

		/**
		 * The singelton instance of WPCBL_Check_Broken_Links.
		 *
		 * @since 1.0.0
		 *
		 * @var WPCBL_Check_Broken_Links
		 */
		private static $instance = null;

		/**
		 * Returns the singelton instance of WPCBL_Check_Broken_Links.
		 *
		 * Ensures only one instance of WPCBL_Check_Broken_Links is/can be loaded.
		 *
		 * @since 1.0.0
		 *
		 * @return WPCBL_Check_Broken_Links
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * The constructor.
		 *
		 * Private constructor to make sure it can not be called directly from outside the class.
		 *
		 * @since 1.0.0
		 */
		private function __construct() {
			$this->includes();
			$this->hooks();

			do_action( 'wpcbl_check_for_broken_links_loaded' );
		}

		/**
		 * Includes the required files.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function includes() {
			/*
			 * Global includes.
			 */
			include_once WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/includes/functions.php';
			include_once WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/includes/pro-tools.php';
			include_once WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/includes/class-wpcbl-check-for-broken-links-utilities.php';
			include_once WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/includes/class-wpcbl-check-for-broken-links-schedule.php';
			include_once WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/includes/class-wpcbl-check-for-broken-links-connect.php';
			include_once WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/includes/class-wpcbl-check-for-broken-links-remote-apply.php';

			/*
			 * Back-end includes.
			 */
			if ( is_admin() ) {
				include_once WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/includes/admin/class-wpcbl-check-for-broken-links-admin-notices.php';
				include_once WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/includes/admin/class-wpcbl-check-for-broken-links-admin-assets.php';
				include_once WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/includes/admin/class-wpcbl-check-for-broken-links-admin-settings.php';
				include_once WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/includes/admin/class-wpcbl-check-for-broken-links-admin-links-list-table.php';
				include_once WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/includes/admin/class-wpcbl-check-for-broken-links-admin-ajax.php';
				include_once WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/includes/admin/class-wpcbl-check-for-broken-links-admin-export.php';
			}
		}

		/**
		 * Plugin hooks.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function hooks() {
			add_filter( 'plugin_row_meta', array( $this, 'add_custom_links' ), 10, 2 );
			add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
		}

		/**
		 * One-time upgrade routines, keyed on the stored plugin version.
		 *
		 * 3.0.0 clears stored scan results so every upgrader starts on the
		 * redesigned first-run dashboard.
		 *
		 * @since 3.0.0
		 *
		 * @return void
		 */
		public function maybe_upgrade() {
			$stored_version = get_option( 'wpcbl_check_for_broken_links_version', '' );

			if ( WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION === $stored_version ) {
				return;
			}

			if ( version_compare( $stored_version, '3.0.0', '<' ) ) {
				delete_option( 'wpcbl_check_for_broken_links_links' );
				delete_option( 'wpcbl_last_scan_summary' );
			} else {
				// Prune stored results that hit the built-in exclusion list
				// (cart, admin, login URLs) added after the stored version.
				WPCBL_Check_Broken_Links_Utilities::purge_default_excluded_results();
			}

			update_option( 'wpcbl_check_for_broken_links_version', WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION, false );
		}

		/**
		 * Add custom links to the plugin row metadata.
		 *
		 * @param array  $links An array of metadata links.
		 * @param string $file Path to the plugin file.
		 *
		 * @since 1.0.0
		 *
		 * @return array Modified array of metadata links.
		 */
		public function add_custom_links( $links, $file ) {
			// Validate the plugin file.
			if ( strpos( $file, 'check-for-broken-links/check-for-broken-links.php' ) !== false ) {
				$links[] = '<a href="' . esc_url( 'https://wordpress.org/support/plugin/check-for-broken-links/reviews/#new-post' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Leave a review', 'check-for-broken-links' ) . '</a>';
			}

			return $links;
		}

		/**
		 * Activation hooks.
		 *
		 * @since   1.0.0
		 *
		 * @return void
		 */
		public static function activate() {
			/*
			 * Set default settings.
			 */
			$settings['scan_frequency']      = 'weekly';
			$settings['scan_time']           = '00:00';
			$settings['scan_timezone']       = wp_timezone_string();
			$settings['scan_slider_content'] = 'on';
			$settings['email_notifications'] = 'off';
			$settings['email_addresses']     = '';
			$settings['number_of_links']     = 'all';
			$settings['scope_of_links']      = array( 'all' );
			$settings['exclusion_urls']      = '';

			add_option( 'wpcbl_check_for_broken_links_settings', $settings );

			// Schedule the event.
			include_once WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/includes/class-wpcbl-check-for-broken-links-schedule.php';

			WPCBL_Check_Broken_Links_Schedule::update_scan_schedule( $settings );
		}

		/**
		 * Deactivation hooks.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public static function deactivate() {
			wp_clear_scheduled_hook( 'wpcbl_check_for_broken_links_scheduled_event' );
		}

		/**
		 * Uninstall hooks.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public static function uninstall() {
			include_once WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . 'uninstall.php';
		}
	}

	// Plugin hooks.
	register_activation_hook( __FILE__, array( 'WPCBL_Check_Broken_Links', 'activate' ) );
	register_deactivation_hook( __FILE__, array( 'WPCBL_Check_Broken_Links', 'deactivate' ) );
	register_uninstall_hook( __FILE__, array( 'WPCBL_Check_Broken_Links', 'uninstall' ) );

endif;

/**
 * Init plugin.
 *
 * @since 1.0.0
 */
function wpcbl_check_for_broken_links_init() {
	// Global for backwards compatibility.
	$GLOBALS['wpcbl_check_for_broken_links'] = WPCBL_Check_Broken_Links::get_instance();
}

add_action( 'plugins_loaded', 'wpcbl_check_for_broken_links_init', 0 );
