<?php
/**
 * The WPCBL_Check_Broken_Links_Admin_Ajax class.
 *
 * @package WPCBL_Check_Broken_Links/Admin
 * @author  Norse Digital Group LLC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WPCBL_Check_Broken_Links_Admin_Ajax' ) ) :

	/**
	 * Admin Ajax.
	 *
	 * Calls admin Ajax.
	 *
	 * @since 1.0.0
	 */
	class WPCBL_Check_Broken_Links_Admin_Ajax {
		/**
		 * The constructor.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function __construct() {
			add_action( 'wp_ajax_wpcbl_broken_links_manual_scan', array( $this, 'manual_scan' ) );
			add_action( 'wp_ajax_wpcbl_clear_scan_results', array( $this, 'clear_scan_results' ) );
			add_action( 'wp_ajax_wpcbl_scan_progress', array( $this, 'scan_progress' ) );
			add_action( 'wp_ajax_wpcbl_dismiss_ttswp_banner', array( $this, 'dismiss_ttswp_banner' ) );
			add_action( 'wp_ajax_wpcbl_edit_link_url', array( $this, 'edit_link_url' ) );
			add_action( 'wp_ajax_wpcbl_unlink', array( $this, 'unlink' ) );
			add_action( 'wp_ajax_wpcbl_not_broken', array( $this, 'not_broken' ) );
			add_action( 'wp_ajax_wpcbl_dismiss_link', array( $this, 'dismiss_link' ) );
			add_action( 'wp_ajax_wpcbl_recheck_all', array( $this, 'recheck_all' ) );
			add_action( 'wp_ajax_wpcbl_fix_redirect', array( $this, 'fix_redirect' ) );
			add_action( 'wp_ajax_wpcbl_wayback_lookup', array( $this, 'wayback_lookup' ) );
			add_action( 'wp_ajax_wpcbl_ai_fix', array( $this, 'ai_fix' ) );
			add_action( 'wp_ajax_wpcbl_ai_fix_batch_start', array( $this, 'ai_fix_batch_start' ) );
			add_action( 'wp_ajax_wpcbl_ai_fix_batch_status', array( $this, 'ai_fix_batch_status' ) );
			add_action( 'wp_ajax_wpcbl_ai_fix_batch_dismiss', array( $this, 'ai_fix_batch_dismiss' ) );
			add_action( 'wp_ajax_wpcbl_rank_state', array( $this, 'rank_state' ) );
			add_action( 'wp_ajax_wpcbl_rank_add_keywords', array( $this, 'rank_add_keywords' ) );
			add_action( 'wp_ajax_wpcbl_rank_delete', array( $this, 'rank_delete' ) );
			add_action( 'wp_ajax_wpcbl_rank_bulk_delete', array( $this, 'rank_bulk_delete' ) );
			add_action( 'wp_ajax_wpcbl_rank_refresh', array( $this, 'rank_refresh' ) );
			add_action( 'wp_ajax_wpcbl_rank_export', array( $this, 'rank_export' ) );
			add_action( 'wp_ajax_wpcbl_rank_settings', array( $this, 'rank_settings' ) );
			add_action( 'wp_ajax_wpcbl_rank_share', array( $this, 'rank_share' ) );
			add_action( 'wp_ajax_wpcbl_uptime_state', array( $this, 'uptime_state' ) );
			add_action( 'wp_ajax_wpcbl_uptime_create', array( $this, 'uptime_create' ) );
			add_action( 'wp_ajax_wpcbl_uptime_update', array( $this, 'uptime_update' ) );
			add_action( 'wp_ajax_wpcbl_uptime_delete', array( $this, 'uptime_delete' ) );
			add_action( 'wp_ajax_wpcbl_uptime_toggle', array( $this, 'uptime_toggle' ) );
			add_action( 'wp_ajax_wpcbl_seo_audit_state', array( $this, 'seo_audit_state' ) );
			add_action( 'wp_ajax_wpcbl_seo_audit_run', array( $this, 'seo_audit_run' ) );
			add_action( 'wp_ajax_wpcbl_seo_audit_share', array( $this, 'seo_audit_share' ) );
			add_action( 'wp_ajax_wpcbl_seo_audit_issue_pages', array( $this, 'seo_audit_issue_pages' ) );
			add_action( 'wp_ajax_wpcbl_seo_audit_ai_fix', array( $this, 'seo_audit_ai_fix' ) );
			add_action( 'wp_ajax_wpcbl_seo_audit_ai_apply', array( $this, 'seo_audit_ai_apply' ) );
			add_action( 'admin_post_wpcbl_seo_audit_pdf', array( $this, 'seo_audit_pdf' ) );
			add_action( 'wp_ajax_wpcbl_ilo_state', array( $this, 'ilo_state' ) );
			add_action( 'wp_ajax_wpcbl_ilo_run', array( $this, 'ilo_run' ) );
			add_action( 'wp_ajax_wpcbl_ilo_resolve', array( $this, 'ilo_resolve' ) );
			add_action( 'wp_ajax_wpcbl_ilo_apply', array( $this, 'ilo_apply' ) );
			add_action( 'wp_ajax_wpcbl_ilo_undo', array( $this, 'ilo_undo' ) );
			add_action( 'wp_ajax_wpcbl_ilo_post_types', array( $this, 'ilo_post_types' ) );
			add_action( 'wp_ajax_wpcbl_aiv_state', array( $this, 'aiv_state' ) );
			add_action( 'wp_ajax_wpcbl_aiv_add_prompts', array( $this, 'aiv_add_prompts' ) );
			add_action( 'wp_ajax_wpcbl_aiv_delete_prompt', array( $this, 'aiv_delete_prompt' ) );
			add_action( 'wp_ajax_wpcbl_aiv_refresh', array( $this, 'aiv_refresh' ) );
			add_action( 'wp_ajax_wpcbl_billing_change_plan', array( $this, 'billing_change_plan' ) );
		}

		/**
		 * Pro: replace a permanently redirected URL with its final destination.
		 *
		 * @since 3.0.0
		 *
		 * @return void
		 */
		/**
		 * A posted link URL, tolerating malformed schemes (htp://...) that
		 * esc_url_raw() empties. Stored broken links can be exactly that, and
		 * row actions must still find and fix them.
		 *
		 * @param string $key The $_POST key.
		 *
		 * @since 3.0.4
		 *
		 * @return string
		 */
		private function posted_link_url( $key ) {
			if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Callers verify the nonce first.
				return '';
			}

			$raw   = sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Callers verify the nonce first.
			$clean = esc_url_raw( $raw );

			return '' !== $clean ? $clean : $raw;
		}

		public function fix_redirect() {
			$this->verify_link_action_request();

			if ( ! wpcbl_has_pro() || 'on' !== wpcbl_get_option( 'fix_redirects', '' ) ) {
				wp_send_json_error( esc_html__( 'Fixing redirects is a Pro feature.', 'check-for-broken-links' ), 403 );
			}

			$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
			$url     = $this->posted_link_url( 'url' );

			if ( ! $post_id || '' === $url ) {
				wp_send_json_error( esc_html__( 'Missing data for the redirect fix.', 'check-for-broken-links' ) );
			}

			// Follow permanent redirects (301/308) manually, up to 5 hops.
			$destination = $url;
			for ( $hop = 0; $hop < 5; $hop++ ) {
				$response = wp_safe_remote_head( $destination, array( 'timeout' => 10, 'redirection' => 0 ) );
				if ( is_wp_error( $response ) ) {
					break;
				}
				$code     = (int) wp_remote_retrieve_response_code( $response );
				$location = wp_remote_retrieve_header( $response, 'location' );
				if ( ! in_array( $code, array( 301, 308 ), true ) || empty( $location ) ) {
					break;
				}
				$destination = $location;
			}

			if ( $destination === $url ) {
				wp_send_json_error( esc_html__( 'No permanent redirect found for this URL.', 'check-for-broken-links' ) );
			}

			$post = get_post( $post_id );
			if ( ! $post || false === strpos( $post->post_content, $url ) ) {
				wp_send_json_error( esc_html__( 'The URL was not found in the post content.', 'check-for-broken-links' ) );
			}

			if ( ! $this->update_content_preserving_modified( $post_id, str_replace( $url, $destination, $post->post_content ) ) ) {
				wp_send_json_error( esc_html__( 'Could not update the post.', 'check-for-broken-links' ) );
			}

			// The destination was just verified live, so the row simply goes away.
			$this->update_stored_results( $url, null, $post_id );

			wp_send_json_success( array( 'destination' => $destination ) );
		}

		/**
		 * Pro: look up an archived copy of a dead external link on the
		 * Wayback Machine. Server-side, 5s timeout, fails gracefully.
		 *
		 * @since 3.0.0
		 *
		 * @return void
		 */
		public function wayback_lookup() {
			$this->verify_link_action_request();

			if ( ! wpcbl_has_pro() || 'on' !== wpcbl_get_option( 'wayback_suggestions', '' ) ) {
				wp_send_json_error( esc_html__( 'Wayback suggestions are a Pro feature.', 'check-for-broken-links' ), 403 );
			}

			$url = $this->posted_link_url( 'url' );
			if ( '' === $url ) {
				wp_send_json_error( esc_html__( 'Missing URL.', 'check-for-broken-links' ) );
			}

			$response = wp_remote_get( 'https://archive.org/wayback/available?url=' . rawurlencode( $url ), array( 'timeout' => 5 ) );

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				wp_send_json_error( esc_html__( 'The Wayback Machine did not respond. Try again later.', 'check-for-broken-links' ) );
			}

			$data     = json_decode( wp_remote_retrieve_body( $response ), true );
			$snapshot = isset( $data['archived_snapshots']['closest']['url'] ) ? esc_url_raw( $data['archived_snapshots']['closest']['url'] ) : '';

			if ( '' === $snapshot ) {
				wp_send_json_error( esc_html__( 'No archived version found for this URL.', 'check-for-broken-links' ) );
			}

			wp_send_json_success( array( 'archived_url' => $snapshot ) );
		}

		/**
		 * Fix with AI: send one broken link's context to the SaaS and return
		 * the verified suggestions. Applying a fix goes through the existing
		 * edit_link_url / unlink handlers, never through new content code.
		 *
		 * @since 3.0.3
		 */
		public function ai_fix() {
			$this->verify_link_action_request();

			if ( ! wpcbl_has_pro() || 'on' !== wpcbl_get_option( 'ai_fix', '' ) ) {
				wp_send_json_error( esc_html__( 'Fix with AI is a Pro feature.', 'check-for-broken-links' ), 403 );
			}

			$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
			$url     = $this->posted_link_url( 'url' );
			$code    = isset( $_POST['code'] ) ? absint( $_POST['code'] ) : 0;

			if ( ! $post_id || '' === $url ) {
				wp_send_json_error( esc_html__( 'Missing data for the AI fix.', 'check-for-broken-links' ) );
			}

			$post = get_post( $post_id );
			if ( ! $post || false === WPCBL_Check_Broken_Links_Utilities::find_url_in_content( $post->post_content, $url ) ) {
				wp_send_json_error( esc_html__( 'The URL was not found in the post content. It may live in a builder or slider field.', 'check-for-broken-links' ) );
			}

			$connect = wpcbl_connect();
			if ( ! $connect || ! $connect->is_connected() ) {
				wp_send_json_error( esc_html__( 'Connect this site to brokenlinkchecker.io first, from Settings.', 'check-for-broken-links' ) );
			}

			// Relative URLs go absolute. Malformed ones (htp://...) are sent
			// as-is: the AI treats a scheme typo as its easiest fix.
			$api_url = WPCBL_Check_Broken_Links_Utilities::absolutize_url( $url );

			$context = WPCBL_Check_Broken_Links_Utilities::extract_link_context( $post->post_content, $url );

			$result = $connect->ai_fix(
				array(
					'url'         => $api_url,
					'anchor'      => $context['anchor'],
					'sentence'    => $context['sentence'],
					'post_title'  => mb_substr( get_the_title( $post ), 0, 300 ),
					'language'    => mb_substr( get_locale(), 0, 10 ),
					'http_status' => $code,
				)
			);

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( esc_html__( 'Could not reach brokenlinkchecker.io. Check the connection and try again.', 'check-for-broken-links' ) );
			}

			if ( 401 === $result['code'] ) {
				wp_send_json_error( esc_html__( 'The site connection expired. Reconnect from Settings.', 'check-for-broken-links' ) );
			}

			if ( 403 === $result['code'] ) {
				wp_send_json_error( esc_html__( 'Fix with AI is not included in this plan.', 'check-for-broken-links' ), 403 );
			}

			if ( 429 === $result['code'] ) {
				$quota = isset( $result['data']['quota'] ) ? $result['data']['quota'] : array();
				wp_send_json_error(
					sprintf(
						/* translators: 1: AI fixes used this month, 2: monthly limit. */
						esc_html__( 'You reached this month\'s AI fix limit, %1$d of %2$d used. The counter resets on the first of the month.', 'check-for-broken-links' ),
						isset( $quota['used'] ) ? (int) $quota['used'] : 0,
						isset( $quota['limit'] ) ? (int) $quota['limit'] : 0
					)
				);
			}

			if ( 200 !== $result['code'] || empty( $result['data']['recommendation'] ) ) {
				wp_send_json_error( esc_html__( 'The AI service did not answer. Try again in a moment.', 'check-for-broken-links' ) );
			}

			wp_send_json_success( $result['data'] );
		}

		/**
		 * Bulk Fix with AI: queue every fixable broken link as one batch job.
		 *
		 * @since 3.0.3
		 */
		public function ai_fix_batch_start() {
			$this->verify_link_action_request();

			if ( ! wpcbl_has_pro() || 'on' !== wpcbl_get_option( 'ai_fix', '' ) ) {
				wp_send_json_error( esc_html__( 'Fix with AI is a Pro feature.', 'check-for-broken-links' ), 403 );
			}

			$connect = wpcbl_connect();
			if ( ! $connect || ! $connect->is_connected() ) {
				wp_send_json_error( esc_html__( 'Connect this site to brokenlinkchecker.io first, from Settings.', 'check-for-broken-links' ) );
			}

			// Double-click / already-running guard: hand back the existing job
			// instead of spending quota on a second batch.
			$existing = get_option( 'wpcbl_ai_fix_batch', array() );
			if ( ! empty( $existing['job_id'] ) ) {
				wp_send_json_success(
					array(
						'job_id'  => $existing['job_id'],
						'resumed' => true,
					)
				);
			}

			$stored = get_option( 'wpcbl_check_for_broken_links_links', array() );
			$broken = isset( $stored['broken'] ) && is_array( $stored['broken'] ) ? $stored['broken'] : array();

			// Match the results table exactly: rows dismissed this session
			// (cleared on the next scan) never reach the batch either.
			$session_dismissed = (array) get_option( 'wpcbl_session_dismissed', array() );
			if ( ! empty( $session_dismissed ) ) {
				$broken = array_values(
					array_filter(
						$broken,
						function ( $link ) use ( $session_dismissed ) {
							return ! isset( $link['link'] ) || ! in_array( $link['link'], $session_dismissed, true );
						}
					)
				);
			}

			if ( array() === $broken ) {
				wp_send_json_error( esc_html__( 'No broken links to fix. Run a scan first.', 'check-for-broken-links' ) );
			}

			$batch = WPCBL_Check_Broken_Links_Utilities::build_ai_fix_batch( $broken );

			if ( array() === $batch['links'] ) {
				wp_send_json_error( esc_html__( 'None of these links can be fixed automatically. They live in builder, slider or comment content.', 'check-for-broken-links' ) );
			}

			$result = $connect->ai_fix_batch( $batch['links'] );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( esc_html__( 'Could not reach brokenlinkchecker.io. Check the connection and try again.', 'check-for-broken-links' ) );
			}

			if ( 401 === $result['code'] ) {
				wp_send_json_error( esc_html__( 'The site connection expired. Reconnect from Settings.', 'check-for-broken-links' ) );
			}

			if ( 403 === $result['code'] ) {
				wp_send_json_error( esc_html__( 'Fix with AI is not included in this plan.', 'check-for-broken-links' ), 403 );
			}

			if ( 201 !== $result['code'] || empty( $result['data']['job_id'] ) ) {
				wp_send_json_error( esc_html__( 'The AI service did not answer. Try again in a moment.', 'check-for-broken-links' ) );
			}

			update_option(
				'wpcbl_ai_fix_batch',
				array(
					'job_id'  => sanitize_text_field( (string) $result['data']['job_id'] ),
					'rows'    => $batch['rows'],
					'skipped' => $batch['skipped'],
					'started' => time(),
				),
				false
			);

			wp_send_json_success(
				array(
					'job_id'        => $result['data']['job_id'],
					'queued'        => isset( $result['data']['queued'] ) ? (int) $result['data']['queued'] : 0,
					'skipped_quota' => isset( $result['data']['skipped_quota'] ) ? (int) $result['data']['skipped_quota'] : 0,
					'skipped_local' => count( $batch['skipped'] ),
				)
			);
		}

		/**
		 * Poll the running batch and return merged review rows.
		 *
		 * @since 3.0.3
		 */
		public function ai_fix_batch_status() {
			$this->verify_link_action_request();

			$saved = get_option( 'wpcbl_ai_fix_batch', array() );
			if ( empty( $saved['job_id'] ) ) {
				wp_send_json_success( array( 'status' => 'none' ) );
			}

			// The toggle may come back, so leave the saved job alone here.
			if ( ! wpcbl_has_pro() || 'on' !== wpcbl_get_option( 'ai_fix', '' ) ) {
				wp_send_json_success( array( 'status' => 'none' ) );
			}

			$connect = wpcbl_connect();
			if ( ! $connect || ! $connect->is_connected() ) {
				wp_send_json_success( array( 'status' => 'none' ) );
			}

			$result = $connect->ai_fix_batch_status( (string) $saved['job_id'] );

			if ( is_wp_error( $result ) ) {
				// Transient: let the JS failure counter decide when to stand down.
				wp_send_json_error( esc_html__( 'Could not reach brokenlinkchecker.io. Check the connection and try again.', 'check-for-broken-links' ) );
			}

			if ( 401 === $result['code'] ) {
				// The client already cleared the stale token. Nothing to poll.
				wp_send_json_success( array( 'status' => 'none' ) );
			}

			if ( 404 === $result['code'] ) {
				// The job was pruned server side. Forget it locally too.
				delete_option( 'wpcbl_ai_fix_batch' );
				wp_send_json_success( array( 'status' => 'none' ) );
			}

			if ( 403 === $result['code'] ) {
				// The plan no longer includes Fix with AI. The job is unusable.
				delete_option( 'wpcbl_ai_fix_batch' );
				wp_send_json_success( array( 'status' => 'none' ) );
			}

			if ( 200 !== $result['code'] ) {
				wp_send_json_error( esc_html__( 'The AI service did not answer. Try again in a moment.', 'check-for-broken-links' ) );
			}

			$data = $result['data'];

			wp_send_json_success(
				array(
					'status'        => isset( $data['status'] ) ? (string) $data['status'] : 'processing',
					'counters'      => isset( $data['counters'] ) && is_array( $data['counters'] ) ? $data['counters'] : array(),
					'quota'         => isset( $data['quota'] ) && is_array( $data['quota'] ) ? $data['quota'] : array(),
					'rows'          => WPCBL_Check_Broken_Links_Utilities::merge_ai_fix_batch_rows(
						isset( $saved['rows'] ) ? (array) $saved['rows'] : array(),
						isset( $data['links'] ) ? (array) $data['links'] : array()
					),
					'skipped_local' => isset( $saved['skipped'] ) ? array_values( (array) $saved['skipped'] ) : array(),
				)
			);
		}

		/**
		 * Close the batch review and forget the job.
		 *
		 * @since 3.0.3
		 */
		public function ai_fix_batch_dismiss() {
			$this->verify_link_action_request();
			delete_option( 'wpcbl_ai_fix_batch' );
			wp_send_json_success();
		}

		/**
		 * Shared guard for the rank tracker endpoints: nonce + capability +
		 * connection. No Pro gate: the tracker is on every plan.
		 *
		 * @since 3.0.5
		 *
		 * @return WPCBL_Check_Broken_Links_Connect
		 */
		private function verify_rank_request() {
			$this->verify_link_action_request();

			$connect = wpcbl_connect();
			if ( ! $connect || ! $connect->is_connected() ) {
				wp_send_json_error( esc_html__( 'Connect this site to brokenlinkchecker.io first.', 'check-for-broken-links' ), 403 );
			}

			return $connect;
		}

		/**
		 * Map a SaaS rank/uptime response onto the wp_send_json_* contract.
		 * Any 2xx counts as success (204 No Content, from DELETE endpoints
		 * like uptime's destroy(), decodes to an empty data array and
		 * still reaches wp_send_json_success()).
		 *
		 * @since 3.0.5
		 *
		 * @param array|WP_Error $result rank_request() result.
		 *
		 * @return void
		 */
		private function send_rank_result( $result ) {
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( esc_html__( 'brokenlinkchecker.io is not reachable right now. Try again in a minute.', 'check-for-broken-links' ), 502 );
			}

			$code = $result['code'];
			if ( $code < 200 || $code >= 300 ) {
				$message = isset( $result['data']['message'] ) ? $result['data']['message'] : '';
				if ( '' === $message && isset( $result['data']['errors'] ) && is_array( $result['data']['errors'] ) ) {
					$first   = reset( $result['data']['errors'] );
					$message = is_array( $first ) ? (string) reset( $first ) : (string) $first;
				}
				wp_send_json_error( '' !== $message ? $message : esc_html__( 'Something went wrong. Please try again.', 'check-for-broken-links' ), $code );
			}

			wp_send_json_success( $result['data'] );
		}

		/**
		 * Rank tracker: return the cached (or freshly fetched) tracker state.
		 *
		 * @since 3.0.5
		 *
		 * @return void
		 */
		public function rank_state() {
			$connect = $this->verify_rank_request();
			$fresh   = isset( $_POST['fresh'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['fresh'] ) );
			$this->send_rank_result( $connect->rank_state( $fresh ) );
		}

		/**
		 * Rank tracker: add one or more keywords to track.
		 *
		 * @since 3.0.5
		 *
		 * @return void
		 */
		public function rank_add_keywords() {
			$connect  = $this->verify_rank_request();
			$keywords = isset( $_POST['keywords'] ) ? sanitize_textarea_field( wp_unslash( $_POST['keywords'] ) ) : '';
			$market   = isset( $_POST['market'] ) ? sanitize_key( $_POST['market'] ) : '';
			$device   = isset( $_POST['device'] ) ? sanitize_key( $_POST['device'] ) : '';

			if ( '' === $keywords ) {
				wp_send_json_error( esc_html__( 'Enter at least one keyword.', 'check-for-broken-links' ), 400 );
			}

			$body = array( 'keywords' => $keywords );
			if ( '' !== $market ) {
				$body['market'] = $market;
			}
			if ( '' !== $device ) {
				$body['device'] = $device;
			}

			$connect->flush_rank_state();
			$this->send_rank_result( $connect->rank_request( 'POST', '/keywords', $body ) );
		}

		/**
		 * Rank tracker: stop tracking a single keyword.
		 *
		 * @since 3.0.5
		 *
		 * @return void
		 */
		public function rank_delete() {
			$connect = $this->verify_rank_request();
			$id      = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

			if ( ! $id ) {
				wp_send_json_error( esc_html__( 'Missing keyword.', 'check-for-broken-links' ), 400 );
			}

			$connect->flush_rank_state();
			$this->send_rank_result( $connect->rank_request( 'DELETE', '/keywords/' . $id ) );
		}

		/**
		 * Rank tracker: stop tracking several keywords at once.
		 *
		 * @since 3.0.5
		 *
		 * @return void
		 */
		public function rank_bulk_delete() {
			$connect = $this->verify_rank_request();
			$ids     = isset( $_POST['ids'] ) ? array_filter( array_map( 'absint', (array) $_POST['ids'] ) ) : array();

			if ( array() === $ids ) {
				wp_send_json_error( esc_html__( 'Select at least one keyword.', 'check-for-broken-links' ), 400 );
			}

			$connect->flush_rank_state();
			$this->send_rank_result( $connect->rank_request( 'POST', '/bulk-delete', array( 'ids' => array_values( $ids ) ) ) );
		}

		/**
		 * Rank tracker: force a fresh position check for some or all keywords.
		 *
		 * @since 3.0.5
		 *
		 * @return void
		 */
		public function rank_refresh() {
			$connect = $this->verify_rank_request();
			$ids     = isset( $_POST['ids'] ) ? array_filter( array_map( 'absint', (array) $_POST['ids'] ) ) : array();

			$body = array();
			if ( array() !== $ids ) {
				$body['ids'] = array_values( $ids );
			}

			$connect->flush_rank_state();
			$this->send_rank_result( $connect->rank_request( 'POST', '/refresh', $body ) );
		}

		/**
		 * Rank tracker: export tracked keywords as CSV.
		 *
		 * @since 3.0.5
		 *
		 * @return void
		 */
		public function rank_export() {
			$connect = $this->verify_rank_request();
			$ids     = isset( $_POST['ids'] ) ? array_filter( array_map( 'absint', (array) $_POST['ids'] ) ) : array();

			$body = array();
			if ( array() !== $ids ) {
				$body['ids'] = array_values( $ids );
			}

			$this->send_rank_result( $connect->rank_request( 'POST', '/export', $body ) );
		}

		/**
		 * Rank tracker: update the tracker's device/engine/email settings.
		 *
		 * @since 3.0.5
		 *
		 * @return void
		 */
		public function rank_settings() {
			$connect = $this->verify_rank_request();

			$body = array(
				'device'           => isset( $_POST['device'] ) ? sanitize_key( $_POST['device'] ) : '',
				'engine'           => isset( $_POST['engine'] ) ? sanitize_key( $_POST['engine'] ) : '',
				'email_enabled'    => isset( $_POST['email_enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['email_enabled'] ) ),
				'email_recipients' => isset( $_POST['email_recipients'] ) ? sanitize_text_field( wp_unslash( $_POST['email_recipients'] ) ) : '',
			);

			$connect->flush_rank_state();
			$this->send_rank_result( $connect->rank_request( 'PUT', '/settings', $body ) );
		}

		/**
		 * Rank tracker: turn public sharing on/off and set its password.
		 *
		 * @since 3.0.5
		 *
		 * @return void
		 */
		public function rank_share() {
			$connect  = $this->verify_rank_request();
			$sharing  = isset( $_POST['sharing'] ) ? sanitize_key( $_POST['sharing'] ) : '';
			$password = isset( $_POST['password'] ) ? substr( (string) wp_unslash( $_POST['password'] ), 0, 72 ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Password is sent verbatim to the SaaS, only length-capped.

			if ( ! in_array( $sharing, array( 'on', 'off' ), true ) ) {
				wp_send_json_error( esc_html__( 'Invalid sharing option.', 'check-for-broken-links' ), 400 );
			}

			$body = array( 'sharing' => $sharing );
			if ( '' !== $password ) {
				$body['password'] = $password;
			}

			$connect->flush_rank_state();
			$this->send_rank_result( $connect->rank_request( 'POST', '/share', $body ) );
		}

		/**
		 * Shared guard for the uptime monitor endpoints: nonce + capability +
		 * connection. No Pro gate: uptime monitoring is on every plan.
		 *
		 * @since 3.0.6
		 *
		 * @return WPCBL_Check_Broken_Links_Connect
		 */
		private function verify_uptime_request() {
			$this->verify_link_action_request();

			$connect = wpcbl_connect();
			if ( ! $connect || ! $connect->is_connected() ) {
				wp_send_json_error( esc_html__( 'Connect this site to brokenlinkchecker.io first.', 'check-for-broken-links' ), 403 );
			}

			return $connect;
		}

		/**
		 * Uptime monitor: return the cached (or freshly fetched) monitor state.
		 *
		 * @since 3.0.6
		 *
		 * @return void
		 */
		public function uptime_state() {
			$connect = $this->verify_uptime_request();
			$this->send_rank_result( $connect->uptime_state( ! empty( $_POST['fresh'] ) ) );
		}

		/**
		 * Uptime monitor: create a new monitor.
		 *
		 * @since 3.0.6
		 *
		 * @return void
		 */
		public function uptime_create() {
			$connect = $this->verify_uptime_request();
			$body    = wpcbl_uptime_sanitize_payload( $this->posted_uptime_fields( array( 'type', 'name', 'url', 'interval_seconds' ) ) );

			// name/url are only required for monitor types the SaaS doesn't
			// default itself: the one-click "Monitor this site" button
			// posts type + interval_seconds only and the SaaS fills in
			// name/url from the connected site's URL, and heartbeat
			// monitors have no polled URL at all (UptimeController::store,
			// MonitorManager::rules()). Type presence is all this local
			// gate needs to check; an invalid combination still comes back
			// as a real validation error from the SaaS.
			if ( empty( $body['type'] ) ) {
				wp_send_json_error( esc_html__( 'Choose a monitor type.', 'check-for-broken-links' ), 400 );
			}

			$connect->flush_uptime_state();
			$this->send_rank_result( $connect->uptime_request( 'POST', '/monitors', $body ) );
		}

		/**
		 * Uptime monitor: update an existing monitor.
		 *
		 * @since 3.0.6
		 *
		 * @return void
		 */
		public function uptime_update() {
			$connect = $this->verify_uptime_request();
			$id      = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

			if ( ! $id ) {
				wp_send_json_error( esc_html__( 'Missing monitor.', 'check-for-broken-links' ), 400 );
			}

			$body = wpcbl_uptime_sanitize_payload(
				$this->posted_uptime_fields( array( 'type', 'name', 'url', 'interval_seconds', 'alert_emails', 'alert_after_failures' ) )
			);

			$connect->flush_uptime_state();
			$this->send_rank_result( $connect->uptime_request( 'PUT', '/monitors/' . $id, $body ) );
		}

		/**
		 * Uptime monitor: delete a monitor.
		 *
		 * @since 3.0.6
		 *
		 * @return void
		 */
		public function uptime_delete() {
			$connect = $this->verify_uptime_request();
			$id      = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

			if ( ! $id ) {
				wp_send_json_error( esc_html__( 'Missing monitor.', 'check-for-broken-links' ), 400 );
			}

			$connect->flush_uptime_state();
			$this->send_rank_result( $connect->uptime_request( 'DELETE', '/monitors/' . $id ) );
		}

		/**
		 * Uptime monitor: pause or resume a monitor.
		 *
		 * @since 3.0.6
		 *
		 * @return void
		 */
		public function uptime_toggle() {
			$connect = $this->verify_uptime_request();
			$id      = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

			if ( ! $id ) {
				wp_send_json_error( esc_html__( 'Missing monitor.', 'check-for-broken-links' ), 400 );
			}

			$connect->flush_uptime_state();
			$this->send_rank_result( $connect->uptime_request( 'POST', '/monitors/' . $id . '/toggle' ) );
		}

		/**
		 * Shared guard for every SEO/AEO audit endpoint: nonce + capability +
		 * connection. No Pro gate: the free plan gets its monthly audit
		 * allowance too, enforced by the SaaS, not here.
		 *
		 * @since 3.0.7
		 *
		 * @return WPCBL_Check_Broken_Links_Connect
		 */
		private function verify_seo_audit_request() {
			$this->verify_link_action_request();

			$connect = wpcbl_connect();
			if ( ! $connect || ! $connect->is_connected() ) {
				wp_send_json_error( esc_html__( 'Connect this site to brokenlinkchecker.io first.', 'check-for-broken-links' ), 403 );
			}

			return $connect;
		}

		/**
		 * SEO/AEO audit: return the cached (or freshly fetched) audit state.
		 *
		 * @since 3.0.7
		 *
		 * @return void
		 */
		public function seo_audit_state() {
			$connect = $this->verify_seo_audit_request();
			$this->send_rank_result( $connect->seo_audit_state( ! empty( $_POST['fresh'] ) ) );
		}

		/**
		 * SEO/AEO audit: start an audit. Site mode sends this site's
		 * published URLs, so a freshly connected site never waits on a
		 * crawl first. Page mode audits a single URL.
		 *
		 * @since 3.0.7
		 *
		 * @return void
		 */
		public function seo_audit_run() {
			$connect = $this->verify_seo_audit_request();

			$mode = isset( $_POST['mode'] ) && 'page' === sanitize_text_field( wp_unslash( $_POST['mode'] ) ) ? 'page' : 'site';
			$body = array(
				'mode'       => $mode,
				'skip_query' => ! empty( $_POST['skip_query'] ),
			);

			if ( 'page' === $mode ) {
				$body['url'] = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
			} else {
				$body['urls'] = wpcbl_collect_site_urls();
			}

			$result = $connect->seo_audit_request( 'POST', '/run', $body );
			$connect->flush_seo_audit_state();

			$this->send_rank_result( $result );
		}

		/**
		 * SEO/AEO audit: turn the public read-only share link on or off.
		 *
		 * @since 3.0.7
		 *
		 * @return void
		 */
		public function seo_audit_share() {
			$connect  = $this->verify_seo_audit_request();
			$audit_id = isset( $_POST['audit_id'] ) ? sanitize_text_field( wp_unslash( $_POST['audit_id'] ) ) : '';

			if ( '' === $audit_id ) {
				wp_send_json_error( esc_html__( 'No audit to share.', 'check-for-broken-links' ), 400 );
			}

			$result = $connect->seo_audit_request( 'POST', '/' . rawurlencode( $audit_id ) . '/share' );
			$connect->flush_seo_audit_state();

			$this->send_rank_result( $result );
		}

		/**
		 * SEO/AEO audit: every affected URL for one issue, fetched on demand.
		 * The polled state carries only a short preview, so the full list is
		 * requested when a reader actually opens it.
		 *
		 * @since 3.0.7
		 *
		 * @return void
		 */
		public function seo_audit_issue_pages() {
			$connect  = $this->verify_seo_audit_request();
			$audit_id = isset( $_POST['audit_id'] ) ? sanitize_text_field( wp_unslash( $_POST['audit_id'] ) ) : '';
			$issue_id = isset( $_POST['issue_id'] ) ? sanitize_text_field( wp_unslash( $_POST['issue_id'] ) ) : '';

			if ( '' === $audit_id || '' === $issue_id ) {
				wp_send_json_error( esc_html__( 'That issue is not available.', 'check-for-broken-links' ), 400 );
			}

			$this->send_rank_result( $connect->seo_audit_issue_pages( $audit_id, $issue_id ) );
		}

		/**
		 * SEO/AEO audit: ask for AI-written fixes for one issue. Returns
		 * suggestions for review. Nothing touches the site here.
		 *
		 * @since 3.0.7
		 *
		 * @return void
		 */
		public function seo_audit_ai_fix() {
			$connect  = $this->verify_seo_audit_request();
			$audit_id = isset( $_POST['audit_id'] ) ? sanitize_text_field( wp_unslash( $_POST['audit_id'] ) ) : '';
			$issue_id = isset( $_POST['issue_id'] ) ? sanitize_text_field( wp_unslash( $_POST['issue_id'] ) ) : '';

			if ( '' === $audit_id || '' === $issue_id ) {
				wp_send_json_error( esc_html__( 'That issue is not available.', 'check-for-broken-links' ), 400 );
			}

			$result = $connect->seo_audit_ai_fix( $audit_id, $issue_id );

			if ( ! is_wp_error( $result ) && isset( $result['data'] ) && is_array( $result['data'] ) ) {
				$result['data']['target'] = WPCBL_Check_Broken_Links_Seo_Apply::target_label();
			}

			$this->send_rank_result( $result );
		}

		/**
		 * SEO/AEO audit: write one approved suggestion into the post, then tell
		 * the SaaS what happened so both sides agree.
		 *
		 * @since 3.0.7
		 *
		 * @return void
		 */
		public function seo_audit_ai_apply() {
			$connect = $this->verify_seo_audit_request();

			$audit_id = isset( $_POST['audit_id'] ) ? sanitize_text_field( wp_unslash( $_POST['audit_id'] ) ) : '';
			$fix_id   = isset( $_POST['fix_id'] ) ? sanitize_text_field( wp_unslash( $_POST['fix_id'] ) ) : '';
			$url      = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
			$field    = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
			$value    = isset( $_POST['value'] ) ? sanitize_textarea_field( wp_unslash( $_POST['value'] ) ) : '';
			$dismiss  = ! empty( $_POST['dismiss'] );

			if ( '' === $audit_id || '' === $fix_id ) {
				wp_send_json_error( esc_html__( 'That fix is not available.', 'check-for-broken-links' ), 400 );
			}

			if ( $dismiss ) {
				$connect->seo_audit_ai_fix_resolve( $audit_id, $fix_id, 'dismissed' );
				wp_send_json_success( array( 'dismissed' => true ) );
			}

			$applied = WPCBL_Check_Broken_Links_Seo_Apply::apply( $url, $field, $value );

			if ( empty( $applied['ok'] ) ) {
				wp_send_json_error( $applied['message'], 422 );
			}

			// Only record it upstream once the write actually succeeded here.
			$connect->seo_audit_ai_fix_resolve( $audit_id, $fix_id, 'applied' );

			wp_send_json_success( $applied );
		}

		/**
		 * SEO/AEO audit: stream the audit PDF. Not an ajax action -- this
		 * returns binary, so it goes through admin-post and exits rather
		 * than sending JSON.
		 *
		 * @since 3.0.7
		 *
		 * @return void
		 */
		public function seo_audit_pdf() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You are not allowed to do this.', 'check-for-broken-links' ), '', array( 'response' => 403 ) );
			}

			check_admin_referer( 'wpcbl_seo_audit_pdf' );

			$audit_id = isset( $_GET['audit_id'] ) ? sanitize_text_field( wp_unslash( $_GET['audit_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified above via check_admin_referer().
			$connect  = wpcbl_connect();

			if ( '' === $audit_id || ! $connect || ! $connect->is_connected() ) {
				wp_die( esc_html__( 'That audit is not available.', 'check-for-broken-links' ), '', array( 'response' => 404 ) );
			}

			$pdf = $connect->seo_audit_pdf( $audit_id );

			if ( is_wp_error( $pdf ) || 200 !== $pdf['code'] || '' === $pdf['body'] ) {
				wp_die(
					esc_html__( 'The PDF could not be downloaded. Try the share link instead.', 'check-for-broken-links' ),
					'',
					array( 'response' => 502 )
				);
			}

			nocache_headers();
			header( 'Content-Type: application/pdf' );
			header( 'Content-Length: ' . strlen( $pdf['body'] ) );

			// Prefer the filename the server sent (domain plus the audit's
			// real date, from SeoAuditPdf::filename()) over inventing one
			// from today's date, which discards both.
			$disposition = isset( $pdf['disposition'] ) ? (string) $pdf['disposition'] : '';
			if ( '' === $disposition || false === stripos( $disposition, 'filename=' ) ) {
				$disposition = 'attachment; filename="seo-audit-' . gmdate( 'Y-m-d' ) . '.pdf"';
			}
			header( 'Content-Disposition: ' . $disposition );

			// Raw binary passthrough: escaping would corrupt the file.
			echo $pdf['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}

		/**
		 * Internal Link Optimizer: current run, findings and suggestions.
		 *
		 * Reuses verify_seo_audit_request() as its guard rather than a
		 * separate ILO-specific copy -- the guard (nonce, manage_options,
		 * is_connected()) is identical, only the API root differs, and
		 * that root lives in internal_links_request(), not the guard.
		 *
		 * @since 3.0.8
		 *
		 * @return void
		 */
		public function ilo_state() {
			$connect = $this->verify_seo_audit_request();
			$this->send_rank_result( $connect->internal_links_request( 'GET' ) );
		}

		/**
		 * Internal Link Optimizer: upload one batch of pages.
		 *
		 * The browser calls this repeatedly, walking the site. A short
		 * batch means the end, and that call also starts the run, so the
		 * upload can never be left half-finished and unclaimed.
		 *
		 * @since 3.0.8
		 *
		 * @return void
		 */
		public function ilo_run() {
			$connect = $this->verify_seo_audit_request();

			// The Dashboard card reads through the cached
			// internal_links_state() wrapper; a run starting means the
			// figures it shows are about to change, so the cache is
			// dropped up front rather than left to serve a stale count
			// for up to five minutes.
			$connect->flush_internal_links_state();

			$offset = isset( $_POST['offset'] ) ? absint( wp_unslash( $_POST['offset'] ) ) : 0;
			$upload = isset( $_POST['upload'] ) ? sanitize_text_field( wp_unslash( $_POST['upload'] ) ) : '';
			$stored = isset( $_POST['stored'] ) ? absint( wp_unslash( $_POST['stored'] ) ) : 0;
			// The account's page cap, read by the browser from the state
			// payload's page_cap and passed through on every call. 0 means
			// the browser never learned one, in which case nothing here
			// changes and the server's own cap check is the only one.
			$cap    = isset( $_POST['cap'] ) ? absint( wp_unslash( $_POST['cap'] ) ) : 0;
			$batch  = 25;
			$capped = false;

			// Empty means "follow the shared auditable types", exactly
			// today's behaviour -- wpcbl_collect_site_pages() intersects
			// a non-empty selection with wpcbl_auditable_post_types()
			// itself, so a stale saved type can never resurrect a junk
			// post type here even if the site's own settings changed
			// since it was saved.
			$post_types = wpcbl_get_option( 'ilo_post_types', array() );
			$post_types = is_array( $post_types ) ? $post_types : array();

			// Without this block a free site with 60 pages uploaded batch
			// 1, then had batch 2 refused with a 422 page_cap, which ended
			// the walk before /run was ever posted: the analysis could
			// never start at all and the uploaded rows sat orphaned until
			// the daily sweep. The room left in the cap decides instead.
			if ( $cap > 0 ) {
				$room = $cap - $stored;

				if ( $room <= 0 ) {
					// The cap is spent. The walk is over exactly as it is
					// when the site runs out of posts, so this takes the
					// same route: no /pages call, straight to /run.
					if ( '' === $upload ) {
						wp_send_json_error( esc_html__( 'The upload could not be found. Start the analysis again.', 'check-for-broken-links' ), 400 );
					}

					// Spending the cap does not by itself mean anything
					// was left out: a site whose page count lands exactly
					// on the cap reaches this same branch, with nothing
					// genuinely missed. has_more from the last batch
					// cannot settle that (it is true whenever that
					// batch's query came back full, which is also true
					// exactly on this boundary), so a fresh probe query
					// is what decides -- querying past the site's real
					// end always comes back with zero pages, with none
					// of has_more's ambiguity.
					$genuinely_capped = $this->ilo_more_pages_after( $offset, $post_types );

					$started = $connect->internal_links_request( 'POST', '/run', array( 'upload' => $upload ) );

					if ( is_wp_error( $started ) || $started['code'] < 200 || $started['code'] >= 300 ) {
						$this->send_rank_result( $started );
					}

					$payload = array(
						'upload'       => $upload,
						'pages_stored' => $stored,
						'done'         => true,
						'next_offset'  => $offset,
						'run_id'       => isset( $started['data']['run_id'] ) ? $started['data']['run_id'] : '',
					);

					if ( $genuinely_capped ) {
						// The browser says so plainly. The page is on
						// every plan, so this is a statement of what
						// was read, never an upgrade prompt.
						$payload['capped']   = true;
						$payload['page_cap'] = $cap;
					}

					wp_send_json_success( $payload );
				}

				if ( $room < $batch ) {
					// Room for part of a batch. Collect only that many and
					// end the walk after uploading them.
					$batch  = $room;
					$capped = true;
				}
			}

			// wpcbl_collect_site_pages() owns the offset arithmetic: the
			// rows it hands back and the query rows it actually consumed
			// are different numbers (the front page rides outside $limit,
			// a dedup or permalink skip drops a row without freeing an
			// offset slot), and only the collector can see both. done and
			// next_offset below are read from its has_more/next_offset,
			// never derived from count( $pages ) again.
			$collected = wpcbl_collect_site_pages( $offset, $batch, $post_types );
			$pages     = $collected['pages'];

			if ( $capped && count( $pages ) > $batch ) {
				// The front page rides outside the collector's query limit
				// on the first batch, so a capped batch can come back one
				// row over the room left. Drop the extra rather than post
				// a chunk the server would refuse outright.
				$pages = array_slice( $pages, 0, $batch );
			}

			if ( array() === $pages ) {
				// has_more decides this, ahead of the offset check: an
				// empty batch does not mean the walk is over just because
				// it landed at offset 0. A site whose first (or any)
				// batch is entirely removed by the dedup or
				// empty-permalink skip, with real posts still behind it,
				// is not an empty site -- it is a walk that has not
				// finished yet. Only when has_more is ALSO false does the
				// offset get to decide between "nothing published at
				// all" (0) and "the walk just ended" (anything else).
				if ( $collected['has_more'] ) {
					// Not over, whatever the offset: nothing to upload
					// this round, but the walk continues. There is no
					// /pages response to read pages_stored from here, so
					// the browser's own running total (echoed back as
					// 'stored') passes straight through unchanged,
					// keeping the progress number monotonic instead of
					// resetting to 0 mid-walk. Task 9's JS is responsible
					// for sending it.
					wp_send_json_success(
						array(
							'upload'       => $upload,
							'pages_stored' => $stored,
							'done'         => false,
							'next_offset'  => $collected['next_offset'],
						)
					);
				}

				if ( 0 === $offset ) {
					// Genuinely nothing published -- a real error.
					wp_send_json_error( esc_html__( 'No published pages were found to analyse.', 'check-for-broken-links' ), 422 );
				}

				// The walk is genuinely over: nothing left to upload, and
				// nothing left to walk past. The server requires
				// pages => a non-empty array, so posting one here would be
				// rejected outright rather than treated as a no-op; skip
				// /pages entirely and go straight to /run instead. There
				// is no /pages response to read the upload id from at
				// this point, so the id the browser already sent along in
				// this same request is what carries forward -- and
				// without one there is nothing to start a run for, so
				// refuse here with a plain message rather than letting
				// the server's raw validation string reach the user.
				if ( '' === $upload ) {
					wp_send_json_error( esc_html__( 'The upload could not be found. Start the analysis again.', 'check-for-broken-links' ), 400 );
				}

				$started = $connect->internal_links_request( 'POST', '/run', array( 'upload' => $upload ) );

				if ( is_wp_error( $started ) || $started['code'] < 200 || $started['code'] >= 300 ) {
					$this->send_rank_result( $started );
				}

				wp_send_json_success(
					array(
						// No /pages response landed on this call either,
						// so the running total the browser already holds
						// is echoed back here too -- the same reason as
						// the has_more branch above. Hardcoding 0 here
						// would reset the displayed count on the very
						// last response of the walk.
						'upload'       => $upload,
						'pages_stored' => $stored,
						'done'         => true,
						'next_offset'  => $offset,
						'run_id'       => isset( $started['data']['run_id'] ) ? $started['data']['run_id'] : '',
					)
				);
			}

			$body = array( 'pages' => $pages );

			if ( '' !== $upload ) {
				$body['upload'] = $upload;
			}

			$result = $connect->internal_links_request( 'POST', '/pages', $body );

			if ( is_wp_error( $result ) || $result['code'] < 200 || $result['code'] >= 300 ) {
				$this->send_rank_result( $result );
			}

			$upload = isset( $result['data']['upload'] ) ? $result['data']['upload'] : $upload;
			// A capped batch ends the walk whatever the collector says is
			// left: the cap, not the site, is what stops here.
			$done   = $capped || ! $collected['has_more'];

			$payload = array(
				'upload'       => $upload,
				'pages_stored' => isset( $result['data']['pages_stored'] ) ? (int) $result['data']['pages_stored'] : 0,
				'done'         => $done,
				'next_offset'  => $done ? $offset : $collected['next_offset'],
			);

			// Only when the cap really cut the walk short. A short last
			// batch that happened to fit inside the room left is the site
			// ending, not the cap, and saying otherwise would be wrong.
			// $collected['has_more'] is not what decides it: shrinking
			// this call's own query to exactly the room left reproduces
			// the same boundary ambiguity room <= 0 above works around,
			// so the same probe settles it here too.
			if ( $capped && $this->ilo_more_pages_after( $collected['next_offset'], $post_types ) ) {
				$payload['capped']   = true;
				$payload['page_cap'] = $cap;
			}

			if ( $done ) {
				$started = $connect->internal_links_request( 'POST', '/run', array( 'upload' => $upload ) );

				if ( is_wp_error( $started ) || $started['code'] < 200 || $started['code'] >= 300 ) {
					$this->send_rank_result( $started );
				}

				$payload['run_id'] = isset( $started['data']['run_id'] ) ? $started['data']['run_id'] : '';
			}

			wp_send_json_success( $payload );
		}

		/**
		 * Whether at least one more resolvable page sits beyond $offset,
		 * once the plan's page cap has stopped ilo_run()'s walk.
		 *
		 * $collected['has_more'] cannot answer this on its own: it is
		 * true whenever the query that filled the batch just uploaded
		 * came back full, which is also true exactly on the boundary
		 * where the site's page count lands on the cap with nothing left
		 * behind it. A fresh one-row probe at the offset the walk would
		 * resume from has none of that ambiguity -- querying past the
		 * site's real end always comes back with zero pages.
		 *
		 * @since 3.0.8
		 *
		 * @param int   $offset     Query offset to probe from.
		 * @param array $post_types Saved post-type filter, same as the walk used.
		 *
		 * @return bool
		 */
		private function ilo_more_pages_after( $offset, $post_types = array() ) {
			$probe = wpcbl_collect_site_pages( $offset, 1, $post_types );

			return array() !== $probe['pages'];
		}

		/**
		 * Internal Link Optimizer: save which post types the optimizer
		 * reads when it maps this site's links. Saving never starts a
		 * run -- it only changes what the next one covers.
		 *
		 * @since 3.0.8
		 *
		 * @return void
		 */
		public function ilo_post_types() {
			$this->verify_seo_audit_request();

			$submitted = isset( $_POST['post_types'] ) ? (array) wp_unslash( $_POST['post_types'] ) : array();
			$submitted = array_values( array_unique( array_map( 'sanitize_key', $submitted ) ) );

			$auditable = wpcbl_auditable_post_types();
			$unknown   = array_diff( $submitted, $auditable );

			// A crafted POST cannot make the optimizer read a post type
			// this site does not otherwise allow: anything outside the
			// auditable list is refused outright, not silently dropped.
			if ( array() !== $unknown ) {
				wp_send_json_error( esc_html__( 'One or more of those post types are not available on this site.', 'check-for-broken-links' ), 400 );
			}

			$settings                   = get_option( 'wpcbl_check_for_broken_links_settings', array() );
			$settings['ilo_post_types'] = $submitted;
			update_option( 'wpcbl_check_for_broken_links_settings', $settings );

			wp_send_json_success( array( 'post_types' => $submitted ) );
		}

		/**
		 * Internal Link Optimizer: approve or skip one suggestion.
		 *
		 * @since 3.0.8
		 *
		 * @return void
		 */
		public function ilo_resolve() {
			$connect = $this->verify_seo_audit_request();

			$id     = isset( $_POST['suggestion_id'] ) ? sanitize_text_field( wp_unslash( $_POST['suggestion_id'] ) ) : '';
			$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

			if ( '' === $id || ! in_array( $status, array( 'pending', 'approved', 'skipped' ), true ) ) {
				wp_send_json_error( esc_html__( 'That suggestion could not be updated.', 'check-for-broken-links' ), 400 );
			}

			$this->send_rank_result( $connect->internal_links_request( 'POST', '/suggestions/' . rawurlencode( $id ), array( 'status' => $status ) ) );
		}

		/**
		 * Internal Link Optimizer: apply one suggestion to a post.
		 *
		 * Every refusal here is deliberate and reported as such. Nothing
		 * is guessed at, because a wrong guess silently edits the wrong
		 * sentence of a customer's page.
		 *
		 * $anchor and $sentence are the two fields a suggestion carries
		 * that get written into post_content verbatim (target_url goes
		 * through esc_url_raw() and esc_attr() already, in the applier).
		 * They come back from the SaaS API, not from this site's admin,
		 * so wp_kses_post() runs on both before either reaches the
		 * applier -- a compromised or malformed API response can not
		 * plant a script tag in a customer's page this way.
		 *
		 * If this method's guard order, status codes or messages ever
		 * change, tests/helpers/ilo-actions.php must change with it -- it
		 * mirrors this method for a standalone test that cannot load
		 * WordPress.
		 *
		 * @since 3.0.8
		 *
		 * @return void
		 */
		public function ilo_apply() {
			$connect = $this->verify_seo_audit_request();

			$id       = isset( $_POST['suggestion_id'] ) ? sanitize_text_field( wp_unslash( $_POST['suggestion_id'] ) ) : '';
			$post_id  = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
			$target   = isset( $_POST['target_url'] ) ? esc_url_raw( wp_unslash( $_POST['target_url'] ) ) : '';
			$anchor   = isset( $_POST['anchor_text'] ) ? wp_kses_post( wp_unslash( $_POST['anchor_text'] ) ) : '';
			$method   = isset( $_POST['method'] ) ? sanitize_text_field( wp_unslash( $_POST['method'] ) ) : '';
			$exist    = isset( $_POST['existing_text'] ) ? wp_unslash( $_POST['existing_text'] ) : '';
			$after    = isset( $_POST['insert_after'] ) ? wp_unslash( $_POST['insert_after'] ) : '';
			$sentence = isset( $_POST['insert_sentence'] ) ? wp_kses_post( wp_unslash( $_POST['insert_sentence'] ) ) : '';
			$source   = isset( $_POST['source_url'] ) ? esc_url_raw( wp_unslash( $_POST['source_url'] ) ) : '';

			if ( '' === $id || $post_id < 1 ) {
				wp_send_json_error( esc_html__( 'This suggestion does not point at a page on this site. Open it on your dashboard instead.', 'check-for-broken-links' ), 400 );
			}

			$post = get_post( $post_id );

			if ( ! $post ) {
				wp_send_json_error( esc_html__( 'That page no longer exists on this site.', 'check-for-broken-links' ), 404 );
			}

			// The post id comes from the API, which resolves a project by
			// DOMAIN. Two WordPress installs sharing one domain resolve to
			// the same project, so an id minted on install A can arrive
			// here on install B and point at a completely different post.
			// The suggestion's own source_url is what settles it: unless
			// this post's permalink IS that URL, this is not the page the
			// suggestion was written for, and shared boilerplate ("Read
			// more about our services") would match on it anyway.
			if ( ! self::ilo_same_url( get_permalink( $post_id ), $source ) ) {
				wp_send_json_error( esc_html__( 'That suggestion was written for a different page, so this one was left alone. Open it on your dashboard instead.', 'check-for-broken-links' ), 409 );
			}

			if ( 'publish' !== $post->post_status ) {
				wp_send_json_error( esc_html__( 'That page is not published, so it was left alone.', 'check-for-broken-links' ), 409 );
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				wp_send_json_error( esc_html__( 'You cannot edit that page.', 'check-for-broken-links' ), 403 );
			}

			// The server sends 'remove_duplicate' for this method
			// (SuggestionWriter, FixPromptWriter and DiffPreview all
			// agree on that name -- verified against the SaaS source).
			// 'unlink' is accepted too, at the cost of one extra
			// condition, as a guard against a payload from an older or
			// future server that used the plugin brief's original
			// (wrong) name instead.
			if ( 'remove_duplicate' === $method || 'unlink' === $method ) {
				$result = WPCBL_Check_Broken_Links_Link_Apply::unlink_text( $post->post_content, $exist );
			} elseif ( 'insert_sentence' === $method ) {
				$result = WPCBL_Check_Broken_Links_Link_Apply::insert_sentence( $post->post_content, $after, $sentence, $anchor, $target );
			} else {
				$result = WPCBL_Check_Broken_Links_Link_Apply::wrap_existing( $post->post_content, $exist, $target );
			}

			if ( ! $result['ok'] ) {
				wp_send_json_error( self::ilo_reason_message( $result['reason'] ), 422 );
			}

			$saved = wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $result['content'],
				),
				true
			);

			if ( is_wp_error( $saved ) ) {
				wp_send_json_error( esc_html__( 'The page could not be saved. Please try again.', 'check-for-broken-links' ), 500 );
			}

			update_post_meta( $post_id, '_wpcbl_ilo_undo_' . $id, $result['undo'] );

			// wp_update_post() only keeps a revision when the post type
			// supports the 'revisions' feature. The post-types picker lets
			// a site point the optimizer at any public type, and plenty of
			// custom post types ship without revisions in their supports
			// array, so this cannot be assumed true and has to be checked.
			$has_revisions = post_type_supports( $post->post_type, 'revisions' );

			// The dashboard and the plugin have to agree on a suggestion's
			// status. When this write-back does not land, the page still
			// has its link but the dashboard still lists the suggestion as
			// open, and the message below says so rather than claiming the
			// two are in sync.
			$reported = $connect->internal_links_request( 'POST', '/suggestions/' . rawurlencode( $id ), array( 'status' => 'applied' ) );

			wp_send_json_success(
				array(
					'status'  => 'applied',
					'message' => self::ilo_apply_message( self::ilo_synced( $reported ), $has_revisions ),
				)
			);
		}

		/**
		 * Internal Link Optimizer: put one applied suggestion back.
		 *
		 * If this method's guard order, status codes or messages ever
		 * change, tests/helpers/ilo-actions.php must change with it -- it
		 * mirrors this method for a standalone test that cannot load
		 * WordPress.
		 *
		 * @since 3.0.8
		 *
		 * @return void
		 */
		public function ilo_undo() {
			$connect = $this->verify_seo_audit_request();

			$id      = isset( $_POST['suggestion_id'] ) ? sanitize_text_field( wp_unslash( $_POST['suggestion_id'] ) ) : '';
			$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;

			if ( '' === $id || $post_id < 1 ) {
				wp_send_json_error( esc_html__( 'There is nothing to undo here.', 'check-for-broken-links' ), 400 );
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				wp_send_json_error( esc_html__( 'You cannot edit that page.', 'check-for-broken-links' ), 403 );
			}

			$undo = get_post_meta( $post_id, '_wpcbl_ilo_undo_' . $id, true );
			$post = get_post( $post_id );

			if ( ! is_array( $undo ) || ! $post ) {
				wp_send_json_error( esc_html__( 'There is nothing to undo here.', 'check-for-broken-links' ), 404 );
			}

			$result = WPCBL_Check_Broken_Links_Link_Apply::undo( $post->post_content, $undo );

			if ( ! $result['ok'] ) {
				wp_send_json_error( self::ilo_undo_failure_message( $post->post_type ), 422 );
			}

			$saved = wp_update_post( array( 'ID' => $post_id, 'post_content' => $result['content'] ), true );

			if ( is_wp_error( $saved ) ) {
				wp_send_json_error( esc_html__( 'The page could not be saved. Please try again.', 'check-for-broken-links' ), 500 );
			}

			delete_post_meta( $post_id, '_wpcbl_ilo_undo_' . $id );

			$reported = $connect->internal_links_request( 'POST', '/suggestions/' . rawurlencode( $id ), array( 'status' => 'approved' ) );

			wp_send_json_success(
				array(
					'status'  => 'approved',
					'message' => self::ilo_synced( $reported )
						? esc_html__( 'Link removed and the page put back.', 'check-for-broken-links' )
						: esc_html__( 'Link removed and the page put back. Your dashboard could not be updated, so it still shows this suggestion as applied.', 'check-for-broken-links' ),
				)
			);
		}

		/**
		 * Did a status write-back to the API actually land?
		 *
		 * If this method changes, tests/helpers/ilo-actions.php must
		 * change with it.
		 *
		 * @since 3.0.8
		 *
		 * @param mixed $result Return value of internal_links_request().
		 *
		 * @return bool
		 */
		private static function ilo_synced( $result ) {
			if ( is_wp_error( $result ) || ! is_array( $result ) || ! isset( $result['code'] ) ) {
				return false;
			}

			return $result['code'] >= 200 && $result['code'] < 300;
		}

		/**
		 * The apply success message, for all four combinations of
		 * whether the dashboard write-back landed and whether the post
		 * type keeps a revision.
		 *
		 * wp_update_post() only creates a revision when the post type
		 * supports the 'revisions' feature. The Internal Link Optimizer
		 * can be pointed at any public post type, and custom post types
		 * often ship without revisions, so the message must never claim
		 * one exists unless it actually does. The undo itself does not
		 * depend on a revision -- it restores from stored post meta -- so
		 * every variant still points the customer at the one click that
		 * puts the link back.
		 *
		 * If this method changes, tests/helpers/ilo-actions.php must
		 * change with it.
		 *
		 * @since 3.0.8
		 *
		 * @param bool $synced        Whether the status write-back landed.
		 * @param bool $has_revisions Whether the post type supports
		 *                            revisions.
		 *
		 * @return string
		 */
		private static function ilo_apply_message( $synced, $has_revisions ) {
			if ( $has_revisions ) {
				return $synced
					? esc_html__( 'Link added. WordPress kept a revision of the page.', 'check-for-broken-links' )
					: esc_html__( 'Link added and WordPress kept a revision of the page. Your dashboard could not be updated, so it still shows this suggestion as open.', 'check-for-broken-links' );
			}

			return $synced
				? esc_html__( 'Link added. You can undo it from this page.', 'check-for-broken-links' )
				: esc_html__( 'Link added. You can undo it from this page. Your dashboard could not be updated, so it still shows this suggestion as open.', 'check-for-broken-links' );
		}

		/**
		 * The undo failure message, for a post type with revisions and
		 * one without.
		 *
		 * The failure only fires when the stored fragment no longer
		 * matches the live content, so the automatic undo cannot run.
		 * On a post type with revisions, the revision browser is a real
		 * way back in. On one without, it is not, and pointing the
		 * customer at an empty revision browser would only confuse them,
		 * so this tells them plainly to edit the page directly instead.
		 *
		 * If this method changes, tests/helpers/ilo-actions.php must
		 * change with it.
		 *
		 * @since 3.0.8
		 *
		 * @param string $post_type The post type of the page being undone.
		 *
		 * @return string
		 */
		private static function ilo_undo_failure_message( $post_type ) {
			if ( post_type_supports( $post_type, 'revisions' ) ) {
				return esc_html__( 'This page changed since the link was added. Use the revision browser in the editor to put it back.', 'check-for-broken-links' );
			}

			return esc_html__( 'This page changed since the link was added. The automatic undo cannot be applied, so edit the page directly to remove the link.', 'check-for-broken-links' );
		}

		/**
		 * Are these two URLs the same page?
		 *
		 * A stored source_url and a live permalink can honestly differ on
		 * scheme (the site moved to https after the run) and on a trailing
		 * slash (permalink settings), and neither difference makes them a
		 * different page. Nothing else is normalised: case and www are
		 * left alone, because a host that differs there is a different
		 * install as far as this guard is concerned, and guessing is what
		 * this guard exists to stop.
		 *
		 * If this method changes, tests/helpers/ilo-actions.php must
		 * change with it.
		 *
		 * @since 3.0.8
		 *
		 * @param string $a First URL.
		 * @param string $b Second URL.
		 *
		 * @return bool
		 */
		private static function ilo_same_url( $a, $b ) {
			$a = self::ilo_normalize_url( $a );
			$b = self::ilo_normalize_url( $b );

			return '' !== $a && $a === $b;
		}

		/**
		 * Strip the scheme and any trailing slash from a URL.
		 *
		 * If this method changes, tests/helpers/ilo-actions.php must
		 * change with it.
		 *
		 * @since 3.0.8
		 *
		 * @param string $url URL to normalise.
		 *
		 * @return string
		 */
		private static function ilo_normalize_url( $url ) {
			$url = trim( (string) $url );

			if ( '' === $url ) {
				return '';
			}

			$url = preg_replace( '#^[a-z][a-z0-9+.-]*://#i', '', $url );

			return rtrim( (string) $url, '/' );
		}

		/**
		 * Turn a refusal code into something a person can act on.
		 *
		 * The default branch covers 'not_found' and 'no_paragraph_end'
		 * alike. Raw classic-editor content usually has no wrapping <p>
		 * tags at all (those are added at render time by wpautop, not
		 * stored), so insert_sentence() reports 'not_found' there rather
		 * than 'no_paragraph_end' even though the text is really on the
		 * page. The wording below deliberately names no specific cause
		 * (not "a theme or page builder", not "your editor") because it
		 * covers three different ones at once -- text truly absent, text
		 * generated by a builder, and plain classic-editor content that
		 * was never wrapped in a <p> tag -- and naming just one of them
		 * would read as flatly wrong for the other two.
		 *
		 * @since 3.0.8
		 *
		 * @param string $reason Reason code from the applier.
		 *
		 * @return string
		 */
		private static function ilo_reason_message( $reason ) {
			if ( 'already_linked' === $reason ) {
				return esc_html__( 'That text already links to this page. Nothing to do.', 'check-for-broken-links' );
			}

			if ( 'linked_elsewhere' === $reason ) {
				return esc_html__( 'That text already links somewhere else, so it was left alone.', 'check-for-broken-links' );
			}

			if ( 'anchor_not_in_sentence' === $reason ) {
				return esc_html__( 'The suggested sentence does not contain the anchor text. Add this link in the editor.', 'check-for-broken-links' );
			}

			return esc_html__( 'That text was not found where the plugin can safely edit it. Add this link in the editor instead.', 'check-for-broken-links' );
		}

		/**
		 * Shared guard for the AI Visibility endpoints: nonce + capability +
		 * connection. Reuses verify_seo_audit_request() rather than a
		 * separate copy, same as ilo_state() does -- the guard is
		 * identical, only the API root differs, and that root lives in
		 * ai_visibility_request(), not the guard.
		 *
		 * @since 3.0.8
		 *
		 * @return WPCBL_Check_Broken_Links_Connect
		 */
		private function verify_aiv_request() {
			return $this->verify_seo_audit_request();
		}

		/**
		 * AI Visibility: current overview, prompts and quota.
		 *
		 * Read-only against the SaaS: cadence (how often a reading is
		 * captured) is server-owned because each reading costs real money.
		 * aiv_refresh() below is the one deliberate, rate-limited
		 * exception ("Run check now"), not this action. Reads through the
		 * cached ai_visibility_state() wrapper, same as the Dashboard
		 * card, rather than hitting the SaaS on every page load.
		 *
		 * @since 3.0.8
		 *
		 * @return void
		 */
		public function aiv_state() {
			$connect = $this->verify_aiv_request();
			$this->send_rank_result( $connect->ai_visibility_state( ! empty( $_POST['fresh'] ) ) );
		}

		/**
		 * AI Visibility: start tracking one or more prompts.
		 *
		 * @since 3.0.8
		 *
		 * @return void
		 */
		public function aiv_add_prompts() {
			$connect = $this->verify_aiv_request();
			$prompts = isset( $_POST['prompts'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompts'] ) ) : '';

			if ( '' === $prompts ) {
				wp_send_json_error( esc_html__( 'Enter at least one prompt.', 'check-for-broken-links' ), 400 );
			}

			// The prompt count feeds both the page and the Dashboard
			// card's "tracking" state -- drop the cache now so neither
			// shows a stale count for up to five minutes.
			$connect->flush_ai_visibility_state();
			$this->send_rank_result( $connect->ai_visibility_request( 'POST', '/prompts', array( 'prompts' => $prompts ) ) );
		}

		/**
		 * AI Visibility: stop tracking one prompt.
		 *
		 * @since 3.0.8
		 *
		 * @return void
		 */
		public function aiv_delete_prompt() {
			$connect = $this->verify_aiv_request();
			$id      = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

			if ( ! $id ) {
				wp_send_json_error( esc_html__( 'Missing prompt.', 'check-for-broken-links' ), 400 );
			}

			$connect->flush_ai_visibility_state();
			$this->send_rank_result( $connect->ai_visibility_request( 'DELETE', '/prompts/' . $id ) );
		}

		/**
		 * AI Visibility: "Run check now". A deliberate, rate-limited
		 * exception to the read-only rule on the other three AI
		 * Visibility actions above -- at most one manual capture per
		 * project per calendar day, enforced server-side
		 * (AiVisibilityService::requestManualMentionsRefresh() on
		 * brokenlinkchecker.io), never trusted here.
		 *
		 * Not routed through send_rank_result(): that helper's generic
		 * "Something went wrong" fallback is wrong for 429 (the day's
		 * manual check is already spent) and 404 (the feature is off for
		 * this account), so both get their own honest copy when the SaaS
		 * does not supply a message of its own. 202 (accepted) still goes
		 * through wp_send_json_success like every other successful call.
		 *
		 * @since 3.0.8
		 *
		 * @return void
		 */
		public function aiv_refresh() {
			$connect = $this->verify_aiv_request();
			$result  = $connect->ai_visibility_request( 'POST', '/refresh' );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( esc_html__( 'brokenlinkchecker.io is not reachable right now. Try again in a minute.', 'check-for-broken-links' ), 502 );
			}

			$code    = $result['code'];
			$message = isset( $result['data']['message'] ) ? (string) $result['data']['message'] : '';

			if ( 202 === $code ) {
				// The reading itself lands minutes from now, not on this
				// response -- drop the cache so the next state load does
				// not hold a stale prompt count, even though the overview
				// will not show the new reading until it actually lands.
				$connect->flush_ai_visibility_state();
				wp_send_json_success( array(
					'message' => '' !== $message ? $message : esc_html__( 'Checking now. New results arrive in a few minutes.', 'check-for-broken-links' ),
				) );
			}

			if ( 429 === $code ) {
				wp_send_json_error( '' !== $message ? $message : esc_html__( 'You already ran a check today. The next manual check is available tomorrow.', 'check-for-broken-links' ), 429 );
			}

			if ( 404 === $code ) {
				wp_send_json_error( esc_html__( 'Run check now is not available for this account yet.', 'check-for-broken-links' ), 404 );
			}

			wp_send_json_error( '' !== $message ? $message : esc_html__( 'Something went wrong. Please try again.', 'check-for-broken-links' ), $code );
		}

		/**
		 * Pull the given uptime monitor fields out of $_POST, wp_unslash()ed,
		 * for wpcbl_uptime_sanitize_payload(). A key absent from $_POST is
		 * left out entirely so the sanitizer (and the SaaS) can tell "not
		 * sent" apart from "sent empty".
		 *
		 * @since 3.0.6
		 *
		 * @param string[] $keys Field names to look for in $_POST.
		 *
		 * @return array
		 */
		private function posted_uptime_fields( $keys ) {
			$fields = array();

			foreach ( $keys as $key ) {
				if ( ! isset( $_POST[ $key ] ) ) {
					continue;
				}
				$fields[ $key ] = wp_unslash( $_POST[ $key ] );
			}

			return $fields;
		}

		/**
		 * Upgrade page: change the account's plan tier (prorated upgrade or
		 * credited downgrade, same as the dashboard billing page). On success
		 * the cached entitlements and rank state are dropped so the new plan
		 * quotas show right away.
		 *
		 * @since 3.0.5
		 *
		 * @return void
		 */
		public function billing_change_plan() {
			$connect  = $this->verify_rank_request();
			$plan     = isset( $_POST['plan'] ) ? sanitize_key( $_POST['plan'] ) : '';
			$interval = isset( $_POST['interval'] ) ? sanitize_key( $_POST['interval'] ) : '';
			$keywords = isset( $_POST['keywords'] ) ? absint( $_POST['keywords'] ) : 0;
			$daily    = isset( $_POST['daily'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['daily'] ) );
			$ai       = isset( $_POST['ai'] ) ? absint( $_POST['ai'] ) : 0;

			if ( ! in_array( $plan, array( 'personal', 'business', 'agency' ), true )
				|| ! in_array( $interval, array( 'monthly', 'yearly' ), true ) ) {
				wp_send_json_error( esc_html__( 'Invalid plan selection.', 'check-for-broken-links' ), 400 );
			}

			$result = $connect->billing_change_plan( $plan, $interval, $keywords, $daily, $ai );

			if ( ! is_wp_error( $result ) && 200 === $result['code'] && ! empty( $result['data']['ok'] ) ) {
				delete_transient( WPCBL_Check_Broken_Links_Connect::TRANSIENT_ENT );
				$connect->flush_rank_state();
				$connect->flush_billing_shape();
			}

			$this->send_rank_result( $result );
		}

		/**
		 * Shared guard for the link-action endpoints: nonce + capability.
		 *
		 * @since 3.0.0
		 *
		 * @return void
		 */
		private function verify_link_action_request() {
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpcbl_check_for_broken_links' ) ) {
				wp_die( esc_html__( 'Cheatin&#8217; huh?', 'check-for-broken-links' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( esc_html__( 'You do not have permission to manage links.', 'check-for-broken-links' ), 403 );
			}
		}

		/**
		 * Update a post's content without touching post_modified.
		 *
		 * @since 3.0.0
		 *
		 * @param int    $post_id     The post ID.
		 * @param string $new_content The new post content.
		 *
		 * @return bool
		 */
		private function update_content_preserving_modified( $post_id, $new_content ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return false;
			}

			$keep_modified = function ( $data, $postarr ) use ( $post ) {
				if ( isset( $postarr['ID'] ) && (int) $postarr['ID'] === (int) $post->ID ) {
					$data['post_modified']     = $post->post_modified;
					$data['post_modified_gmt'] = $post->post_modified_gmt;
				}
				return $data;
			};

			add_filter( 'wp_insert_post_data', $keep_modified, 10, 2 );
			$result = wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $new_content,
				),
				true
			);
			remove_filter( 'wp_insert_post_data', $keep_modified, 10 );

			return ! is_wp_error( $result ) && $result;
		}

		/**
		 * Remove (or re-URL) entries for a URL in the stored scan results.
		 *
		 * @since 3.0.0
		 *
		 * @param string      $url     The URL to match.
		 * @param string|null $new_url Replacement URL, or null to drop the entries.
		 * @param int|null    $post_id Limit to entries found in this post; null matches all.
		 *
		 * @return void
		 */
		private function update_stored_results( $url, $new_url = null, $post_id = null ) {
			$links = get_option( 'wpcbl_check_for_broken_links_links', array() );

			foreach ( array( 'broken', 'warning', 'good', 'redirect' ) as $bucket ) {
				if ( empty( $links[ $bucket ] ) || ! is_array( $links[ $bucket ] ) ) {
					continue;
				}

				foreach ( $links[ $bucket ] as $index => $entry ) {
					if ( ! isset( $entry['link'] ) || $entry['link'] !== $url ) {
						continue;
					}
					if ( null !== $post_id && ( ! isset( $entry['ID'] ) || (int) $entry['ID'] !== (int) $post_id ) ) {
						continue;
					}
					if ( null === $new_url ) {
						unset( $links[ $bucket ][ $index ] );
					} else {
						$links[ $bucket ][ $index ]['link'] = $new_url;
					}
				}

				$links[ $bucket ] = array_values( $links[ $bucket ] );
			}

			update_option( 'wpcbl_check_for_broken_links_links', $links );
			$this->refresh_summary_counts( $links );
		}

		/**
		 * Keep the stat cards honest after rows are removed or reclassified.
		 *
		 * @since 3.0.0
		 *
		 * @param array $links The stored links option value.
		 *
		 * @return void
		 */
		private function refresh_summary_counts( $links ) {
			$summary = get_option( 'wpcbl_last_scan_summary' );
			if ( is_array( $summary ) ) {
				$summary['broken'] = isset( $links['broken'] ) ? count( $links['broken'] ) : 0;
				update_option( 'wpcbl_last_scan_summary', $summary );
			}
		}

		/**
		 * Append a fresh entry to the stored results in its status bucket.
		 *
		 * @since 3.0.0
		 *
		 * @param array $entry The result entry (type key decides the bucket).
		 *
		 * @return void
		 */
		private function append_stored_result( $entry ) {
			$bucket = isset( $entry['type'] ) ? $entry['type'] : 'broken';
			$links  = get_option( 'wpcbl_check_for_broken_links_links', array() );

			if ( ! isset( $links[ $bucket ] ) || ! is_array( $links[ $bucket ] ) ) {
				$links[ $bucket ] = array();
			}
			$links[ $bucket ][] = $entry;

			update_option( 'wpcbl_check_for_broken_links_links', $links );
			$this->refresh_summary_counts( $links );
		}

		/**
		 * Replace a link URL inside a post's content (Edit URL row action).
		 *
		 * @since 3.0.0
		 *
		 * @return void
		 */
		public function edit_link_url() {
			$this->verify_link_action_request();

			$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
			$old_url = $this->posted_link_url( 'old_url' );
			$new_url = isset( $_POST['new_url'] ) ? esc_url_raw( wp_unslash( $_POST['new_url'] ) ) : '';
			$fix_all = isset( $_POST['fix_all'] ) && '1' === $_POST['fix_all'];

			if ( ! $post_id || '' === $old_url || '' === $new_url ) {
				wp_send_json_error( esc_html__( 'Missing data for the URL update.', 'check-for-broken-links' ) );
			}

			$post = get_post( $post_id );
			if ( ! $post || false === WPCBL_Check_Broken_Links_Utilities::find_url_in_content( $post->post_content, $old_url ) ) {
				wp_send_json_error( esc_html__( 'The URL was not found in the post content. It may live in a builder or slider field.', 'check-for-broken-links' ) );
			}

			// "Fix all similar": replace the URL in every published post that
			// contains it, not only the row's post.
			$post_ids = array( $post_id );
			if ( $fix_all ) {
				global $wpdb;
				$like         = '%' . $wpdb->esc_like( $old_url ) . '%';
				$like_encoded = '%' . $wpdb->esc_like( str_replace( '&', '&amp;', $old_url ) ) . '%';
				$post_ids     = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND (post_content LIKE %s OR post_content LIKE %s) LIMIT 200", $like, $like_encoded ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot content search across posts; WP_Query cannot match post_content substrings.
				$post_ids     = array_map( 'absint', (array) $post_ids );
				if ( ! in_array( $post_id, $post_ids, true ) ) {
					$post_ids[] = $post_id;
				}
			}

			// Re-check the new URL once so its rows land in the right bucket:
			// gone from the table when healthy, correct badge when not.
			$new_status = WPCBL_Check_Broken_Links_Utilities::check_url_status_code( $new_url, false, $post_id );

			$updated = 0;
			foreach ( $post_ids as $update_id ) {
				$update_post = get_post( $update_id );
				if ( ! $update_post ) {
					continue;
				}
				$match = WPCBL_Check_Broken_Links_Utilities::find_url_in_content( $update_post->post_content, $old_url );
				if ( false === $match ) {
					continue;
				}
				// Keep the replacement in the same encoding as the match.
				$replacement = $match === $old_url ? $new_url : str_replace( '&', '&amp;', $new_url );
				if ( $this->update_content_preserving_modified( $update_id, str_replace( $match, $replacement, $update_post->post_content ) ) ) {
					++$updated;
					$this->update_stored_results( $old_url, null, $update_id );

					if ( in_array( $new_status['type'], array( 'broken', 'warning' ), true ) ) {
						$new_status['ID']          = $update_id;
						$new_status['link_source'] = WPCBL_Check_Broken_Links_Utilities::get_link_source( $new_url );
						$new_status['element']     = 'link';
						$new_status['detected_at'] = WPCBL_Check_Broken_Links_Utilities::format_scan_datetime( null, 'F j, Y', wpcbl_get_option( 'scan_timezone', wp_timezone_string() ) );
						$this->append_stored_result( $new_status );
					}
				}
			}

			if ( 0 === $updated ) {
				wp_send_json_error( esc_html__( 'Could not update the post.', 'check-for-broken-links' ) );
			}

			wp_send_json_success( array( 'updated' => $updated, 'new_status' => $new_status['type'] ) );
		}

		/**
		 * Remove the a-tag around a URL but keep the anchor text (Unlink row action).
		 *
		 * @since 3.0.0
		 *
		 * @return void
		 */
		public function unlink() {
			$this->verify_link_action_request();

			$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
			$url     = $this->posted_link_url( 'url' );

			if ( ! $post_id || '' === $url ) {
				wp_send_json_error( esc_html__( 'Missing data for the unlink action.', 'check-for-broken-links' ) );
			}

			$post = get_post( $post_id );
			if ( ! $post ) {
				wp_send_json_error( esc_html__( 'Post not found.', 'check-for-broken-links' ) );
			}

			$match = WPCBL_Check_Broken_Links_Utilities::find_url_in_content( $post->post_content, $url );
			if ( false === $match ) {
				wp_send_json_error( esc_html__( 'The link was not found in the post content. It may live in a builder or slider field.', 'check-for-broken-links' ) );
			}

			$pattern     = '/<a\b[^>]*href=["\']' . preg_quote( $match, '/' ) . '["\'][^>]*>(.*?)<\/a>/is';
			$new_content = preg_replace( $pattern, '$1', $post->post_content, -1, $replacements );

			if ( null === $new_content || 0 === $replacements ) {
				wp_send_json_error( esc_html__( 'The link was not found in the post content. It may live in a builder or slider field.', 'check-for-broken-links' ) );
			}

			if ( ! $this->update_content_preserving_modified( $post_id, $new_content ) ) {
				wp_send_json_error( esc_html__( 'Could not update the post.', 'check-for-broken-links' ) );
			}

			$this->update_stored_results( $url );

			wp_send_json_success();
		}

		/**
		 * Whitelist a URL (Not broken row action): removed from results and
		 * skipped in future scans.
		 *
		 * @since 3.0.0
		 *
		 * @return void
		 */
		public function not_broken() {
			$this->verify_link_action_request();

			$url = $this->posted_link_url( 'url' );
			if ( '' === $url ) {
				wp_send_json_error( esc_html__( 'Missing URL.', 'check-for-broken-links' ) );
			}

			$dismissed = (array) get_option( 'wpcbl_dismissed_urls', array() );
			if ( ! in_array( $url, $dismissed, true ) ) {
				$dismissed[] = $url;
				update_option( 'wpcbl_dismissed_urls', $dismissed, false );
			}

			$this->update_stored_results( $url );

			wp_send_json_success();
		}

		/**
		 * Hide a URL from the current results only (Dismiss row action).
		 * Re-appears if the next scan still finds it broken.
		 *
		 * @since 3.0.0
		 *
		 * @return void
		 */
		public function dismiss_link() {
			$this->verify_link_action_request();

			$url = $this->posted_link_url( 'url' );
			if ( '' === $url ) {
				wp_send_json_error( esc_html__( 'Missing URL.', 'check-for-broken-links' ) );
			}

			$session_dismissed = (array) get_option( 'wpcbl_session_dismissed', array() );
			if ( ! in_array( $url, $session_dismissed, true ) ) {
				$session_dismissed[] = $url;
				update_option( 'wpcbl_session_dismissed', $session_dismissed, false );
			}

			wp_send_json_success();
		}

		/**
		 * Clear cached link statuses and dismissed-only entries, keeping the
		 * "Not broken" whitelist. The JS then starts a fresh manual scan.
		 *
		 * @since 3.0.0
		 *
		 * @return void
		 */
		public function recheck_all() {
			$this->verify_link_action_request();

			delete_option( 'wpcbl_check_for_broken_links_links' );
			delete_option( 'wpcbl_session_dismissed' );

			wp_send_json_success();
		}

		/**
		 * Report live scan progress, polled by the admin JS while a scan runs.
		 *
		 * @since 3.0.0
		 *
		 * @return void
		 */
		public function scan_progress() {
			// Check for nonce security.
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpcbl_check_for_broken_links' ) ) {
				wp_die( esc_html__( 'Cheatin&#8217; huh?', 'check-for-broken-links' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( '', 403 );
			}

			wp_send_json_success( get_option( 'wpcbl_scan_progress', null ) );
		}

		/**
		 * Permanently dismiss the TTSWP cross-promotion banner for this user.
		 *
		 * Stored as user meta (not a transient) so it never comes back.
		 *
		 * @since 3.0.0
		 *
		 * @return void
		 */
		public function dismiss_ttswp_banner() {
			// Check for nonce security.
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpcbl_check_for_broken_links' ) ) {
				wp_die( esc_html__( 'Cheatin&#8217; huh?', 'check-for-broken-links' ) );
			}

			update_user_meta( get_current_user_id(), 'wpcbl_ttswp_banner_dismissed', 1 );

			wp_send_json_success();
		}

		/**
		 * Clear the stored scan results and last-scan summary.
		 *
		 * Settings are left untouched; the Scan Results page falls back to
		 * its empty state until the next scan runs.
		 *
		 * @since 3.0.0
		 *
		 * @return void
		 */
		public function clear_scan_results() {
			// Check for nonce security.
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpcbl_check_for_broken_links' ) ) {
				wp_die( esc_html__( 'Cheatin&#8217; huh?', 'check-for-broken-links' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( esc_html__( 'You do not have permission to clear scan results.', 'check-for-broken-links' ), 403 );
			}

			delete_option( 'wpcbl_check_for_broken_links_links' );
			delete_option( 'wpcbl_last_scan_summary' );
			delete_option( 'wpcbl_ai_fix_batch' );

			wp_send_json_success();
		}

		/**
		 * Manual scan.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function manual_scan() {
			// Check for nonce security.
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpcbl_check_for_broken_links' ) ) {
				wp_die( esc_html__( 'Cheatin&#8217; huh?', 'check-for-broken-links' ) );
			}

			// Scanning is an admin-only, expensive operation.
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( esc_html__( 'You do not have permission to run a scan.', 'check-for-broken-links' ), 403 );
			}

			if ( isset( $_POST ) && isset( $_POST['action'] ) && 'wpcbl_broken_links_manual_scan' === $_POST['action'] ) {
				// Run the scan.
				WPCBL_Check_Broken_Links_Utilities::process_scan( false, 'manual' );

				// Get the links from the db.
				$links        = get_option( 'wpcbl_check_for_broken_links_links', array() );
				$broken_links = isset( $links['broken'] ) ? $links['broken'] : array();

				// Create a new instance of the table class.
				$broken_links_table = new WPCBL_Check_Broken_Links_Admin_Links_List_Table();
				$broken_links_table->prepare_items();

				// Capture the output of the display method.
				ob_start();

				// If there are no broken links, return a message.
				if ( empty( $broken_links ) ) {
					include_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/views/no-broken-links.php';
				}

				echo '<form method="get">';
				$broken_links_table->display();
				echo '</form>';
				$table_html = ob_get_clean();

				// Return the table HTML in the AJAX response.
				wp_send_json_success( array( 'table_html' => $table_html, 'summary' => get_option( 'wpcbl_last_scan_summary' ) ) );
			}

			die();
		}
	}

	return new WPCBL_Check_Broken_Links_Admin_Ajax();

endif;
