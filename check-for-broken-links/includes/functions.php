<?php
/**
 * Some helper core functions.
 *
 * @package WPCBL_Check_Broken_Links
 * @author Norse Digital Group LLC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'wpcbl_str_starts_with' ) ) {
	/**
	 * Check if a string starts with another string.
	 *
	 * @param string $haystack - The string to search in.
	 * @param string $needle   - The string to search for.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	function wpcbl_str_starts_with( $haystack, $needle ) {
		return strpos( $haystack, $needle ) === 0;
	}
}

if ( ! function_exists( 'wpcbl_str_ends_with' ) ) {
	/**
	 * Check if a string ends with another string.
	 *
	 * @param string $haystack - The string to search in.
	 * @param string $needle   - The string to search for.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	function wpcbl_str_ends_with( $haystack, $needle ) {
		return '' !== $needle && substr( $haystack, -strlen( $needle ) ) === (string) $needle;
	}
}

if ( ! function_exists( 'wpcbl_str_contains' ) ) {
	/**
	 * Check if a string contains another string.
	 *
	 * @param string $haystack - The string to search in.
	 * @param string $needle   - The string to search for.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	function wpcbl_str_contains( $haystack, $needle ) {
		return '' !== $needle && mb_strpos( $haystack, $needle ) !== false;
	}
}

if ( ! function_exists( 'wpcbl_has_pro' ) ) {
	/**
	 * Whether Pro features are available on this install.
	 *
	 * The free plugin always returns false; the Pro add-on unlocks
	 * features through the filter.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	function wpcbl_has_pro() {
		return (bool) apply_filters( 'wpcbl_has_pro', false );
	}
}

if ( ! function_exists( 'wpcbl_remove_url_params' ) ) {
	/**
	 * Whether brokenlinkchecker.io should drop URL parameters while it
	 * crawls this site. On unless the admin switched it off.
	 *
	 * @since 3.1.3
	 *
	 * @return bool
	 */
	function wpcbl_remove_url_params() {
		$settings = get_option( 'wpcbl_check_for_broken_links_settings', array() );

		return ! ( is_array( $settings ) && isset( $settings['remove_url_params'] ) && 'off' === $settings['remove_url_params'] );
	}
}

if ( ! function_exists( 'wpcbl_url_params_tip' ) ) {
	/**
	 * Tooltip for the Remove URL parameters switch, with this site's own
	 * address in the example.
	 *
	 * @since 3.1.3
	 *
	 * @return string
	 */
	function wpcbl_url_params_tip() {
		$domain = untrailingslashit( preg_replace( '#^https?://#i', '', home_url() ) );

		return sprintf(
			/* translators: %s: this site's address without https://, e.g. example.com. */
			__( 'Enable this if you want URL parameters to be ignored during the crawl. For example, %1$s/?parameter1=value1 and %1$s/?parameter1=value2 URLs will be replaced by %1$s/.', 'check-for-broken-links' ),
			$domain
		);
	}
}

if ( ! function_exists( 'wpcbl_get_option' ) ) {
	/**
	 * Get an option from the plugin settings.
	 *
	 * @param string $option_name - The option name.
	 * @param mixed  $default     - The default value.
	 *
	 * @since 1.0.0
	 *
	 * @return mixed
	 */
	function wpcbl_get_option( $option_name, $default = false ) {
		$settings = get_option( 'wpcbl_check_for_broken_links_settings', array() );

		if ( isset( $settings[ $option_name ] ) ) {
			return $settings[ $option_name ];
		}

		return $default;
	}
}

