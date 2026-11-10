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

			// Shared by every page script below: where to post, and the fallback
			// error. Each script gets its own copy under its own global.
			$wpcbl_base = array(
				'ajaxUrl'      => esc_url( admin_url( 'admin-ajax.php' ) ),
				'nonce'        => wp_create_nonce( 'wpcbl_check_for_broken_links' ),
				'errorGeneric' => esc_html__( 'Something went wrong. Please try again.', 'check-for-broken-links' ),
			);

			// The main admin script: the scan flow, the results table actions, the
			// universal modal and the Settings re-check. Loaded only on the pages
			// that use one of those (3.1.7). Before that it loaded on every plugin
			// page, with the Uptime, SEO Audit and Plans code and strings inside.
			$wpcbl_core_pages = array(
				'wpcbl-check-for-broken-links',          // Dashboard: scan trigger and progress.
				'wpcbl-check-for-broken-links-scan',     // Results table and its row actions.
				'wpcbl-check-for-broken-links-settings', // Re-check all links.
				'wpcbl-check-for-broken-links-uptime',   // The universal modal.
			);

			if ( in_array( $current_page, $wpcbl_core_pages, true ) ) {
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
						// The Dashboard: where the scan trigger buttons live and
						// progress shows while a scan runs.
						'scanPageUrl'  => esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links' ) ),
						// Broken link scan: where the results table lives. A scan
						// started from the Dashboard (no table on that page) lands
						// here once it finishes.
						'scanResultsUrl' => esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-scan' ) ),
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
					)
				);
			}

			// Interval and "time ago" wording, used by both the Uptime Monitor and
			// the SEO / AEO Audit page scripts.
			$wpcbl_time_strings = array();
			if ( in_array( $current_page, array( 'wpcbl-check-for-broken-links-uptime', 'wpcbl-check-for-broken-links-seo-audit' ), true ) ) {
				$wpcbl_time_strings = array(
					'uptimeIntervalMinute'               => esc_html__( 'minute', 'check-for-broken-links' ),
					'uptimeIntervalMinutes'              => esc_html__( 'minutes', 'check-for-broken-links' ),
					'uptimeIntervalHour'                 => esc_html__( 'hour', 'check-for-broken-links' ),
					'uptimeIntervalHours'                => esc_html__( 'hours', 'check-for-broken-links' ),
					'uptimeIntervalDay'                  => esc_html__( 'day', 'check-for-broken-links' ),
					'uptimeIntervalDays'                 => esc_html__( 'days', 'check-for-broken-links' ),
					/* translators: %1$s: how long ago, for example "5 minutes". */
					'uptimeAgo'                          => esc_html__( '%1$s ago', 'check-for-broken-links' ),
					'uptimeJustNow'                      => esc_html__( 'Just now', 'check-for-broken-links' ),
				);
			}

			// Uptime Monitor: its own page script since 3.1.7. It reuses the
			// universal modal from the main admin script, hence the dependency.
			if ( 'wpcbl-check-for-broken-links-uptime' === $current_page ) {
				$wpcbl_page_rel  = 'assets/dist/js/admin/cbl-uptime.js';
				$wpcbl_page_path = WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/' . $wpcbl_page_rel;
				$wpcbl_page_ver  = file_exists( $wpcbl_page_path ) ? filemtime( $wpcbl_page_path ) : WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION;

				wp_enqueue_script( 'cbl-uptime', WPCBL_CHECK_BROKEN_LINKS_ROOT_URL . $wpcbl_page_rel, array( 'jquery', 'wpcbl_check_for_broken_links_admin_scripts' ), $wpcbl_page_ver, true );

				wp_localize_script(
					'cbl-uptime',
					'wpcblUptime',
					array_merge(
						$wpcbl_base,
						$wpcbl_time_strings,
						array(
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
							'uptimeNeverChecked'                 => esc_html__( 'Not checked yet', 'check-for-broken-links' ),
						)
					)
				);
			}

			// SEO / AEO Audit: its own self-contained page script since 3.1.7.
			if ( 'wpcbl-check-for-broken-links-seo-audit' === $current_page ) {
				$wpcbl_page_rel  = 'assets/dist/js/admin/cbl-seo-audit.js';
				$wpcbl_page_path = WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/' . $wpcbl_page_rel;
				$wpcbl_page_ver  = file_exists( $wpcbl_page_path ) ? filemtime( $wpcbl_page_path ) : WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION;

				wp_enqueue_script( 'cbl-seo-audit', WPCBL_CHECK_BROKEN_LINKS_ROOT_URL . $wpcbl_page_rel, array( 'jquery' ), $wpcbl_page_ver, true );

				wp_localize_script(
					'cbl-seo-audit',
					'wpcblAudit',
					array_merge(
						$wpcbl_base,
						$wpcbl_time_strings,
						array(
							'auditLoadError'   => esc_html__( 'Could not load your audit. brokenlinkchecker.io might be briefly unavailable.', 'check-for-broken-links' ),
							'auditRunning'     => esc_html__( 'Audit running', 'check-for-broken-links' ),
							'auditRunningHint' => esc_html__( 'Checking your pages now. Results appear here automatically when it finishes.', 'check-for-broken-links' ),
							'auditNone'        => esc_html__( 'No audits yet. Run the first one to see what to fix.', 'check-for-broken-links' ),
							'auditFailed'      => esc_html__( 'The last audit could not read any pages. Try again in a minute.', 'check-for-broken-links' ),
							'auditRunLabel'    => esc_html__( 'Run audit', 'check-for-broken-links' ),
							'auditRunningLabel' => esc_html__( 'Running', 'check-for-broken-links' ),
							'auditShareOn'     => esc_html__( 'Share report', 'check-for-broken-links' ),
							'auditShareOff'    => esc_html__( 'Stop sharing', 'check-for-broken-links' ),
							'auditCopy'        => esc_html__( 'Copy link', 'check-for-broken-links' ),
							'auditCopied'      => esc_html__( 'Copied', 'check-for-broken-links' ),
							'auditPdf'         => esc_html__( 'Download PDF', 'check-for-broken-links' ),
							'auditHistory'     => esc_html__( 'Audit history', 'check-for-broken-links' ),
							'auditUpgrade'     => esc_html__( 'See plans', 'check-for-broken-links' ),
							/* translators: 1: pages covered by the plan, 2: total pages the site publishes. */
							'auditPagesCapped' => esc_html__( 'Your plan covered %1$d of the %2$d pages this site publishes.', 'check-for-broken-links' ),
							'auditLever'       => esc_html__( 'Biggest lever', 'check-for-broken-links' ),
							'auditScoreLabel'  => esc_html__( 'Score', 'check-for-broken-links' ),
							'auditPerfectScore' => esc_html__( 'This site passes every weighted check in this audit.', 'check-for-broken-links' ),
							/* translators: 1: category label, 2: points lost to that category, 3: total points lost. */
							'auditLeverLine'   => esc_html__( '%1$s is costing you %2$s of the %3$s points you lost', 'check-for-broken-links' ),
							'auditPointsWent'  => esc_html__( 'Where the points went', 'check-for-broken-links' ),
							'auditPointsHint'  => esc_html__( 'Each block is that category\'s share of the 100-point score. Red is unearned.', 'check-for-broken-links' ),
							'auditQueue'       => esc_html__( 'Fix queue', 'check-for-broken-links' ),
							/* translators: 1: issue-type count phrase, for example "3 issue types". 2: pages phrase, for example "12 pages". */
							'auditQueueHint'   => esc_html__( 'Grouped by recoverable points · %1$s across %2$s', 'check-for-broken-links' ),
							/* translators: %1$s: recoverable points for this category. */
							'auditRecover'     => esc_html__( '+%1$s pts recoverable', 'check-for-broken-links' ),
							'auditIssueType'   => esc_html__( 'issue type', 'check-for-broken-links' ),
							'auditIssueTypes'  => esc_html__( 'issue types', 'check-for-broken-links' ),
							/* translators: %1$d: number of checks passed. */
							'auditChecksPassed' => esc_html__( '%1$d checks passed', 'check-for-broken-links' ),
							'auditShowAll'     => esc_html__( 'Show all', 'check-for-broken-links' ),
							'auditHide'        => esc_html__( 'Hide', 'check-for-broken-links' ),
							'auditLiveData'    => esc_html__( 'Authority and speed · live data', 'check-for-broken-links' ),
							'auditCopyUrls'    => esc_html__( 'Copy URLs', 'check-for-broken-links' ),
							'auditFixWithAi'   => esc_html__( 'Fix with AI', 'check-for-broken-links' ),
							'auditFixWorking'  => esc_html__( 'Working', 'check-for-broken-links' ),
							'auditFixReview'   => esc_html__( 'Review before anything is saved', 'check-for-broken-links' ),
							'auditFixNow'      => esc_html__( 'Now', 'check-for-broken-links' ),
							'auditFixNew'      => esc_html__( 'Suggested', 'check-for-broken-links' ),
							'auditFixApply'    => esc_html__( 'Apply', 'check-for-broken-links' ),
							'auditFixDismiss'  => esc_html__( 'Skip', 'check-for-broken-links' ),
							'auditFixApplied'  => esc_html__( 'Saved.', 'check-for-broken-links' ),
							/* translators: %1$s: number of pages in the audit. */
							'auditShowAllPages' => esc_html__( 'Show all %1$s', 'check-for-broken-links' ),
							'auditPagesSample' => esc_html__( 'This audit stored a sample. Run a new audit to capture every page.', 'check-for-broken-links' ),
							/* translators: %1$d: additional pages beyond the ones already listed. */
							'auditAndMore'     => esc_html__( 'and %1$d more', 'check-for-broken-links' ),
							'auditOf100'       => esc_html__( 'of 100', 'check-for-broken-links' ),
							'auditPassedLabel' => esc_html__( 'checks passed', 'check-for-broken-links' ),
							'auditRefDomains'  => esc_html__( 'Referring domains', 'check-for-broken-links' ),
							'auditBacklinks'   => esc_html__( 'Backlinks', 'check-for-broken-links' ),
							'auditDomainRank'  => esc_html__( 'Domain rank', 'check-for-broken-links' ),
							'auditKeywords'    => esc_html__( 'Ranking keywords', 'check-for-broken-links' ),
							'auditTraffic'     => esc_html__( 'Est. monthly visits', 'check-for-broken-links' ),
							'auditLighthouse'  => esc_html__( 'Lighthouse mobile', 'check-for-broken-links' ),
							'auditLcp'         => esc_html__( 'Largest paint', 'check-for-broken-links' ),
							'auditCls'         => esc_html__( 'Layout shift', 'check-for-broken-links' ),
							'auditQuotaUsed'   => esc_html__( 'audits used this month', 'check-for-broken-links' ),
							'auditPagesLabel'  => esc_html__( 'pages checked', 'check-for-broken-links' ),
							'auditLastAudit'   => esc_html__( 'last audit', 'check-for-broken-links' ),
							'auditPageUnit'    => esc_html__( 'page', 'check-for-broken-links' ),
							'auditPagesUnit'   => esc_html__( 'pages', 'check-for-broken-links' ),
							// Relative-time units the meta line's "last audit …" needs
							// beyond what the uptime block already localizes (minute/
							// hour/day, plus the shared "%1$s ago" / "Just now" -- an
							// audit can be months or years old, a monitor check cannot).
							'auditIntervalMonth'  => esc_html__( 'month', 'check-for-broken-links' ),
							'auditIntervalMonths' => esc_html__( 'months', 'check-for-broken-links' ),
							'auditIntervalYear'   => esc_html__( 'year', 'check-for-broken-links' ),
							'auditIntervalYears'  => esc_html__( 'years', 'check-for-broken-links' ),
							'upgradeUrl'       => esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-upgrade' ) ),
						)
					)
				);
			}

			// Plans & upgrades: its own self-contained page script since 3.1.7.
			if ( 'wpcbl-check-for-broken-links-upgrade' === $current_page ) {
				$wpcbl_page_rel  = 'assets/dist/js/admin/cbl-upgrade.js';
				$wpcbl_page_path = WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/' . $wpcbl_page_rel;
				$wpcbl_page_ver  = file_exists( $wpcbl_page_path ) ? filemtime( $wpcbl_page_path ) : WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION;

				wp_enqueue_script( 'cbl-upgrade', WPCBL_CHECK_BROKEN_LINKS_ROOT_URL . $wpcbl_page_rel, array( 'jquery' ), $wpcbl_page_ver, true );

				wp_localize_script(
					'cbl-upgrade',
					'wpcblUpgrade',
					array_merge(
						$wpcbl_base,
						array(
							/* translators: %1$s: plan name. */
							'bilChangeConfirm'                => esc_html__( 'Switch to the %1$s plan? Upgrades are charged prorated to your card right away, downgrades are credited to your next invoice.', 'check-for-broken-links' ),
							'bilWorking'                      => esc_html__( 'One moment…', 'check-for-broken-links' ),
						)
					)
				);
			}

			// Internal Link Optimizer: its own small self-contained script,
			// loaded only on its own screen, not folded into the shared
			// admin bundle above.
			if ( 'wpcbl-check-for-broken-links-internal-links' === $current_page ) {
				$ilo_rel  = 'assets/dist/js/admin/cbl-internal-links.js';
				$ilo_path = WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/' . $ilo_rel;
				$ilo_ver  = file_exists( $ilo_path ) ? filemtime( $ilo_path ) : WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION;

				wp_enqueue_script(
					'cbl-internal-links',
					WPCBL_CHECK_BROKEN_LINKS_ROOT_URL . $ilo_rel,
					array(),
					$ilo_ver,
					true
				);

				wp_localize_script(
					'cbl-internal-links',
					'wpcblIlo',
					array(
						'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
						// Same nonce action verify_link_action_request() checks
						// for every other link-action endpoint.
						'nonce'      => wp_create_nonce( 'wpcbl_check_for_broken_links' ),
						'upgradeUrl' => esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-upgrade' ) ),
						// Which post types the optimizer can read, and which
						// of them are currently selected. Purely local WP
						// data, so it ships in the page bootstrap rather than
						// riding the SaaS-proxied ilo_state response. Empty
						// 'selected' means every type is ticked -- that is
						// what an unset ilo_post_types option means.
						'postTypes'  => array(
							'available' => wpcbl_ilo_post_type_options(),
							'selected'  => (array) wpcbl_get_option( 'ilo_post_types', array() ),
						),
						'i18n'       => array(
							'generic'          => esc_html__( 'Something went wrong. Please try again.', 'check-for-broken-links' ),
							'loadError'        => esc_html__( 'Could not load your analysis. brokenlinkchecker.io might be briefly unavailable.', 'check-for-broken-links' ),
							'runLabel'         => esc_html__( 'Run analysis', 'check-for-broken-links' ),
							'runningLabel'     => esc_html__( 'Running', 'check-for-broken-links' ),
							/* translators: %1$s: number of pages uploaded so far. */
							'uploading'        => esc_html__( 'Uploading pages, %1$s so far', 'check-for-broken-links' ),
							/* translators: 1: pages analysed, 2: the plan's page cap. Shown only when the two differ. */
							'capLimited'       => esc_html__( 'This analysis covers %1$s pages of your site. Your plan reads up to %2$s pages in one analysis.', 'check-for-broken-links' ),
							/* translators: %1$s: pages analysed, equal to the plan's page cap. */
							'capLimitedSame'   => esc_html__( 'This analysis covers %1$s pages of your site, the most your plan reads in one analysis.', 'check-for-broken-links' ),
							'postTypesTitle'   => esc_html__( 'Post types to read', 'check-for-broken-links' ),
							'postTypesHint'    => esc_html__( 'These are the post types the optimizer reads when it maps your links.', 'check-for-broken-links' ),
							'postTypesSave'    => esc_html__( 'Save selection', 'check-for-broken-links' ),
							'postTypesSaved'   => esc_html__( 'Saved. This applies to your next analysis.', 'check-for-broken-links' ),
							'none'             => esc_html__( 'No analysis yet. Run one to see the internal links your pages are missing.', 'check-for-broken-links' ),
							'failed'           => esc_html__( 'The last analysis could not read any pages. Try again in a minute.', 'check-for-broken-links' ),
							'runningTitle'     => esc_html__( 'Analysis running', 'check-for-broken-links' ),
							'runningHint'      => esc_html__( 'Checking your pages now. Results appear here automatically when it finishes.', 'check-for-broken-links' ),
							/* translators: 1: suggestions used this month, 2: monthly limit. */
							'quotaUsed'        => esc_html__( '%1$s of %2$s suggestions used this month', 'check-for-broken-links' ),
							/* translators: %1$s: suggestions used this month. */
							'quotaUnlimited'   => esc_html__( '%1$s suggestions used this month', 'check-for-broken-links' ),
							'upgrade'          => esc_html__( 'See plans', 'check-for-broken-links' ),
							'tabSuggested'     => esc_html__( 'Suggested links', 'check-for-broken-links' ),
							'tabOrphan'        => esc_html__( 'No content links', 'check-for-broken-links' ),
							'tabDead'          => esc_html__( 'Dead ends', 'check-for-broken-links' ),
							'tabBuried'        => esc_html__( 'Buried pages', 'check-for-broken-links' ),
							'tabWeak'          => esc_html__( 'Weak anchors', 'check-for-broken-links' ),
							'tileAnalyzed'     => esc_html__( 'pages analyzed', 'check-for-broken-links' ),
							'tileLinksPerPage' => esc_html__( 'links per page', 'check-for-broken-links' ),
							'tileOrphan'       => esc_html__( 'pages with no content links', 'check-for-broken-links' ),
							'tileDead'         => esc_html__( 'dead ends', 'check-for-broken-links' ),
							'tileBuried'       => esc_html__( 'buried pages', 'check-for-broken-links' ),
							'tileWeak'         => esc_html__( 'weak anchors', 'check-for-broken-links' ),
							'emptySuggested'   => esc_html__( 'No new links to suggest. The internal linking on this site is already strong.', 'check-for-broken-links' ),
							'emptyOrphan'      => esc_html__( 'Every page gets at least one link from another page\'s content.', 'check-for-broken-links' ),
							'emptyDead'        => esc_html__( 'Every page sends the reader somewhere next.', 'check-for-broken-links' ),
							'emptyBuried'      => esc_html__( 'Every page sits within three clicks of the homepage.', 'check-for-broken-links' ),
							'emptyWeak'        => esc_html__( 'Every internal link describes where it goes.', 'check-for-broken-links' ),
							'colAnchor'        => esc_html__( 'Anchor text', 'check-for-broken-links' ),
							'colOnPage'        => esc_html__( 'On page', 'check-for-broken-links' ),
							'colLinksTo'       => esc_html__( 'Links to', 'check-for-broken-links' ),
							'anchorLabel'      => esc_html__( 'Anchor', 'check-for-broken-links' ),
							'nowLabel'         => esc_html__( 'Now', 'check-for-broken-links' ),
							'afterLabel'       => esc_html__( 'After', 'check-for-broken-links' ),
							'approve'          => esc_html__( 'Approve', 'check-for-broken-links' ),
							'skip'             => esc_html__( 'Skip', 'check-for-broken-links' ),
							'apply'            => esc_html__( 'Apply', 'check-for-broken-links' ),
							'undo'             => esc_html__( 'Undo', 'check-for-broken-links' ),
							'applyAll'         => esc_html__( 'Apply all approved', 'check-for-broken-links' ),
							'dashboardLink'    => esc_html__( 'Open on dashboard', 'check-for-broken-links' ),
							'statusPending'    => esc_html__( 'Pending', 'check-for-broken-links' ),
							'statusApproved'   => esc_html__( 'Approved', 'check-for-broken-links' ),
							'statusSkipped'    => esc_html__( 'Skipped', 'check-for-broken-links' ),
							'statusApplied'    => esc_html__( 'Applied', 'check-for-broken-links' ),
						),
					)
				);
			}

			// Rank Tracker (3.1.3 layout): its own page script and stylesheet,
			// same pattern as the AI Visibility Tracker below.
			if ( 'wpcbl-check-for-broken-links-rank-tracker' === $current_page ) {
				$rt_js      = 'assets/dist/js/admin/cbl-rank-tracker.js';
				$rt_css     = 'assets/dist/css/admin/cbl-rank-tracker.css';
				$rt_js_ver  = file_exists( WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/' . $rt_js ) ? filemtime( WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/' . $rt_js ) : WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION;
				$rt_css_ver = file_exists( WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/' . $rt_css ) ? filemtime( WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/' . $rt_css ) : WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION;

				wp_enqueue_style( 'cbl-rank-tracker', WPCBL_CHECK_BROKEN_LINKS_ROOT_URL . $rt_css, array( 'wpcbl_check_for_broken_links_admin_styles' ), $rt_css_ver, 'all' );
				wp_enqueue_script( 'cbl-rank-tracker', WPCBL_CHECK_BROKEN_LINKS_ROOT_URL . $rt_js, array(), $rt_js_ver, true );

				// First-run ideas: the site's own name and main page titles,
				// the searches a site owner is most likely to care about.
				$rt_ideas = array();
				$rt_skip  = array( 'home', 'blog', 'contact', 'contact us', 'about', 'about us', 'privacy policy', 'cookie policy', 'terms', 'cart', 'checkout', 'my account', 'shop', 'sample page' );
				$rt_name  = strtolower( trim( wp_strip_all_tags( get_bloginfo( 'name' ) ) ) );
				if ( '' !== $rt_name ) {
					$rt_ideas[] = $rt_name;
				}
				$rt_pages = get_posts(
					array(
						'post_type'      => 'page',
						'post_status'    => 'publish',
						'posts_per_page' => 12,
						'orderby'        => 'menu_order title',
						'order'          => 'ASC',
						'fields'         => 'ids',
					)
				);
				foreach ( $rt_pages as $rt_page_id ) {
					$rt_title = strtolower( trim( wp_strip_all_tags( get_the_title( $rt_page_id ) ) ) );
					if ( '' === $rt_title || strlen( $rt_title ) > 40 || in_array( $rt_title, $rt_skip, true ) || in_array( $rt_title, $rt_ideas, true ) ) {
						continue;
					}
					$rt_ideas[] = $rt_title;
					if ( count( $rt_ideas ) >= 5 ) {
						break;
					}
				}

				wp_localize_script(
					'cbl-rank-tracker',
					'wpcblRank',
					array(
						'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
						'nonce'       => wp_create_nonce( 'wpcbl_check_for_broken_links' ),
						'settingsUrl' => esc_url( add_query_arg( 'view', 'settings', admin_url( 'admin.php?page=wpcbl-check-for-broken-links-rank-tracker' ) ) ),
						'upgradeUrl'  => esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-upgrade' ) ),
						'flagsBase'   => esc_url( WPCBL_CHECK_BROKEN_LINKS_ROOT_URL . 'assets/dist/images/admin/flags/' ),
						'ideas'       => array_map( 'esc_html', $rt_ideas ),
						'i18n'        => array(
							'loadError'       => esc_html__( 'Could not load rankings. brokenlinkchecker.io might be briefly unavailable.', 'check-for-broken-links' ),
							'generic'         => esc_html__( 'Something went wrong. Please try again.', 'check-for-broken-links' ),
							'google'          => esc_html__( 'Google', 'check-for-broken-links' ),
							'bing'            => esc_html__( 'Bing', 'check-for-broken-links' ),
							'both'            => esc_html__( 'Google and Bing', 'check-for-broken-links' ),
							'bothShort'       => esc_html__( 'Both', 'check-for-broken-links' ),
							'desktop'         => esc_html__( 'Desktop', 'check-for-broken-links' ),
							'mobile'          => esc_html__( 'Mobile', 'check-for-broken-links' ),
							'deviceBoth'      => esc_html__( 'Desktop and mobile', 'check-for-broken-links' ),
							'change'          => esc_html__( 'Change', 'check-for-broken-links' ),
							// First run.
							'firstTitle'      => esc_html__( 'Track your first keywords', 'check-for-broken-links' ),
							'firstLead'       => esc_html__( 'Add the searches you want to rank for. First positions arrive within minutes.', 'check-for-broken-links' ),
							'kwPlaceholder'   => esc_html__( 'One keyword per line, or separate with commas', 'check-for-broken-links' ),
							'ideasTitle'      => esc_html__( 'Ideas to start with', 'check-for-broken-links' ),
							'startTracking'   => esc_html__( 'Start tracking', 'check-for-broken-links' ),
							/* translators: %s: number of keywords. */
							'startTrackingN'  => esc_html__( 'Start tracking %s keywords', 'check-for-broken-links' ),
							'startTracking1'  => esc_html__( 'Start tracking 1 keyword', 'check-for-broken-links' ),
							'adding'          => esc_html__( 'Adding…', 'check-for-broken-links' ),
							'weekly'          => esc_html__( 'Positions update weekly.', 'check-for-broken-links' ),
							'daily'           => esc_html__( 'Positions update every morning.', 'check-for-broken-links' ),
							/* translators: %s: number of free keyword slots. */
							'slotsAvailable'  => esc_html__( '%s keyword slots available. Each keyword uses 1 slot per engine and device.', 'check-for-broken-links' ),
							/* translators: 1: slots this addition uses, 2: free slots. */
							'slotsUses'       => esc_html__( 'Uses %1$s of %2$s available keyword slots', 'check-for-broken-links' ),
							/* translators: 1: slots this addition needs, 2: free slots. */
							'slotsNeeds'      => esc_html__( 'Needs %1$s slots, only %2$s available', 'check-for-broken-links' ),
							'previewTitle'    => esc_html__( 'What you will see', 'check-for-broken-links' ),
							'previewKw1'      => esc_html__( 'your main keyword', 'check-for-broken-links' ),
							'previewKw2'      => esc_html__( 'product + city', 'check-for-broken-links' ),
							'previewKw3'      => esc_html__( 'how to …', 'check-for-broken-links' ),
							'trend'           => esc_html__( 'Trend', 'check-for-broken-links' ),
							'keyword'         => esc_html__( 'Keyword', 'check-for-broken-links' ),
							'point1'          => esc_html__( 'Desktop and mobile positions side by side', 'check-for-broken-links' ),
							'point2'          => esc_html__( 'Weekly changes, best position and trend', 'check-for-broken-links' ),
							'point3'          => esc_html__( 'Search volume, CPC and intent for each keyword', 'check-for-broken-links' ),
							// Results.
							/* translators: 1: slots used, 2: slot limit. */
							'slotsUsed'       => esc_html__( '%1$s of %2$s keyword slots used', 'check-for-broken-links' ),
							'getMore'         => esc_html__( 'Get more', 'check-for-broken-links' ),
							'getMoreKeywords' => esc_html__( 'Get more keywords', 'check-for-broken-links' ),
							/* translators: 1: date of the last check, 2: date of the next check. */
							'updatedNext'     => esc_html__( 'Updated %1$s · next update %2$s', 'check-for-broken-links' ),
							/* translators: %s: date of the next check. */
							'firstUpdate'     => esc_html__( 'First update %s', 'check-for-broken-links' ),
							'refreshNow'      => esc_html__( 'Refresh now', 'check-for-broken-links' ),
							'refreshing'      => esc_html__( 'Checking…', 'check-for-broken-links' ),
							/* translators: 1: refreshes left, 2: weekly refresh allowance. */
							'refreshesLeft'   => esc_html__( '%1$s of %2$s refreshes left this week', 'check-for-broken-links' ),
							'pubTitle'        => esc_html__( 'Public report', 'check-for-broken-links' ),
							'pubOffHint'      => esc_html__( 'Share read-only rankings with clients or your team. No login needed.', 'check-for-broken-links' ),
							'pubOnHint'       => esc_html__( 'Anyone with the link can view rankings. Updates automatically.', 'check-for-broken-links' ),
							'pubOnPwHint'     => esc_html__( 'Anyone with the link and the password can view rankings. Updates automatically.', 'check-for-broken-links' ),
							'generate'        => esc_html__( 'Generate public URL', 'check-for-broken-links' ),
							'generating'      => esc_html__( 'Generating…', 'check-for-broken-links' ),
							'copy'            => esc_html__( 'Copy', 'check-for-broken-links' ),
							'copied'          => esc_html__( 'Copied', 'check-for-broken-links' ),
							'open'            => esc_html__( 'Open', 'check-for-broken-links' ),
							'disableLink'     => esc_html__( 'Turn off the public link', 'check-for-broken-links' ),
							'visibility'      => esc_html__( 'Visibility', 'check-for-broken-links' ),
							'traffic'         => esc_html__( 'Estimated traffic', 'check-for-broken-links' ),
							'avgPosition'     => esc_html__( 'Average position', 'check-for-broken-links' ),
							/* translators: %s: change in percentage points. */
							'pts'             => esc_html__( '%s pts', 'check-for-broken-links' ),
							'perMonth'        => esc_html__( '/mo', 'check-for-broken-links' ),
							'filter'          => esc_html__( 'Filter keywords', 'check-for-broken-links' ),
							/* translators: %s: number of selected keywords. */
							'selected'        => esc_html__( '%s selected', 'check-for-broken-links' ),
							'stopTracking'    => esc_html__( 'Stop tracking', 'check-for-broken-links' ),
							'stopConfirm'     => esc_html__( 'Stop tracking the selected keywords? Their ranking history is deleted.', 'check-for-broken-links' ),
							'cancel'          => esc_html__( 'Cancel', 'check-for-broken-links' ),
							'selectAll'       => esc_html__( 'Select all keywords', 'check-for-broken-links' ),
							/* translators: %s: keyword. */
							'selectRow'       => esc_html__( 'Select %s', 'check-for-broken-links' ),
							'best'            => esc_html__( 'Best', 'check-for-broken-links' ),
							'weeks8'          => esc_html__( '8 weeks', 'check-for-broken-links' ),
							'volume'          => esc_html__( 'Volume', 'check-for-broken-links' ),
							'cpc'             => esc_html__( 'CPC', 'check-for-broken-links' ),
							'intent'          => esc_html__( 'Intent', 'check-for-broken-links' ),
							'rankingPage'     => esc_html__( 'Ranking page', 'check-for-broken-links' ),
							'notTracked'      => esc_html__( 'Not tracked on this device', 'check-for-broken-links' ),
							'notRanked'       => esc_html__( 'Not in the top 100', 'check-for-broken-links' ),
							'checking'        => esc_html__( 'Checking', 'check-for-broken-links' ),
							'pendingNote'     => esc_html__( 'New keywords are being checked. Positions appear here in a few minutes.', 'check-for-broken-links' ),
							/* translators: %s: the filter text. */
							'noMatch'         => esc_html__( 'No keywords match "%s".', 'check-for-broken-links' ),
							'dailyTitle'      => esc_html__( 'Daily ranking updates', 'check-for-broken-links' ),
							'dailyText'       => esc_html__( 'Positions update weekly on your plan. The Daily add-on re-checks every keyword every morning.', 'check-for-broken-links' ),
							'dailyButton'     => esc_html__( 'Get daily updates', 'check-for-broken-links' ),
							// Add modal.
							'addTitle'        => esc_html__( 'Add keywords', 'check-for-broken-links' ),
							'addLead'         => esc_html__( 'One keyword per line, or separate them with commas.', 'check-for-broken-links' ),
							'close'           => esc_html__( 'Close', 'check-for-broken-links' ),
							'dupes'           => esc_html__( 'Duplicates are removed automatically.', 'check-for-broken-links' ),
							/* translators: %s: number of keywords. */
							'detected'        => esc_html__( '%s keywords detected', 'check-for-broken-links' ),
							'detected1'       => esc_html__( '1 keyword detected', 'check-for-broken-links' ),
							'location'        => esc_html__( 'Location and language', 'check-for-broken-links' ),
							'engine'          => esc_html__( 'Search engine', 'check-for-broken-links' ),
							'engineHint'      => esc_html__( 'Set for the whole tracker in Settings.', 'check-for-broken-links' ),
							'device'          => esc_html__( 'Device', 'check-for-broken-links' ),
							/* translators: 1: keywords, 2: engines, 3: devices. */
							'slotMath'        => esc_html__( '%1$s × %2$s × %3$s', 'check-for-broken-links' ),
							'kwUnit'          => esc_html__( 'keyword', 'check-for-broken-links' ),
							'kwUnits'         => esc_html__( 'keywords', 'check-for-broken-links' ),
							'engineUnit'      => esc_html__( 'engine', 'check-for-broken-links' ),
							'engineUnits'     => esc_html__( 'engines', 'check-for-broken-links' ),
							'deviceUnit'      => esc_html__( 'device', 'check-for-broken-links' ),
							'deviceUnits'     => esc_html__( 'devices', 'check-for-broken-links' ),
							/* translators: 1: slots in use, 2: slot limit, 3: slots left after adding. */
							'slotsAfter'      => esc_html__( '%1$s of %2$s already in use. %3$s left after adding.', 'check-for-broken-links' ),
							'slotsOverFoot'   => esc_html__( 'Remove keywords, pick one device, or get more slots.', 'check-for-broken-links' ),
							/* translators: 1: engine name, 2: device name. */
							'onEngineDevice'  => esc_html__( 'On %1$s, %2$s', 'check-for-broken-links' ),
							/* translators: 1: number of keywords, 2: engine name, 3: device name. */
							'nOnEngineDevice' => esc_html__( '%1$s keywords on %2$s, %3$s', 'check-for-broken-links' ),
							/* translators: 1: engine name, 2: device name. */
							'oneOnEngineDevice' => esc_html__( '1 keyword on %1$s, %2$s', 'check-for-broken-links' ),
							// Settings.
							'tracking'        => esc_html__( 'Tracking', 'check-for-broken-links' ),
							'engineLead'      => esc_html__( 'Where rankings are checked.', 'check-for-broken-links' ),
							'googleHint'      => esc_html__( 'Most search traffic', 'check-for-broken-links' ),
							'bingHint'        => esc_html__( 'Also powers ChatGPT search', 'check-for-broken-links' ),
							'bothHint'        => esc_html__( 'Compare both side by side', 'check-for-broken-links' ),
							'defaultDevice'   => esc_html__( 'Default device', 'check-for-broken-links' ),
							'deviceLead'      => esc_html__( 'Rankings can differ between desktop and mobile results. New keywords use this device.', 'check-for-broken-links' ),
							'defaultLocation' => esc_html__( 'Default location and language', 'check-for-broken-links' ),
							'locationLead'    => esc_html__( 'The country and language rankings are checked in. New keywords use this location. Keywords you already track keep theirs.', 'check-for-broken-links' ),
							'emailReports'    => esc_html__( 'Email reports', 'check-for-broken-links' ),
							'emailLead'       => esc_html__( 'Get ranking changes by email after each update.', 'check-for-broken-links' ),
							'sendTo'          => esc_html__( 'Send to', 'check-for-broken-links' ),
							'emailAdd'        => esc_html__( 'Add another email', 'check-for-broken-links' ),
							'emailFirst'      => esc_html__( 'name@example.com', 'check-for-broken-links' ),
							'emailHint'       => esc_html__( 'Press Enter or comma to add each address.', 'check-for-broken-links' ),
							'emailBad'        => esc_html__( 'That is not a valid email address.', 'check-for-broken-links' ),
							/* translators: %s: email address. */
							'emailRemove'     => esc_html__( 'Remove %s', 'check-for-broken-links' ),
							'pwTitle'         => esc_html__( 'Public report password', 'check-for-broken-links' ),
							'pwLead'          => esc_html__( 'Optional. Visitors to the public link must enter it first.', 'check-for-broken-links' ),
							'pwIsSet'         => esc_html__( 'A password is set. Enter a new one to replace it.', 'check-for-broken-links' ),
							'pwNeedsLink'     => esc_html__( 'Generate a public URL on the tracker first.', 'check-for-broken-links' ),
							'pwSave'          => esc_html__( 'Set password', 'check-for-broken-links' ),
							'pwPlaceholder'   => esc_html__( 'At least 4 characters', 'check-for-broken-links' ),
							'pwSaved'         => esc_html__( 'Password set.', 'check-for-broken-links' ),
						),
					)
				);
			}

			// Premium Guest Posts: its own page script, same pattern as the
			// trackers. Packages ride along from the cached catalog, so the
			// script prices the summary without a round trip.
			if ( 'wpcbl-check-for-broken-links-guest-posts' === $current_page ) {
				$gp_connect = wpcbl_connect();
				$gp_catalog = $gp_connect ? $gp_connect->fetch_guest_posts_catalog() : null;

				if ( $gp_catalog ) {
					$gp_rel  = 'assets/dist/js/admin/cbl-guest-posts.js';
					$gp_path = WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/' . $gp_rel;
					$gp_ver  = file_exists( $gp_path ) ? filemtime( $gp_path ) : WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION;

					wp_enqueue_script( 'cbl-guest-posts', WPCBL_CHECK_BROKEN_LINKS_ROOT_URL . $gp_rel, array(), $gp_ver, true );

					$gp_default = $gp_catalog['packages'][0]['key'];
					foreach ( $gp_catalog['packages'] as $gp_package ) {
						if ( $gp_package['popular'] ) {
							$gp_default = $gp_package['key'];
							break;
						}
					}

					// One-time markers on the way back from Stripe or from
					// connect. They only choose a message and are never
					// trusted: a payment is confirmed against Stripe by
					// brokenlinkchecker.io, not by this query string.
					// phpcs:disable WordPress.Security.NonceVerification.Recommended
					$gp_flash   = isset( $_GET['wpcbl_gp'] ) ? sanitize_key( wp_unslash( $_GET['wpcbl_gp'] ) ) : '';
					$gp_session = isset( $_GET['wpcbl_gp_session'] ) ? sanitize_text_field( wp_unslash( $_GET['wpcbl_gp_session'] ) ) : '';
					// phpcs:enable WordPress.Security.NonceVerification.Recommended

					wp_localize_script(
						'cbl-guest-posts',
						'wpcblGp',
						array(
							'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
							'nonce'      => wp_create_nonce( 'wpcbl_check_for_broken_links' ),
							'connected'  => $gp_connect->is_connected(),
							// Raw, not esc_url(): the script assigns it to
							// location.href, where &#038; would break the query.
							'connectUrl' => add_query_arg( 'next', 'guest-posts', wp_nonce_url( admin_url( 'admin-post.php?action=wpcbl_connect_start' ), 'wpcbl_connect_start' ) ),
							'pageUrl'    => $gp_connect->guest_posts_url(),
							'packages'   => $gp_catalog['packages'],
							'defaultKey' => $gp_default,
							'flash'      => in_array( $gp_flash, array( 'paid', 'cancelled', 'connected' ), true ) ? $gp_flash : '',
							'session'    => preg_match( '/^cs_\w+$/', $gp_session ) ? $gp_session : '',
							'i18n'       => array(
								'generic'            => esc_html__( 'Something went wrong. Please try again.', 'check-for-broken-links' ),
								'targetUrl'          => esc_html__( 'Target URL', 'check-for-broken-links' ),
								'targetKeyword'      => esc_html__( 'Target keyword', 'check-for-broken-links' ),
								/* translators: %s: row number. */
								'targetUrlN'         => esc_html__( 'Target URL %s', 'check-for-broken-links' ),
								/* translators: %s: row number. */
								'targetKeywordN'     => esc_html__( 'Target keyword %s', 'check-for-broken-links' ),
								/* translators: %s: row number. */
								'removeTargetN'      => esc_html__( 'Remove target %s', 'check-for-broken-links' ),
								'urlPlaceholder'     => esc_html__( 'https://yoursite.com/page', 'check-for-broken-links' ),
								'keywordPlaceholder' => esc_html__( 'e.g. broken link checker', 'check-for-broken-links' ),
								'urlInvalid'         => esc_html__( 'Enter a full address that starts with https://', 'check-for-broken-links' ),
								/* translators: 1: package name, 2: number of guest posts. */
								'pickedMeta'         => esc_html__( '%1$s · %2$s posts', 'check-for-broken-links' ),
								/* translators: %s: number of guest posts. */
								'postsWords'         => esc_html__( '%s × 1,000+ words', 'check-for-broken-links' ),
								/* translators: 1: delivery time in days, 2: delivery date, e.g. Nov 14. */
								'deliveryBy'         => esc_html__( '%1$s days · by %2$s', 'check-for-broken-links' ),
								/* translators: %s: order total, e.g. $249. */
								'placeOrder'         => esc_html__( 'Place order · %s', 'check-for-broken-links' ),
								/* translators: %s: order total, e.g. $249. */
								'connectAndOrder'    => esc_html__( 'Connect site and order · %s', 'check-for-broken-links' ),
								'opening'            => esc_html__( 'Opening secure checkout…', 'check-for-broken-links' ),
								'hintEmpty'          => esc_html__( 'Enter a target URL to continue.', 'check-for-broken-links' ),
								'hintReady'          => esc_html__( 'Charged today. Refund in full if we miss the deadline.', 'check-for-broken-links' ),
								'hintConnect'        => esc_html__( 'Connecting is free and takes a minute. Checkout opens right after.', 'check-for-broken-links' ),
								'paid'               => esc_html__( 'Order confirmed. We start on it today and report every live URL here.', 'check-for-broken-links' ),
								'paidPending'        => esc_html__( 'Stripe is still confirming your payment. Your order appears here within a few minutes.', 'check-for-broken-links' ),
								'cancelled'          => esc_html__( 'Checkout cancelled. Nothing was charged.', 'check-for-broken-links' ),
								'connected'          => esc_html__( 'Site connected. Place your order below.', 'check-for-broken-links' ),
								'connectedOpening'   => esc_html__( 'Site connected. Opening secure checkout for your order.', 'check-for-broken-links' ),
								/* translators: %s: due date, e.g. Nov 14. */
								'due'                => esc_html__( 'Due %s', 'check-for-broken-links' ),
								/* translators: %s: number of guest posts. */
								'postsCount'         => esc_html__( '%s posts', 'check-for-broken-links' ),
								/* translators: %s: address of the published guest post. */
								'liveAt'             => esc_html__( 'Live: %s', 'check-for-broken-links' ),
								'status'             => array(
									'paid'        => esc_html__( 'Paid', 'check-for-broken-links' ),
									'in_progress' => esc_html__( 'In progress', 'check-for-broken-links' ),
									'delivered'   => esc_html__( 'Delivered', 'check-for-broken-links' ),
									'refunded'    => esc_html__( 'Refunded', 'check-for-broken-links' ),
								),
							),
						)
					);
				}
			}

			// AI Visibility Tracker: its own small self-contained script,
			// same pattern as the Internal Link Optimizer above.
			if ( 'wpcbl-check-for-broken-links-ai-visibility' === $current_page ) {
				$aiv_rel  = 'assets/dist/js/admin/cbl-ai-visibility.js';
				$aiv_path = WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/' . $aiv_rel;
				$aiv_ver  = file_exists( $aiv_path ) ? filemtime( $aiv_path ) : WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION;

				wp_enqueue_script(
					'cbl-ai-visibility',
					WPCBL_CHECK_BROKEN_LINKS_ROOT_URL . $aiv_rel,
					array(),
					$aiv_ver,
					true
				);

				wp_localize_script(
					'cbl-ai-visibility',
					'wpcblAiv',
					array(
						'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
						// Same nonce action verify_link_action_request() checks
						// for every other link-action endpoint.
						'nonce'       => wp_create_nonce( 'wpcbl_check_for_broken_links' ),
						'upgradeUrl'  => esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-upgrade' ) ),
						// "Try:" example prompts under the add-prompt box. Static
						// copy, not API data, so plain esc_html__() strings.
						'suggestions' => array(
							esc_html__( 'best broken link checker for WordPress', 'check-for-broken-links' ),
							esc_html__( 'how to fix 404 errors in WordPress', 'check-for-broken-links' ),
							esc_html__( 'free WordPress SEO audit tool', 'check-for-broken-links' ),
						),
						'i18n'        => array(
							'generic'            => esc_html__( 'Something went wrong. Please try again.', 'check-for-broken-links' ),
							'loadError'          => esc_html__( 'Could not load your AI visibility data. brokenlinkchecker.io might be briefly unavailable.', 'check-for-broken-links' ),
							/* translators: 1: AI Visibility credits used this month, 2: monthly credit allowance. */
							'quotaUsed'          => esc_html__( '%1$s of %2$s prompt credits used', 'check-for-broken-links' ),
							'upgrade'            => esc_html__( 'See plans', 'check-for-broken-links' ),
							'runLabel'           => esc_html__( 'Run check now', 'check-for-broken-links' ),
							'runChecking'        => esc_html__( 'Checking now', 'check-for-broken-links' ),
							'overviewTitle'      => esc_html__( 'Brand mentions overview', 'check-for-broken-links' ),
							'overviewSubPending' => esc_html__( 'Not measured yet. The first reading arrives within the window below.', 'check-for-broken-links' ),
							'overviewSubResults' => esc_html__( 'Your most recent weekly check across ChatGPT, Perplexity and Google AI.', 'check-for-broken-links' ),
							/* translators: %1$s: number of days until the first reading, from the account's cadence. */
							'none'               => esc_html__( 'Not measured yet. The first reading arrives within %1$s days.', 'check-for-broken-links' ),
							'mentionsLabel'      => esc_html__( 'Mentions', 'check-for-broken-links' ),
							'noPrevious'         => esc_html__( 'No earlier reading to compare yet.', 'check-for-broken-links' ),
							/* translators: %1$s: increase in AI mentions since the previous reading. */
							'up'                 => esc_html__( 'Up %1$s since the last reading.', 'check-for-broken-links' ),
							/* translators: %1$s: decrease in AI mentions since the previous reading. */
							'down'               => esc_html__( 'Down %1$s since the last reading.', 'check-for-broken-links' ),
							'noChange'           => esc_html__( 'No change since the last reading.', 'check-for-broken-links' ),
							'sourcesTitle'       => esc_html__( 'Cited alongside you', 'check-for-broken-links' ),
							'sourcesPaidOnly'    => esc_html__( 'The competitor list comes with a paid plan.', 'check-for-broken-links' ),
							'sourcesEmpty'       => esc_html__( 'No other domains cited in this reading.', 'check-for-broken-links' ),
							/* translators: 1: competitor domain, 2: mentions for that domain. */
							'sourceItem'         => esc_html__( '%1$s, %2$s mentions', 'check-for-broken-links' ),
							// Stat cards.
							'scoreTitle'         => esc_html__( 'Visibility score', 'check-for-broken-links' ),
							'scoreNote'          => esc_html__( 'Share of tracked prompts that cite you', 'check-for-broken-links' ),
							'scoreNoneNote'      => esc_html__( 'Available once a prompt has an answer', 'check-for-broken-links' ),
							'mentionsTitle'      => esc_html__( 'Brand mentions', 'check-for-broken-links' ),
							'mentionsNoneNote'   => esc_html__( 'Not measured yet', 'check-for-broken-links' ),
							'nextCheckTitle'     => esc_html__( 'Next check', 'check-for-broken-links' ),
							'nextDaysUnit'       => esc_html__( 'days', 'check-for-broken-links' ),
							'nextDueNow'         => esc_html__( 'Due', 'check-for-broken-links' ),
							'nextDueNote'        => esc_html__( 'Expected any time now', 'check-for-broken-links' ),
							/* translators: %1$s: number of days between checks (the account's cadence). */
							'nextRunsNote'       => esc_html__( 'Checked every %1$s days', 'check-for-broken-links' ),
							// Onboarding steps (awaiting first check).
							'stepPromptTitle'    => esc_html__( 'Prompt added', 'check-for-broken-links' ),
							/* translators: 1: prompts currently tracked, 2: the account's prompt credit limit. */
							'stepPromptDesc'     => esc_html__( 'You are tracking %1$s of %2$s prompts.', 'check-for-broken-links' ),
							'stepQueuedTitle'    => esc_html__( 'First check queued', 'check-for-broken-links' ),
							'stepQueuedDesc'     => esc_html__( 'Each prompt is asked on all three assistants.', 'check-for-broken-links' ),
							/* translators: %1$s: number of days until the first reading, from the account's cadence. */
							'stepResultsTitle'   => esc_html__( 'Results within %1$s days', 'check-for-broken-links' ),
							'stepResultsDesc'    => esc_html__( 'Results appear on this page when they land.', 'check-for-broken-links' ),
							// Charts.
							'weeklyChartTitle'   => esc_html__( 'Mentions per weekly check', 'check-for-broken-links' ),
							'weeklyChartEmpty'   => esc_html__( 'No readings yet. Your first weekly check will appear here.', 'check-for-broken-links' ),
							'weeklyChartOnePoint' => esc_html__( 'One reading so far. A trend line appears after the next weekly check.', 'check-for-broken-links' ),
							'engineChartTitle'   => esc_html__( 'Mention rate by assistant', 'check-for-broken-links' ),
							'engineRateNone'     => esc_html__( 'Not measured yet', 'check-for-broken-links' ),
							// Tracked prompts.
							'promptsTitle'       => esc_html__( 'Tracked prompts', 'check-for-broken-links' ),
							'promptsHint'        => esc_html__( 'The questions your customers ask AI. One prompt per line, each line uses one credit.', 'check-for-broken-links' ),
							'promptsEmpty'       => esc_html__( 'No prompts yet. Add the questions your customers ask AI to see if you come up.', 'check-for-broken-links' ),
							'promptsFooter'      => esc_html__( 'Data refreshes weekly. Results reflect answers at check time and can vary between runs.', 'check-for-broken-links' ),
							'suggestionsLabel'   => esc_html__( 'Try:', 'check-for-broken-links' ),
							'addPlaceholder'     => esc_html__( 'One prompt per line, for example: best broken link checker for WordPress', 'check-for-broken-links' ),
							'addButton'          => esc_html__( 'Track prompts', 'check-for-broken-links' ),
							'addEmpty'           => esc_html__( 'Enter at least one prompt.', 'check-for-broken-links' ),
							/* translators: %1$s: prompt credits left after the ones already tracked. */
							'creditsLeft'        => esc_html__( '%1$s credits left', 'check-for-broken-links' ),
							'colPrompt'          => esc_html__( 'Prompt', 'check-for-broken-links' ),
							'colChatgpt'         => esc_html__( 'ChatGPT', 'check-for-broken-links' ),
							'colPerplexity'      => esc_html__( 'Perplexity', 'check-for-broken-links' ),
							'colGoogleAi'        => esc_html__( 'Google AI', 'check-for-broken-links' ),
							'colVisibility'      => esc_html__( 'Visibility', 'check-for-broken-links' ),
							'colChecked'         => esc_html__( 'Checked', 'check-for-broken-links' ),
							'cited'              => esc_html__( 'Cited', 'check-for-broken-links' ),
							/* translators: %1$s: citation position number. */
							'citedWithPosition'  => esc_html__( 'Cited #%1$s', 'check-for-broken-links' ),
							'absent'             => esc_html__( 'Absent', 'check-for-broken-links' ),
							'pending'            => esc_html__( 'Pending', 'check-for-broken-links' ),
							'notMeasured'        => esc_html__( 'Not measured yet', 'check-for-broken-links' ),
							'neverChecked'       => esc_html__( 'Not yet checked', 'check-for-broken-links' ),
							'remove'             => esc_html__( 'Remove', 'check-for-broken-links' ),
						),
					)
				);
			}
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

			if ( 'wpcbl-check-for-broken-links-settings' === $current_page ) {
				$settings_rel = 'assets/dist/css/admin/cbl-settings.css';
				$settings_abs = WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/' . $settings_rel;
				$settings_ver = file_exists( $settings_abs ) ? filemtime( $settings_abs ) : WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION;

				wp_enqueue_style( 'wpcbl_check_for_broken_links_settings', WPCBL_CHECK_BROKEN_LINKS_ROOT_URL . $settings_rel, array( 'wpcbl_check_for_broken_links_admin_styles' ), $settings_ver, 'all' );
			}

			if ( 'wpcbl-check-for-broken-links' === $current_page ) {
				$dashboard_rel = 'assets/dist/css/admin/cbl-dashboard.css';
				$dashboard_abs = WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/' . $dashboard_rel;
				$dashboard_ver = file_exists( $dashboard_abs ) ? filemtime( $dashboard_abs ) : WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION;

				wp_enqueue_style( 'wpcbl_check_for_broken_links_dashboard', WPCBL_CHECK_BROKEN_LINKS_ROOT_URL . $dashboard_rel, array( 'wpcbl_check_for_broken_links_admin_styles' ), $dashboard_ver, 'all' );
			}

			if ( 'wpcbl-check-for-broken-links-guest-posts' === $current_page ) {
				$gp_rel = 'assets/dist/css/admin/cbl-guest-posts.css';
				$gp_abs = WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/' . $gp_rel;
				$gp_ver = file_exists( $gp_abs ) ? filemtime( $gp_abs ) : WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION;

				wp_enqueue_style( 'wpcbl_check_for_broken_links_guest_posts', WPCBL_CHECK_BROKEN_LINKS_ROOT_URL . $gp_rel, array( 'wpcbl_check_for_broken_links_admin_styles' ), $gp_ver, 'all' );
			}

			if ( 'wpcbl-check-for-broken-links-upgrade' === $current_page ) {
				$upgrade_rel = 'assets/dist/css/admin/cbl-upgrade.css';
				$upgrade_abs = WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/' . $upgrade_rel;
				$upgrade_ver = file_exists( $upgrade_abs ) ? filemtime( $upgrade_abs ) : WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION;

				wp_enqueue_style( 'wpcbl_check_for_broken_links_upgrade', WPCBL_CHECK_BROKEN_LINKS_ROOT_URL . $upgrade_rel, array( 'wpcbl_check_for_broken_links_admin_styles' ), $upgrade_ver, 'all' );
			}
		}
	}

	return new WPCBL_Check_Broken_Links_Admin_Assets();

endif;
