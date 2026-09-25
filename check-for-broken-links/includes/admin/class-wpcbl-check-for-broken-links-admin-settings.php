<?php
/**
 * The WPCBL_Check_Broken_Links_Admin_Settings class.
 *
 * @package WPCBL_Check_Broken_Links/Admin
 * @author Norse Digital Group LLC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WPCBL_Check_Broken_Links_Admin_Settings' ) ) :

	/**
	 * Admin menus.
	 *
	 * Adds menu and sub-menus pages.
	 *
	 * @since 1.0.0
	 */
	class WPCBL_Check_Broken_Links_Admin_Settings {
		/**
		 * The settings.
		 *
		 * @var array
		 */
		private $settings;

		/**
		 * The constructor.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function __construct() {
			// Get settings.
			$this->settings = get_option( 'wpcbl_check_for_broken_links_settings', array() );

			// Actions.
			add_action( 'admin_menu', array( $this, 'menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_init', array( $this, 'redirect_legacy_tabs' ) );
			add_action( 'admin_post_wpcbl_enable_ai_fix', array( $this, 'handle_enable_ai_fix' ) );
			add_action( 'admin_post_wpcbl_review_dismiss', array( $this, 'handle_review_dismiss' ) );
			add_action( 'admin_bar_menu', array( $this, 'admin_bar_link' ), 90 );

			// Filters.
			add_filter( 'set-screen-option', array( $this, 'set_screen_option' ), 10, 3 );
			add_filter( 'screen_options_show_screen', array( $this, 'hide_screen_options' ), 10, 2 );
		}

		/**
		 * Adds a quick link to the scan page in the WordPress admin bar.
		 *
		 * @since 3.0.0
		 *
		 * @param WP_Admin_Bar $wp_admin_bar The admin bar instance.
		 *
		 * @return void
		 */
		public function admin_bar_link( $wp_admin_bar ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$wp_admin_bar->add_node(
				array(
					'id'    => 'wpcbl-broken-link-checker',
					'title' => '<img src="' . esc_url( WPCBL_CHECK_BROKEN_LINKS_ROOT_URL . 'assets/dist/images/cbl-icon.png' ) . '" style="width:16px;height:16px;vertical-align:text-top;margin-right:5px;" alt="">' . esc_html__( 'Broken Link Checker', 'check-for-broken-links' ),
					'href'  => admin_url( 'admin.php?page=wpcbl-check-for-broken-links' ),
				)
			);

			// Same order the plugin sidebar uses, so the two never disagree.
			// A tool that opens on brokenlinkchecker.io is marked, rather than
			// looking like another wp-admin page that failed to load.
			foreach ( self::admin_bar_services() as $service ) {
				$wp_admin_bar->add_node(
					array(
						'id'     => 'wpcbl-service-' . $service['id'],
						'parent' => 'wpcbl-broken-link-checker',
						'title'  => esc_html( $service['title'] ) . ( $service['external'] ? ' <span aria-hidden="true">&#8599;</span>' : '' ),
						'href'   => $service['href'],
						'meta'   => $service['external']
							? array( 'target' => '_blank', 'rel' => 'noopener' )
							: array(),
					)
				);
			}
		}

		/**
		 * The services listed under the admin bar node.
		 *
		 * @since 3.0.7
		 *
		 * @return array<int, array{id: string, title: string, href: string, external: bool}>
		 */
		private static function admin_bar_services() {
			$page = static function ( $slug ) {
				return admin_url( 'admin.php?page=wpcbl-check-for-broken-links' . $slug );
			};

			$services = array(
				array(
					'id'       => 'dashboard',
					'title'    => __( 'Dashboard', 'check-for-broken-links' ),
					'href'     => $page( '' ),
					'external' => false,
				),
				array(
					'id'       => 'scan',
					'title'    => __( 'Broken link scan', 'check-for-broken-links' ),
					'href'     => $page( '-scan' ),
					'external' => false,
				),
				array(
					'id'       => 'rank-tracker',
					'title'    => __( 'Keyword Rank Tracker', 'check-for-broken-links' ),
					'href'     => $page( '-rank-tracker' ),
					'external' => false,
				),
				array(
					'id'       => 'seo-audit',
					'title'    => __( 'SEO / AEO Audit', 'check-for-broken-links' ),
					'href'     => $page( '-seo-audit' ),
					'external' => false,
				),
				array(
					'id'       => 'internal-links',
					'title'    => __( 'Internal Link Optimizer', 'check-for-broken-links' ),
					'href'     => $page( '-internal-links' ),
					'external' => false,
				),
				array(
					'id'       => 'ai-visibility',
					'title'    => __( 'AI Visibility Tracker', 'check-for-broken-links' ),
					'href'     => $page( '-ai-visibility' ),
					'external' => false,
				),
				array(
					'id'       => 'uptime',
					'title'    => __( 'Uptime Monitor', 'check-for-broken-links' ),
					'href'     => $page( '-uptime' ),
					'external' => false,
				),
			);

			$services[] = array(
				'id'       => 'settings',
				'title'    => __( 'Settings & billing', 'check-for-broken-links' ),
				'href'     => $page( '-settings' ),
				'external' => false,
			);

			return $services;
		}

		/**
		 * Hides the Screen Options tab (and with it the whole screen-meta
		 * block, since the plugin registers no help tabs) on plugin pages.
		 *
		 * @since 3.0.0
		 *
		 * @param bool      $show   Whether to show the tab.
		 * @param WP_Screen $screen The current screen.
		 *
		 * @return bool
		 */
		public function hide_screen_options( $show, $screen ) {
			if ( $screen instanceof WP_Screen && false !== strpos( $screen->id, 'wpcbl-check-for-broken-links' ) ) {
				return false;
			}

			return $show;
		}

		/**
		 * Adds menu and sub-menus pages.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function menu() {
			$hook = add_menu_page(
				esc_html__( 'Check for Broken Links', 'check-for-broken-links' ),
				esc_html__( 'Broken Links', 'check-for-broken-links' ),
				'manage_options',
				'wpcbl-check-for-broken-links',
				array( $this, 'menu_page' ),
				'dashicons-editor-unlink'
			);

			add_action( "load-$hook", array( $this, 'screen_option' ) );

			add_submenu_page(
				'wpcbl-check-for-broken-links',
				esc_html__( 'Dashboard', 'check-for-broken-links' ),
				esc_html__( 'Dashboard', 'check-for-broken-links' ),
				'manage_options',
				'wpcbl-check-for-broken-links'
			);

			add_submenu_page(
				'wpcbl-check-for-broken-links',
				esc_html__( 'Broken link scan', 'check-for-broken-links' ),
				esc_html__( 'Broken link scan', 'check-for-broken-links' ),
				'manage_options',
				'wpcbl-check-for-broken-links-scan',
				array( $this, 'scan_results_page' )
			);

			add_submenu_page(
				'wpcbl-check-for-broken-links',
				esc_html__( 'Scan reports', 'check-for-broken-links' ),
				esc_html__( 'Scan reports', 'check-for-broken-links' ),
				'manage_options',
				'wpcbl-check-for-broken-links-reports',
				array( $this, 'reports_page' )
			);

			add_submenu_page(
				'wpcbl-check-for-broken-links',
				esc_html__( 'Rank Tracker', 'check-for-broken-links' ),
				esc_html__( 'Rank Tracker', 'check-for-broken-links' ),
				'manage_options',
				'wpcbl-check-for-broken-links-rank-tracker',
				array( $this, 'rank_tracker_page' )
			);

			add_submenu_page(
				'wpcbl-check-for-broken-links',
				esc_html__( 'SEO / AEO Audit', 'check-for-broken-links' ),
				esc_html__( 'SEO / AEO Audit', 'check-for-broken-links' ),
				'manage_options',
				'wpcbl-check-for-broken-links-seo-audit',
				array( $this, 'seo_audit_page' )
			);

			add_submenu_page(
				'wpcbl-check-for-broken-links',
				esc_html__( 'Internal Link Optimizer', 'check-for-broken-links' ),
				esc_html__( 'Internal Link Optimizer', 'check-for-broken-links' ),
				'manage_options',
				'wpcbl-check-for-broken-links-internal-links',
				array( $this, 'internal_links_page' )
			);

			add_submenu_page(
				'wpcbl-check-for-broken-links',
				esc_html__( 'AI Visibility Tracker', 'check-for-broken-links' ),
				esc_html__( 'AI Visibility Tracker', 'check-for-broken-links' ),
				'manage_options',
				'wpcbl-check-for-broken-links-ai-visibility',
				array( $this, 'ai_visibility_page' )
			);

			add_submenu_page(
				'wpcbl-check-for-broken-links',
				esc_html__( 'Uptime Monitor', 'check-for-broken-links' ),
				esc_html__( 'Uptime Monitor', 'check-for-broken-links' ),
				'manage_options',
				'wpcbl-check-for-broken-links-uptime',
				array( $this, 'uptime_page' )
			);

			add_submenu_page(
				'wpcbl-check-for-broken-links',
				esc_html__( 'SEO / AEO Tip', 'check-for-broken-links' ),
				esc_html__( 'SEO / AEO Tip', 'check-for-broken-links' ),
				'manage_options',
				'wpcbl-check-for-broken-links-seo-tip',
				array( $this, 'seo_tip_page' )
			);

			add_submenu_page(
				'wpcbl-check-for-broken-links',
				esc_html__( 'Settings & billing', 'check-for-broken-links' ),
				esc_html__( 'Settings & billing', 'check-for-broken-links' ),
				'manage_options',
				'wpcbl-check-for-broken-links-settings',
				array( $this, 'settings_page' )
			);

			add_submenu_page(
				'wpcbl-check-for-broken-links',
				esc_html__( 'Help', 'check-for-broken-links' ),
				esc_html__( 'Help', 'check-for-broken-links' ),
				'manage_options',
				'wpcbl-check-for-broken-links-help',
				array( $this, 'help_page' )
			);

			// Retired page, kept routable so an old bookmark still lands
			// somewhere useful. Parented to options.php rather than the plugin
			// menu: that registers the slug without drawing a menu item.
			// remove_submenu_page() would not work here, because it also drops
			// the page from $_registered_pages, and WordPress then denies
			// admin.php?page= outright. That is the same trap the upgrade page
			// below documents.
			add_submenu_page(
				'options.php',
				esc_html__( 'Pro Tools', 'check-for-broken-links' ),
				esc_html__( 'Pro Tools', 'check-for-broken-links' ),
				'manage_options',
				'wpcbl-check-for-broken-links-pro-tools',
				array( $this, 'redirect_retired_pages' )
			);

			// Paid plans manage their subscription here instead of being
			// nagged to upgrade, so the label follows the plan. Removing the
			// submenu would also block direct access to the page (WordPress
			// denies admin.php?page= for unregistered submenus), which broke
			// the Rank Tracker upsell buttons for Pro users.
			$wpcbl_upgrade_label = function_exists( 'wpcbl_has_pro' ) && wpcbl_has_pro()
				? esc_html__( 'Plans & upgrades', 'check-for-broken-links' )
				: esc_html__( 'Upgrade to Pro', 'check-for-broken-links' );

			add_submenu_page(
				'wpcbl-check-for-broken-links',
				$wpcbl_upgrade_label,
				$wpcbl_upgrade_label,
				'manage_options',
				'wpcbl-check-for-broken-links-upgrade',
				array( $this, 'upgrade_page' )
			);
		}

		/**
		 * Renders the Dashboard page (main).
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function menu_page() {
			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/dashboard.php';
		}

		/**
		 * Renders the Broken link scan page: the results table split out of
		 * the Dashboard in 3.0.8.
		 *
		 * @since 3.0.8
		 *
		 * @return void
		 */
		public function scan_results_page() {
			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/scan-results.php';
		}

		/**
		 * Renders the Settings page.
		 *
		 * @since 2.1.0
		 *
		 * @return void
		 */
		public function settings_page() {
			$wpcbl_connect_notice = isset( $_GET['wpcbl_connect'] ) ? sanitize_key( wp_unslash( $_GET['wpcbl_connect'] ) ) : '';

			// Each handshake failure has its own reason so the user (and
			// support) can tell a timeout from a host that blocks requests.
			$wpcbl_try_again = ' <a href="' . esc_url( wpcbl_connect_url() ) . '">' . esc_html__( 'Try again', 'check-for-broken-links' ) . '</a>';
			$wpcbl_failures  = array(
				'expired'     => __( 'The connect request expired before it was approved.', 'check-for-broken-links' ),
				'unreachable' => __( 'This site could not reach brokenlinkchecker.io. Your host may block outgoing requests.', 'check-for-broken-links' ),
				'rejected'    => __( 'brokenlinkchecker.io did not accept the connect code.', 'check-for-broken-links' ),
				'mismatch'    => __( 'The connection was approved for a different site address.', 'check-for-broken-links' ),
				'error'       => __( 'Could not complete the connection.', 'check-for-broken-links' ),
			);

			if ( 'connected' === $wpcbl_connect_notice ) {
				$wpcbl_connected_msg = wpcbl_has_pro()
					? __( 'Site connected. Pro is now active.', 'check-for-broken-links' )
					: __( 'Site connected.', 'check-for-broken-links' );
				add_settings_error( 'wpcbl_connect', 'wpcbl_connect', $wpcbl_connected_msg, 'updated' );
			} elseif ( 'disconnected' === $wpcbl_connect_notice ) {
				add_settings_error( 'wpcbl_connect', 'wpcbl_connect', __( 'Site disconnected.', 'check-for-broken-links' ), 'updated' );
			} elseif ( 'refreshed' === $wpcbl_connect_notice ) {
				add_settings_error( 'wpcbl_connect', 'wpcbl_connect', __( 'Connection refreshed. Your plan and features are up to date.', 'check-for-broken-links' ), 'updated' );
			} elseif ( isset( $wpcbl_failures[ $wpcbl_connect_notice ] ) ) {
				add_settings_error( 'wpcbl_connect', 'wpcbl_connect', esc_html( $wpcbl_failures[ $wpcbl_connect_notice ] ) . $wpcbl_try_again, 'error' );
			}

			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/settings.php';
		}

		/**
		 * Renders the Scan reports page.
		 *
		 * @since 3.0.4
		 *
		 * @return void
		 */
		public function reports_page() {
			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/reports.php';
		}

		/**
		 * Renders the Rank Tracker page.
		 *
		 * @since 3.0.5
		 *
		 * @return void
		 */
		public function rank_tracker_page() {
			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/rank-tracker.php';
		}

		/**
		 * Renders the Uptime Monitor page.
		 *
		 * @since 3.0.6
		 *
		 * @return void
		 */
		public function uptime_page() {
			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/uptime.php';
		}

		/**
		 * Renders the SEO Audit page.
		 *
		 * @since 3.0.7
		 *
		 * @return void
		 */
		public function seo_audit_page() {
			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/seo-audit.php';
		}

		/**
		 * Render the Internal Links page.
		 *
		 * @since 3.0.8
		 *
		 * @return void
		 */
		public function internal_links_page() {
			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/internal-links.php';
		}

		/**
		 * Render the AI Visibility Tracker page.
		 *
		 * @since 3.0.8
		 *
		 * @return void
		 */
		public function ai_visibility_page() {
			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/ai-visibility.php';
		}

		/**
		 * Renders the SEO / AEO Tip page.
		 *
		 * @since 3.0.0
		 *
		 * @return void
		 */
		public function seo_tip_page() {
			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/seo-tip.php';
		}

		/**
		 * Renders the Help page.
		 *
		 * @since 2.1.0
		 *
		 * @return void
		 */
		public function help_page() {
			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/help.php';
		}

		/**
		 * Renders the Upgrade page.
		 *
		 * @since 2.1.0
		 *
		 * @return void
		 */
		public function upgrade_page() {
			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/upgrade.php';
		}

		/**
		 * Sends the retired Pro Tools slug to the dashboard.
		 *
		 * Pro Tools previewed four tools. Fix with AI and the SEO / AEO Audit
		 * have since shipped, and the rest live on brokenlinkchecker.io, so
		 * the page had nothing left to preview.
		 *
		 * The slug stays registered on purpose. wp-admin/admin.php calls
		 * wp_die() for an unregistered plugin page BEFORE admin_init fires, so
		 * an admin_init redirect can never run. Registering the page and then
		 * hiding it from the menu is what keeps an old bookmark working.
		 *
		 * @since 3.0.7
		 *
		 * @return void
		 */
		public function redirect_retired_pages() {
			wp_safe_redirect( admin_url( 'admin.php?page=wpcbl-check-for-broken-links' ) );
			exit;
		}

		/**
		 * 301s the pre-2.1 tab URLs (&tab=general / &tab=help) to the new
		 * submenu pages. &tab=scan just renders the main page (no redirect).
		 *
		 * @since 2.1.0
		 *
		 * @return void
		 */
		public function redirect_legacy_tabs() {
			if ( wp_doing_ajax() ) {
				return;
			}

			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tab  = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( 'wpcbl-check-for-broken-links' !== $page || '' === $tab ) {
				return;
			}

			$map = array(
				'general' => 'wpcbl-check-for-broken-links-settings',
				'help'    => 'wpcbl-check-for-broken-links-help',
			);

			if ( isset( $map[ $tab ] ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=' . $map[ $tab ] ) );
				exit;
			}
		}

		/**
		 * Screen option.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function screen_option() {
			$option = 'per_page';
			$args   = array(
				'label'   => 'Links',
				'default' => 10,
				'option'  => 'links_per_page',
			);

			add_screen_option( $option, $args );
		}

		/**
		 * Registers settings.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function register_settings() {
			// Ignore the phpcs warning here as we are dynamically registering the setting.
			register_setting( // phpcs:ignore PluginCheck.CodeAnalysis.SettingSanitization.register_settingDynamic
				'wpcbl_check_for_broken_links_settings',
				'wpcbl_check_for_broken_links_settings',
				array(
					'type'              => 'array',
					'description'       => esc_html__( 'Settings for the Check for Broken Links plugin.', 'check-for-broken-links' ),
					'sanitize_callback' => array( $this, 'sanitize_settings' ),
					'show_in_rest'      => false,
					'default'           => array(),
				)
			);

			// The Settings page renders its own markup (templates/admin/settings.php)
			// and posts to options.php under this option, so no sections or fields
			// are registered.
		}

		/**
		 * Sanitization callback for plugin settings.
		 *
		 * @since 1.0.0
		 *
		 * @param array $input The raw input values.
		 *
		 * @return array $sanitized_input The sanitized input values.
		 */
		public function sanitize_settings( $input ) {
			$sanitized_input = array();
			$has_pro         = wpcbl_has_pro();

			// Scheduled scans are Pro. Before 3.1.3 this was forced to never
			// for everyone, so Pro sites could not save a schedule at all.
			$frequency                         = isset( $input['scan_frequency'] ) ? sanitize_key( $input['scan_frequency'] ) : 'never';
			$sanitized_input['scan_frequency'] = $has_pro && in_array( $frequency, array( 'daily', 'weekly', 'monthly' ), true ) ? $frequency : 'never';

			if ( isset( $input['scan_time'] ) && preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $input['scan_time'] ) ) {
				$sanitized_input['scan_time'] = $input['scan_time'];
			} else {
				$sanitized_input['scan_time'] = '00:00';
			}

			if ( isset( $input['scan_timezone'] ) ) {
				$timezone = sanitize_text_field( wp_unslash( $input['scan_timezone'] ) );

				try {
					new DateTimeZone( $timezone );
					$sanitized_input['scan_timezone'] = $timezone;
				} catch ( Exception $e ) {
					$sanitized_input['scan_timezone'] = wp_timezone_string();
				}
			} else {
				$sanitized_input['scan_timezone'] = wp_timezone_string();
			}

			// Email alerts are Pro, and were blanked for everyone before 3.1.3.
			// Addresses are kept while alerts are off, so switching them back
			// on does not mean typing the list again.
			$sanitized_input['email_notifications'] = $has_pro && isset( $input['email_notifications'] ) ? 'on' : '';
			$sanitized_input['scan_slider_content'] = isset( $input['scan_slider_content'] ) ? 'on' : '';

			// On by default. The form posts "off" from a hidden field when the
			// switch is off, so a stored array without the key stays on.
			$sanitized_input['remove_url_params'] = isset( $input['remove_url_params'] ) && 'off' === $input['remove_url_params'] ? 'off' : 'on';

			$emails = array();
			if ( $has_pro && isset( $input['email_addresses'] ) ) {
				foreach ( preg_split( '/[\s,;]+/', (string) $input['email_addresses'] ) as $email ) {
					$email = sanitize_email( $email );
					if ( '' !== $email && is_email( $email ) ) {
						$emails[] = $email;
					}
				}
			}
			$sanitized_input['email_addresses'] = implode( ', ', array_unique( $emails ) );

			// Ensure `number_of_links` is stored correctly (Radio Button).
			if ( isset( $input['number_of_links'] ) ) {
				$allowed_values                     = array( 'all', 'set_number' );
				$sanitized_input['number_of_links'] = in_array( $input['number_of_links'], $allowed_values, true ) ? $input['number_of_links'] : 'all';
			}

			// Ensure `set_links_number` is stored correctly (Number Input).
			if ( isset( $input['set_links_number'] ) && 'set_number' === $input['number_of_links'] ) {
				$sanitized_input['set_links_number'] = absint( $input['set_links_number'] );
			} else {
				$sanitized_input['set_links_number'] = '';
			}

			// Content types to scan (checkbox array). Unchecking everything
			// falls back to all public post types, matching the default.
			$public_types = get_post_types( array( 'public' => true ), 'names' );
			$public_types = array_values( array_diff( $public_types, array( 'attachment', 'nav_menu_item', 'revision' ) ) );
			if ( isset( $input['scan_post_types'] ) && is_array( $input['scan_post_types'] ) ) {
				$sanitized_input['scan_post_types'] = array_values( array_intersect( array_map( 'sanitize_key', (array) $input['scan_post_types'] ), $public_types ) );
			} else {
				$sanitized_input['scan_post_types'] = array();
			}
			if ( empty( $sanitized_input['scan_post_types'] ) ) {
				$sanitized_input['scan_post_types'] = $public_types;
			}

			// Link types (checkbox array). Unchecking both falls back to both.
			$allowed_link_types = array( 'html', 'image' );
			if ( isset( $input['link_types'] ) && is_array( $input['link_types'] ) ) {
				$sanitized_input['link_types'] = array_values( array_intersect( array_map( 'sanitize_key', (array) $input['link_types'] ), $allowed_link_types ) );
			} else {
				$sanitized_input['link_types'] = array();
			}
			if ( empty( $sanitized_input['link_types'] ) ) {
				$sanitized_input['link_types'] = $allowed_link_types;
			}

			// Request timeout in seconds, clamped to 5-120.
			$sanitized_input['timeout'] = isset( $input['timeout'] ) ? min( max( absint( $input['timeout'] ), 5 ), 120 ) : 30;

			// Exclusion rules: one substring per line, plain text (not URLs).
			if ( isset( $input['exclusion_urls'] ) ) {
				$rules                             = explode( "\n", $input['exclusion_urls'] );
				$sanitized_rules                   = array_filter( array_map( 'sanitize_text_field', array_map( 'trim', $rules ) ) );
				$sanitized_input['exclusion_urls'] = implode( "\n", $sanitized_rules ); // Store as a multiline string.
			}

			// The Internal Link Optimizer stores its post types in this same
			// option from its own page. The Settings form never posts them, so
			// keep what was saved instead of wiping it on every save.
			if ( isset( $input['ilo_post_types'] ) && is_array( $input['ilo_post_types'] ) ) {
				$sanitized_input['ilo_post_types'] = array_values( array_unique( array_map( 'sanitize_key', $input['ilo_post_types'] ) ) );
			} else {
				$stored = get_option( 'wpcbl_check_for_broken_links_settings', array() );
				if ( is_array( $stored ) && isset( $stored['ilo_post_types'] ) && is_array( $stored['ilo_post_types'] ) ) {
					$sanitized_input['ilo_post_types'] = $stored['ilo_post_types'];
				}
			}

			// Pro-only toggles are stored empty in the free plugin.
			$pro_toggles = array( 'notify_authors', 'scan_comments', 'scan_custom_fields', 'nofollow_broken', 'fix_redirects', 'wayback_suggestions', 'ai_fix' );
			foreach ( $pro_toggles as $pro_toggle ) {
				$sanitized_input[ $pro_toggle ] = $has_pro && isset( $input[ $pro_toggle ] ) ? 'on' : '';
			}

			return $sanitized_input;
		}

		/**
		 * Sets the screen option.
		 *
		 * @since 1.0.0
		 *
		 * @param string $status The status.
		 * @param string $option The option.
		 * @param int    $value The value.
		 *
		 * @return int
		 */
		public function set_screen_option( $status, $option, $value ) {
			if ( 'links_per_page' == $option ) {
				return min( max( absint( $value ), 1 ), 500 );
			}
			return $status;
		}

		/**
		 * The Dashboard's review request. "Maybe later" and the close
		 * button hide it for 30 days. "Leave a review" hides it for good
		 * and sends the admin to the WordPress.org review form.
		 *
		 * @since 3.1.1
		 *
		 * @return void
		 */
		public function handle_review_dismiss() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You are not allowed to do this.', 'check-for-broken-links' ), '', array( 'response' => 403 ) );
			}
			check_admin_referer( 'wpcbl_review_dismiss' );

			$mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'later';

			if ( 'review' === $mode ) {
				update_option( 'wpcbl_review_dismissed', 1, false );
				wp_redirect( 'https://wordpress.org/support/plugin/check-for-broken-links/reviews/#new-post' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- fixed external URL, no user input.
				exit;
			}

			set_transient( 'wpcbl_review_later', 1, 30 * DAY_IN_SECONDS );
			wp_safe_redirect( admin_url( 'admin.php?page=wpcbl-check-for-broken-links' ) );
			exit;
		}

		/**
		 * One-click "Turn on Fix with AI" from Broken link scan: flips the
		 * setting and returns to that page, where Fix all with AI is ready
		 * to use.
		 *
		 * @since 3.0.4
		 *
		 * @return void
		 */
		public function handle_enable_ai_fix() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You are not allowed to do this.', 'check-for-broken-links' ), '', array( 'response' => 403 ) );
			}
			check_admin_referer( 'wpcbl_enable_ai_fix' );

			$settings           = get_option( 'wpcbl_check_for_broken_links_settings', array() );
			$settings['ai_fix'] = 'on';
			update_option( 'wpcbl_check_for_broken_links_settings', $settings );

			// This card only lives on Broken link scan (3.0.8): redirect there,
			// not to the Dashboard, so "Fix all with AI" is right where the
			// admin was reaching for it.
			wp_safe_redirect( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-scan' ) );
			exit;
		}
	}

	return new WPCBL_Check_Broken_Links_Admin_Settings();

endif;
