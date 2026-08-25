<?php
/**
 * The WPCBL_Check_Broken_Links_Utilities class.
 *
 * @package WPCBL_Check_Broken_Links
 * @author Brokenlinkchecker.io
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WPCBL_Check_Broken_Links_Utilities' ) ) {
	/**
	 * Utilities.
	 *
	 * @since 1.0.0
	 */
	class WPCBL_Check_Broken_Links_Utilities {
		/**
		 * Process the scan.
		 *
		 * @since 1.0.0
		 *
		 * @param bool   $send_email Whether to send notification emails.
		 * @param string $scan_source Scan source. Accepts manual or scheduled.
		 *
		 * @return bool True if the scan was successful, false otherwise.
		 */
		public static function process_scan( $send_email = true, $scan_source = 'scheduled' ) {
			$scan_start = microtime( true );

			// Get the settings.
			$settings         = get_option( 'wpcbl_check_for_broken_links_settings', array() );
			$email_enabled    = isset( $settings['email_notifications'] ) ? $settings['email_notifications'] : 'off';
			$email_addresses  = isset( $settings['email_addresses'] ) ? $settings['email_addresses'] : '';
			$number_of_links  = isset( $settings['number_of_links'] ) ? $settings['number_of_links'] : 'all';
			$set_number       = isset( $settings['set_links_number'] ) ? $settings['set_links_number'] : 0;
			$exclusion_urls   = isset( $settings['exclusion_urls'] ) ? $settings['exclusion_urls'] : '';
			$scan_timezone    = isset( $settings['scan_timezone'] ) ? $settings['scan_timezone'] : wp_timezone_string();
			$scan_sliders     = isset( $settings['scan_slider_content'] ) ? $settings['scan_slider_content'] : 'on';
			// Plain substring rules, exactly as the Settings page promises:
			// any link whose URL contains the line's text is skipped. No
			// slash-trimming — that made "/tag/" also match "/tagged-articles".
			$links_to_exclude = array_map( 'trim', explode( "\n", $exclusion_urls ) );

			if ( 'all' == $number_of_links ) {
				$number_of_links = -1;
			} else {
				$number_of_links = (int) $set_number;
			}

			// Get the data to scan.
			$data_to_scan = self::get_data_to_scan( $settings );

			// Get already saved links.
			$links_to_update = array(
				'good'    => array(),
				'warning' => array(),
				'broken'  => array(),
				'total'   => 0,
			);

			$count = 0;
			$break = false;
			$smart_slider_ids = array();

			// Link Types setting: which HTML elements get scanned.
			$link_types = isset( $settings['link_types'] ) && is_array( $settings['link_types'] ) && ! empty( $settings['link_types'] ) ? $settings['link_types'] : array( 'html', 'image' );

			// A fresh scan resets Dismiss-ed entries (the Not broken whitelist is kept).
			delete_option( 'wpcbl_session_dismissed' );

			// Live progress for the admin UI: one tick per content item plus one
			// for the slider stage, polled via the wpcbl_scan_progress AJAX action.
			$progress_total   = count( $data_to_scan ) + ( 'on' === $scan_sliders ? 1 : 0 );
			$progress_current = 0;
			update_option( 'wpcbl_scan_progress', array( 'current' => 0, 'total' => $progress_total, 'links' => 0 ), false );

			foreach ( $data_to_scan as $single ) {
				if ( is_object( $single ) ) {
					$is_comment = true;
					$post_id    = $single->comment_ID;
					$content    = $single->comment_content;
				} else {
					$is_comment      = false;
					$post_id         = $single;
					$get_the_content = get_the_content( null, false, $post_id );

					$content = apply_filters( 'the_content', $get_the_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deliberately running content through the core filter so shortcodes/builders render before link extraction.
				}

				$content_smart_slider_ids = self::extract_smart_slider_ids( $content );
				$smart_slider_ids         = array_merge( $smart_slider_ids, $content_smart_slider_ids );
				$slider_links             = self::extract_slider_links( $content );

				// Extract links from content.
				$links = self::extract_links( $content, $link_types );

				if ( ! empty( $links ) ) {
					foreach ( $links as $link_item ) {
						$link = $link_item['url'];
						++$count;

						if ( -1 !== $number_of_links && $count > (int) $number_of_links ) {
							$break = true;
							break;
						}

						// Exclusion rules are substring matches; the Not broken
						// whitelist is checked in the same helper.
						if ( self::is_excluded( $link, $links_to_exclude ) ) {
							continue;
						}

						// Check the link.
						$status = self::check_link( $link, $post_id, $is_comment );

						$link_source           = self::get_link_source( $link );
						$status['link_source'] = $link_source;
						$status['element']     = $link_item['element'];
						$status['detected_at'] = self::format_scan_datetime( null, 'F j, Y', $scan_timezone );

						if ( in_array( $link, $slider_links, true ) || in_array( rtrim( $link, '/' ), array_map( 'untrailingslashit', $slider_links ), true ) ) {
							$status['is_slider'] = true;
						}

						$links_to_update[ $status['type'] ][] = $status;
					}
				}

				++$progress_current;
				update_option( 'wpcbl_scan_progress', array( 'current' => $progress_current, 'total' => $progress_total, 'links' => $count ), false );

				if ( $break ) {
					break;
				}
			}

			
			if ( 'on' === $scan_sliders ) {
				// Best-effort: scan slider plugin content (if present).
				list( $slider_results, $slider_count ) = self::scan_slider_content( $links_to_exclude, $number_of_links, $count, $scan_timezone, array_unique( array_map( 'absint', $smart_slider_ids ) ) );
				$count += $slider_count;
				if ( ! empty( $slider_results ) ) {
					foreach ( $slider_results as $sr ) {
						if ( isset( $sr['type'] ) ) {
							$links_to_update[ $sr['type'] ][] = $sr;
						}
					}
				}
			}
			// Update the total scanned links.
			$links_to_update['total'] = $count;

			update_option( 'wpcbl_scan_progress', array( 'current' => $progress_total, 'total' => $progress_total, 'links' => $count ), false );

			$email_result = array(
				'status'     => 'skipped',
				'message'    => __( 'Email disabled for this scan.', 'check-for-broken-links' ),
				'recipients' => '',
			);

			// Send email.
			$scan_time = time();
			$scan_time_str = self::format_scan_datetime( null, 'j F Y \a\t H:i:s', $scan_timezone );

			if ( $send_email ) {
				$email_result = self::send_mails( $email_enabled, $email_addresses, $links_to_update['broken'], $scan_time_str );
			}

			$wpcbl_summary = array(
				'time'     => $scan_time,
				'time_str' => $scan_time_str,
				'duration' => microtime( true ) - $scan_start,
				'total'    => $count,
				'broken'   => count( $links_to_update['broken'] ),
				'email'    => $email_result,
				'source'   => sanitize_key( $scan_source ),
			);

			update_option( 'wpcbl_last_scan_summary', $wpcbl_summary );
			self::record_scan_history( $wpcbl_summary );

			delete_option( 'wpcbl_scan_progress' );
			update_option( 'wpcbl_completed_scans', (int) get_option( 'wpcbl_completed_scans', 0 ) + 1, false );

			return update_option( 'wpcbl_check_for_broken_links_links', $links_to_update );
		}

		/**
		 * Get the data to scan.
		 *
		 * @param array $settings Default empty array.
		 *
		 * @since 1.0.0
		 *
		 * @return array
		 */
		public static function get_data_to_scan( $settings = array() ) {
			$data_to_scan = array();

			// Public post types (except attachments, menus, revisions), narrowed
			// to the configured Content Types to Scan when set.
			$post_types = get_post_types( array( 'public' => true ), 'names' );
			$post_types = array_values( array_diff( $post_types, array( 'attachment', 'nav_menu_item', 'revision' ) ) );

			$selected_types = isset( $settings['scan_post_types'] ) && is_array( $settings['scan_post_types'] ) ? $settings['scan_post_types'] : array();
			if ( ! empty( $selected_types ) ) {
				$post_types = array_values( array_intersect( $post_types, $selected_types ) );
			}

			if ( empty( $post_types ) ) {
				$post_types = array( 'post', 'page' );
			}

			$args = array(
				'post_type'      => $post_types,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
			);

			$post_ids = get_posts( $args );
			$data_to_scan = array_merge( $data_to_scan, $post_ids );

			// Comment scanning is a Pro feature.
			$scan_comments = isset( $settings['scan_comments'] ) ? $settings['scan_comments'] : '';
			if ( wpcbl_has_pro() && 'on' === $scan_comments ) {
				$comments     = get_comments( array( 'status' => 'approve' ) );
				$data_to_scan = array_merge( $data_to_scan, $comments );
			}

			return $data_to_scan;
		}

		/**
		 * Find a URL inside raw post content, tolerating entity-encoded
		 * ampersands. Returns the form that actually appears, or false when
		 * the URL only exists in rendered output (builders, shortcodes).
		 *
		 * @since 3.0.0
		 *
		 * @param string $content Raw post content.
		 * @param string $url     The URL to look for.
		 *
		 * @return string|false
		 */
		public static function find_url_in_content( $content, $url ) {
			if ( '' === $url ) {
				return false;
			}

			if ( false !== strpos( $content, $url ) ) {
				return $url;
			}

			$encoded = str_replace( '&', '&amp;', $url );
			if ( $encoded !== $url && false !== strpos( $content, $encoded ) ) {
				return $encoded;
			}

			return false;
		}

		/**
		 * Anchor text and surrounding plain text for a URL in raw post
		 * content. The scan results store neither, so the AI fix flow
		 * extracts both at request time. "Sentence" is a plain-text window
		 * around the link, which gives the AI richer context than a single
		 * parsed sentence would.
		 *
		 * @since 3.0.3
		 *
		 * @param string $content Raw post content.
		 * @param string $url     The broken URL.
		 *
		 * @return array{anchor: string, sentence: string} Empty strings when
		 *                                                 the URL is absent.
		 */
		public static function extract_link_context( $content, $url ) {
			$context = array(
				'anchor'   => '',
				'sentence' => '',
			);

			$match = self::find_url_in_content( $content, $url );
			if ( false === $match ) {
				return $context;
			}

			$position = strpos( $content, $match );
			$pattern  = '/<a\b[^>]*href=["\']' . preg_quote( $match, '/' ) . '["\'][^>]*>(.*?)<\/a>/is';

			if ( preg_match( $pattern, $content, $m, PREG_OFFSET_CAPTURE ) ) {
				$position          = $m[0][1];
				$context['anchor'] = mb_substr( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $m[1][0] ) ) ), 0, 300 );
			}

			$start  = max( 0, $position - 300 );
			$length = 600 + strlen( $match );
			$window = mb_strcut( $content, $start, $length );

			// Boundary cleanup only where the window really cut into the content:
			// an untruncated edge never holds a tag remnant.
			if ( $start > 0 ) {
				$window = preg_replace( '/^[^<>]*>/', '', $window );
			}
			if ( $start + strlen( $window ) < strlen( $content ) ) {
				$window = preg_replace( '/<\/?[a-zA-Z][^>]*$/', '', $window );
			}
			$plain  = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $window ) ) );

			$context['sentence'] = mb_substr( $plain, 0, 1000 );

			return $context;
		}

		/**
		 * Turn the stored broken bucket into AI fix batch payloads. Entries
		 * the plugin cannot edit (comments, sliders, images, missing posts,
		 * URLs absent from raw content) land in skipped with reason
		 * 'manual', and entries past the API's 500-link cap land in
		 * skipped with reason 'cap'.
		 *
		 * @since 3.0.3
		 *
		 * @param array $broken_entries Rows from the broken bucket.
		 *
		 * @return array{links: array, rows: array, skipped: array}
		 */
		/**
		 * Append a finished scan's summary to the rolling scan history,
		 * newest first, capped at 20 entries. Available on every plan.
		 *
		 * @since 3.0.4
		 *
		 * @param array $summary The wpcbl_last_scan_summary payload.
		 *
		 * @return void
		 */
		public static function record_scan_history( $summary ) {
			$history = get_option( 'wpcbl_scan_history', array() );
			if ( ! is_array( $history ) ) {
				$history = array();
			}

			array_unshift(
				$history,
				array(
					'time'     => isset( $summary['time'] ) ? (int) $summary['time'] : 0,
					'time_str' => isset( $summary['time_str'] ) ? (string) $summary['time_str'] : '',
					'duration' => isset( $summary['duration'] ) ? (float) $summary['duration'] : 0.0,
					'total'    => isset( $summary['total'] ) ? (int) $summary['total'] : 0,
					'broken'   => isset( $summary['broken'] ) ? (int) $summary['broken'] : 0,
					'source'   => isset( $summary['source'] ) ? (string) $summary['source'] : '',
				)
			);

			update_option( 'wpcbl_scan_history', array_slice( $history, 0, 20 ), false );
		}

		/**
		 * Absolute form of a stored link URL for the AI fix API: root-relative
		 * URLs resolve against the home origin, protocol-relative URLs get the
		 * home scheme. Anything else (absolute or malformed) passes through.
		 *
		 * @since 3.0.4
		 *
		 * @param string $url The stored link URL.
		 *
		 * @return string
		 */
		public static function absolutize_url( $url ) {
			$url = trim( (string) $url );

			if ( '' === $url || preg_match( '~^https?://~i', $url ) ) {
				return $url;
			}

			$home   = wp_parse_url( home_url() );
			$scheme = isset( $home['scheme'] ) ? $home['scheme'] : 'https';

			if ( wpcbl_str_starts_with( $url, '//' ) ) {
				return $scheme . ':' . $url;
			}

			if ( '/' === $url[0] ) {
				$origin = $scheme . '://' . ( isset( $home['host'] ) ? $home['host'] : '' ) . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );

				return $origin . $url;
			}

			return $url;
		}

		public static function build_ai_fix_batch( $broken_entries ) {
			$links   = array();
			$rows    = array();
			$skipped = array();

			foreach ( (array) $broken_entries as $entry ) {
				$url     = isset( $entry['link'] ) ? (string) $entry['link'] : '';
				$post_id = isset( $entry['ID'] ) ? (int) $entry['ID'] : 0;
				$element = isset( $entry['element'] ) ? $entry['element'] : 'link';

				if ( count( $links ) >= 500 ) {
					$skipped[] = array( 'url' => $url, 'post_id' => $post_id, 'reason' => 'cap' );
					continue;
				}

				if ( '' === $url || ! $post_id || ! empty( $entry['is_comment'] ) || ! empty( $entry['is_slider'] ) || 'image' === $element ) {
					$skipped[] = array( 'url' => $url, 'post_id' => $post_id, 'reason' => 'manual' );
					continue;
				}

				$post = get_post( $post_id );
				if ( ! $post || false === self::find_url_in_content( $post->post_content, $url ) ) {
					$skipped[] = array( 'url' => $url, 'post_id' => $post_id, 'reason' => 'manual' );
					continue;
				}

				// Relative URLs go absolute. Malformed ones (htp://...) are sent
				// as-is: the AI treats a scheme typo as its easiest fix.
				$api_url = self::absolutize_url( $url );

				$context = self::extract_link_context( $post->post_content, $url );

				$links[] = array(
					'url'         => $api_url,
					'anchor'      => $context['anchor'],
					'sentence'    => $context['sentence'],
					'post_title'  => mb_substr( get_the_title( $post ), 0, 300 ),
					'language'    => mb_substr( get_locale(), 0, 10 ),
					'http_status' => isset( $entry['code'] ) ? (int) $entry['code'] : 0,
				);
				$rows[]  = array(
					'url'     => $url,
					'post_id' => $post_id,
				);
			}

			return array(
				'links'   => $links,
				'rows'    => $rows,
				'skipped' => $skipped,
			);
		}

		/**
		 * Zip locally stored batch rows with the backend's per-link results.
		 * Backend rows come back in submission order, so index alignment is
		 * the primary match with a URL sanity check, falling back to the
		 * first row with the same URL when order ever differs.
		 *
		 * @since 3.0.3
		 *
		 * @param array $rows          Stored rows: url + post_id, in POST order.
		 * @param array $backend_links The batch status endpoint's links array.
		 *
		 * @return array Merged rows: url, post_id, status, recommendation,
		 *               candidates, wayback_url.
		 */
		public static function merge_ai_fix_batch_rows( $rows, $backend_links ) {
			$merged  = array();
			$backend = array_values( (array) $backend_links );
			$used    = array();

			foreach ( array_values( (array) $rows ) as $index => $row ) {
				$row_url = isset( $row['url'] ) ? (string) $row['url'] : '';
				// The batch payload sends relative URLs in absolute form, so
				// backend rows carry the absolute URL. Compare on that form.
				$api_url = self::absolutize_url( $row_url );
				$remote  = array();

				if ( isset( $backend[ $index ] ) && is_array( $backend[ $index ] ) && ! isset( $used[ $index ] )
					&& ( ! isset( $backend[ $index ]['url'] ) || $backend[ $index ]['url'] === $api_url ) ) {
					$remote           = $backend[ $index ];
					$used[ $index ]   = true;
				} else {
					foreach ( $backend as $backend_index => $candidate ) {
						if ( ! isset( $used[ $backend_index ] ) && is_array( $candidate ) && isset( $candidate['url'] ) && $candidate['url'] === $api_url ) {
							$remote                  = $candidate;
							$used[ $backend_index ]  = true;
							break;
						}
					}
				}

				$result = isset( $remote['result'] ) && is_array( $remote['result'] ) ? $remote['result'] : array();

				$merged[] = array(
					'url'            => $row_url,
					'post_id'        => isset( $row['post_id'] ) ? (int) $row['post_id'] : 0,
					'status'         => isset( $remote['status'] ) ? (string) $remote['status'] : 'queued',
					'recommendation' => isset( $result['recommendation'] ) ? (string) $result['recommendation'] : '',
					'candidates'     => isset( $result['candidates'] ) && is_array( $result['candidates'] ) ? $result['candidates'] : array(),
					'wayback_url'    => isset( $result['wayback_url'] ) && is_string( $result['wayback_url'] ) ? $result['wayback_url'] : '',
				);
			}

			return $merged;
		}

		/**
		 * Built-in exclusion substrings, applied before the user's own rules.
		 * These are dynamic or admin URLs that are never real broken links.
		 *
		 * @since 3.0.0
		 *
		 * @return array
		 */
		public static function get_default_exclusions() {
			$defaults = array(
				'admin-ajax.php',
				'?add-to-cart=',
				'&add-to-cart=',
				'wp-login.php',
				'xmlrpc.php',
				'/wp-json/',
				'?wc-ajax=',
				'/cart/?',
				'/checkout/?',
				'?replytocom=',
				'/wp-admin/',
			);

			return apply_filters( 'wpcbl_default_exclusions', $defaults );
		}

		/**
		 * Whether a URL hits the built-in exclusion list (substrings and
		 * non-content schemes).
		 *
		 * @since 3.0.0
		 *
		 * @param string $url The URL to test.
		 *
		 * @return bool
		 */
		public static function is_default_excluded( $url ) {
			$url = (string) $url;

			foreach ( array( 'mailto:', 'tel:', 'javascript:', '#' ) as $prefix ) {
				if ( wpcbl_str_starts_with( $url, $prefix ) ) {
					return true;
				}
			}

			foreach ( self::get_default_exclusions() as $rule ) {
				if ( '' !== $rule && false !== strpos( $url, $rule ) ) {
					return true;
				}
			}

			// WooCommerce account-endpoint URLs. When shortcodes render during
			// a scan, WooCommerce builds these relative to the "current page",
			// which is not the real my-account page, producing root-relative
			// URLs like /orders/ that visitors never see.
			if ( class_exists( 'WooCommerce' ) && '/' === substr( $url, 0, 1 ) ) {
				$endpoints = array( 'orders', 'view-order', 'downloads', 'edit-account', 'edit-address', 'payment-methods', 'add-payment-method', 'delete-payment-method', 'set-default-payment-method', 'lost-password', 'customer-logout', 'order-pay', 'order-received' );
				if ( function_exists( 'WC' ) && isset( WC()->query ) && method_exists( WC()->query, 'get_query_vars' ) ) {
					$endpoints = array_unique( array_merge( $endpoints, array_values( WC()->query->get_query_vars() ) ) );
				}

				$path = rtrim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
				foreach ( $endpoints as $endpoint ) {
					if ( '' !== $endpoint && '/' . $endpoint === $path ) {
						return true;
					}
				}
			}

			return false;
		}

		/**
		 * Whether a URL matches the built-in exclusions, a user exclusion rule
		 * (substring match), or the "Not broken" whitelist.
		 *
		 * @since 3.0.0
		 *
		 * @param string $url   The URL to test.
		 * @param array  $rules Exclusion rules, one substring each.
		 *
		 * @return bool
		 */
		public static function is_excluded( $url, $rules ) {
			if ( self::is_default_excluded( $url ) ) {
				return true;
			}

			foreach ( (array) $rules as $rule ) {
				$rule = trim( (string) $rule );
				if ( '' !== $rule && false !== strpos( $url, $rule ) ) {
					return true;
				}
			}

			$dismissed = (array) get_option( 'wpcbl_dismissed_urls', array() );

			return in_array( $url, $dismissed, true );
		}

		/**
		 * Remove stored scan results that match the built-in exclusions and
		 * refresh the last-scan counters. Runs once per plugin update.
		 *
		 * @since 3.0.0
		 *
		 * @return void
		 */
		public static function purge_default_excluded_results() {
			$links = get_option( 'wpcbl_check_for_broken_links_links', array() );
			if ( empty( $links ) || ! is_array( $links ) ) {
				return;
			}

			$removed = 0;
			foreach ( array( 'broken', 'warning', 'good', 'redirect' ) as $bucket ) {
				if ( empty( $links[ $bucket ] ) || ! is_array( $links[ $bucket ] ) ) {
					continue;
				}
				foreach ( $links[ $bucket ] as $index => $entry ) {
					if ( isset( $entry['link'] ) && self::is_default_excluded( $entry['link'] ) ) {
						unset( $links[ $bucket ][ $index ] );
						++$removed;
					}
				}
				$links[ $bucket ] = array_values( $links[ $bucket ] );
			}

			if ( 0 === $removed ) {
				return;
			}

			if ( isset( $links['total'] ) ) {
				$links['total'] = max( 0, (int) $links['total'] - $removed );
			}

			update_option( 'wpcbl_check_for_broken_links_links', $links );

			$summary = get_option( 'wpcbl_last_scan_summary' );
			if ( is_array( $summary ) ) {
				$summary['broken'] = count( $links['broken'] );
				if ( isset( $summary['total'] ) ) {
					$summary['total'] = max( 0, (int) $summary['total'] - $removed );
				}
				update_option( 'wpcbl_last_scan_summary', $summary );
			}
		}

		/**
		 * Scan slider plugin database tables for URLs (best-effort).
		 *
		 * Supports common slider plugins by checking known tables if they exist.
		 *
		 * @since 1.0.1
		 *
		 * @param array $links_to_exclude Links to exclude.
		 * @param int   $limit            Max number of links (-1 for unlimited).
		 * @param int   $already_count    Count already scanned.
		 * @param string $scan_timezone   PHP timezone identifier.
		 * @param array $smart_slider_ids Active Smart Slider IDs found in site content.
		 *
		 * @return array [results, count]
		 */
		public static function scan_slider_content( $links_to_exclude, $limit, $already_count, $scan_timezone = '', $smart_slider_ids = array() ) {
			global $wpdb;

			$results = array();
			$count   = 0;

			// Helper to check limit.
			$can_continue = function() use ( $limit, $already_count, &$count ) {
				if ( -1 === (int) $limit ) {
					return true;
				}
				return ( $already_count + $count ) < (int) $limit;
			};

			// Helper: scan a table with columns containing blobs.
			$scan_table = function( $table, $id_col, $blob_cols, $label_prefix, $where = '' ) use ( $wpdb, $links_to_exclude, $scan_timezone, &$results, &$count, $can_continue ) {
				$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Existence probe for third-party slider tables; no WP API and no point caching a one-shot scan.
				if ( $exists !== $table ) {
					return;
				}

				$cols_sql = esc_sql( implode( ', ', array_map( 'sanitize_key', $blob_cols ) ) );
				$id_col   = esc_sql( sanitize_key( $id_col ) );
				$where    = '' !== $where ? ' WHERE ' . $where : '';

				// Identifiers cannot be prepared. Table names are internal literals built
				// from $wpdb->prefix, columns/ids are sanitize_key()+esc_sql()'d above, and
				// $where fragments are hardcoded with absint()'d ids by the callers.
				$rows = $wpdb->get_results( "SELECT {$id_col} AS sid, {$cols_sql} FROM {$table}{$where}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- See identifier note above; scanning third-party tables has no WP API.
				if ( empty( $rows ) ) {
					return;
				}

				foreach ( $rows as $r ) {
					if ( ! $can_continue() ) {
						break;
					}
					$blob = '';
					foreach ( $blob_cols as $c ) {
						$c = sanitize_key( $c );
						$blob .= "\n" . ( isset( $r[ $c ] ) ? (string) $r[ $c ] : '' );
					}
					$links = self::extract_links( $blob );
					if ( empty( $links ) ) {
						continue;
					}
					foreach ( $links as $link_item ) {
						if ( ! $can_continue() ) {
							break;
						}
						$url = trim( $link_item['url'] );

						if ( '' === $url || '#' === $url[0] || '?' === $url[0] ) {
							continue;
						}

						// Substring exclusion rules + the Not broken whitelist.
						if ( self::is_excluded( $url, $links_to_exclude ) ) {
							continue;
						}
						$status = self::check_link( $url, 0, false );
						$status['element']      = $link_item['element'];
						$status['source_label'] = sprintf( '%s (ID %d)', $label_prefix, (int) $r['sid'] );
						$status['source_url']   = '';
						$status['is_slider']    = true;
						$status['link_source']  = self::get_link_source( $url );
						$status['detected_at']  = self::format_scan_datetime( null, 'F j, Y', $scan_timezone );

						$results[] = $status;
						$count++;
					}
				}
			};

			// Revolution Slider.
			$scan_table( $wpdb->prefix . 'revslider_slides', 'id', array( 'params', 'layers' ), 'Revolution Slider Slide' );

			// Smart Slider 3 (Nextend). This can expose URLs stored outside post content.
			$smart_slider_slides = $wpdb->prefix . 'nextend2_smartslider3_slides';
			$smart_slider_parent = $wpdb->prefix . 'nextend2_smartslider3_sliders';
			$smart_slider_where  = '';
			$smart_slider_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $smart_slider_slides ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Existence probe for a third-party slider table; no WP API and no point caching a one-shot scan.
			// Table name is an internal literal built from $wpdb->prefix; identifiers cannot be prepared.
			$smart_slider_cols   = $smart_slider_exists === $smart_slider_slides ? $wpdb->get_col( 'SHOW COLUMNS FROM ' . esc_sql( $smart_slider_slides ), 0 ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- See identifier note above.

			if ( is_array( $smart_slider_cols ) ) {
				$smart_slider_filters = array();

				if ( in_array( 'published', $smart_slider_cols, true ) ) {
					$smart_slider_filters[] = 'published = 1';
				}

				if ( empty( $smart_slider_ids ) ) {
					$smart_slider_filters[] = '1 = 0';
				} elseif ( in_array( 'slider', $smart_slider_cols, true ) ) {
					$parent_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $smart_slider_parent ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Existence probe for a third-party slider table; no WP API and no point caching a one-shot scan.
					$smart_slider_ids_sql = implode( ',', array_map( 'absint', $smart_slider_ids ) );

					$smart_slider_filters[] = "slider IN ({$smart_slider_ids_sql})";

					if ( $parent_exists === $smart_slider_parent ) {
						$smart_slider_filters[] = "slider IN (SELECT id FROM {$smart_slider_parent})";
					}
				}

				$smart_slider_where = implode( ' AND ', $smart_slider_filters );
			}

			$scan_table( $smart_slider_slides, 'id', array( 'slide' ), 'Smart Slider Slide', $smart_slider_where );

			return array( $results, $count );
		}

		/**
		 * Extract Smart Slider IDs referenced in active site content.
		 *
		 * @param string $content Content to scan.
		 *
		 * @return array
		 */
		public static function extract_smart_slider_ids( $content ) {
			$ids = array();

			if ( empty( $content ) || ! is_string( $content ) ) {
				return $ids;
			}

			if ( preg_match_all( '/\[smartslider3[^\]]*(?:slider|id)=["\']?(\d+)["\']?/i', $content, $matches ) ) {
				$ids = array_merge( $ids, $matches[1] );
			}

			if ( preg_match_all( '/"slider"\s*:\s*"?(\d+)"?/i', $content, $matches ) ) {
				$ids = array_merge( $ids, $matches[1] );
			}

			return array_values( array_unique( array_map( 'absint', $ids ) ) );
		}

		/**
		 * Extract links that are inside rendered slider markup.
		 *
		 * @param string $content Content to scan.
		 *
		 * @return array
		 */
		public static function extract_slider_links( $content ) {
			$matches = array();

			if ( empty( $content ) || ! is_string( $content ) ) {
				return $matches;
			}

			$html_dom = new DOMDocument();
			$old_value = libxml_use_internal_errors( true );
			$html_dom->loadHTML( $content );
			libxml_clear_errors();

			$attributes = self::get_html_link_sources();

			foreach ( $attributes as $tag => $attribute ) {
				foreach ( $html_dom->getElementsByTagName( $tag ) as $node ) {
					if ( ! self::node_has_slider_ancestor( $node ) ) {
						continue;
					}

					$link = filter_var( $node->getAttribute( $attribute ), FILTER_SANITIZE_URL );

					if ( '' !== $link ) {
						$matches[] = $link;
					}
				}
			}

			libxml_use_internal_errors( $old_value );

			return array_values( array_unique( $matches ) );
		}

		/**
		 * Check if a DOM node is inside known slider markup.
		 *
		 * @param DOMNode $node DOM node.
		 *
		 * @return bool
		 */
		private static function node_has_slider_ancestor( $node ) {
			while ( $node ) {
				if ( ! $node instanceof DOMElement ) {
					$node = isset( $node->parentNode ) ? $node->parentNode : null;
					continue;
				}

				$signature = strtolower( $node->getAttribute( 'class' ) . ' ' . $node->getAttribute( 'id' ) . ' ' . $node->getAttribute( 'data-title' ) );

				if ( false !== strpos( $signature, 'n2-ss' ) || false !== strpos( $signature, 'smartslider' ) || false !== strpos( $signature, 'smart-slider' ) || false !== strpos( $signature, 'nextend' ) || false !== strpos( $signature, 'rev_slider' ) ) {
					return true;
				}

				$node = isset( $node->parentNode ) ? $node->parentNode : null;
			}

			return false;
		}

		/**
		 * Format a date in the plugin scan timezone.
		 *
		 * @param string|null $date     Date string, or null for now.
		 * @param string      $format   PHP date format.
		 * @param string      $timezone PHP timezone identifier.
		 *
		 * @return string
		 */
		public static function format_scan_datetime( $date = null, $format = 'F j, Y g:i A', $timezone = '' ) {
			if ( empty( $timezone ) ) {
				$settings = get_option( 'wpcbl_check_for_broken_links_settings', array() );
				$timezone = isset( $settings['scan_timezone'] ) ? $settings['scan_timezone'] : wp_timezone_string();
			}

			try {
				$timezone_object = new DateTimeZone( $timezone );
			} catch ( Exception $e ) {
				$timezone_object = wp_timezone();
			}

			$date_object = is_null( $date ) ? new DateTime( 'now', $timezone_object ) : new DateTime( $date, $timezone_object );

			return $date_object->format( $format );
		}


		/**
		 * Extract links from content.
		 *
		 * @param string $content The content.
		 *
		 * @since 1.0.0
		 *
		 * @return array
		 */
		public static function extract_links( $content, $link_types = null ) {
			// Array that will contain our extracted links.
			$matches = array();

			// Get html link sources, narrowed by the Link Types setting when given.
			$html_link_sources = self::get_html_link_sources();
			if ( is_array( $link_types ) ) {
				if ( ! in_array( 'html', $link_types, true ) ) {
					unset( $html_link_sources['a'], $html_link_sources['iframe'] );
				}
				if ( ! in_array( 'image', $link_types, true ) ) {
					unset( $html_link_sources['img'] );
				}
			}

			if ( ! empty( $html_link_sources ) && ! empty( $content ) ) {

				// Fetch the DOM once.
				$html_dom = new DOMDocument();

				// Check if the DOMDocument was created.
				if ( empty( $html_dom ) || ! $html_dom || ! method_exists( $html_dom, 'loadHTML' ) ) {
					return $matches;
				}

				// Save old value of libxml use internal errors.
				$old_value = libxml_use_internal_errors( true );

				// Load the content.
				$html_dom->loadHTML( $content );

				// Clear the errors.
				libxml_clear_errors();

				// Look for each source.
				foreach ( $html_link_sources as $tag => $html_link_source ) {
					$links = $html_dom->getElementsByTagName( $tag );

					// Loop through the DOMNodeList.
					if ( ! empty( $links ) ) {
						foreach ( $links as $link ) {

							// Get the link in the href attribute.
							$link_href = filter_var( $link->getAttribute( $html_link_source ), FILTER_SANITIZE_URL );

							// Add the link to our array with its element type.
							$matches[] = array(
								'url'     => $link_href,
								'element' => 'img' === $tag ? 'image' : 'link',
							);
						}
					}
				}

				// Restore the old value.
				libxml_use_internal_errors( $old_value );
			}

			return $matches;
		}

		/**
		 * Get the html link sources from the html.
		 *
		 * @since 1.0.0
		 *
		 * @return array
		 */
		public static function get_html_link_sources() {
			$el = array(
				'a'      => 'href',
				'img'    => 'src',
				'iframe' => 'src',
			);
			return filter_var_array( apply_filters( 'wpcbl_html_link_sources', $el ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		}

		/**
		 * Check if a URL is broken or unsecure
		 *
		 * @param string  $link The link to check.
		 * @param integer $post_id The post ID.
		 * @param boolean $is_comment If the link is a comment.
		 *
		 * @since 1.0.0
		 *
		 * @return array
		 */
		public static function check_link( $link, $post_id, $is_comment ) {
			// Filter the link.
			$link = apply_filters( 'wpcbl_link_before_prechecks', $link );

			// Assuming the link is okay.
			$status = array(
				'type'         => 'good',
				'code'         => 200,
				'text'         => 'OK',
				'link'         => $link,
				'ID'           => $post_id,
				'is_comment'   => $is_comment,
				'detected_at'  => wpcbl_convert_timezone(),
				'marked_fixed' => '',
			);

			// Handle the filtered link if false.
			if ( ! $link ) {
				return array(
					'type'         => 'broken',
					'code'         => 0,
					'text'         => 'Did not pass pre-check filter',
					'link'         => $link,
					'ID'           => $post_id,
					'is_comment'   => $is_comment,
					'detected_at'  => wpcbl_convert_timezone(),
					'marked_fixed' => 'not-fixed',
				);

				// Handle the filtered link if in-proper array.
			} elseif ( is_array( $link ) && ( ! isset( $link['type'] ) || ! isset( $link['code'] ) || ! isset( $link['text'] ) ) ) {
				return array(
					'type'         => 'broken',
					'code'         => 0,
					'text'         => 'Did not pass pre-check filter',
					'link'         => $link,
					'ID'           => $post_id,
					'is_comment'   => $is_comment,
					'detected_at'  => wpcbl_convert_timezone(),
					'marked_fixed' => 'not-fixed',
				);

				// Return the filtered link as a status if proper array.
			} elseif ( is_array( $link ) ) {
				return $link;

				// Skip null links.
			} elseif ( $link && strlen( trim( $link ) ) == 0 ) {
				$status['text'] = 'Skipping null';
				return $status;

				// Skip if it is a hashtag / anchor link / query string.
			} elseif ( '#' == $link[0] || '?' == $link[0] ) {
				$status['text'] = 'Skipping: starts with ' . $link[0];
				return $status;

				// Skip in-page anchors of the homepage ("/#top", the full home
				// URL plus "#top", or the home path on subdirectory installs) —
				// same class as the "#top" case above.
			} elseif ( false !== strpos( $link, '#' )
				&& in_array(
					rtrim( (string) strtok( $link, '#' ), '/' ),
					array(
						'',
						rtrim( home_url(), '/' ),
						rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' ),
					),
					true
				) ) {
				$status['text'] = 'Skipping: in-page anchor';
				return $status;

				// Skip if omitted.
			} elseif ( '' == $link ) {
				$status = array(
					'type'         => 'broken',
					'code'         => 0,
					'text'         => 'Empty link',
					'link'         => $link,
					'ID'           => $post_id,
					'is_comment'   => $is_comment,
					'detected_at'  => wpcbl_convert_timezone(),
					'marked_fixed' => 'not-fixed',
				);

				// If the match is local, easy check.
			} elseif ( wpcbl_str_starts_with( $link, home_url() ) || wpcbl_str_starts_with( $link, '/' ) ) {

				// Check locally first.
				if ( ! url_to_postid( $link ) ) {

					// It may be redirected or an archive page, so let's check status anyway.
					return self::check_url_status_code( $link, $is_comment, $post_id );
				}

				// Otherwise.
			} else {

				// Skip url schemes.
				foreach ( self::get_url_schemes() as $scheme ) {
					if ( wpcbl_str_starts_with( $link, $scheme . ':' ) ) {
						$status['text'] = 'Skipping: Non-Http URL Schema';
						return $status;
					}
				}

				// Return the status.
				return self::check_url_status_code( $link, $is_comment, $post_id );
			}

			return $status;
		}

		/**
		 * Check a URL to see if it Exists
		 *
		 * @param string       $url The URL to check.
		 * @param boolean      $is_comment If the link is a comment.
		 * @param integer      $post_id The post ID.
		 * @param integer|null $timeout The timeout.
		 *
		 * @since 1.0.0
		 *
		 * @return array
		 */
		public static function check_url_status_code( $url, $is_comment, $post_id = 0, $timeout = null ) {
			// Get timeout from the settings (5-120 seconds, default 30).
			if ( is_null( $timeout ) ) {
				$timeout = min( max( (int) wpcbl_get_option( 'timeout', 30 ), 5 ), 120 );
			}

			// Browsers never send the #fragment to the server, so neither do we.
			// On HTTP stacks that pass it through raw, the target site answers
			// 404 for e.g. "apage/#ananchor" and a working link gets reported
			// broken. The report row below still shows the original $url.
			$request_url = $url;
			$hash_pos    = strpos( $request_url, '#' );
			if ( false !== $hash_pos ) {
				$request_url = substr( $request_url, 0, $hash_pos );
			}

			// Root-relative href: resolve against the site ORIGIN (scheme://host),
			// the way a browser does. Prepending home_url() doubles the directory
			// on subdirectory installs ("/dev/apage/" became "/dev/dev/apage/").
			if ( wpcbl_str_starts_with( $request_url, '/' ) ) {
				$home_parts = wp_parse_url( home_url() );
				$origin     = $home_parts['scheme'] . '://' . $home_parts['host']
					. ( isset( $home_parts['port'] ) ? ':' . $home_parts['port'] : '' );
				$link       = $origin . $request_url;
			} else {
				$link = $request_url;
			}

			// Check if from youtube.
			$watch_url = self::is_youtube_link( $link );
			if ( $watch_url ) {
				$link = 'https://www.youtube.com/oembed?format=json&url=' . $watch_url;
			}

			// The request args.
			$http_request_args = apply_filters(
				'wpcbl_http_request_args',
				array(
					'method'      => 'HEAD',
					'timeout'     => $timeout,
					'redirection' => 5,
					'httpversion' => '1.1',
					'sslverify'   => true,
				),
				$url
			);

			// Check the link.
			$response = wp_safe_remote_get( $link, $http_request_args );
			if ( ! is_wp_error( $response ) ) {
				$code  = wp_remote_retrieve_response_code( $response );
				$error = 'Unknown';
			} else {
				$code  = 0;
				$error = $response->get_error_message();
			}

			$invalid_url_error = 0 === (int) $code && 'A valid URL was not provided.' === $error;
			$fallback_codes    = apply_filters( 'wpcbl_get_fallback_status_codes', array( 0, 403, 404, 405, 406 ), $url );

			if ( ! $invalid_url_error && in_array( (int) $code, array_map( 'absint', (array) $fallback_codes ), true ) ) {
				$existing_headers = isset( $http_request_args['headers'] ) && is_array( $http_request_args['headers'] ) ? $http_request_args['headers'] : array();
				$fallback_args    = array_merge(
					$http_request_args,
					array(
						'method'              => 'GET',
						'limit_response_size' => 2048,
						'headers'             => array_merge(
							array(
								'User-Agent' => 'Mozilla/5.0 (compatible; CheckForBrokenLinks/' . WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION . '; ' . home_url() . ')',
								'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
							),
							$existing_headers
						),
					)
				);
				$fallback_args    = apply_filters( 'wpcbl_http_request_get_fallback_args', $fallback_args, $url, $http_request_args );
				$fallback_response = wp_safe_remote_get( $link, $fallback_args );

				if ( ! is_wp_error( $fallback_response ) ) {
					$fallback_code = (int) wp_remote_retrieve_response_code( $fallback_response );

					if ( $fallback_code > 0 && $fallback_code !== (int) $code ) {
						$code  = $fallback_code;
						$error = 'Unknown';
					}
				}
			}

			// Let's make invalid URL 0 codes broken.
			if ( 0 === $code && 'A valid URL was not provided.' == $error ) {
				$code = 666;
			}

			// Possible Codes.
			$codes = array(
				0   => $error,
				100 => 'Continue',
				101 => 'Switching Protocols',
				102 => 'Processing',
				103 => 'Early Hints',
				200 => 'OK',
				201 => 'Created',
				202 => 'Accepted',
				203 => 'Non-Authoritative Information',
				204 => 'No Content',
				205 => 'Reset Content',
				206 => 'Partial Content',
				207 => 'Multi-Status',
				208 => 'Already Reported',
				226 => 'IM Used',
				300 => 'Multiple Choices',
				301 => 'Moved Permanently',
				302 => 'Found',
				303 => 'See Other',
				304 => 'Not Modified',
				305 => 'Use Proxy',
				306 => 'Switch Proxy',
				307 => 'Temporary Redirect',
				308 => 'Permanent Redirect',
				400 => 'Bad Request',
				401 => 'Unauthorized',
				402 => 'Payment Required',
				403 => 'Forbidden or Unsecure',
				404 => 'Not Found',
				405 => 'Method Not Allowed',
				406 => 'Not Acceptable',
				407 => 'Proxy Authentication Required',
				408 => 'Request Timeout',
				409 => 'Conflict',
				410 => 'Gone',
				411 => 'Length Required',
				412 => 'Precondition Failed',
				413 => 'Payload Too Large',
				414 => 'URI Too Long',
				415 => 'Unsupported Media Type',
				416 => 'Range Not Satisfiable',
				417 => 'Expectation Failed',
				418 => 'I\'m a teapot',
				421 => 'Misdirected Request',
				422 => 'Unprocessable Entity',
				423 => 'Locked',
				424 => 'Failed Dependency',
				425 => 'Too Early',
				426 => 'Upgrade Required',
				428 => 'Precondition Required',
				429 => 'Too Many Requests',
				431 => 'Request Header Fields Too Large',
				451 => 'Unavailable For Legal Reasons',
				500 => 'Internal Server Error',
				501 => 'Not Implemented',
				502 => 'Bad Gateway',
				503 => 'Service Unavailable',
				504 => 'Gateway Timeout',
				505 => 'HTTP Version Not Supported',
				506 => 'Variant Also Negotiates',
				507 => 'Insufficient Storage',
				508 => 'Loop Detected',
				510 => 'Not Extended',
				511 => 'Network Authentication Required',

				// Unofficial codes.
				218 => 'This is fine',
				419 => 'Page Expired',
				420 => 'Method Failure',
				430 => 'Request Header Fields Too Large',
				450 => 'Blocked by Windows Parental Controls',
				498 => 'Invalid Token',
				499 => 'Token Required',
				509 => 'Bandwidth Limit Exceeded',
				526 => 'Invalid SSL Certificate',
				529 => 'Site is overloaded',
				530 => 'Site is frozen',
				598 => 'Network read timeout error',
				440 => 'Login Time-out',
				444 => 'No Response',
				494 => 'Request header too large',
				495 => 'SSL Certificate Error',
				496 => 'SSL Certificate Required',
				497 => 'HTTP Request Sent to HTTPS Port',
				520 => 'Web Server Returned an Unknown Error',
				521 => 'Web Server Is Down',
				522 => 'Connection Timed Out',
				523 => 'Origin Is Unreachable',
				524 => 'A Timeout Occurred',
				525 => 'SSL Handshake Failed',
				527 => 'Railgun Error',
				666 => 'Invalid URL',
				999 => 'Scanning Not Permitted',
			);

			// Bad links: 404/410 (and the invalid-URL marker) are broken.
			if ( in_array( $code, self::get_bad_status_codes() ) ) {
				$type = 'broken';

				// Warnings: timeouts, bot blocks, SSL errors, and all server errors.
			} elseif ( in_array( $code, self::get_warning_status_codes() ) || (int) $code >= 500 ) {
				$type = 'warning';

				// Good links.
			} else {
				$type = 'good';
			}

			// Filter status.
			$status = apply_filters(
				'wpcbl_status',
				array(
					'type'         => $type,
					'code'         => $code,
					'text'         => isset( $codes[ $code ] ) ? $codes[ $code ] : $error,
					'link'         => $url,
					'ID'           => $post_id,
					'is_comment'   => $is_comment,
					'detected_at'  => wpcbl_convert_timezone(),
					'marked_fixed' => 'not-fixed',
				)
			);

			return $status;
		}

		/**
		 * Get all the URL Schemes to ignore in the pre-check.
		 *
		 * @since 1.0.0
		 *
		 * @return array
		 */
		public static function get_url_schemes() {
			// Official: https://www.iana.org/assignments/uri-schemes/uri-schemes.xhtml.
			$official = array( 'aaa', 'aaas', 'about', 'acap', 'acct', 'acd', 'acr', 'adiumxtra', 'adt', 'afp', 'afs', 'aim', 'amss', 'android', 'appdata', 'apt', 'ar', 'ark', 'at', 'attachment', 'aw', 'barion', 'bb', 'beshare', 'bitcoin', 'bitcoincash', 'blob', 'bolo', 'brid', 'browserext', 'cabal', 'calculator', 'callto', 'cap', 'cast', 'casts', 'chrome', 'chrome-extension', 'cid', 'coap', 'coap+tcp', 'coap+ws', 'coaps', 'coaps+tcp', 'coaps+ws', 'com-eventbrite-attendee', 'content', 'content-type', 'crid', 'cstr', 'cvs', 'dab', 'dat', 'data', 'dav', 'dhttp', 'diaspora', 'dict', 'did', 'dis', 'dlna-playcontainer', 'dlna-playsingle', 'dns', 'dntp', 'doi', 'dpp', 'drm', 'drop', 'dtmi', 'dtn', 'dvb', 'dvx', 'dweb', 'ed2k', 'eid', 'elsi', 'embedded', 'ens', 'ethereum', 'example', 'facetime', 'fax', 'feed', 'feedready', 'fido', 'file', 'filesystem', 'finger', 'first-run-pen-experience', 'fish', 'fm', 'ftp', 'fuchsia-pkg', 'geo', 'gg', 'git', 'gitoid', 'gizmoproject', 'go', 'gopher', 'graph', 'grd', 'gtalk', 'h323', 'ham', 'hcap', 'hcp', 'hxxp', 'hxxps', 'hydrazone', 'hyper', 'iax', 'icap', 'icon', 'im', 'imap', 'info', 'iotdisco', 'ipfs', 'ipn', 'ipns', 'ipp', 'ipps', 'irc', 'irc6', 'ircs', 'iris', 'iris.beep', 'iris.lwz', 'iris.xpc', 'iris.xpcs', 'isostore', 'itms', 'jabber', 'jar', 'jms', 'keyparc', 'lastfm', 'lbry', 'ldap', 'ldaps', 'leaptofrogans', 'lid', 'lorawan', 'lpa', 'lvlt', 'machineProvisioningProgressReporter', 'magnet', 'mailserver', 'mailto', 'maps', 'market', 'matrix', 'message', 'microsoft.windows.camera', 'microsoft.windows.camera.multipicker', 'microsoft.windows.camera.picker', 'mid', 'mms', 'modem', 'mongodb', 'moz', 'ms-access', 'ms-appinstaller', 'ms-browser-extension', 'ms-calculator', 'ms-drive-to', 'ms-enrollment', 'ms-excel', 'ms-eyecontrolspeech', 'ms-gamebarservices', 'ms-gamingoverlay', 'ms-getoffice', 'ms-help', 'ms-infopath', 'ms-inputapp', 'ms-launchremotedesktop', 'ms-lockscreencomponent-config', 'ms-media-stream-id', 'ms-meetnow', 'ms-mixedrealitycapture', 'ms-mobileplans', 'ms-newsandinterests', 'ms-officeapp', 'ms-people', 'ms-project', 'ms-powerpoint', 'ms-publisher', 'ms-remotedesktop', 'ms-remotedesktop-launch', 'ms-restoretabcompanion', 'ms-screenclip', 'ms-screensketch', 'ms-search', 'ms-search-repair', 'ms-secondary-screen-controller', 'ms-secondary-screen-setup', 'ms-settings', 'ms-settings-airplanemode', 'ms-settings-bluetooth', 'ms-settings-camera', 'ms-settings-cellular', 'ms-settings-cloudstorage', 'ms-settings-connectabledevices', 'ms-settings-displays-topology', 'ms-settings-emailandaccounts', 'ms-settings-language', 'ms-settings-location', 'ms-settings-lock', 'ms-settings-nfctransactions', 'ms-settings-notifications', 'ms-settings-power', 'ms-settings-privacy', 'ms-settings-proximity', 'ms-settings-screenrotation', 'ms-settings-wifi', 'ms-settings-workplace', 'ms-spd', 'ms-stickers', 'ms-sttoverlay', 'ms-transit-to', 'ms-useractivityset', 'ms-virtualtouchpad', 'ms-visio', 'ms-walk-to', 'ms-whiteboard', 'ms-whiteboard-cmd', 'ms-word', 'msnim', 'msrp', 'msrps', 'mss', 'mt', 'mtqp', 'mumble', 'mupdate', 'mvn', 'mvrp', 'mvrps', 'news', 'nfs', 'ni', 'nih', 'nntp', 'notes', 'num', 'ocf', 'oid', 'onenote', 'onenote-cmd', 'opaquelocktoken', 'openid', 'openpgp4fpr', 'otpauth', 'p1', 'pack', 'palm', 'paparazzi', 'payment', 'payto', 'pkcs11', 'platform', 'pop', 'pres', 'prospero', 'proxy', 'pwid', 'psyc', 'pttp', 'qb', 'query', 'quic-transport', 'redis', 'rediss', 'reload', 'res', 'resource', 'rmi', 'rsync', 'rtmfp', 'rtmp', 'rtsp', 'rtsps', 'rtspu', 'sarif', 'secondlife', 'secret-token', 'service', 'session', 'sftp', 'sgn', 'shc', 'shttp', 'sieve', 'simpleledger', 'simplex', 'sip', 'sips', 'skype', 'smb', 'smp', 'sms', 'smtp', 'snews', 'snmp', 'soap.beep', 'soap.beeps', 'soldat', 'spiffe', 'spotify', 'ssb', 'ssh', 'starknet', 'steam', 'stun', 'stuns', 'submit', 'svn', 'swh', 'swid', 'swidpath', 'tag', 'taler', 'teamspeak', 'tel', 'teliaeid', 'telnet', 'tftp', 'things', 'thismessage', 'tip', 'tn3270', 'tool', 'turn', 'turns', 'tv', 'udp', 'unreal', 'upt', 'urn', 'ut2004', 'uuid-in-package', 'v-event', 'vemmi', 'ventrilo', 'ves', 'videotex', 'vnc', 'view-source', 'vscode', 'vscode-insiders', 'vsls', 'w3', 'wais', 'web3', 'wcr', 'webcal', 'web+ap', 'wifi', 'wpid', 'ws', 'wss', 'wtai', 'wyciwyg', 'xcon', 'xcon-userid', 'xfire', 'xmlrpc.beep', 'xmlrpc.beeps', 'xmpp', 'xftp', 'xrcp', 'xri', 'ymsgr' );

			// Unofficial: https://en.wikipedia.org/wiki/List_of_URI_schemes.
			$unofficial = array( 'admin', 'app', 'freeplane', 'javascript', 'jdbc', 'msteams', 'ms-spd', 'odbc', 'psns', 'rdar', 's3', 'trueconf', 'slack', 'stratum', 'viber', 'zoommtg', 'zoomus' );

			// Return them.
			$all_schemes = array_unique( array_merge( $official, $unofficial ) );
			return filter_var_array( apply_filters( 'wpcbl_url_schemes', $all_schemes ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		}

		/**
		 * Check if a link is on YouTube, if so return ID
		 * Does not check if the video is valid.
		 *
		 * @param string $link The link to check.
		 *
		 * @since 1.0.0
		 *
		 * @return boolean
		 */
		public static function is_youtube_link( $link ) {
			// The id.
			$id = false;

			// Get the host.
			$parse = wp_parse_url( $link );
			if ( isset( $parse['host'] ) && isset( $parse['path'] ) ) {
				$host = $parse['host'];
				$path = $parse['path'];

				// Make sure it's on youtube.
				if ( $host && in_array( $host, array( 'youtube.com', 'www.youtube.com', 'youtu.be' ) ) ) {

					// if it's embeded video.
					if ( strpos( $path, '/embed/' ) !== false ) {
						$id = str_replace( '/embed/', '', $path );
						if ( strpos( $id, '&' ) !== false ) {
							$id = substr( $id, 0, strpos( $id, '&' ) );
						}

						// if it contains v.
					} elseif ( strpos( $path, '/v/' ) !== false ) {
						$id = str_replace( '/v/', '', $path );
						if ( strpos( $id, '&' ) !== false ) {
							$id = substr( $id, 0, strpos( $id, '&' ) );
						}

						// if it contains watch.
					} elseif ( strpos( $path, '/watch' ) !== false && isset( $parse['query'] ) ) {
						parse_str( $parse['query'], $queries );
						if ( isset( $queries['v'] ) ) {
							$id = $queries['v'];
						}
					}
				}
			}

			// validate if id.
			if ( $id ) {
				// Create a watch url.s.
				return 'https://www.youtube.com/watch?v=' . $id;
			}

			return false;
		}

		/**
		 * Get the link source is it internal or external.
		 *
		 * @param string $link The link to check.
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public static function get_link_source( $link ) {
			$link_host = wp_parse_url( $link, PHP_URL_HOST );
			$site_host = wp_parse_url( get_site_url(), PHP_URL_HOST );

			// Relative URLs have no host and always point at this site.
			if ( empty( $link_host ) ) {
				return 'internal';
			}

			return $link_host === $site_host ? 'internal' : 'external';
		}

		/**
		 * Get the bad status codes.
		 *
		 * @since 1.0.0
		 *
		 * @return array
		 */
		public static function get_bad_status_codes() {
			// 404/410 are genuinely gone; 666 is the plugin's invalid-URL marker.
			$default_codes = array( 404, 410, 666 );
			return filter_var_array( apply_filters( 'wpcbl_bad_status_codes', $default_codes ), FILTER_SANITIZE_NUMBER_INT );
		}

		/**
		 * Get the warning status codes.
		 *
		 * @since 1.0.0
		 *
		 * @return array
		 */
		public static function get_warning_status_codes() {
			// 0 covers wp_error results (timeouts, SSL failures, DNS). 403/429 are
			// usually bot blocks, 408 is a timeout, 495-497 are SSL problems.
			// Every 5xx code is added as a warning in check_url_status_code().
			$default_codes = array( 0, 403, 408, 429, 495, 496, 497 );
			$default_codes = filter_var_array( apply_filters( 'wpcbl_warning_status_codes', $default_codes ), FILTER_SANITIZE_NUMBER_INT );

			return $default_codes;
		}

		/**
		 * Convert timezone.
		 *
		 * @param string $date Default null.
		 * @param string $format Default 'F j, Y g:i A'.
		 * @param string $timezone Default null.
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public static function convert_timezone( $date = null, $format = 'F j, Y g:i A', $timezone = null ) {
			// Get today as default.
			if ( is_null( $date ) ) {
				$date = gmdate( 'Y-m-d H:i:s' );
			}

			// Get the date in UTC time.
			$date = new DateTime( $date, new DateTimeZone( 'UTC' ) );

			// Get the timezone string.
			if ( ! is_null( $timezone ) ) {
				$timezone_string = $timezone;
			} else {
				$timezone_string = wp_timezone_string();
			}

			// Set the timezone to the new one.
			$date->setTimezone( new DateTimeZone( $timezone_string ) );

			// Format it the way we way.
			$new_date = $date->format( $format );

			return $new_date;
		}

		/**
		 * Send email.
		 *
		 * @param string $email_enabled Default 'off'.
		 * @param string $email_addresses Default ''.
		 * @param array  $broken_links Default [].
		 * @param string $scan_time_str Formatted scan time.
		 *
		 * @since 1.0.0
		 *
		 * @return array
		 */
		public static function send_mails( $email_enabled = 'off', $email_addresses = '', $broken_links = array(), $scan_time_str = '' ) {
			$result = array(
				'status'     => 'skipped',
				'message'    => __( 'Email notifications are disabled.', 'check-for-broken-links' ),
				'recipients' => '',
			);

			if ( 'on' !== $email_enabled ) {
				return $result;
			}

			if ( empty( $email_addresses ) ) {
				$result['message'] = __( 'No email address is saved.', 'check-for-broken-links' );
				return $result;
			}

			$recipients = array_filter(
				array_map( 'trim', explode( ',', $email_addresses ) ),
				'is_email'
			);

			$result['recipients'] = implode( ', ', $recipients );

			if ( empty( $recipients ) ) {
				$result['message'] = __( 'No valid email address is saved.', 'check-for-broken-links' );
				return $result;
			}

			if ( empty( $broken_links ) ) {
				$result['message'] = __( 'No broken links were found.', 'check-for-broken-links' );
				return $result;
			}

			// Headers.
			$headers   = array();
			$headers[] = 'From: ' . WPCBL_CHECK_BROKEN_LINKS_PLUGIN_NAME . ' <' . get_bloginfo( 'admin_email' ) . '>';
			$headers[] = 'Content-Type: text/html; charset=UTF-8';

			if ( empty( $scan_time_str ) ) {
				$scan_time_str = current_time( 'mysql' );
			}

			// Subject.
			$subject = sprintf(
				/* translators: 1: site name, 2: scan time. */
				esc_html__( 'Broken Links Found on %1$s - %2$s', 'check-for-broken-links' ),
				wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				$scan_time_str
			);

			// Message.
			$message = esc_html__( 'The following broken links were found on', 'check-for-broken-links' ) . ' ' . esc_url( get_site_url() ) . ' ' . esc_html__( 'during the scan at', 'check-for-broken-links' ) . ' ' . esc_html( $scan_time_str ) . ':<br><br>';

			$links_to_send = array();

			foreach ( $broken_links as $type => $link ) {
				$links_to_send[] = esc_html__( 'URL:', 'check-for-broken-links' ) . ' ' . esc_url( $link['link'] ) . '<br>' . esc_html__( 'Status Code:', 'check-for-broken-links' ) . ' ' . esc_html( $link['code'] ) . ' - ' . esc_html( $link['text'] );
			}

			// Add links and footer.
			$message .= implode( '<br><br>', $links_to_send ) . '<br><br><em>- ' . WPCBL_CHECK_BROKEN_LINKS_PLUGIN_NAME . ' ' . esc_html__( 'Plugin', 'check-for-broken-links' ) . '</em>';

			// Pro: also notify post authors about broken links in their own posts.
			if ( wpcbl_has_pro() && 'on' === wpcbl_get_option( 'notify_authors', '' ) ) {
				$links_by_author = array();
				foreach ( $broken_links as $author_link ) {
					if ( empty( $author_link['ID'] ) || ! empty( $author_link['is_comment'] ) || ! empty( $author_link['is_slider'] ) ) {
						continue;
					}
					$author_id = (int) get_post_field( 'post_author', $author_link['ID'] );
					if ( $author_id ) {
						$links_by_author[ $author_id ][] = $author_link;
					}
				}

				foreach ( $links_by_author as $author_id => $author_links ) {
					$author_email = get_the_author_meta( 'user_email', $author_id );
					if ( ! $author_email || in_array( $author_email, (array) $recipients, true ) ) {
						continue;
					}

					$author_lines = array();
					foreach ( $author_links as $author_link ) {
						$author_lines[] = esc_html__( 'URL:', 'check-for-broken-links' ) . ' ' . esc_url( $author_link['link'] ) . '<br>' . esc_html__( 'Found in:', 'check-for-broken-links' ) . ' ' . esc_html( get_the_title( $author_link['ID'] ) );
					}

					$author_message = esc_html__( 'Broken links were found in your posts on', 'check-for-broken-links' ) . ' ' . esc_url( get_site_url() ) . ':<br><br>' . implode( '<br><br>', $author_lines ) . '<br><br><em>- ' . WPCBL_CHECK_BROKEN_LINKS_PLUGIN_NAME . ' ' . esc_html__( 'Plugin', 'check-for-broken-links' ) . '</em>';

					wp_mail( $author_email, $subject, $author_message, $headers );
				}
			}

			if ( wp_mail( $recipients, $subject, $message, $headers ) ) {
				$result['status']  = 'sent';
				$result['message'] = __( 'Email sent successfully.', 'check-for-broken-links' );
				return $result;
			}

			$result['status']  = 'failed';
			$result['message'] = __( 'wp_mail() returned false.', 'check-for-broken-links' );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( WPCBL_CHECK_BROKEN_LINKS_PLUGIN_NAME . ' ' . esc_html__( 'email could not be sent. Please check for issues with WP Mailer.', 'check-for-broken-links' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			return $result;
		}

		/**
		 * Register the loader image to the media library.
		 *
		 * @param string $path The path to the image.
		 *
		 * @since 1.0.0
		 *
		 * @return integer|boolean
		 */
		public static function register_loader_image( $path ) {
			$upload_dir = wp_upload_dir();
			$full_path  = trailingslashit( WPCBL_CHECK_BROKEN_LINKS_ROOT_PATH . '/assets/dist/images/' ) . $path;

			// Ensure the file exists.
			if ( ! file_exists( $full_path ) ) {
				return false;
			}

			// Check file type.
			$file_type = wp_check_filetype( basename( $full_path ) );

			// Check if the image is already registered.
			$args = array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_wpcbl_loader',
						'value'   => $path,
						'compare' => '=',
					),
				),
				'posts_per_page' => 1,
			);

			$query = new WP_Query( $args );
			if ( $query->have_posts() ) {
				return $query->posts[0]->ID;
			}

			// Copy the image to the uploads directory.
			$destination_path = trailingslashit( $upload_dir['path'] ) . basename( $full_path );
			if ( ! copy( $full_path, $destination_path ) ) {
				return false;
			}

			// Create the attachment.
			$attachment = array(
				'guid'           => trailingslashit( $upload_dir['url'] ) . basename( $full_path ),
				'post_mime_type' => $file_type['type'],
				'post_title'     => sanitize_file_name( basename( $full_path ) ),
				'post_status'    => 'inherit',
			);

			$attach_id = wp_insert_attachment( $attachment, $destination_path );
			if ( ! is_wp_error( $attach_id ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
				wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $destination_path ) );
				update_post_meta( $attach_id, '_wpcbl_loader', $path );
				return $attach_id;
			}

			return false;
		}

		/**
		 * Get the loader image HTML.
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public static function get_loader_image_html() {
			$attachment_id = self::register_loader_image( 'loader.gif' );

			if ( $attachment_id ) {
				return wp_get_attachment_image(
					$attachment_id,
					array( 30, 30 ),
					false,
					array(
						'class' => 'wpcbl_loader_margin',
						'alt'   => __( 'Check for broken links loader', 'check-for-broken-links' ),
					)
				);
			}

			// Fallback to the URL -- added an empty string for now.
			return '';
		}
	}
}