if ( ! function_exists( 'wpcbl_get_post_or_comment_title' ) ) {
	/**
	 * Get the post or comment title.
	 *
	 * @param array $item - The item.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	function wpcbl_get_post_or_comment_title( $item ) {
		if ( ! isset( $item['ID'] ) ) {
			return;
		}

		$id = $item['ID'];

		if ( isset( $item['is_comment'] ) && $item['is_comment'] ) {
			return __( 'Author: ', 'check-for-broken-links' ) . get_comment_author( $id );
		}
		return get_the_title( $id );
	}
}

if ( ! function_exists( 'wpcbl_get_post_or_comment_link' ) ) {
	/**
	 * Get the post or comment link.
	 *
	 * @param array $item - The item.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	function wpcbl_get_post_or_comment_link( $item ) {
		if ( ! isset( $item['ID'] ) ) {
			return;
		}

		$id = $item['ID'];

		if ( isset( $item['is_comment'] ) && $item['is_comment'] ) {
			return get_comment_link( $id );
		}
		return get_permalink( $id );
	}
}

if ( ! function_exists( 'wpcbl_get_post_or_comment_edit_link' ) ) {
	/**
	 * Get the post or comment edit link.
	 *
	 * @param array $item - The item.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	function wpcbl_get_post_or_comment_edit_link( $item ) {
		if ( ! isset( $item['ID'] ) ) {
			return;
		}

		$id = $item['ID'];

		if ( isset( $item['is_comment'] ) && $item['is_comment'] ) {
			return get_edit_comment_link( $id );
		}
		return get_edit_post_link( $id );
	}
}

if ( ! function_exists( 'wpcbl_get_post_or_comment_type' ) ) {
	/**
	 * Get the post or comment type.
	 *
	 * @param array $item - The item.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	function wpcbl_get_post_or_comment_type( $item ) {
		if ( isset( $item['is_slider'] ) && $item['is_slider'] ) {
			return __( 'Slider', 'check-for-broken-links' );
		}

		if ( isset( $item['is_comment'] ) && $item['is_comment'] ) {
			return __( 'Comment', 'check-for-broken-links' );
		}

		if ( ! isset( $item['ID'] ) ) {
			return __( 'Custom', 'check-for-broken-links' );
		}

		$post_type = get_post_type( $item['ID'] );

		if ( 'post' === $post_type ) {
			return __( 'Post', 'check-for-broken-links' );
		}

		if ( 'page' === $post_type ) {
			return __( 'Page', 'check-for-broken-links' );
		}

		// Custom post types show their own singular label, e.g. Product.
		$post_type_object = $post_type ? get_post_type_object( $post_type ) : null;
		if ( $post_type_object && ! empty( $post_type_object->labels->singular_name ) ) {
			return $post_type_object->labels->singular_name;
		}

		return __( 'Custom', 'check-for-broken-links' );
	}
}

if ( ! function_exists( 'wpcbl_convert_timezone' ) ) {
	/**
	 * Convert timezone
	 *
	 * @param string $date - The date.
	 * @param string $format - The format.
	 * @param string $timezone - The timezone.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	function wpcbl_convert_timezone( $date = null, $format = 'F j, Y g:i A', $timezone = null ) {
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
}

if ( ! function_exists( 'wpcbl_scannable_post_types' ) ) {
	/**
	 * Content post types a scan covers: public, with an admin UI, and not a
	 * builder/system type. Shared by the dashboard "ready to scan" count and
	 * the Content Types to Scan setting so both lists always match.
	 *
	 * @since 3.0.0
	 *
	 * @return WP_Post_Type[] Post type objects keyed by post type name.
	 */
	function wpcbl_scannable_post_types() {
		$types = get_post_types( array( 'public' => true ), 'objects' );

		// Registered public by builders/core but not content a user scans.
		$excluded = array(
			'attachment',
			'revision',
			'nav_menu_item',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'user_request',
			'wp_block',
			'wp_template',
			'wp_template_part',
			'wp_global_styles',
			'wp_navigation',
			'wp_font_family',
			'wp_font_face',
			'elementor_library',
			'e-landing-page',
			'e-floating-buttons',
			'tglobal_style',
		);

		// Themify Builder and Elementor internals share these prefixes;
		// Themify's real CPTs (tdcpt_*) stay in.
		$excluded_prefixes = array( 'tbuilder_', 'themify_', 'e-' );

		foreach ( $types as $name => $type ) {
			if ( in_array( $name, $excluded, true ) || empty( $type->show_ui ) ) {
				unset( $types[ $name ] );
				continue;
			}

			foreach ( $excluded_prefixes as $prefix ) {
				if ( 0 === strpos( $name, $prefix ) ) {
					unset( $types[ $name ] );
					break;
				}
			}
		}

		/**
		 * Filters the post types offered for broken link scanning.
		 *
		 * @since 3.0.0
		 *
		 * @param WP_Post_Type[] $types Scannable post types, keyed by name.
		 */
		return apply_filters( 'wpcbl_scannable_post_types', $types );
	}
}

