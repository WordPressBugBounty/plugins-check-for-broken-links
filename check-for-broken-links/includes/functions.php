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
