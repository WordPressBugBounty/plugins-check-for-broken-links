<?php
/**
 * Writes an approved SEO fix into WordPress.
 *
 * Titles and meta descriptions do not live in one place. Each SEO plugin
 * keeps them in its own post meta, and WordPress core has no meta description
 * at all. So this detects what is running and writes where that plugin reads,
 * rather than guessing a single key and silently doing nothing.
 *
 * @package WPCBL_Check_Broken_Links
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WPCBL_Check_Broken_Links_Seo_Apply' ) ) :

	/**
	 * Applies approved title and description fixes to posts.
	 *
	 * @since 3.0.7
	 */
	class WPCBL_Check_Broken_Links_Seo_Apply {

		/**
		 * Supported SEO plugins, in detection order.
		 *
		 * Each entry names the constant that proves the plugin is active and
		 * the post meta keys it reads for the title tag and meta description.
		 *
		 * @since 3.0.7
		 *
		 * @return array<string, array{label: string, constant: string, title: string, description: string}>
		 */
		public static function supported() {
			return array(
				'yoast'    => array(
					'label'       => 'Yoast SEO',
					'constant'    => 'WPSEO_VERSION',
					'title'       => '_yoast_wpseo_title',
					'description' => '_yoast_wpseo_metadesc',
				),
				'rankmath' => array(
					'label'       => 'Rank Math',
					'constant'    => 'RANK_MATH_VERSION',
					'title'       => 'rank_math_title',
					'description' => 'rank_math_description',
				),
				'seopress' => array(
					'label'       => 'SEOPress',
					'constant'    => 'SEOPRESS_VERSION',
					'title'       => '_seopress_titles_title',
					'description' => '_seopress_titles_desc',
				),
			);
		}

		/**
		 * Which SEO plugin this site runs, if any.
		 *
		 * @since 3.0.7
		 *
		 * @return array{key: string, label: string, title: string, description: string}|null
		 */
		public static function detect() {
			foreach ( self::supported() as $key => $plugin ) {
				if ( defined( $plugin['constant'] ) ) {
					return array(
						'key'         => $key,
						'label'       => $plugin['label'],
						'title'       => $plugin['title'],
						'description' => $plugin['description'],
					);
				}
			}

			return null;
		}

		/**
		 * A short, honest description of where a fix would be written.
		 *
		 * @since 3.0.7
		 *
		 * @return string
		 */
		public static function target_label() {
			$seo = self::detect();

			if ( $seo ) {
				/* translators: %s: the detected SEO plugin name. */
				return sprintf( esc_html__( 'Fixes are written to %s.', 'check-for-broken-links' ), $seo['label'] );
			}

			return esc_html__( 'No SEO plugin detected. Titles are written to the post title, and descriptions cannot be saved.', 'check-for-broken-links' );
		}

		/**
		 * Apply one fix.
		 *
		 * @since 3.0.7
		 *
		 * @param string $url   The page the fix belongs to.
		 * @param string $field Either title or description.
		 * @param string $value The approved text.
		 *
		 * @return array{ok: bool, message: string, post_id?: int, target?: string}
		 */
		public static function apply( $url, $field, $value ) {
			$field = 'title' === $field ? 'title' : 'description';
			$value = trim( wp_strip_all_tags( (string) $value ) );

			if ( '' === $value ) {
				return array(
					'ok'      => false,
					'message' => esc_html__( 'That suggestion was empty, so nothing was written.', 'check-for-broken-links' ),
				);
			}

			$post_id = self::resolve_post( $url );

			if ( ! $post_id ) {
				return array(
					'ok'      => false,
					'message' => esc_html__( 'That URL does not match a post on this site, so nothing was written.', 'check-for-broken-links' ),
				);
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return array(
					'ok'      => false,
					'message' => esc_html__( 'You are not allowed to edit that post.', 'check-for-broken-links' ),
				);
			}

			$seo = self::detect();

			if ( $seo ) {
				update_post_meta( $post_id, $seo[ $field ], $value );

				return array(
					'ok'      => true,
					'post_id' => $post_id,
					'target'  => $seo['label'],
					/* translators: %s: the SEO plugin the value was written to. */
					'message' => sprintf( esc_html__( 'Saved to %s.', 'check-for-broken-links' ), $seo['label'] ),
				);
			}

			// No SEO plugin: a description has nowhere to live, and inventing a
			// meta key would write a value nothing ever reads.
			if ( 'description' === $field ) {
				return array(
					'ok'      => false,
					'message' => esc_html__( 'No SEO plugin is active, so there is nowhere to save a meta description. Install one, then apply again.', 'check-for-broken-links' ),
				);
			}

			// A title still has a home, but this one is visible on the page, so
			// the caller is told plainly what changed.
			$updated = wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => $value,
				),
				true
			);

			if ( is_wp_error( $updated ) ) {
				return array(
					'ok'      => false,
					'message' => esc_html__( 'WordPress refused the update.', 'check-for-broken-links' ),
				);
			}

			return array(
				'ok'      => true,
				'post_id' => $post_id,
				'target'  => 'post_title',
				'message' => esc_html__( 'Saved as the post title. It is visible on the page, not only in search results.', 'check-for-broken-links' ),
			);
		}

		/**
		 * Find the post a URL belongs to.
		 *
		 * url_to_postid() handles most permalink shapes but returns 0 for the
		 * front page, so that is resolved separately.
		 *
		 * @since 3.0.7
		 *
		 * @param string $url Page URL.
		 *
		 * @return int Post id, or 0 when the URL is not a post on this site.
		 */
		private static function resolve_post( $url ) {
			$url = trim( (string) $url );

			if ( '' === $url ) {
				return 0;
			}

			$post_id = (int) url_to_postid( $url );

			if ( $post_id ) {
				return $post_id;
			}

			$home = untrailingslashit( home_url( '/' ) );

			if ( untrailingslashit( $url ) === $home && 'page' === get_option( 'show_on_front' ) ) {
				return (int) get_option( 'page_on_front' );
			}

			return 0;
		}
	}

endif;