if ( ! function_exists( 'wpcbl_connect' ) ) {
	/**
	 * The shared connect client instance (created in the connect class file).
	 *
	 * @since 3.1.0
	 *
	 * @return WPCBL_Check_Broken_Links_Connect|null
	 */
	function wpcbl_connect() {
		return isset( $GLOBALS['wpcbl_connect'] ) ? $GLOBALS['wpcbl_connect'] : null;
	}
}

if ( ! function_exists( 'wpcbl_connect_plan' ) ) {
	/**
	 * The connected account's plan slug from the stored connection, or ''.
	 *
	 * @since 3.0.3
	 *
	 * @return string
	 */
	function wpcbl_connect_plan() {
		$connection = get_option( 'wpcbl_connection', array() );

		return isset( $connection['plan'] ) ? (string) $connection['plan'] : '';
	}
}

if ( ! function_exists( 'wpcbl_uptime_sanitize_payload' ) ) {
	/**
	 * Sanitize a raw (already wp_unslash()ed) uptime-monitor field set into
	 * the body sent to the SaaS. Shared by the create and update AJAX
	 * handlers; only recognized keys survive, everything else (including
	 * `id`, which the handlers route separately) is dropped.
	 *
	 * @since 3.0.6
	 *
	 * @param array $input Raw field values, keyed by field name. Only keys
	 *                      actually present are considered "set" by the
	 *                      caller, so omit a key entirely to leave it out
	 *                      of the returned body.
	 *
	 * @return array Sanitized body, containing only recognized, valid keys.
	 */
	function wpcbl_uptime_sanitize_payload( array $input ) {
		$body = array();

		if ( array_key_exists( 'type', $input ) ) {
			$type = sanitize_key( $input['type'] );
			if ( in_array( $type, array( 'http', 'heartbeat' ), true ) ) {
				$body['type'] = $type;
			}
		}

		if ( array_key_exists( 'name', $input ) ) {
			$body['name'] = sanitize_text_field( $input['name'] );
		}

		if ( array_key_exists( 'url', $input ) ) {
			$body['url'] = esc_url_raw( $input['url'] );
		}

		if ( array_key_exists( 'interval_seconds', $input ) ) {
			$body['interval_seconds'] = absint( $input['interval_seconds'] );
		}

		if ( array_key_exists( 'alert_after_failures', $input ) ) {
			$body['alert_after_failures'] = absint( $input['alert_after_failures'] );
		}

		if ( array_key_exists( 'alert_emails', $input ) ) {
			$emails               = array_filter( array_map( 'sanitize_email', (array) $input['alert_emails'] ) );
			$body['alert_emails'] = array_values( $emails );
		}

		return $body;
	}
}

