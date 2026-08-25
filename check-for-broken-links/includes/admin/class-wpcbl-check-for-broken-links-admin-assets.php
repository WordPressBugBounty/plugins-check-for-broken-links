<?php
/**
 * The WPCBL_Check_Broken_Links_Admin_Assets class.
 *
 * @package WPCBL_Check_Broken_Links/Admin
 * @author Norse Digital Group LLC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WPCBL_Check_Broken_Links_Admin_Assets' ) ) :

	/**
	 * Admin assets.
	 *
	 * Handles back-end styles and scripts.
	 *
	 * @since   1.0.0
	 */
	class WPCBL_Check_Broken_Links_Admin_Assets {
		/**
		 * The constructor.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function __construct() {
			add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ), 20 );
			add_action( 'admin_enqueue_scripts', array( $this, 'styles' ), 20 );
			add_action( 'admin_head', array( $this, 'favicon' ) );
		}

		/**
		 * Prints the plugin icon as favicon on the plugin's own admin pages.
		 *
		 * @since 2.1.0
		 *
		 * @return void
		 */
		public function favicon() {
			$current_page = isset( $_GET ) && isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( empty( $current_page ) || ! wpcbl_str_starts_with( $current_page, 'wpcbl-check-for-broken-links' ) ) {
				return;
			}

			echo '<link rel="icon" type="image/png" sizes="128x128" href="' . esc_url( WPCBL_CHECK_BROKEN_LINKS_ROOT_URL . 'assets/dist/images/cbl-icon.png' ) . '" />' . "\n";
		}

		/**
		 * Enqueues admin scripts.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function scripts() {
			$current_page = isset( $_GET ) && isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( empty( $current_page ) || ! wpcbl_str_starts_with( $current_page, 'wpcbl-check-for-broken-links' ) ) {
				return; // Dashboard scan flow + Settings re-check both need the script.
			}

			// Global admin scripts.
			$script_rel  = 'assets/dist/js/admin/check-for-broken-links-admin-scripts.js';
			$script_path = WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/' . $script_rel;
			$script_ver  = file_exists( $script_path ) ? filemtime( $script_path ) : WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION;
			wp_enqueue_script(
				'wpcbl_check_for_broken_links_admin_scripts',
				WPCBL_CHECK_BROKEN_LINKS_ROOT_URL . $script_rel,
				array( 'jquery' ),
				$script_ver,
				true
			);

			$wpcbl_ai_batch = get_option( 'wpcbl_ai_fix_batch', array() );

			// Localization variables.
			wp_localize_script(
				'wpcbl_check_for_broken_links_admin_scripts',
				'wpcbl_check_for_broken_links_params',
				array(
					'ajaxUrl'      => esc_url( admin_url( 'admin-ajax.php' ) ),
					'nonce'        => wp_create_nonce( 'wpcbl_check_for_broken_links' ),
					'scanPageUrl'  => esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links' ) ),
					'clearConfirm' => esc_html__( 'Clear the current scan results? Settings are kept. Run a new scan anytime to check your links again.', 'check-for-broken-links' ),
					/* translators: 1: items scanned so far, 2: total items, 3: links checked so far. */
					'progressText'   => esc_html__( 'Checking item %1$s of %2$s. %3$s links checked so far.', 'check-for-broken-links' ),
					'editUrlPrompt'  => esc_html__( 'Every occurrence of the old URL in this post is replaced.', 'check-for-broken-links' ),
					'editUrlTitle'   => esc_html__( 'Edit URL', 'check-for-broken-links' ),
					'fixAllLabel'    => esc_html__( 'Fix all similar: replace this URL in every post where it appears', 'check-for-broken-links' ),
					'unlinkConfirm'  => esc_html__( 'The link is removed but its text stays in the post.', 'check-for-broken-links' ),
					'unlinkTitle'    => esc_html__( 'Unlink', 'check-for-broken-links' ),
					'recheckConfirm' => esc_html__( 'Clear all cached link statuses and run a fresh scan now? Links you marked as Not broken stay skipped.', 'check-for-broken-links' ),
					'recheckTitle'   => esc_html__( 'Re-check all links', 'check-for-broken-links' ),
					'clearTitle'     => esc_html__( 'Clear Results', 'check-for-broken-links' ),
					'errorGeneric'   => esc_html__( 'Something went wrong. Please try again.', 'check-for-broken-links' ),
					'errorTitle'     => esc_html__( 'Action failed', 'check-for-broken-links' ),
					'modalConfirm'   => esc_html__( 'Confirm', 'check-for-broken-links' ),
					'modalCancel'    => esc_html__( 'Cancel', 'check-for-broken-links' ),
					'modalSave'      => esc_html__( 'Save URL', 'check-for-broken-links' ),
					'modalOk'        => esc_html__( 'OK', 'check-for-broken-links' ),
					'scanningLabel'  => esc_html__( 'Scanning…', 'check-for-broken-links' ),
					'aiFixTitle'       => esc_html__( 'Fix with AI', 'check-for-broken-links' ),
					'aiFixWorking'     => esc_html__( 'Analyzing…', 'check-for-broken-links' ),
					'aiFixPickMessage' => esc_html__( 'Pick the replacement to apply. Every option was checked live before it reached you.', 'check-for-broken-links' ),
					'aiFixNoneMessage' => esc_html__( 'No working replacement was found. You can remove the link and keep its text.', 'check-for-broken-links' ),
					'aiFixApply'       => esc_html__( 'Apply fix', 'check-for-broken-links' ),
					'aiFixUnlink'      => esc_html__( 'Remove link', 'check-for-broken-links' ),
					'aiFixWayback'     => esc_html__( 'View archived version', 'check-for-broken-links' ),
					/* translators: 1: AI fixes used this month, 2: monthly limit. */
					'aiFixQuotaText'   => esc_html__( '%1$s of %2$s AI fixes used this month.', 'check-for-broken-links' ),
					'aiBatchConfirmTitle' => esc_html__( 'Fix all with AI', 'check-for-broken-links' ),
					/* translators: %1$s: number of broken links. */
					'aiBatchConfirmMsg'   => esc_html__( 'Analyze up to %1$s broken links with AI? Dismissed links are left out. This uses your monthly AI fix allowance, one per link.', 'check-for-broken-links' ),
					'aiBatchStart'        => esc_html__( 'Start analysis', 'check-for-broken-links' ),
					/* translators: 1: analyzed links so far, 2: total queued links. */
					'aiBatchAnalyzing'    => esc_html__( '%1$s of %2$s checked', 'check-for-broken-links' ),
					'aiBatchAnalyzingLabel' => esc_html__( 'Analyzing broken links', 'check-for-broken-links' ),
					'aiBatchReviewTitle'  => esc_html__( 'Review AI fixes', 'check-for-broken-links' ),
					'aiBatchApply'        => esc_html__( 'Apply', 'check-for-broken-links' ),
					'aiBatchApplyAll'     => esc_html__( 'Apply all suggestions', 'check-for-broken-links' ),
					'aiBatchSkipRow'      => esc_html__( 'Skip', 'check-for-broken-links' ),
					'aiBatchApplied'      => esc_html__( 'Applied', 'check-for-broken-links' ),
					'aiBatchSkipped'      => esc_html__( 'Skipped', 'check-for-broken-links' ),
					'aiBatchRemoveLabel'  => esc_html__( 'Remove link, keep its text', 'check-for-broken-links' ),
					'aiBatchFailedRow'    => esc_html__( 'Could not analyze this link. Try the row action instead.', 'check-for-broken-links' ),
					'aiBatchManualRow'    => esc_html__( 'Cannot fix automatically. Edit the post directly.', 'check-for-broken-links' ),
					/* translators: %1$s: number of links beyond one batch. */
					'aiBatchCapRows'      => esc_html__( 'One batch covers 500 links. Run Fix all with AI again for the other %1$s.', 'check-for-broken-links' ),
					/* translators: %1$s: number of links past the monthly limit. */
					'aiBatchQuotaRows'    => esc_html__( 'Monthly AI fix limit reached for %1$s links. The counter resets on the first of the month.', 'check-for-broken-links' ),
					'aiBatchUpgrade'      => esc_html__( 'Get a bigger plan', 'check-for-broken-links' ),
					'aiBatchClose'        => esc_html__( 'Close review', 'check-for-broken-links' ),
					'aiBatchDone'         => esc_html__( 'All done. Reloading the results…', 'check-for-broken-links' ),
					/* translators: %1$s: number of fixes that failed. */
					'aiBatchApplyFailures' => esc_html__( '%1$s fixes did not apply. The rows stay in the list so you can retry them.', 'check-for-broken-links' ),
					'aiBatchJob'          => (string) ( ! empty( $wpcbl_ai_batch['job_id'] ) ? $wpcbl_ai_batch['job_id'] : '' ),
					'aiBatchCanUpgrade'   => in_array( (string) wpcbl_connect_plan(), array( 'personal', 'business' ), true ),
					'aiBatchAccountUrl'   => 'https://brokenlinkchecker.io/account',
					'rankTitle'         => esc_html__( 'Rank Tracker', 'check-for-broken-links' ),
					'rankAddTitle'      => esc_html__( 'Add keywords', 'check-for-broken-links' ),
					'rankAddMessage'    => esc_html__( 'One keyword per line, or separate them with commas.', 'check-for-broken-links' ),
					'rankAddButton'     => esc_html__( 'Start tracking', 'check-for-broken-links' ),
					'rankDeviceDesktop' => esc_html__( 'Desktop', 'check-for-broken-links' ),
					'rankDeviceMobile'  => esc_html__( 'Mobile', 'check-for-broken-links' ),
					'rankDeviceBoth'    => esc_html__( 'Desktop and mobile', 'check-for-broken-links' ),
					/* translators: 1: positions used, 2: positions limit. */
					'rankQuotaText'     => esc_html__( '%1$s of %2$s positions used', 'check-for-broken-links' ),
					/* translators: 1: refreshes used, 2: monthly refresh limit. */
					'rankRefreshesText' => esc_html__( '%1$s of %2$s refreshes left this month', 'check-for-broken-links' ),
					'rankRefreshNow'    => esc_html__( 'Refresh now', 'check-for-broken-links' ),
					'rankExportCsv'     => esc_html__( 'Export CSV', 'check-for-broken-links' ),
					'rankDeleteSelected' => esc_html__( 'Delete', 'check-for-broken-links' ),
					'rankDeleteTitle'   => esc_html__( 'Remove keywords', 'check-for-broken-links' ),
					'rankDeleteConfirm' => esc_html__( 'Stop tracking the selected keywords? Their history is deleted.', 'check-for-broken-links' ),
					'rankPendingNote'   => esc_html__( 'First positions arrive in a few minutes.', 'check-for-broken-links' ),
					'rankEmptyTitle'    => esc_html__( 'No keywords yet', 'check-for-broken-links' ),
					'rankEmptyMessage'  => esc_html__( 'Add the searches you want to rank for and positions arrive within minutes.', 'check-for-broken-links' ),
					'rankColumns'       => array(
						'keyword'     => esc_html__( 'Keyword', 'check-for-broken-links' ),
						'position'    => esc_html__( 'Position', 'check-for-broken-links' ),
						'change'      => esc_html__( 'Change', 'check-for-broken-links' ),
						'best'        => esc_html__( 'Best', 'check-for-broken-links' ),
						'volume'      => esc_html__( 'Volume', 'check-for-broken-links' ),
						'cpc'         => esc_html__( 'CPC', 'check-for-broken-links' ),
						'intent'      => esc_html__( 'Intent', 'check-for-broken-links' ),
						'competition' => esc_html__( 'Competition', 'check-for-broken-links' ),
						'page'        => esc_html__( 'Page', 'check-for-broken-links' ),
						'checked'     => esc_html__( 'Checked', 'check-for-broken-links' ),
					),
					'rankSettingsTitle' => esc_html__( 'Tracker settings', 'check-for-broken-links' ),
					'rankSettingsSaved' => esc_html__( 'Settings saved.', 'check-for-broken-links' ),
					'rankShareCopied'   => esc_html__( 'Link copied.', 'check-for-broken-links' ),
					'rankNotConnected'  => esc_html__( 'Connect this site to brokenlinkchecker.io first.', 'check-for-broken-links' ),
					'rankLoadError'     => esc_html__( 'Could not load rankings. brokenlinkchecker.io might be briefly unavailable.', 'check-for-broken-links' ),
					'rankRetry'         => esc_html__( 'Try again', 'check-for-broken-links' ),
					'rankGetMore'       => esc_html__( 'Get more positions', 'check-for-broken-links' ),
					'rankDailyTitle'    => esc_html__( 'Daily ranking updates', 'check-for-broken-links' ),
					'rankDailyMessage'  => esc_html__( 'Positions update weekly on your plan. The Daily add-on re-checks every keyword every morning.', 'check-for-broken-links' ),
					'rankDailyButton'   => esc_html__( 'Get daily updates', 'check-for-broken-links' ),
					'rankFlagsBase'                   => esc_url( WPCBL_CHECK_BROKEN_LINKS_ROOT_URL . 'assets/dist/images/admin/flags/' ),
					'rankUpgradeUrl'                  => esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-upgrade' ) ),
					/* translators: %1$s: date of the next scheduled ranking check. */
					'rankNextUpdate'                  => esc_html__( 'Next ranking update: %1$s', 'check-for-broken-links' ),
					/* translators: %1$s: plan name. */
					'bilChangeConfirm'                => esc_html__( 'Switch to the %1$s plan? Upgrades are charged prorated to your card right away, downgrades are credited to your next invoice.', 'check-for-broken-links' ),
					'bilWorking'                      => esc_html__( 'One moment…', 'check-for-broken-links' ),
					'rankEngineGoogle'                => esc_html__( 'Google', 'check-for-broken-links' ),
					'rankEngineBing'                  => esc_html__( 'Bing', 'check-for-broken-links' ),
					'rankEngineBoth'                  => esc_html__( 'Google and Bing', 'check-for-broken-links' ),
					'rankSettingsDevice'             => esc_html__( 'Default device', 'check-for-broken-links' ),
					'rankSettingsEngine'             => esc_html__( 'Search engine', 'check-for-broken-links' ),
					'rankSettingsEmailLabel'         => esc_html__( 'Email reports', 'check-for-broken-links' ),
					'rankSettingsEmailPlaceholder'   => esc_html__( 'name@example.com, name2@example.com', 'check-for-broken-links' ),
					'rankSettingsShareLabel'         => esc_html__( 'Public share link', 'check-for-broken-links' ),
					'rankSettingsPasswordPlaceholder' => esc_html__( 'Optional password', 'check-for-broken-links' ),
					'rankSettingsSave'               => esc_html__( 'Save settings', 'check-for-broken-links' ),
					'rankSettingsCopyLink'           => esc_html__( 'Copy link', 'check-for-broken-links' ),
					/* translators: %1$s: number of keywords selected. */
					'rankBulkSelected'               => esc_html__( '%1$s selected', 'check-for-broken-links' ),
					'rankChartVisibility'             => esc_html__( 'Visibility', 'check-for-broken-links' ),
					'rankChartTraffic'                => esc_html__( 'Estimated traffic', 'check-for-broken-links' ),
					'rankChartAvgPosition'            => esc_html__( 'Average position', 'check-for-broken-links' ),
					'uptimeLoadError'                  => esc_html__( 'Could not load your uptime monitors. brokenlinkchecker.io might be briefly unavailable.', 'check-for-broken-links' ),
					'uptimeMonitorThis'                => esc_html__( 'Monitor this site', 'check-for-broken-links' ),
					/* translators: 1: monitors used, 2: monitor limit. */
					'uptimeQuotaText'                  => esc_html__( '%1$s of %2$s monitors used', 'check-for-broken-links' ),
					'uptimeGetMore'                     => esc_html__( 'Get more monitors', 'check-for-broken-links' ),
					'uptimeUpgradeUrl'                  => esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-upgrade' ) ),
					'uptimeTypeHttp'                    => esc_html__( 'Website', 'check-for-broken-links' ),
					'uptimeTypeHeartbeat'               => esc_html__( 'WP-Cron', 'check-for-broken-links' ),
					'uptimeTypeSsl'                     => esc_html__( 'SSL expiry', 'check-for-broken-links' ),
					'uptimeTypeDomain'                  => esc_html__( 'Domain expiry', 'check-for-broken-links' ),
					'uptimeExpiryChecking'               => esc_html__( 'Checking expiry', 'check-for-broken-links' ),
					'uptimeStatusUp'                    => esc_html__( 'Up', 'check-for-broken-links' ),
					'uptimeStatusDown'                  => esc_html__( 'Down', 'check-for-broken-links' ),
					'uptimeStatusPending'               => esc_html__( 'Checking', 'check-for-broken-links' ),
					'uptimeStatusPaused'                => esc_html__( 'Paused', 'check-for-broken-links' ),
					'uptimeColumns'                     => array(
						'name'         => esc_html__( 'Monitor', 'check-for-broken-links' ),
						'uptime_day'   => esc_html__( '24h uptime', 'check-for-broken-links' ),
						'uptime_month' => esc_html__( '30d uptime', 'check-for-broken-links' ),
						'avg_ms'       => esc_html__( 'Avg response', 'check-for-broken-links' ),
						'last_checked' => esc_html__( 'Last check', 'check-for-broken-links' ),
					),
					'uptimeActionEdit'                  => esc_html__( 'Edit', 'check-for-broken-links' ),
					'uptimeActionSave'                  => esc_html__( 'Save', 'check-for-broken-links' ),
					'uptimeActionCancel'                => esc_html__( 'Cancel', 'check-for-broken-links' ),
					'uptimeActionPause'                 => esc_html__( 'Pause', 'check-for-broken-links' ),
					'uptimeActionResume'                => esc_html__( 'Resume', 'check-for-broken-links' ),
					'uptimeActionDelete'                => esc_html__( 'Delete', 'check-for-broken-links' ),
					'uptimeDeleteTitle'                 => esc_html__( 'Delete monitor', 'check-for-broken-links' ),
					'uptimeDeleteConfirm'               => esc_html__( 'Delete this monitor? Its check history is gone too.', 'check-for-broken-links' ),
					'uptimeEditName'                    => esc_html__( 'Name', 'check-for-broken-links' ),
					'uptimeEditUrl'                     => esc_html__( 'URL', 'check-for-broken-links' ),
					'uptimeEditInterval'                => esc_html__( 'Check interval', 'check-for-broken-links' ),
					'uptimeEditAlertAfter'               => esc_html__( 'Alert after', 'check-for-broken-links' ),
					'uptimeEditAlertAfterSuffix'         => esc_html__( 'failed checks', 'check-for-broken-links' ),
					'uptimeEditAlertEmails'              => esc_html__( 'Alert emails', 'check-for-broken-links' ),
					'uptimeEditAlertEmailsPlaceholder'   => esc_html__( 'name@example.com, name2@example.com', 'check-for-broken-links' ),
					'uptimeEmptyTitle'                   => esc_html__( 'No monitors yet', 'check-for-broken-links' ),
					'uptimeEmptyMessage'                 => esc_html__( 'Add your site and know the moment it goes down.', 'check-for-broken-links' ),
					'uptimeHeartbeatTitle'               => esc_html__( 'Watch WP-Cron', 'check-for-broken-links' ),
					'uptimeHeartbeatMessage'             => esc_html__( 'Get an alert when scheduled WordPress tasks stop running.', 'check-for-broken-links' ),
					'uptimeHeartbeatButton'              => esc_html__( 'Watch WP-Cron', 'check-for-broken-links' ),
					'uptimeHeartbeatMonitorName'         => esc_html__( 'WP cron', 'check-for-broken-links' ),
					'uptimeSnippetIntro'                 => esc_html__( 'Paste this in a site-specific plugin or your theme functions.php file.', 'check-for-broken-links' ),
					'uptimeCopyLink'                     => esc_html__( 'Copy link', 'check-for-broken-links' ),
					'uptimeCopied'                       => esc_html__( 'Link copied.', 'check-for-broken-links' ),
					'uptimeIncidentsTitle'               => esc_html__( 'Recent incidents', 'check-for-broken-links' ),
					'uptimeIncidentsEmpty'               => esc_html__( 'No incidents recorded yet.', 'check-for-broken-links' ),
					'uptimeIncidentOngoing'              => esc_html__( 'Ongoing', 'check-for-broken-links' ),
					'uptimeIncidentsStarted'             => esc_html__( 'Started', 'check-for-broken-links' ),
					'uptimeIncidentsMonitor'             => esc_html__( 'Monitor', 'check-for-broken-links' ),
					'uptimeIncidentsCause'               => esc_html__( 'Cause', 'check-for-broken-links' ),
					'uptimeIncidentsDuration'            => esc_html__( 'Duration', 'check-for-broken-links' ),
					'uptimeIntervalSecond'               => esc_html__( 'second', 'check-for-broken-links' ),
					'uptimeIntervalSeconds'              => esc_html__( 'seconds', 'check-for-broken-links' ),
					'uptimeIntervalMinute'               => esc_html__( 'minute', 'check-for-broken-links' ),
					'uptimeIntervalMinutes'              => esc_html__( 'minutes', 'check-for-broken-links' ),
					'uptimeIntervalHour'                 => esc_html__( 'hour', 'check-for-broken-links' ),
					'uptimeIntervalHours'                => esc_html__( 'hours', 'check-for-broken-links' ),
					'uptimeIntervalDay'                  => esc_html__( 'day', 'check-for-broken-links' ),
					'uptimeIntervalDays'                 => esc_html__( 'days', 'check-for-broken-links' ),
					/* translators: %1$s: how long ago, for example "5 minutes". */
					'uptimeAgo'                          => esc_html__( '%1$s ago', 'check-for-broken-links' ),
					'uptimeJustNow'                      => esc_html__( 'Just now', 'check-for-broken-links' ),
					'uptimeNeverChecked'                 => esc_html__( 'Not checked yet', 'check-for-broken-links' ),
				)
			);

		}

		/**
		 * Enqueues admin styles.
		 *
		 * @since   1.0.0
		 *
		 * @return void
		 */
		public function styles() {
			$current_page = isset( $_GET ) && isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( empty( $current_page ) || ! wpcbl_str_starts_with( $current_page, 'wpcbl-check-for-broken-links' ) ) {
				return;
			}

			// Redesign stylesheet (replaces the legacy admin stylesheet).
			$style_rel  = 'assets/dist/css/admin/cbl-admin-redesign.css';
			$style_path = WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/' . $style_rel;
			$style_ver  = file_exists( $style_path ) ? filemtime( $style_path ) : WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION;

			wp_enqueue_style( 'dashicons' );
			wp_enqueue_style( 'wpcbl_check_for_broken_links_admin_styles', WPCBL_CHECK_BROKEN_LINKS_ROOT_URL . $style_rel, array(), $style_ver, 'all' );
		}
	}

	return new WPCBL_Check_Broken_Links_Admin_Assets();

endif;
