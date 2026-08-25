<?php
/**
 * Dashboard-initiated fixes: the brokenlinkchecker.io dashboard queues
 * apply jobs (replace a broken URL or unlink it) and pings this site's
 * REST route. The plugin then pulls the jobs over its own authenticated
 * API connection, applies them to post content, and reports each outcome.
 *
 * The ping carries no secrets and triggers no work by itself: whether
 * anything happens is decided by the authenticated pull. A transient
 * lock throttles repeated pings.
 *
 * @package WPCBL_Check_Broken_Links
 * @since 3.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPCBL_Check_Broken_Links_Remote_Apply' ) ) :

	/**
	 * Pulls and applies dashboard-initiated fix jobs.
	 *
	 * @since 3.0.4
	 */
	class WPCBL_Check_Broken_Links_Remote_Apply {

		const LOCK_TRANSIENT = 'wpcbl_apply_lock';
		const LOCK_TTL       = 30;

		/**
		 * Register the REST ping route and the entitlements-poll fallback.
		 *
		 * @since 3.0.4
		 */
		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
			add_action( 'wpcbl_entitlements_refreshed', array( $this, 'process_jobs' ) );
		}

		/**
		 * REST: POST /wp-json/wpcbl/v1/apply-ping.
		 *
		 * @since 3.0.4
		 *
		 * @return void
		 */
		public function register_routes() {
			register_rest_route(
				'wpcbl/v1',
				'/apply-ping',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle_ping' ),
					'permission_callback' => '__return_true',
				)
			);
		}

		/**
		 * Ping handler: acknowledge, then pull and apply pending jobs.
		 *
		 * @since 3.0.4
		 *
		 * @return WP_REST_Response
		 */
		public function handle_ping() {
			$connect = wpcbl_connect();

			if ( ! $connect || ! $connect->is_connected() ) {
				return new WP_REST_Response( array( 'ok' => false ), 200 );
			}

			if ( false !== get_transient( self::LOCK_TRANSIENT ) ) {
				return new WP_REST_Response( array( 'ok' => true, 'throttled' => true ), 200 );
			}
			set_transient( self::LOCK_TRANSIENT, 1, self::LOCK_TTL );

			$processed = $this->process_jobs();

			return new WP_REST_Response( array( 'ok' => true, 'processed' => $processed ), 200 );
		}

		/**
		 * Pull pending jobs, apply each, report each outcome.
		 *
		 * @since 3.0.4
		 *
		 * @return int Number of jobs processed.
		 */
		public function process_jobs() {
			$connect = wpcbl_connect();

			if ( ! $connect || ! $connect->is_connected() ) {
				return 0;
			}

			$jobs = $connect->fetch_apply_jobs();

			foreach ( $jobs as $job ) {
				if ( empty( $job['id'] ) || empty( $job['broken_url'] ) || empty( $job['action'] ) ) {
					continue;
				}

				$outcome = 'replace' === $job['action']
					? $this->apply_replace( $job['broken_url'], isset( $job['new_url'] ) ? (string) $job['new_url'] : '' )
					: $this->apply_unlink( $job['broken_url'] );

				$connect->report_apply_job( (string) $job['id'], $outcome );
			}

			return count( $jobs );
		}

		/**
		 * Replace a broken URL with a new one in every published post that
		 * contains it (mirrors the Edit URL row action's fix-all path).
		 *
		 * @since 3.0.4
		 *
		 * @param string $old_url The broken URL.
		 * @param string $new_url The replacement URL.
		 *
		 * @return array status, message, updated.
		 */
		private function apply_replace( $old_url, $new_url ) {
			if ( '' === $new_url ) {
				return array(
					'status'  => 'failed',
					'message' => 'The job has no replacement URL.',
				);
			}

			$updated = 0;

			foreach ( $this->posts_containing( $old_url ) as $post_id ) {
				$post = get_post( $post_id );
				if ( ! $post ) {
					continue;
				}

				$match = WPCBL_Check_Broken_Links_Utilities::find_url_in_content( $post->post_content, $old_url );
				if ( false === $match ) {
					continue;
				}

				// Keep the replacement in the same encoding as the match.
				$replacement = $match === $old_url ? $new_url : str_replace( '&', '&amp;', $new_url );

				if ( $this->update_content_preserving_modified( $post_id, str_replace( $match, $replacement, $post->post_content ) ) ) {
					++$updated;
				}
			}

			if ( 0 === $updated ) {
				return array(
					'status'  => 'failed',
					'message' => 'The URL was not found in any post content. It may live in a builder or slider field.',
				);
			}

			$this->forget_stored_url( $old_url );

			return array(
				'status'  => 'applied',
				'updated' => $updated,
			);
		}

		/**
		 * Remove the a-tag around a URL but keep the anchor text, in every
		 * published post that contains it.
		 *
		 * @since 3.0.4
		 *
		 * @param string $url The broken URL.
		 *
		 * @return array status, message, updated.
		 */
		private function apply_unlink( $url ) {
			$updated = 0;

			foreach ( $this->posts_containing( $url ) as $post_id ) {
				$post = get_post( $post_id );
				if ( ! $post ) {
					continue;
				}

				$match = WPCBL_Check_Broken_Links_Utilities::find_url_in_content( $post->post_content, $url );
				if ( false === $match ) {
					continue;
				}

				$pattern     = '/<a\b[^>]*href=["\']' . preg_quote( $match, '/' ) . '["\'][^>]*>(.*?)<\/a>/is';
				$new_content = preg_replace( $pattern, '$1', $post->post_content, -1, $replacements );

				if ( null === $new_content || 0 === $replacements ) {
					continue;
				}

				if ( $this->update_content_preserving_modified( $post_id, $new_content ) ) {
					++$updated;
				}
			}

			if ( 0 === $updated ) {
				return array(
					'status'  => 'failed',
					'message' => 'The link was not found in any post content. It may live in a builder or slider field.',
				);
			}

			$this->forget_stored_url( $url );

			return array(
				'status'  => 'applied',
				'updated' => $updated,
			);
		}

		/**
		 * Published posts whose content contains the URL, raw or &amp;-encoded.
		 *
		 * @since 3.0.4
		 *
		 * @param string $url The URL to search for.
		 *
		 * @return array Post ids, at most 200.
		 */
		private function posts_containing( $url ) {
			global $wpdb;

			$like         = '%' . $wpdb->esc_like( $url ) . '%';
			$like_encoded = '%' . $wpdb->esc_like( str_replace( '&', '&amp;', $url ) ) . '%';
			$post_ids     = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND (post_content LIKE %s OR post_content LIKE %s) LIMIT 200", $like, $like_encoded ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot content search across posts; WP_Query cannot match post_content substrings.

			return array_map( 'absint', (array) $post_ids );
		}

		/**
		 * Update post content without touching the modified date (mirrors the
		 * row actions, so remote fixes do not bump post_modified either).
		 *
		 * @since 3.0.4
		 *
		 * @param int    $post_id     Post to update.
		 * @param string $new_content Replacement content.
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
		 * Drop every stored scan-result row for a fixed URL and refresh the
		 * summary counts, so the plugin's own list stays honest.
		 *
		 * @since 3.0.4
		 *
		 * @param string $url The fixed URL.
		 *
		 * @return void
		 */
		private function forget_stored_url( $url ) {
			$links = get_option( 'wpcbl_check_for_broken_links_links', array() );

			foreach ( array( 'broken', 'warning', 'good', 'redirect' ) as $bucket ) {
				if ( empty( $links[ $bucket ] ) || ! is_array( $links[ $bucket ] ) ) {
					continue;
				}

				foreach ( $links[ $bucket ] as $index => $entry ) {
					if ( isset( $entry['link'] ) && $entry['link'] === $url ) {
						unset( $links[ $bucket ][ $index ] );
					}
				}

				$links[ $bucket ] = array_values( $links[ $bucket ] );
			}

			update_option( 'wpcbl_check_for_broken_links_links', $links );

			$summary = get_option( 'wpcbl_last_scan_summary' );
			if ( is_array( $summary ) ) {
				$summary['broken'] = isset( $links['broken'] ) ? count( $links['broken'] ) : 0;
				update_option( 'wpcbl_last_scan_summary', $summary );
			}
		}
	}

	new WPCBL_Check_Broken_Links_Remote_Apply();

endif;
