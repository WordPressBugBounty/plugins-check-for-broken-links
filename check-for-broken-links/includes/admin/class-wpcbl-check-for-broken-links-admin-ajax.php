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
