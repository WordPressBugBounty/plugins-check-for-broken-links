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
					'id'       => 'scan',
					'title'    => __( 'Dashboard & Scan', 'check-for-broken-links' ),
					'href'     => $page( '' ),
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
					'id'       => 'uptime',
					'title'    => __( 'Uptime Monitor', 'check-for-broken-links' ),
					'href'     => $page( '-uptime' ),
					'external' => false,
				),
			);

			// The remaining tools live on brokenlinkchecker.io. wpcbl_go()
			// carries the connected site through, so they land on the right
			// project instead of a generic dashboard.
			if ( function_exists( 'wpcbl_go_url' ) ) {
				$services[] = array(
					'id'       => 'ai-visibility',
					'title'    => __( 'AI Visibility Tracker', 'check-for-broken-links' ),
					'href'     => wpcbl_go_url( 'ai-visibility' ),
					'external' => true,
				);
				$services[] = array(
					'id'       => 'internal-links',
					'title'    => __( 'Internal Link Optimizer', 'check-for-broken-links' ),
					'href'     => wpcbl_go_url( 'internal-links' ),
					'external' => true,
				);
			}

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
				esc_html__( 'Dashboard & Scan', 'check-for-broken-links' ),
				esc_html__( 'Dashboard & Scan', 'check-for-broken-links' ),
				'manage_options',
				'wpcbl-check-for-broken-links'
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
		 * Renders the Scan Results page (main).
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function menu_page() {
			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/scan.php';
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

			if ( 'connected' === $wpcbl_connect_notice ) {
				add_settings_error( 'wpcbl_connect', 'wpcbl_connect', __( 'Site connected. Pro is now active.', 'check-for-broken-links' ), 'updated' );
			} elseif ( 'disconnected' === $wpcbl_connect_notice ) {
				add_settings_error( 'wpcbl_connect', 'wpcbl_connect', __( 'Site disconnected.', 'check-for-broken-links' ), 'updated' );
			} elseif ( 'refreshed' === $wpcbl_connect_notice ) {
				add_settings_error( 'wpcbl_connect', 'wpcbl_connect', __( 'Connection refreshed. Your plan and features are up to date.', 'check-for-broken-links' ), 'updated' );
			} elseif ( 'error' === $wpcbl_connect_notice ) {
				add_settings_error( 'wpcbl_connect', 'wpcbl_connect', __( 'Could not complete the connection. Please try again.', 'check-for-broken-links' ), 'error' );
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

			$has_pro = wpcbl_has_pro();

			// Section order: General -> Scan -> Notifications -> Pro/SEO.
			// Each section renders as its own card (before_section/after_section).
			add_settings_section(
				'wpcbl_check_for_broken_links_general_settings_section',
				esc_html__( 'General', 'check-for-broken-links' ),
				array( $this, 'settings_autosave_hint' ),
				'wpcbl-check-for-broken-links',
				array(
					'before_section' => '<div class="cbl-card cbl-settings-card">',
					'after_section'  => '</div>',
				)
			);

			add_settings_section(
				'wpcbl_check_for_broken_links_scan_scope_section',
				esc_html__( 'Scan', 'check-for-broken-links' ),
				null,
				'wpcbl-check-for-broken-links',
				array(
					// Deep-link target for the dashboard's "Adjust in Settings" link.
					'before_section' => '<div id="scan" class="cbl-card cbl-settings-card">',
					'after_section'  => '</div>',
				)
			);

			// Email alerts are a Pro feature. Free installs still see the
			// Notifications card, but its fields are dimmed and inert until Pro
			// is active, so nobody types settings that will not take effect.
			$wpcbl_notifications_locked = ! wpcbl_has_pro();
			add_settings_section(
				'wpcbl_check_for_broken_links_notifications_section',
				esc_html__( 'Notifications', 'check-for-broken-links' ),
				$wpcbl_notifications_locked ? array( $this, 'settings_notifications_lock_note' ) : null,
				'wpcbl-check-for-broken-links',
				array(
					'before_section' => $wpcbl_notifications_locked
						? '<div class="cbl-card cbl-settings-card cbl-settings-card-locked">'
						: '<div class="cbl-card cbl-settings-card">',
					'after_section'  => '</div>',
				)
			);

			add_settings_section(
				'wpcbl_check_for_broken_links_seo_section',
				// Free users see the Unlock-with-Pro card's own heading instead.
				$has_pro ? esc_html__( 'SEO', 'check-for-broken-links' ) : '',
				null,
				'wpcbl-check-for-broken-links',
				array(
					// The -seo class keeps this card's toggle helpers on the
					// right; other cards move descriptions under their labels.
					'before_section' => '<div class="cbl-card cbl-settings-card cbl-settings-card-seo">',
					'after_section'  => '</div>',
				)
			);

			// --- General ---
			add_settings_field(
				'scan_frequency',
				esc_html__( 'Scan Frequency', 'check-for-broken-links' ),
				array( $this, 'settings_scan_frequency' ),
				'wpcbl-check-for-broken-links',
				'wpcbl_check_for_broken_links_general_settings_section'
			);

			add_settings_field(
				'number_of_links',
				esc_html__( 'Number of Links to Scan', 'check-for-broken-links' ),
				array( $this, 'settings_number_of_links' ),
				'wpcbl-check-for-broken-links',
				'wpcbl_check_for_broken_links_general_settings_section'
			);

			add_settings_field(
				'timeout',
				esc_html__( 'Timeout', 'check-for-broken-links' ),
				array( $this, 'settings_timeout' ),
				'wpcbl-check-for-broken-links',
				'wpcbl_check_for_broken_links_general_settings_section'
			);

			add_settings_field(
				'recheck_all',
				esc_html__( 'Re-check', 'check-for-broken-links' ),
				array( $this, 'settings_recheck_all' ),
				'wpcbl-check-for-broken-links',
				'wpcbl_check_for_broken_links_general_settings_section'
			);

			// --- Scan ---
			add_settings_field(
				'scan_post_types',
				esc_html__( 'Content Types to Scan', 'check-for-broken-links' ),
				array( $this, 'settings_scan_post_types' ),
				'wpcbl-check-for-broken-links',
				'wpcbl_check_for_broken_links_scan_scope_section'
			);

			add_settings_field(
				'link_types',
				esc_html__( 'Link Types', 'check-for-broken-links' ),
				array( $this, 'settings_link_types' ),
				'wpcbl-check-for-broken-links',
				'wpcbl_check_for_broken_links_scan_scope_section'
			);

			add_settings_field(
				'exclusion_urls',
				esc_html__( 'Exclusions', 'check-for-broken-links' ),
				array( $this, 'settings_exclusion_urls' ),
				'wpcbl-check-for-broken-links',
				'wpcbl_check_for_broken_links_scan_scope_section'
			);

			add_settings_field(
				'scan_slider_content',
				esc_html__( 'Slider Content', 'check-for-broken-links' ),
				array( $this, 'settings_scan_slider_content' ),
				'wpcbl-check-for-broken-links',
				'wpcbl_check_for_broken_links_scan_scope_section'
			);

			// Pro only: free users see this feature inside the Unlock-with-Pro card.
			if ( $has_pro ) {
				add_settings_field(
					'scan_comments',
					esc_html__( 'Comments & Custom Fields', 'check-for-broken-links' ),
					array( $this, 'settings_comments_custom_fields' ),
					'wpcbl-check-for-broken-links',
					'wpcbl_check_for_broken_links_scan_scope_section'
				);
			}

			// --- Notifications ---
			add_settings_field(
				'email_notifications',
				esc_html__( 'Email Notifications', 'check-for-broken-links' ),
				array( $this, 'settings_email_notifications' ),
				'wpcbl-check-for-broken-links',
				'wpcbl_check_for_broken_links_notifications_section'
			);

			add_settings_field(
				'email_addresses',
				esc_html__( 'Email Address(es)', 'check-for-broken-links' ),
				array( $this, 'settings_email_addresses' ),
				'wpcbl-check-for-broken-links',
				'wpcbl_check_for_broken_links_notifications_section'
			);

			// Pro only: free users see this feature inside the Unlock-with-Pro card.
			if ( $has_pro ) {
				add_settings_field(
					'notify_authors',
					esc_html__( 'Notify Post Authors', 'check-for-broken-links' ),
					array( $this, 'settings_pro_toggle' ),
					'wpcbl-check-for-broken-links',
					'wpcbl_check_for_broken_links_notifications_section',
					array(
						'key'    => 'notify_authors',
						'label'  => __( 'Also email the post author when broken links are found in their posts', 'check-for-broken-links' ),
						'helper' => __( 'Authors only get alerts for their own content.', 'check-for-broken-links' ),
					)
				);
			}

			// --- Pro / SEO ---
			// Free: all six Pro features collapse into one Unlock-with-Pro card
			// with a single upgrade CTA. Pro: the four SEO toggles render here,
			// working, individually.
			if ( ! $has_pro ) {
				add_settings_field(
					'unlock_pro',
					'',
					array( $this, 'settings_unlock_pro' ),
					'wpcbl-check-for-broken-links',
					'wpcbl_check_for_broken_links_seo_section',
					array( 'class' => 'cbl-seo-toolkit-row' )
				);

				return;
			}

			add_settings_field(
				'ai_fix',
				esc_html__( 'Fix with AI', 'check-for-broken-links' ),
				array( $this, 'settings_pro_toggle' ),
				'wpcbl-check-for-broken-links',
				'wpcbl_check_for_broken_links_seo_section',
				array(
					'key'    => 'ai_fix',
					'label'  => __( 'Fix broken links with AI', 'check-for-broken-links' ),
					'helper' => __( 'AI finds the working replacement for each broken link and fixes it with one click. Every suggestion is verified live before it reaches you.', 'check-for-broken-links' ),
				)
			);

			add_settings_field(
				'nofollow_broken',
				esc_html__( 'Nofollow Broken Links', 'check-for-broken-links' ),
				array( $this, 'settings_pro_toggle' ),
				'wpcbl-check-for-broken-links',
				'wpcbl_check_for_broken_links_seo_section',
				array(
					'key'    => 'nofollow_broken',
					'label'  => __( 'Add rel=nofollow to broken links until fixed', 'check-for-broken-links' ),
					'helper' => __( 'Stops search engines from following broken links, protecting your crawl budget and rankings.', 'check-for-broken-links' ),
				)
			);

			add_settings_field(
				'fix_redirects',
				esc_html__( 'Auto-fix Redirects', 'check-for-broken-links' ),
				array( $this, 'settings_pro_toggle' ),
				'wpcbl-check-for-broken-links',
				'wpcbl_check_for_broken_links_seo_section',
				array(
					'key'    => 'fix_redirects',
					'label'  => __( 'Detect permanent redirects (301/308)', 'check-for-broken-links' ),
					'helper' => __( 'Redirected links get a Fix redirect action that replaces the URL with the final destination.', 'check-for-broken-links' ),
				)
			);

			add_settings_field(
				'wayback_suggestions',
				esc_html__( 'Replacement Suggestions', 'check-for-broken-links' ),
				array( $this, 'settings_pro_toggle' ),
				'wpcbl-check-for-broken-links',
				'wpcbl_check_for_broken_links_seo_section',
				array(
					'key'    => 'wayback_suggestions',
					'label'  => __( 'Suggest replacements from the Wayback Machine', 'check-for-broken-links' ),
					'helper' => __( 'Broken external links get a View archived version action.', 'check-for-broken-links' ),
				)
			);
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

			// Scheduled scans are a Pro feature in the free plugin.
			$sanitized_input['scan_frequency'] = 'never';

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

			// Email alerts are a Pro feature in the free plugin.
			$sanitized_input['email_notifications'] = '';
			$sanitized_input['scan_slider_content'] = isset( $input['scan_slider_content'] ) ? 'on' : '';

			$sanitized_input['email_addresses'] = '';

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

			// Pro-only toggles are stored empty in the free plugin.
			$pro_toggles = array( 'notify_authors', 'scan_comments', 'scan_custom_fields', 'nofollow_broken', 'fix_redirects', 'wayback_suggestions', 'ai_fix' );
			foreach ( $pro_toggles as $pro_toggle ) {
				$sanitized_input[ $pro_toggle ] = wpcbl_has_pro() && isset( $input[ $pro_toggle ] ) ? 'on' : '';
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
		 * Renders the scan frequency field.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function settings_scan_frequency() {
			$scan_frequency              = 'never';
			$scan_time                   = isset( $this->settings['scan_time'] ) ? $this->settings['scan_time'] : '00:00';
			$scan_timezone               = ! empty( $this->settings['scan_timezone'] ) ? $this->settings['scan_timezone'] : wp_timezone_string();
			$next_scheduled              = false;
			$has_scheduled_scans_access  = wpcbl_has_pro();
			$upgrade_url                 = 'https://checkout.freemius.com/plugin/30464/plan/50060/';

			// Manual-offset sites get "+02:00" from wp_timezone_string(), but
			// wp_timezone_choice() only matches its own "UTC+2" option format.
			if ( preg_match( '/^[+-]/', $scan_timezone ) ) {
				$wpcbl_gmt_offset = (float) get_option( 'gmt_offset' );
				$scan_timezone    = 'UTC' . ( $wpcbl_gmt_offset >= 0 ? '+' : '' ) . rtrim( rtrim( sprintf( '%.2f', $wpcbl_gmt_offset ), '0' ), '.' );
			}

			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/views/sections/fields/scan-frequency.php';
		}

		/**
		 * Render the email addresses field.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function settings_email_addresses() {
			$email_notifications     = isset( $this->settings['email_notifications'] ) ? $this->settings['email_notifications'] : 'off';
			$email_addresses         = isset( $this->settings['email_addresses'] ) ? $this->settings['email_addresses'] : '';
			$has_email_alerts_access = wpcbl_has_pro();
			$upgrade_url             = admin_url( 'admin.php?page=wpcbl-check-for-broken-links-upgrade' );

			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/views/sections/fields/email-addresses.php';
		}

		/**
		 * Renders the "available on Pro" note above the locked Notifications
		 * fields, linking down to the Unlock with Pro card on the same page.
		 *
		 * @since 3.0.1
		 *
		 * @return void
		 */
		/**
		 * One-click "Turn on Fix with AI" from the dashboard: flips the
		 * setting and returns to the scan page, where Fix all with AI is
		 * ready to use.
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

			wp_safe_redirect( admin_url( 'admin.php?page=wpcbl-check-for-broken-links' ) );
			exit;
		}

		/**
		 * The General card's top-right autosave indicator. The settings-page
		 * script flips its text between saving states.
		 *
		 * @since 3.0.4
		 *
		 * @return void
		 */
		public function settings_autosave_hint() {
			printf(
				'<span class="cbl-autosave-hint" id="cbl-autosave-hint" data-idle="%1$s" data-saving="%2$s" data-saved="%3$s">%1$s</span>',
				esc_attr__( 'Changes are saved automatically', 'check-for-broken-links' ),
				esc_attr__( 'Saving…', 'check-for-broken-links' ),
				esc_attr__( 'Saved', 'check-for-broken-links' )
			);
		}

		public function settings_notifications_lock_note() {
			printf(
				'<a class="cbl-settings-card-lock-note" href="#cbl-unlock-pro">%s</a>',
				esc_html__( 'Email alerts are available on Pro', 'check-for-broken-links' )
			);
		}

		/**
		 * Renders the email notifications field.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function settings_email_notifications() {
			$email_notifications     = isset( $this->settings['email_notifications'] ) ? $this->settings['email_notifications'] : 'off';
			$email_addresses         = isset( $this->settings['email_addresses'] ) ? $this->settings['email_addresses'] : '';
			$has_email_alerts_access = wpcbl_has_pro();
			$upgrade_url             = admin_url( 'admin.php?page=wpcbl-check-for-broken-links-upgrade' );

			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/views/sections/fields/email-notifications.php';
		}

		/**
		 * Renders the scan slider content field.
		 *
		 * @since 1.0.2
		 *
		 * @return void
		 */
		public function settings_scan_slider_content() {
			$scan_slider_content = isset( $this->settings['scan_slider_content'] ) ? $this->settings['scan_slider_content'] : 'on';

			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/views/sections/fields/scan-slider-content.php';
		}

		/**
		 * Renders the number of links field.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function settings_number_of_links() {
			$number_of_links = isset( $this->settings['number_of_links'] ) ? $this->settings['number_of_links'] : 'all';
			$set_number      = isset( $this->settings['set_links_number'] ) ? $this->settings['set_links_number'] : '';

			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/views/sections/fields/number-of-links.php';
		}

		/**
		 * Renders the content types to scan field.
		 *
		 * @since 3.0.0
		 *
		 * @return void
		 */
		public function settings_scan_post_types() {
			$scan_post_types = isset( $this->settings['scan_post_types'] ) && is_array( $this->settings['scan_post_types'] ) ? $this->settings['scan_post_types'] : array();

			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/views/sections/fields/scan-post-types.php';
		}

		/**
		 * Renders the link types field.
		 *
		 * @since 3.0.0
		 *
		 * @return void
		 */
		public function settings_link_types() {
			$link_types = isset( $this->settings['link_types'] ) && is_array( $this->settings['link_types'] ) ? $this->settings['link_types'] : array( 'html', 'image' );

			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/views/sections/fields/link-types.php';
		}

		/**
		 * Renders the timeout field.
		 *
		 * @since 3.0.0
		 *
		 * @return void
		 */
		public function settings_timeout() {
			$timeout = isset( $this->settings['timeout'] ) ? (int) $this->settings['timeout'] : 30;

			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/views/sections/fields/timeout.php';
		}

		/**
		 * Renders the Pro comments and custom fields toggles.
		 *
		 * @since 3.0.0
		 *
		 * @return void
		 */
		public function settings_comments_custom_fields() {
			$this->settings_pro_toggle(
				array(
					'key'   => 'scan_comments',
					'label' => __( 'Scan comments', 'check-for-broken-links' ),
				)
			);
			$this->settings_pro_toggle(
				array(
					'key'    => 'scan_custom_fields',
					'label'  => __( 'Scan custom fields (incl. ACF)', 'check-for-broken-links' ),
					'helper' => __( 'Checks links stored in post meta, including ACF fields.', 'check-for-broken-links' ),
				)
			);
		}

		/**
		 * Renders a Pro-gated toggle: visible, disabled, PRO badge, upgrade link.
		 *
		 * @since 3.0.0
		 *
		 * @param array $args Field args: key, label, optional helper.
		 *
		 * @return void
		 */
		public function settings_pro_toggle( $args ) {
			$key         = isset( $args['key'] ) ? $args['key'] : '';
			$label       = isset( $args['label'] ) ? $args['label'] : '';
			$helper      = isset( $args['helper'] ) ? $args['helper'] : '';
			$value       = isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : '';
			$has_pro     = wpcbl_has_pro();
			$upgrade_url = admin_url( 'admin.php?page=wpcbl-check-for-broken-links-upgrade' );

			include WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/views/sections/fields/pro-toggle.php';
		}

		/**
		 * Renders the free-version Unlock-with-Pro card: all six Pro
		 * features as a read-only list with one upgrade CTA.
		 *
		 * @since 3.0.0
		 *
		 * @return void
		 */
		public function settings_unlock_pro() {
			$upgrade_url = admin_url( 'admin.php?page=wpcbl-check-for-broken-links-upgrade' );

			include WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/views/sections/fields/unlock-pro.php';
		}

		/**
		 * Renders the re-check all links button.
		 *
		 * @since 3.0.0
		 *
		 * @return void
		 */
		public function settings_recheck_all() {
			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/views/sections/fields/recheck-all.php';
		}

		/**
		 * Renders the exclusion urls field.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function settings_exclusion_urls() {
			$exclusion_urls = isset( $this->settings['exclusion_urls'] ) ? $this->settings['exclusion_urls'] : '';

			include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/views/sections/fields/exclusion-urls.php';
		}
	}

	return new WPCBL_Check_Broken_Links_Admin_Settings();

endif;
