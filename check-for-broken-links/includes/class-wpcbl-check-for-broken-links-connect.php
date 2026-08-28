<?php
/**
 * Connect client: links this site to a brokenlinkchecker.io account and
 * mirrors its Pro entitlements. Mirrors the TTSWP-Connect handshake.
 *
 * @package WPCBL_Check_Broken_Links
 * @author Norse Digital Group LLC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WPCBL_Check_Broken_Links_Connect' ) ) :

	/**
	 * The connect client.
	 *
	 * @since 3.1.0
	 */
	class WPCBL_Check_Broken_Links_Connect {

		const APP_BASE          = 'https://brokenlinkchecker.io';
		const OPT_TOKEN         = 'wpcbl_site_token';
		const OPT_CONNECTION    = 'wpcbl_connection';
		const OPT_GRACE         = 'wpcbl_entitlements_grace';
		const TRANSIENT_ENT     = 'wpcbl_entitlements';
		const TRANSIENT_NONCE   = 'wpcbl_connect_nonce';
		const TRANSIENT_RANK    = 'wpcbl_rank_state';
		const TRANSIENT_UPTIME  = 'wpcbl_uptime_state';
		const TRANSIENT_PLANS   = 'wpcbl_plans_cache_v2';
		const TRANSIENT_SHAPE   = 'wpcbl_billing_shape';
		const TRANSIENT_AUDIT   = 'wpcbl_seo_audit_state';
		const TRANSIENT_ILO     = 'wpcbl_ilo_state';
		const TRANSIENT_AIV     = 'wpcbl_ai_visibility_state';
		const ENT_TTL           = 12 * HOUR_IN_SECONDS;
		const GRACE_TTL         = 14 * DAY_IN_SECONDS;
		const NONCE_TTL         = 10 * MINUTE_IN_SECONDS;

		/**
		 * Hook the has_pro filter and the throttled entitlements poll.
		 *
		 * @since 3.1.0
		 */
		public function __construct() {
			add_filter( 'wpcbl_has_pro', array( $this, 'filter_has_pro' ) );
			add_action( 'admin_init', array( $this, 'poll_entitlements' ) );
			$this->register_admin_post();
		}

		/**
		 * Wire the handshake admin-post endpoints.
		 *
		 * @since 3.1.0
		 *
		 * @return void
		 */
		public function register_admin_post() {
			add_action( 'admin_post_wpcbl_connect_start', array( $this, 'handle_connect_start' ) );
			add_action( 'admin_post_wpcbl_checkout_start', array( $this, 'handle_checkout_start' ) );
			add_action( 'admin_post_wpcbl_connect_return', array( $this, 'handle_connect_return' ) );
			add_action( 'admin_post_wpcbl_disconnect', array( $this, 'handle_disconnect' ) );
			add_action( 'admin_post_wpcbl_refresh', array( $this, 'handle_refresh' ) );
		}

		/**
		 * Refresh the connection now: drop the 12h entitlements cache and
		 * re-poll the SaaS, so a plan change shows up without the wait.
		 *
		 * @since 3.0.4
		 *
		 * @return void
		 */
		public function handle_refresh() {
			$this->authorize( 'wpcbl_refresh' );

			if ( ! $this->is_connected() ) {
				wp_safe_redirect( $this->settings_url() );
				exit;
			}

			delete_transient( self::TRANSIENT_ENT );
			$this->poll_entitlements();

			wp_safe_redirect( $this->settings_url( 'refreshed' ) );
			exit;
		}

		/**
		 * Build an absolute brokenlinkchecker.io URL.
		 *
		 * @param string $path Leading-slash path.
		 *
		 * @since 3.1.0
		 *
		 * @return string
		 */
		public function app_url( $path = '' ) {
			return self::APP_BASE . $path;
		}

		/**
		 * Whether this site holds a token.
		 *
		 * @since 3.1.0
		 *
		 * @return bool
		 */
		public function is_connected() {
			return '' !== (string) get_option( self::OPT_TOKEN, '' );
		}

		/**
		 * Stored connection metadata for display.
		 *
		 * @since 3.1.0
		 *
		 * @return array
		 */
		public function get_connection() {
			$connection = get_option( self::OPT_CONNECTION, array() );

			return is_array( $connection ) ? $connection : array();
		}

		/**
		 * Effective entitlements: fresh 12h cache if present, else the grace
		 * copy while it is within the 14-day window, else empty.
		 *
		 * @since 3.1.0
		 *
		 * @return array
		 */
		public function get_entitlements() {
			$fresh = get_transient( self::TRANSIENT_ENT );

			if ( is_array( $fresh ) ) {
				return $fresh;
			}

			$grace = get_option( self::OPT_GRACE, array() );

			if ( is_array( $grace ) && ! empty( $grace['data'] ) && isset( $grace['saved_at'] )
				&& ( time() - (int) $grace['saved_at'] ) <= self::GRACE_TTL ) {
				return $grace['data'];
			}

			return array();
		}

		/**
		 * The wpcbl_has_pro filter: true when effective entitlements say pro.
		 * Reads only — never makes a network call inside the filter.
		 *
		 * @param bool $has_pro Incoming value.
		 *
		 * @since 3.1.0
		 *
		 * @return bool
		 */
		public function filter_has_pro( $has_pro ) {
			$entitlements = $this->get_entitlements();

			return ! empty( $entitlements['pro'] ) ? true : (bool) $has_pro;
		}

		/**
		 * Persist a freshly-fetched entitlements payload to both the 12h cache
		 * and the grace mirror.
		 *
		 * @param array $data Entitlements response.
		 *
		 * @since 3.1.0
		 *
		 * @return void
		 */
		private function store_entitlements( $data ) {
			set_transient( self::TRANSIENT_ENT, $data, self::ENT_TTL );
			update_option( self::OPT_GRACE, array( 'data' => $data, 'saved_at' => time() ), false );
		}

		/**
		 * Store the token + connection after a successful exchange.
		 *
		 * @param string $token             Permanent site token.
		 * @param array  $exchange_response Decoded exchange response.
		 *
		 * @since 3.1.0
		 *
		 * @return void
		 */
		public function store_token( $token, $exchange_response ) {
			update_option( self::OPT_TOKEN, $token, false );

			$site = isset( $exchange_response['site'] ) ? $exchange_response['site'] : array();

			// email + plan come from the exchange response (Task 1 SaaS change);
			// plan is later kept fresh by poll_entitlements().
			update_option(
				self::OPT_CONNECTION,
				array(
					'email'        => isset( $exchange_response['email'] ) ? $exchange_response['email'] : '',
					'plan'         => isset( $exchange_response['plan'] ) ? $exchange_response['plan'] : 'free',
					'connected_at' => isset( $site['connected_at'] ) ? $site['connected_at'] : '',
				),
				false
			);
		}

		/**
		 * Forget everything about the connection (local only).
		 *
		 * @since 3.1.0
		 *
		 * @return void
		 */
		public function clear_connection() {
			delete_option( self::OPT_TOKEN );
			delete_option( self::OPT_CONNECTION );
			delete_option( self::OPT_GRACE );
			delete_transient( self::TRANSIENT_ENT );
			$this->flush_rank_state();
			$this->flush_uptime_state();
			$this->flush_seo_audit_state();
		}

		/**
		 * Fetch entitlements at most once per 12h (guarded by the cache's
		 * presence). Success refreshes cache + grace and syncs the stored
		 * plan; a 401 means the link is dead, so clear everything; network or
		 * 5xx errors are ignored (grace keeps Pro alive up to 14 days).
		 *
		 * @since 3.1.0
		 *
		 * @return void
		 */
		public function poll_entitlements() {
			if ( ! $this->is_connected() ) {
				return;
			}

			// The 12h transient doubles as the throttle: skip while it lives.
			if ( false !== get_transient( self::TRANSIENT_ENT ) ) {
				return;
			}

			$response = wp_remote_get(
				$this->app_url( '/api/v1/site/entitlements' ),
				array(
					'timeout' => 10,
					'headers' => $this->api_headers(),
				)
			);

			if ( is_wp_error( $response ) ) {
				return; // Offline — grace covers it.
			}

			$code = (int) wp_remote_retrieve_response_code( $response );

			if ( 401 === $code ) {
				$this->clear_connection();
				return;
			}

			if ( 200 !== $code ) {
				return; // 5xx / unexpected — grace covers it.
			}

			$data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! is_array( $data ) || ! isset( $data['plan'] ) ) {
				return;
			}

			$this->store_entitlements( $data );

			// Keep the displayed plan in sync with the source of truth.
			$connection = $this->get_connection();
			if ( $connection ) {
				$connection['plan'] = $data['plan'];
				update_option( self::OPT_CONNECTION, $connection, false );
			}

			// Fallback pickup for dashboard-initiated fixes when the ping
			// never arrived (REST blocked, site offline at the time).
			do_action( 'wpcbl_entitlements_refreshed' );
		}

		/**
		 * The Settings-page URL we send users back to after any handshake.
		 *
		 * @param string $notice Optional notice slug appended as a query arg.
		 *
		 * @since 3.1.0
		 *
		 * @return string
		 */
		private function settings_url( $notice = '' ) {
			$url = admin_url( 'admin.php?page=wpcbl-check-for-broken-links-settings' );

			return $notice ? add_query_arg( 'wpcbl_connect', $notice, $url ) : $url;
		}

		/**
		 * Store a fresh CSRF transient and return its nonce. This is the
		 * TTSWP 3.3.6 fix: the transient is written BEFORE we redirect out, and
		 * the return handler verifies the echoed nonce against it.
		 *
		 * @since 3.1.0
		 *
		 * @return string
		 */
		private function prime_handshake_nonce() {
			$nonce = wp_generate_password( 32, false );
			set_transient( self::TRANSIENT_NONCE, $nonce, self::NONCE_TTL );

			return $nonce;
		}

		/**
		 * The SaaS return URL the plugin advertises during the handshake.
		 *
		 * @since 3.1.0
		 *
		 * @return string
		 */
		private function return_url() {
			return admin_url( 'admin-post.php?action=wpcbl_connect_return' );
		}

		/**
		 * Guard every handler: capability + local WP nonce.
		 *
		 * @param string $action WP nonce action.
		 *
		 * @since 3.1.0
		 *
		 * @return void
		 */
		private function authorize( $action ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You are not allowed to do this.', 'check-for-broken-links' ), '', array( 'response' => 403 ) );
			}
			check_admin_referer( $action );
		}

		/**
		 * Connect this site: prime the CSRF transient, then redirect to the
		 * SaaS approval screen.
		 *
		 * @since 3.1.0
		 *
		 * @return void
		 */
		public function handle_connect_start() {
			$this->authorize( 'wpcbl_connect_start' );

			$nonce = $this->prime_handshake_nonce();

			$url = add_query_arg(
				array(
					'site'       => rawurlencode( home_url() ),
					'return_url' => rawurlencode( $this->return_url() ),
					'nonce'      => $nonce,
				),
				$this->app_url( '/connect' )
			);

			wp_redirect( $url );
			exit;
		}

		/**
		 * Buy a plan: prime the CSRF transient, then redirect to Stripe-hosted
		 * checkout with connect params so payment chains into approval.
		 *
		 * @since 3.1.0
		 *
		 * @return void
		 */
		public function handle_checkout_start() {
			$this->authorize( 'wpcbl_checkout_start' );

			// $_REQUEST, not $_GET: the Upgrade-page plan cards submit a POST
			// form to admin-post.php, so the plan arrives in $_POST. Reading
			// $_GET only made every upgrade click die with "Unknown plan.".
			$plan  = isset( $_REQUEST['plan'] ) ? sanitize_key( wp_unslash( $_REQUEST['plan'] ) ) : '';
			$plans = array( 'personal', 'business', 'agency' );

			if ( ! in_array( $plan, $plans, true ) ) {
				wp_die( esc_html__( 'Unknown plan.', 'check-for-broken-links' ), '', array( 'response' => 400 ) );
			}

			$interval = isset( $_REQUEST['interval'] ) ? sanitize_key( wp_unslash( $_REQUEST['interval'] ) ) : '';
			$daily    = isset( $_REQUEST['daily'] ) && '1' === sanitize_text_field( wp_unslash( $_REQUEST['daily'] ) );
			$keywords = isset( $_REQUEST['keywords'] ) ? absint( $_REQUEST['keywords'] ) : 0;
			$ai       = isset( $_REQUEST['ai'] ) ? absint( $_REQUEST['ai'] ) : 0;

			$nonce = $this->prime_handshake_nonce();

			$args = array(
				'site'       => rawurlencode( home_url() ),
				'return_url' => rawurlencode( $this->return_url() ),
				'nonce'      => $nonce,
			);
			if ( 'monthly' === $interval ) {
				$args['interval'] = 'monthly';
			}
			if ( $daily ) {
				$args['daily'] = '1';
			}
			// The SaaS validates the tier against the plan; unknown values 404
			// there rather than silently falling back.
			if ( $keywords > 0 ) {
				$args['keywords'] = (string) $keywords;
			}
			if ( $ai > 0 ) {
				$args['ai'] = (string) $ai;
			}

			$url = add_query_arg( $args, $this->app_url( '/checkout/' . $plan ) );

			wp_redirect( $url );
			exit;
		}

		/**
		 * Handshake return: verify the echoed nonce against the transient,
		 * exchange the one-time code for the permanent token, store it.
		 *
		 * @since 3.1.0
		 *
		 * @return void
		 */
		public function handle_connect_return() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You are not allowed to do this.', 'check-for-broken-links' ), '', array( 'response' => 403 ) );
			}

			$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
			$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
			$saved = get_transient( self::TRANSIENT_NONCE );
			delete_transient( self::TRANSIENT_NONCE );

			if ( '' === $code || '' === $nonce || ! $saved || ! hash_equals( (string) $saved, $nonce ) ) {
				wp_safe_redirect( $this->settings_url( 'error' ) );
				exit;
			}

			$response = wp_remote_post(
				$this->app_url( '/api/v1/connect/exchange' ),
				array(
					'timeout' => 15,
					'headers' => array(
						'Accept'          => 'application/json',
						'X-WPCBL-Version' => defined( 'WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION' ) ? WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION : '',
					),
					'body'    => array(
						'code'     => $code,
						'site_url' => home_url(),
						'name'     => get_bloginfo( 'name' ),
					),
				)
			);

			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				wp_safe_redirect( $this->settings_url( 'error' ) );
				exit;
			}

			$data = json_decode( wp_remote_retrieve_body( $response ), true );

			// Confirm the token is for THIS site before trusting it. Both
			// sides are normalized the same way the SaaS normalizes site URLs
			// (lowercase scheme+host, no trailing slash) so an uppercase host
			// in home_url() can't fail an otherwise-valid match.
			if ( ! is_array( $data ) || empty( $data['token'] )
				|| empty( $data['site']['url'] )
				|| $this->normalize_site_url( $data['site']['url'] ) !== $this->normalize_site_url( home_url() ) ) {
				wp_safe_redirect( $this->settings_url( 'error' ) );
				exit;
			}

			$this->store_token( $data['token'], $data );
			$this->poll_entitlements_now();

			wp_safe_redirect( $this->settings_url( 'connected' ) );
			exit;
		}

		/**
		 * Normalize a site URL the same way the SaaS does: lowercase scheme +
		 * host, strip the trailing slash. Idempotent on already-normalized
		 * input.
		 *
		 * @param string $url URL to normalize.
		 *
		 * @since 3.1.0
		 *
		 * @return string
		 */
		private function normalize_site_url( $url ) {
			$parts  = wp_parse_url( trim( $url ) );
			$scheme = strtolower( isset( $parts['scheme'] ) ? $parts['scheme'] : 'https' );
			$host   = strtolower( isset( $parts['host'] ) ? $parts['host'] : '' );
			$path   = rtrim( isset( $parts['path'] ) ? $parts['path'] : '', '/' );

			return $scheme . '://' . $host . $path;
		}

		/**
		 * Disconnect: revoke server-side, then clear locally regardless of the
		 * server response so the user is never stuck connected.
		 *
		 * @since 3.1.0
		 *
		 * @return void
		 */
		public function handle_disconnect() {
			$this->authorize( 'wpcbl_disconnect' );

			$token = (string) get_option( self::OPT_TOKEN, '' );

			if ( '' !== $token ) {
				wp_remote_post(
					$this->app_url( '/api/v1/site/disconnect' ),
					array(
						'timeout' => 15,
						'headers' => array(
							'Authorization' => 'Bearer ' . $token,
							'Accept'        => 'application/json',
						),
					)
				);
			}

			$this->clear_connection();

			wp_safe_redirect( $this->settings_url( 'disconnected' ) );
			exit;
		}

		/**
		 * Ask the SaaS for a verified AI fix suggestion for one broken link.
		 *
		 * @since 3.0.3
		 *
		 * @param array $payload url, anchor, sentence, post_title, language,
		 *                       http_status per the AI fix API contract.
		 *
		 * @return array|WP_Error array{code:int, data:array} or the transport error.
		 */
		public function ai_fix( $payload ) {
			$response = wp_remote_post(
				$this->app_url( '/api/v1/site/ai-fix' ),
				array(
					'timeout' => 25,
					'headers' => $this->api_headers(),
					'body'    => $payload,
				)
			);

			return $this->ai_fix_response( $response );
		}

		/**
		 * Queue a batch of broken links for AI fixing.
		 *
		 * @since 3.0.3
		 *
		 * @param array $links List of per-link payload arrays per the AI fix
		 *                     API contract, at most 500.
		 *
		 * @return array|WP_Error array{code:int, data:array} or the transport error.
		 */
		public function ai_fix_batch( $links ) {
			$response = wp_remote_post(
				$this->app_url( '/api/v1/site/ai-fix/batch' ),
				array(
					'timeout' => 25,
					'headers' => $this->api_headers(),
					'body'    => array( 'links' => $links ),
				)
			);

			return $this->ai_fix_response( $response );
		}

		/**
		 * Poll a queued AI fix batch.
		 *
		 * @since 3.0.3
		 *
		 * @param string $job_id The batch job id from ai_fix_batch().
		 *
		 * @return array|WP_Error array{code:int, data:array} or the transport error.
		 */
		public function ai_fix_batch_status( $job_id ) {
			$response = wp_remote_get(
				$this->app_url( '/api/v1/site/ai-fix/batch/' . rawurlencode( $job_id ) ),
				array(
					'timeout' => 15,
					'headers' => $this->api_headers(),
				)
			);

			return $this->ai_fix_response( $response );
		}

		/**
		 * Shared response mapping for the AI fix endpoints: code + decoded
		 * body, 401 clears the connection like the entitlements poll.
		 *
		 * @since 3.0.3
		 *
		 * @param array|WP_Error $response The wp_remote_* response.
		 *
		 * @return array|WP_Error
		 */
		private function ai_fix_response( $response ) {
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( 401 === $code ) {
				$this->clear_connection();
			}

			return array(
				'code' => $code,
				'data' => is_array( $data ) ? $data : array(),
			);
		}

		/**
		 * Standard headers for authenticated SaaS calls. The version header
		 * tells the dashboard which remote features this plugin supports.
		 *
		 * @since 3.0.4
		 *
		 * @return array
		 */
		private function api_headers() {
			return array(
				'Authorization'   => 'Bearer ' . get_option( self::OPT_TOKEN, '' ),
				'Accept'          => 'application/json',
				'X-WPCBL-Version' => defined( 'WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION' ) ? WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION : '',
			);
		}

		/**
		 * Fetch pending dashboard-initiated apply jobs.
		 *
		 * @since 3.0.4
		 *
		 * @return array List of jobs (id, action, broken_url, new_url), empty on any error.
		 */
		public function fetch_apply_jobs() {
			$response = wp_remote_get(
				$this->app_url( '/api/v1/site/apply-jobs' ),
				array(
					'timeout' => 15,
					'headers' => $this->api_headers(),
				)
			);

			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return array();
			}

			$data = json_decode( wp_remote_retrieve_body( $response ), true );

			return isset( $data['jobs'] ) && is_array( $data['jobs'] ) ? $data['jobs'] : array();
		}

		/**
		 * Proxy a rank-tracker API call under /api/v1/site/rank.
		 *
		 * @since 3.0.5
		 *
		 * @param string     $method HTTP method (GET, POST, PUT, DELETE).
		 * @param string     $path   Sub-path appended to /api/v1/site/rank, empty for the base resource.
		 * @param array|null $body   Request body, omitted entirely when null.
		 *
		 * @return array|WP_Error array{code:int, data:array} or the transport error.
		 */
		public function rank_request( $method, $path = '', $body = null ) {
			$args = array(
				'method'  => $method,
				'timeout' => 20,
				'headers' => $this->api_headers(),
			);
			if ( null !== $body ) {
				$args['body'] = $body;
			}

			$response = wp_remote_request( $this->app_url( '/api/v1/site/rank' . $path ), $args );

			return $this->ai_fix_response( $response );
		}

		/**
		 * Cached state; $fresh bypasses and refills the 5-minute transient.
		 *
		 * @since 3.0.5
		 *
		 * @param bool $fresh Bypass the cache and re-fetch.
		 *
		 * @return array|WP_Error array{code:int, data:array} or the transport error.
		 */
		public function rank_state( $fresh = false ) {
			if ( ! $fresh ) {
				$cached = get_transient( self::TRANSIENT_RANK );
				if ( is_array( $cached ) ) {
					return array( 'code' => 200, 'data' => $cached );
				}
			}

			$result = $this->rank_request( 'GET' );
			if ( ! is_wp_error( $result ) && 200 === $result['code'] ) {
				set_transient( self::TRANSIENT_RANK, $result['data'], 5 * MINUTE_IN_SECONDS );
			}

			return $result;
		}

		/**
		 * Drop the cached state after any mutation.
		 *
		 * @since 3.0.5
		 *
		 * @return void
		 */
		public function flush_rank_state() {
			delete_transient( self::TRANSIENT_RANK );
		}

		/**
		 * Proxy an uptime-monitor API call under /api/v1/site/uptime.
		 *
		 * JSON-encodes the body (unlike rank_request(), which stays
		 * form-encoded): WP's wp_remote_request() form-encodes an array body
		 * via http_build_query(), which silently DROPS empty-array keys —
		 * `alert_emails => []` (the documented "clear recipients" contract)
		 * never reached the SaaS. Laravel parses JSON bodies natively and an
		 * empty array survives as `[]` there, so switching just this proxy
		 * to JSON fixes it without touching the rank/ai-fix callers.
		 *
		 * A bodyless POST/PUT/DELETE (e.g. toggle, which sends no fields)
		 * 406s at the host WAF — live-verified against
		 * /api/v1/site/uptime/monitors/{id}/toggle: a Content-Type-less,
		 * body-less POST came back 406, while the identical request with
		 * `Content-Type: application/json` and body `{}` came back 200. So a
		 * null body still gets an empty JSON object on any of those three
		 * methods; GET stays bodyless as before.
		 *
		 * @since 3.0.6
		 *
		 * @param string     $method HTTP method (GET, POST, PUT, DELETE).
		 * @param string     $path   Sub-path appended to /api/v1/site/uptime, empty for the base resource.
		 * @param array|null $body   Request body; null sends `{}` on a
		 *                           mutating method, no body at all on GET.
		 *
		 * @return array|WP_Error array{code:int, data:array} or the transport error.
		 */
		public function uptime_request( $method, $path = '', $body = null ) {
			$headers = $this->api_headers();
			$args    = array(
				'method'  => $method,
				'timeout' => 20,
				'headers' => $headers,
			);

			if ( null !== $body ) {
				$args['headers']['Content-Type'] = 'application/json';
				$args['body']                    = wp_json_encode( $body );
			} elseif ( 'GET' !== strtoupper( $method ) ) {
				// wp_json_encode( array() ) would serialize to `[]`, not `{}`
				// (PHP has no distinct empty-object type) -- send the literal
				// empty object directly instead of routing an empty array
				// through the encoder.
				$args['headers']['Content-Type'] = 'application/json';
				$args['body']                    = '{}';
			}

			$response = wp_remote_request( $this->app_url( '/api/v1/site/uptime' . $path ), $args );

			return $this->ai_fix_response( $response );
		}

		/**
		 * Cached state; $fresh bypasses and refills the 5-minute transient.
		 *
		 * @since 3.0.6
		 *
		 * @param bool $fresh Bypass the cache and re-fetch.
		 *
		 * @return array|WP_Error array{code:int, data:array} or the transport error.
		 */
		public function uptime_state( $fresh = false ) {
			if ( ! $fresh ) {
				$cached = get_transient( self::TRANSIENT_UPTIME );
				if ( is_array( $cached ) ) {
					return array( 'code' => 200, 'data' => $cached );
				}
			}

			$result = $this->uptime_request( 'GET' );
			if ( ! is_wp_error( $result ) && 200 === $result['code'] ) {
				set_transient( self::TRANSIENT_UPTIME, $result['data'], 5 * MINUTE_IN_SECONDS );
			}

			return $result;
		}

		/**
		 * Drop the cached state after any mutation.
		 *
		 * @since 3.0.6
		 *
		 * @return void
		 */
		public function flush_uptime_state() {
			delete_transient( self::TRANSIENT_UPTIME );
		}

		/**
		 * Proxy one call to the site-token SEO audit API.
		 *
		 * Mirrors uptime_request(): a null body still sends `{}` on a
		 * mutating method, because the host WAF 406s a truly bodyless
		 * POST, while GET stays bodyless.
		 *
		 * @since 3.0.7
		 *
		 * @param string     $method HTTP method.
		 * @param string     $path   Sub-path appended to /api/v1/site/seo-audit.
		 * @param array|null $body   Request body, or null.
		 *
		 * @return array|WP_Error array{code:int, data:array} or the transport error.
		 */
		public function seo_audit_request( $method, $path = '', $body = null ) {
			$args = array(
				'method'  => $method,
				'timeout' => 30,
				'headers' => $this->api_headers(),
			);

			if ( null !== $body ) {
				$args['headers']['Content-Type'] = 'application/json';
				$args['body']                    = wp_json_encode( $body );
			} elseif ( 'GET' !== strtoupper( $method ) ) {
				$args['headers']['Content-Type'] = 'application/json';
				$args['body']                    = '{}';
			}

			$response = wp_remote_request( $this->app_url( '/api/v1/site/seo-audit' . $path ), $args );

			return $this->ai_fix_response( $response );
		}

		/**
		 * Internal Link Optimizer API call.
		 *
		 * @since 3.0.8
		 *
		 * @param string     $method HTTP method.
		 * @param string     $path   Path under the internal-links root.
		 * @param array|null $body   JSON body, or null for none.
		 *
		 * @return array|WP_Error array{code:int, data:array} or the transport error.
		 */
		public function internal_links_request( $method, $path = '', $body = null ) {
			$args = array(
				'method'  => $method,
				// A full-site upload chunk is larger than an audit call,
				// so it gets longer than the default thirty seconds.
				'timeout' => 45,
				'headers' => $this->api_headers(),
			);

			if ( null !== $body ) {
				$args['headers']['Content-Type'] = 'application/json';
				$args['body']                    = wp_json_encode( $body );
			} elseif ( 'GET' !== strtoupper( $method ) ) {
				$args['headers']['Content-Type'] = 'application/json';
				$args['body']                    = '{}';
			}

			$response = wp_remote_request( $this->app_url( '/api/v1/site/internal-links' . $path ), $args );

			return $this->ai_fix_response( $response );
		}

		/**
		 * Cached Internal Link Optimizer state; $fresh bypasses and refills
		 * the transient. Modelled exactly on seo_audit_state() -- every
		 * page that reads this on render (the Dashboard's ILO card) must
		 * go through here, never internal_links_request( 'GET' ) directly,
		 * so the Dashboard never makes an uncached, blocking round trip to
		 * the SaaS on every page load.
		 *
		 * @since 3.0.8
		 *
		 * @param bool $fresh Bypass the cache and re-fetch.
		 *
		 * @return array|WP_Error array{code:int, data:array} or the transport error.
		 */
		public function internal_links_state( $fresh = false ) {
			if ( ! $fresh ) {
				$cached = get_transient( self::TRANSIENT_ILO );
				if ( is_array( $cached ) ) {
					return array( 'code' => 200, 'data' => $cached );
				}
			}

			$result = $this->internal_links_request( 'GET' );
			if ( ! is_wp_error( $result ) && 200 === $result['code'] ) {
				set_transient( self::TRANSIENT_ILO, $result['data'], 5 * MINUTE_IN_SECONDS );
			}

			return $result;
		}

		/**
		 * Drop the cached Internal Link Optimizer state (a run started).
		 *
		 * @since 3.0.8
		 *
		 * @return void
		 */
		public function flush_internal_links_state() {
			delete_transient( self::TRANSIENT_ILO );
		}

		/**
		 * AI Visibility Tracker API call: the brand mentions overview,
		 * tracked prompts, and (via $path '/refresh') the rate-limited
		 * "Run check now" action. Cadence otherwise stays server-owned
		 * (each reading costs the business money) -- this proxy is a thin
		 * passthrough, the day-limit itself lives on brokenlinkchecker.io,
		 * never trusted client-side.
		 *
		 * @since 3.0.8
		 *
		 * @param string     $method HTTP method.
		 * @param string     $path   Sub-path appended to /api/v1/site/ai-visibility, empty for the base resource.
		 * @param array|null $body   JSON body, or null for none.
		 *
		 * @return array|WP_Error array{code:int, data:array} or the transport error.
		 */
		public function ai_visibility_request( $method, $path = '', $body = null ) {
			$args = array(
				'method'  => $method,
				'timeout' => 20,
				'headers' => $this->api_headers(),
			);

			if ( null !== $body ) {
				$args['headers']['Content-Type'] = 'application/json';
				$args['body']                    = wp_json_encode( $body );
			} elseif ( 'GET' !== strtoupper( $method ) ) {
				$args['headers']['Content-Type'] = 'application/json';
				$args['body']                    = '{}';
			}

			$response = wp_remote_request( $this->app_url( '/api/v1/site/ai-visibility' . $path ), $args );

			return $this->ai_fix_response( $response );
		}

		/**
		 * Cached AI Visibility state; $fresh bypasses and refills the
		 * transient. Modelled exactly on seo_audit_state(): the Dashboard
		 * card and the AI Visibility page's own state action both read
		 * through here, so wp-admin never makes an uncached, blocking
		 * round trip to the SaaS on every page load.
		 *
		 * @since 3.0.8
		 *
		 * @param bool $fresh Bypass the cache and re-fetch.
		 *
		 * @return array|WP_Error array{code:int, data:array} or the transport error.
		 */
		public function ai_visibility_state( $fresh = false ) {
			if ( ! $fresh ) {
				$cached = get_transient( self::TRANSIENT_AIV );
				if ( is_array( $cached ) ) {
					return array( 'code' => 200, 'data' => $cached );
				}
			}

			$result = $this->ai_visibility_request( 'GET' );
			if ( ! is_wp_error( $result ) && 200 === $result['code'] ) {
				set_transient( self::TRANSIENT_AIV, $result['data'], 5 * MINUTE_IN_SECONDS );
			}

			return $result;
		}

		/**
		 * Drop the cached AI Visibility state (a prompt was added or
		 * removed), so the Dashboard card and the page do not show a
		 * stale count for up to five minutes after the user changes
		 * something.
		 *
		 * @since 3.0.8
		 *
		 * @return void
		 */
		public function flush_ai_visibility_state() {
			delete_transient( self::TRANSIENT_AIV );
		}

		/**
		 * Cached audit state; $fresh bypasses and refills the transient.
		 *
		 * @since 3.0.7
		 *
		 * @param bool $fresh Bypass the cache and re-fetch.
		 *
		 * @return array|WP_Error array{code:int, data:array} or the transport error.
		 */
		public function seo_audit_state( $fresh = false ) {
			if ( ! $fresh ) {
				$cached = get_transient( self::TRANSIENT_AUDIT );
				if ( is_array( $cached ) ) {
					return array( 'code' => 200, 'data' => $cached );
				}
			}

			$result = $this->seo_audit_request( 'GET' );
			if ( ! is_wp_error( $result ) && 200 === $result['code'] ) {
				set_transient( self::TRANSIENT_AUDIT, $result['data'], 5 * MINUTE_IN_SECONDS );
			}

			return $result;
		}

		/**
		 * Drop the cached audit state after any mutation.
		 *
		 * @since 3.0.7
		 *
		 * @return void
		 */
		/**
		 * Every affected URL for one issue. Kept off the cached state so
		 * polling stays small, fetched only when a reader opens the list.
		 *
		 * @since 3.0.7
		 *
		 * @param string $audit_id Audit id.
		 * @param string $issue_id Issue id.
		 *
		 * @return array|WP_Error array{code:int, data:array} or the transport error.
		 */
		public function seo_audit_issue_pages( $audit_id, $issue_id ) {
			return $this->seo_audit_request(
				'POST',
				'/' . rawurlencode( $audit_id ) . '/issue-pages',
				array( 'issue' => $issue_id )
			);
		}

		/**
		 * Ask the SaaS to write fixes for one audit issue. Nothing is applied
		 * here: the response is a list of suggestions for the user to review.
		 *
		 * @since 3.0.7
		 *
		 * @param string $audit_id Audit id.
		 * @param string $issue_id Issue id.
		 *
		 * @return array|WP_Error array{code:int, data:array} or the transport error.
		 */
		public function seo_audit_ai_fix( $audit_id, $issue_id ) {
			return $this->seo_audit_request(
				'POST',
				'/' . rawurlencode( $audit_id ) . '/ai-fix',
				array( 'issue' => $issue_id )
			);
		}

		/**
		 * Tell the SaaS what happened to one suggestion, so its record matches
		 * what is actually on the site.
		 *
		 * @since 3.0.7
		 *
		 * @param string $audit_id      Audit id.
		 * @param string $suggestion_id Suggestion id.
		 * @param string $status        applied or dismissed.
		 *
		 * @return array|WP_Error array{code:int, data:array} or the transport error.
		 */
		public function seo_audit_ai_fix_resolve( $audit_id, $suggestion_id, $status ) {
			return $this->seo_audit_request(
				'POST',
				'/' . rawurlencode( $audit_id ) . '/ai-fix/resolve',
				array( 'id' => $suggestion_id, 'status' => $status )
			);
		}

		public function flush_seo_audit_state() {
			delete_transient( self::TRANSIENT_AUDIT );
		}

		/**
		 * The audit PDF as raw bytes. Not routed through ai_fix_response(),
		 * which JSON-decodes the body and would destroy a binary payload.
		 *
		 * @since 3.0.7
		 *
		 * @param string $audit_id Audit id.
		 *
		 * @return array|WP_Error array{code:int, body:string, type:string} or the transport error.
		 */
		public function seo_audit_pdf( $audit_id ) {
			$response = wp_remote_get(
				$this->app_url( '/api/v1/site/seo-audit/' . rawurlencode( $audit_id ) . '/pdf' ),
				array(
					'timeout' => 60,
					'headers' => $this->api_headers(),
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			return array(
				'code'        => (int) wp_remote_retrieve_response_code( $response ),
				'body'        => wp_remote_retrieve_body( $response ),
				'type'        => wp_remote_retrieve_header( $response, 'content-type' ),
				// SeoAuditPdf::filename() on the SaaS side already builds
				// the real name (domain plus the audit's own date), so the
				// admin-post handler prefers this over inventing one.
				'disposition' => wp_remote_retrieve_header( $response, 'content-disposition' ),
			);
		}

		/**
		 * Public plan catalog for the Upgrade page, cached 12 hours. The SaaS
		 * serves it from the same config its own pages read, so prices in
		 * wp-admin can never drift from brokenlinkchecker.io.
		 *
		 * @since 3.0.5
		 *
		 * @return array|null Plans keyed by slug, or null when unreachable.
		 */
		public function fetch_plans() {
			$cached = get_transient( self::TRANSIENT_PLANS );
			if ( is_array( $cached ) ) {
				return $cached;
			}

			$response = wp_remote_get(
				$this->app_url( '/api/v1/plans' ),
				array(
					'timeout' => 10,
					'headers' => array( 'Accept' => 'application/json' ),
				)
			);

			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return null;
			}

			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! isset( $data['plans'] ) || ! is_array( $data['plans'] ) ) {
				return null;
			}

			$catalog = array(
				'plans'    => $data['plans'],
				'ai_tiers' => isset( $data['ai_tiers'] ) && is_array( $data['ai_tiers'] ) ? $data['ai_tiers'] : array(),
			);

			set_transient( self::TRANSIENT_PLANS, $catalog, 12 * HOUR_IN_SECONDS );

			return $catalog;
		}

		/**
		 * The subscription's current shape (plan, interval, keyword tier,
		 * daily, AI credits) so the plans page can preselect it. Cached
		 * briefly; flushed after a plan change.
		 *
		 * @since 3.0.5
		 *
		 * @return array|null Shape array, or null when unreachable.
		 */
		public function fetch_billing_shape() {
			$cached = get_transient( self::TRANSIENT_SHAPE );
			if ( is_array( $cached ) ) {
				return $cached;
			}

			$result = $this->rank_request_url( 'GET', '/api/v1/site/billing/shape' );
			if ( is_wp_error( $result ) || 200 !== $result['code'] ) {
				return null;
			}

			set_transient( self::TRANSIENT_SHAPE, $result['data'], 5 * MINUTE_IN_SECONDS );

			return $result['data'];
		}

		/**
		 * Authenticated request against an arbitrary API path (the rank
		 * helper is fixed to the rank base).
		 *
		 * @since 3.0.5
		 *
		 * @param string     $method HTTP method.
		 * @param string     $path   Absolute API path.
		 * @param array|null $body   Optional body.
		 *
		 * @return array|WP_Error array{code:int, data:array} or transport error.
		 */
		private function rank_request_url( $method, $path, $body = null ) {
			$args = array(
				'method'  => $method,
				'timeout' => 15,
				'headers' => $this->api_headers(),
			);
			if ( null !== $body ) {
				$args['body'] = $body;
			}

			return $this->ai_fix_response( wp_remote_request( $this->app_url( $path ), $args ) );
		}

		/**
		 * Drop the cached billing shape (after a subscription change).
		 *
		 * @since 3.0.5
		 *
		 * @return void
		 */
		public function flush_billing_shape() {
			delete_transient( self::TRANSIENT_SHAPE );
		}

		/**
		 * Change the account's plan tier from the Upgrade page. Mirrors the
		 * dashboard billing page (prorated upgrade / credited downgrade).
		 *
		 * @since 3.0.5
		 *
		 * @param string $plan     Plan slug.
		 * @param string $interval monthly|yearly.
		 *
		 * @return array|WP_Error array{code:int, data:array} or transport error.
		 */
		public function billing_change_plan( $plan, $interval, $keywords = 0, $daily = false, $ai = 0 ) {
			$response = wp_remote_post(
				$this->app_url( '/api/v1/site/billing/change-plan' ),
				array(
					'timeout' => 25,
					'headers' => $this->api_headers(),
					'body'    => array(
						'plan'     => $plan,
						'interval' => $interval,
						'keywords' => (int) $keywords,
						'daily'    => $daily ? 1 : 0,
						'ai'       => (int) $ai,
					),
				)
			);

			return $this->ai_fix_response( $response );
		}

		/**
		 * Report one apply job's outcome back to the SaaS.
		 *
		 * @since 3.0.4
		 *
		 * @param string $job_id  Job id from fetch_apply_jobs().
		 * @param array  $outcome status (applied|failed), message, updated.
		 *
		 * @return void
		 */
		public function report_apply_job( $job_id, $outcome ) {
			wp_remote_post(
				$this->app_url( '/api/v1/site/apply-jobs/' . rawurlencode( $job_id ) ),
				array(
					'timeout' => 15,
					'headers' => $this->api_headers(),
					'body'    => $outcome,
				)
			);
		}

		/**
		 * Force an immediate entitlements fetch (used right after connecting,
		 * bypassing the 12h throttle by clearing the cache first).
		 *
		 * @since 3.1.0
		 *
		 * @return void
		 */
		private function poll_entitlements_now() {
			delete_transient( self::TRANSIENT_ENT );
			$this->poll_entitlements();
		}
	}

	// One instance, stored globally so templates can read it without
	// re-instantiating (a second instance would double-register the
	// admin_post/filter hooks — WP only de-dupes IDENTICAL callbacks, and
	// two object instances are not identical).
	$GLOBALS['wpcbl_connect'] = new WPCBL_Check_Broken_Links_Connect();

endif;
