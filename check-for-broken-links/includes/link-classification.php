<?php
/**
 * What counts as a broken link.
 *
 * Shared with brokenlinkchecker.io (LinkCheck::UNVERIFIED_FAILURES and
 * CheckLinkJob), so a connected site reads the same broken count in
 * wp-admin and on its dashboard. Change one side, change both.
 *
 * Broken: 404, 410 and every other 4xx answer, a host that does not
 * resolve, a certificate error, and the plugin's invalid-URL marker (666).
 * Visitors hit all of these too.
 *
 * Warning: answers that leave the link unverified. 401, 403 and 429 refuse
 * the checker, 999 is LinkedIn's bot wall, 408 and timeouts never got an
 * answer, and any other 5xx is a server error, usually temporary.
 *
 * @package WPCBL_Check_Broken_Links
 * @author Norse Digital Group LLC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'wpcbl_classify_link_status' ) ) {
	/**
	 * Classify one link check.
	 *
	 * @param int    $code          HTTP status, 0 for a request error, 666 for an invalid URL.
	 * @param string $error         The request error message when $code is 0.
	 * @param array  $bad_codes     Codes that are always broken (filterable by the caller).
	 * @param array  $warning_codes Codes that are always warnings (filterable by the caller).
	 *
	 * @since 3.1.5
	 *
	 * @return string 'broken', 'warning' or 'good'.
	 */
	function wpcbl_classify_link_status( $code, $error, $bad_codes, $warning_codes ) {
		$code = (int) $code;

		if ( 0 === $code && wpcbl_is_dead_host_error( (string) $error ) ) {
			return 'broken';
		}

		if ( in_array( $code, array_map( 'intval', (array) $bad_codes ), true ) ) {
			return 'broken';
		}

		if ( in_array( $code, array_map( 'intval', (array) $warning_codes ), true ) ) {
			return 'warning';
		}

		if ( $code >= 500 && $code <= 599 ) {
			return 'warning';
		}

		if ( $code >= 400 && $code <= 499 ) {
			return 'broken';
		}

		return 'good';
	}
}

if ( ! function_exists( 'wpcbl_is_dead_host_error' ) ) {
	/**
	 * Whether a request error means the host is unreachable for everyone:
	 * the name does not resolve or the certificate is bad. A timeout or a
	 * refused connection is not, the checker may simply have been ignored.
	 *
	 * @param string $error The WP_Error message from wp_remote_get().
	 *
	 * @since 3.1.5
	 *
	 * @return bool
	 */
	function wpcbl_is_dead_host_error( $error ) {
		if ( '' === $error ) {
			return false;
		}

		// A timeout can mention SSL ("SSL connection timeout"). It is still a timeout.
		if ( preg_match( '/cURL error 28:|timed out|timeout/i', $error ) ) {
			return false;
		}

		return wpcbl_is_dns_error( $error ) || (bool) preg_match( '/cURL error (35|51|53|54|58|59|60|64|66|77|80|82|83|90|91):|\bssl\b|certificate/i', $error );
	}
}

if ( ! function_exists( 'wpcbl_is_dns_error' ) ) {
	/**
	 * Whether a request error means the host name does not resolve.
	 *
	 * @param string $error The WP_Error message from wp_remote_get().
	 *
	 * @since 3.1.5
	 *
	 * @return bool
	 */
	function wpcbl_is_dns_error( $error ) {
		return (bool) preg_match( '/cURL error 6:|could not resolve|name or service not known|getaddrinfo|no such host/i', (string) $error );
	}
}