if ( ! function_exists( 'wpcbl_collect_site_urls' ) ) {
	/**
	 * Every public URL this site publishes, home first, then most recently
	 * modified. WordPress knows its own permalinks better than a crawler
	 * does, so an audit started here sends this list instead of waiting for
	 * a site crawl to discover the same pages.
	 *
	 * The limit is a transport cap, not the account's page allowance. The
	 * SaaS truncates to the plan cap and reports how many it received, which
	 * is what lets the report say plainly that coverage was capped.
	 *
	 * @since 3.0.7
	 *
	 * @param int $limit Most URLs to return.
	 *
	 * @return array List of absolute URLs.
	 */
	function wpcbl_collect_site_urls( $limit = 2000 ) {
		$limit = max( 1, (int) $limit );
		$urls  = array( home_url( '/' ) );

		$types = wpcbl_auditable_post_types();

		if ( array() !== $types ) {
			// 'fields' => 'ids' keeps this query itself cheap, but it does
			// NOT avoid loading post objects overall: get_permalink() below
			// calls get_post() per id, and with no cache primed that is one
			// database round trip per post, up to $limit of them, inside a
			// synchronous admin-ajax request. _prime_post_caches() below
			// loads every row for this batch of ids in one query so the
			// permalink loop that follows hits the cache instead.
			$ids = get_posts(
				array(
					'post_type'              => $types,
					'post_status'            => 'publish',
					'fields'                 => 'ids',
					'orderby'                => 'modified',
					'order'                  => 'DESC',
					'posts_per_page'         => $limit,
					'no_found_rows'          => true,
					'ignore_sticky_posts'    => true,
					'suppress_filters'       => false,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			if ( array() !== $ids ) {
				_prime_post_caches( $ids, false, false );
			}

			foreach ( (array) $ids as $id ) {
				$permalink = get_permalink( $id );
				if ( is_string( $permalink ) && '' !== $permalink ) {
					$urls[] = esc_url_raw( $permalink );
				}
			}
		}

		$urls = array_values( array_unique( $urls ) );

		return array_slice( $urls, 0, $limit );
	}
}

if ( ! defined( 'WPCBL_ILO_UPLOAD_CONTENT_CHARS' ) ) {
	/**
	 * Bytes of one page's body the optimizer upload carries.
	 *
	 * Matches links.upload_content_chars on brokenlinkchecker.io. The
	 * server truncates to the same number, but only after the whole POST
	 * has crossed the wire, so a site with a handful of enormous pages
	 * would still push megabytes per chunk. Cutting here keeps every
	 * request small, and the two ends agree on what arrives.
	 *
	 * @since 3.0.8
	 */
	define( 'WPCBL_ILO_UPLOAD_CONTENT_CHARS', 60000 );
}

if ( ! function_exists( 'wpcbl_ilo_trim_content' ) ) {
	/**
	 * Cut one page's body to the upload limit.
	 *
	 * Cuts on bytes, the same unit the server's mb_strcut() uses, so a
	 * page that is under the limit here is under it there too. mb_strcut()
	 * is preferred because it will not split a multibyte character in
	 * half, and substr() is the fallback when mbstring is missing.
	 *
	 * @since 3.0.8
	 *
	 * @param string $content Rendered page body.
	 *
	 * @return string
	 */
	function wpcbl_ilo_trim_content( $content ) {
		$content = (string) $content;

		if ( strlen( $content ) <= WPCBL_ILO_UPLOAD_CONTENT_CHARS ) {
			return $content;
		}

		if ( function_exists( 'mb_strcut' ) ) {
			return mb_strcut( $content, 0, WPCBL_ILO_UPLOAD_CONTENT_CHARS );
		}

		return substr( $content, 0, WPCBL_ILO_UPLOAD_CONTENT_CHARS );
	}
}

if ( ! function_exists( 'wpcbl_collect_site_pages' ) ) {
	/**
	 * Published pages with their content, for the Internal Link Optimizer.
	 *
	 * The optimizer needs what the reader sees, so this sends the
	 * the_content filtered body rather than raw post_content: shortcodes
	 * and blocks are expanded, and no theme chrome is included. Applying a
	 * suggestion later searches raw post_content instead, which is why an
	 * apply can honestly refuse when a builder generated the text.
	 *
	 * Walks in batches so a large site never loads every body at once.
	 *
	 * The offset this function reports back is a QUERY offset -- how many
	 * ids get_posts() has now handed out for this walk -- not a count of
	 * rows returned to the caller. Those two numbers differ (the front
	 * page is extra and outside $limit, a dedup or permalink skip drops a
	 * row without freeing an offset slot), and only this function sees
	 * both, so it alone can report the offset the next call must use.
	 * $limit is always queried in full on every call, front page or not,
	 * so 'has_more' (fewer ids returned than asked for) is a reliable
	 * end-of-set signal regardless of what happened to sit at page_on_front.
	 *
	 * $post_types lets a caller narrow the walk to a saved selection (the
	 * Internal Link Optimizer's own ilo_post_types option). Passing
	 * nothing behaves exactly as before: the full
	 * wpcbl_auditable_post_types() list. Passing a list intersects it
	 * with wpcbl_auditable_post_types() rather than using it directly,
	 * so the builder-record exclusions there still apply and a stale
	 * saved type the site no longer has (or never had) cannot resurrect
	 * a junk post type into the walk.
	 *
	 * @since 3.0.8
	 *
	 * @param int   $offset     Query offset to resume from (the previous call's next_offset).
	 * @param int   $limit      Rows to ask the query for in this batch.
	 * @param array $post_types Optional post-type names to restrict the walk to.
	 *
	 * @return array array{
	 *     pages: array List of array{post_id:int|null, url:string, title:string, content:string}.
	 *     next_offset: int Query offset the next call must use.
	 *     has_more: bool Whether more posts remain beyond this batch.
	 * }
	 */
	function wpcbl_collect_site_pages( $offset = 0, $limit = 50, $post_types = array() ) {
		$offset = max( 0, (int) $offset );
		$limit  = max( 1, (int) $limit );
		$pages  = array();

		// The front page anchors the link graph: click depth is measured
		// from it. When a static page is set as the front page it is a
		// real post, so it leads the first batch with its own content. A
		// blog-index front page has no post behind it, and there is
		// nothing honest to send, so nothing is sent. LinkGraph already
		// falls back to the shallowest page when the home key is absent.
		//
		// The front row rides along OUTSIDE $limit and never touches the
		// offset: $limit is still queried in full below, so batch 0 is
		// never short a row just because the site has a static front page.
		$front = 0 === $offset ? (int) get_option( 'page_on_front' ) : 0;

		if ( $front > 0 ) {
			$front_post = get_post( $front );

			if ( $front_post && 'publish' === $front_post->post_status ) {
				$pages[] = array(
					'post_id' => $front,
					'url'     => esc_url_raw( home_url( '/' ) ),
					'title'   => (string) get_the_title( $front ),
					'content' => wpcbl_ilo_trim_content( apply_filters( 'the_content', $front_post->post_content ) ),
				);
			}
		}

		$auditable = wpcbl_auditable_post_types();

		if ( is_array( $post_types ) && array() !== $post_types ) {
			// A saved selection narrows the walk, but only within what
			// wpcbl_auditable_post_types() already allows -- see the
			// docblock above.
			$types = array_values( array_intersect( $post_types, $auditable ) );
		} else {
			$types = $auditable;
		}

		if ( array() === $types ) {
			// Nothing queryable at all. No ids were consumed, and none
			// ever will be, so the walk ends here.
			return array(
				'pages'       => $pages,
				'next_offset' => $offset,
				'has_more'    => false,
			);
		}

		$ids = get_posts(
			array(
				'post_type'              => $types,
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'posts_per_page'         => $limit,
				'offset'                 => $offset,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'suppress_filters'       => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$ids = (array) $ids;

		if ( array() !== $ids ) {
			_prime_post_caches( $ids, false, false );
		}

		foreach ( $ids as $id ) {
			// Already sent above as the front page. Sending it twice would
			// put two nodes with one url into the graph. This is a display
			// dedup only -- it does not change how many ids get_posts()
			// handed out, so it must not change the offset either.
			if ( $front > 0 && (int) $id === $front ) {
				continue;
			}

			$post = get_post( $id );

			if ( ! $post ) {
				continue;
			}

			$permalink = get_permalink( $id );

			if ( ! is_string( $permalink ) || '' === $permalink ) {
				continue;
			}

			$pages[] = array(
				'post_id' => (int) $id,
				'url'     => esc_url_raw( $permalink ),
				'title'   => (string) get_the_title( $id ),
				'content' => wpcbl_ilo_trim_content( apply_filters( 'the_content', $post->post_content ) ),
			);
		}

		return array(
			'pages'       => $pages,
			// The offset moves by the ids the query actually returned,
			// never by count( $pages ) -- that count shrinks on a dedup
			// or permalink skip while every id was still consumed.
			'next_offset' => $offset + count( $ids ),
			// Fewer ids than asked for is the only reliable "nothing
			// left" signal here, because $limit is always queried in
			// full now (the front row no longer borrows from it).
			'has_more'    => count( $ids ) === $limit,
		);
	}
}

if ( ! function_exists( 'wpcbl_ilo_post_type_options' ) ) {
	/**
	 * The post types the Internal Link Optimizer's post-type picker can
	 * offer, each with its human label. Always the full
	 * wpcbl_auditable_post_types() list (never narrowed by a saved
	 * selection) -- the picker needs every choice on offer, not just
	 * the ones already ticked.
	 *
	 * @since 3.0.8
	 *
	 * @return array List of array{value:string, label:string}.
	 */
	function wpcbl_ilo_post_type_options() {
		$options = array();

		foreach ( wpcbl_auditable_post_types() as $type ) {
			$object = get_post_type_object( $type );
			$label  = ( $object && ! empty( $object->labels->name ) ) ? $object->labels->name : $type;

			$options[] = array(
				'value' => $type,
				'label' => $label,
			);
		}

		return $options;
	}
}

if ( ! function_exists( 'wpcbl_dashboard_health_score' ) ) {
	/**
	 * Site link health score for the Dashboard's hero card: the share of
	 * checked links that were not broken, 0-100.
	 *
	 * Only $total and $broken are stored for every past run (see
	 * WPCBL_Check_Broken_Links_Utilities::record_scan_history()) -- warning
	 * links (redirects, slow responses) are not split out per historical
	 * scan. So "healthy" here means "not broken" rather than "good only",
	 * and the same formula is used for the current score and the previous
	 * run it is compared against, so the "since last scan" delta compares
	 * like with like.
	 *
	 * @since 3.0.8
	 *
	 * @param int $total  Links checked in the run.
	 * @param int $broken Broken links found in the run.
	 *
	 * @return int|null Score 0-100, or null when nothing was checked.
	 */
	function wpcbl_dashboard_health_score( $total, $broken ) {
		$total  = (int) $total;
		$broken = (int) $broken;

		if ( $total <= 0 ) {
			return null;
		}

		$healthy = max( 0, $total - $broken );

		return (int) round( 100 * $healthy / $total );
	}
}

if ( ! function_exists( 'wpcbl_go_url' ) ) {
	/**
	 * Deep link to a tool on brokenlinkchecker.io.
	 *
	 * /go/{target} resolves this site's project and lands on the right
	 * dashboard page, so the visitor never has to pick their site first.
	 *
	 * @since 3.0.7
	 *
	 * @param string $target Tool slug, for example ai-visibility.
	 *
	 * @return string
	 */
	function wpcbl_go_url( $target ) {
		return 'https://brokenlinkchecker.io/go/' . rawurlencode( $target )
			. '?site=' . rawurlencode( home_url() );
	}
}

if ( ! function_exists( 'wpcbl_auditable_post_types' ) ) {
	/**
	 * Content types worth auditing.
	 *
	 * Starts from the Content Types to Scan setting, so the audit covers the
	 * same content the broken link scan does rather than inventing a second
	 * answer the user never chose.
	 *
	 * Page builders register internal record types as public: Themify global
	 * styles, Elementor library entries, reusable blocks. They are publicly
	 * queryable but nobody reads them, and auditing them reports missing
	 * titles and descriptions on records that should never have either.
	 *
	 * @since 3.0.7
	 *
	 * @return array List of post type names.
	 */
	function wpcbl_auditable_post_types() {
		$types = get_post_types( array( 'public' => true ), 'names' );

		// Core plumbing plus the builder record types that show up as public.
		$never = array(
			'attachment',
			'nav_menu_item',
			'revision',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'user_request',
			'wp_block',
			'wp_template',
			'wp_template_part',
			'wp_global_styles',
			'wp_navigation',
			'wp_font_family',
			'wp_font_face',
			'tglobal_style',
			'elementor_library',
			'e-landing-page',
			'themify_layout',
			'themify_layout_part',
		);

		$types = array_values( array_diff( (array) $types, $never ) );

		// Anything hidden from site search or from menus is a record, not a
		// page someone reads. Catches builder types this list has not met yet.
		$types = array_values( array_filter( $types, static function ( $type ) {
			$object = get_post_type_object( $type );

			if ( ! $object ) {
				return false;
			}

			return empty( $object->exclude_from_search ) && ! empty( $object->publicly_queryable );
		} ) );

		// The user's own choice wins when they have made one.
		$selected = wpcbl_get_option( 'scan_post_types', array() );
		if ( is_array( $selected ) && ! empty( $selected ) ) {
			$types = array_values( array_intersect( $types, array_map( 'sanitize_key', $selected ) ) );
		}

		if ( empty( $types ) ) {
			$types = array( 'post', 'page' );
		}

		/**
		 * Filters the content types an SEO audit covers.
		 *
		 * @since 3.0.7
		 *
		 * @param array $types Post type names.
		 */
		return (array) apply_filters( 'wpcbl_auditable_post_types', $types );
	}
}

if ( ! function_exists( 'wpcbl_connect_url' ) ) {
	/**
	 * Link that starts the connect handshake in one click.
	 *
	 * It points at the wpcbl_connect_start handler, which primes the
	 * handshake nonce and redirects to brokenlinkchecker.io/connect.
	 *
	 * @since 3.1.3
	 *
	 * @return string
	 */
	function wpcbl_connect_url() {
		return wp_nonce_url( admin_url( 'admin-post.php?action=wpcbl_connect_start' ), 'wpcbl_connect_start' );
	}
}
