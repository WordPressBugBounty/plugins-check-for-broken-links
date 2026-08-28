<?php
/**
 * Turns one Internal Link Optimizer suggestion into an edit of a post.
 *
 * The string functions here are pure, so they are tested without
 * WordPress loaded. Every one of them is exact match or refuse. There is
 * no fuzzy fallback anywhere in this file, and adding one would let the
 * plugin quietly edit the wrong sentence.
 *
 * The optimizer analyses the_content filtered output, but this edits raw
 * post_content. On a plain post those are the same string. When a
 * shortcode or a page builder generated the text they are not, and the
 * honest outcome is a refusal that names the reason.
 *
 * @package WPCBL_Check_Broken_Links/Includes
 * @since 3.0.8
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WPCBL_Check_Broken_Links_Link_Apply' ) ) :

	/**
	 * Content edits for internal link suggestions.
	 *
	 * @since 3.0.8
	 */
	class WPCBL_Check_Broken_Links_Link_Apply {

		/**
		 * A refusal. The content comes back untouched on purpose, so a
		 * caller that ignores 'ok' still cannot corrupt a post.
		 *
		 * @since 3.0.8
		 *
		 * @param string $content Content, unchanged.
		 * @param string $reason  Machine-readable reason code.
		 *
		 * @return array
		 */
		private static function refuse( $content, $reason ) {
			return array(
				'ok'      => false,
				'content' => $content,
				'reason'  => $reason,
				'undo'    => array(
					'search'  => '',
					'replace' => '',
					'offset'  => -1,
				),
			);
		}

		/**
		 * A success, carrying the payload that reverses it.
		 *
		 * The byte offset is recorded alongside search/replace so undo()
		 * can reverse the exact edit that was made, not just the first
		 * string that happens to match. Two edits can add the same
		 * fragment more than once in a post; the offset is what tells
		 * them apart.
		 *
		 * @since 3.0.8
		 *
		 * @param string $content Edited content.
		 * @param string $search  The fragment the edit added.
		 * @param string $replace The fragment it replaced.
		 * @param int    $offset  Byte offset of $search in $content.
		 *
		 * @return array
		 */
		private static function done( $content, $search, $replace, $offset ) {
			return array(
				'ok'      => true,
				'content' => $content,
				'reason'  => '',
				'undo'    => array(
					'search'  => $search,
					'replace' => $replace,
					'offset'  => $offset,
				),
			);
		}

		/**
		 * Split content into tag/comment and text tokens.
		 *
		 * Editing only the text tokens is what keeps this from ever
		 * matching inside a tag or an attribute value.
		 *
		 * The tag alternative requires a real tag name right after `<` or
		 * `</`, and consumes any quoted attribute value whole before
		 * looking for the closing `>`. That is deliberate: a bare `<` in
		 * prose (`5 < 6`) does not start a tag, and a `>` sitting inside
		 * an attribute value (`title="a > b"`) does not end one early.
		 * Without both of those, a loose `<[^>]*>` pattern either merges
		 * unrelated text into a fake tag or splits a real tag in half,
		 * and either way the depth tracking below stops being reliable.
		 *
		 * @since 3.0.8
		 *
		 * @param string $content Content to split.
		 *
		 * @return array
		 */
		private static function tokens( $content ) {
			$parts = preg_split(
				'/(<!--.*?-->|<\/?[a-zA-Z][^>"\']*(?:(?:"[^"]*"|\'[^\']*\')[^>"\']*)*>)/s',
				(string) $content,
				-1,
				PREG_SPLIT_DELIM_CAPTURE
			);

			return is_array( $parts ) ? $parts : array( (string) $content );
		}

		/**
		 * Wrap the first free occurrence of some text in a link.
		 *
		 * Free means: in text, not inside a tag or attribute, not already
		 * inside an anchor, and not inside a script or style body (that
		 * text is code or CSS, never content to link).
		 *
		 * @since 3.0.8
		 *
		 * @param string $content       Raw post_content.
		 * @param string $existing_text Text to wrap.
		 * @param string $target_url    Link target.
		 *
		 * @return array
		 */
		public static function wrap_existing( $content, $existing_text, $target_url ) {
			$existing_text = (string) $existing_text;

			if ( '' === trim( $existing_text ) ) {
				return self::refuse( $content, 'not_found' );
			}

			$tokens    = self::tokens( $content );
			$depth     = 0;
			$in_other  = false;
			$in_script = false;
			$in_style  = false;
			$offset    = 0;

			foreach ( $tokens as $i => $token ) {
				$len = strlen( $token );

				if ( '' !== $token && '<' === $token[0] ) {
					if ( preg_match( '/^<a[\s>]/i', $token ) ) {
						$depth++;
					} elseif ( preg_match( '/^<\/a\s*>/i', $token ) ) {
						$depth = max( 0, $depth - 1 );
					} elseif ( preg_match( '/^<script[\s>]/i', $token ) ) {
						$in_script = true;
					} elseif ( preg_match( '/^<\/script\s*>/i', $token ) ) {
						$in_script = false;
					} elseif ( preg_match( '/^<style[\s>]/i', $token ) ) {
						$in_style = true;
					} elseif ( preg_match( '/^<\/style\s*>/i', $token ) ) {
						$in_style = false;
					}

					$offset += $len;
					continue;
				}

				if ( $in_script || $in_style ) {
					// A script or style body looks like text to the
					// tokeniser, but it is not content. Never match here.
					$offset += $len;
					continue;
				}

				if ( false === strpos( $token, $existing_text ) ) {
					$offset += $len;
					continue;
				}

				if ( $depth > 0 ) {
					// Found, but already inside a link. Remember it and
					// keep looking for a free occurrence.
					$in_other = true;
					$offset  += $len;
					continue;
				}

				$at              = strpos( $token, $existing_text );
				$absolute_offset = $offset + $at;
				$link            = '<a href="' . esc_attr( esc_url_raw( $target_url ) ) . '">' . $existing_text . '</a>';
				$tokens[ $i ]    = substr( $token, 0, $at ) . $link . substr( $token, $at + strlen( $existing_text ) );

				return self::done( implode( '', $tokens ), $link, $existing_text, $absolute_offset );
			}

			if ( $in_other ) {
				// Already linked to this exact target is a no-op the UI
				// reports as done. Linked anywhere else is the customer's
				// decision to keep, not ours to overwrite.
				$same = '/<a\b[^>]*href=["\']' . preg_quote( (string) $target_url, '/' ) . '["\'][^>]*>\s*' . preg_quote( $existing_text, '/' ) . '\s*<\/a>/i';

				return self::refuse( $content, preg_match( $same, (string) $content ) ? 'already_linked' : 'linked_elsewhere' );
			}

			return self::refuse( $content, 'not_found' );
		}

		/**
		 * Append a sentence to the paragraph that starts with some text.
		 *
		 * The insert_after match must land in ordinary text inside an
		 * open `<p>...</p>` - not inside a tag or attribute value, and
		 * not inside some other element such as a heading. A match
		 * anywhere else is refused rather than guessing which paragraph,
		 * if any, the caller meant.
		 *
		 * @since 3.0.8
		 *
		 * @param string $content      Raw post_content.
		 * @param string $insert_after Text identifying the paragraph.
		 * @param string $sentence     Sentence to append.
		 * @param string $anchor_text  Words inside the sentence to link.
		 * @param string $target_url   Link target.
		 *
		 * @return array
		 */
		public static function insert_sentence( $content, $insert_after, $sentence, $anchor_text, $target_url ) {
			$content      = (string) $content;
			$insert_after = (string) $insert_after;
			$sentence     = trim( (string) $sentence );
			$anchor_text  = (string) $anchor_text;

			if ( '' === trim( $insert_after ) || '' === $sentence ) {
				return self::refuse( $content, 'not_found' );
			}

			$tokens = self::tokens( $content );
			$offset = 0;
			$at     = false;
			$end    = false;
			$in_p   = false;

			foreach ( $tokens as $token ) {
				$len    = strlen( $token );
				$is_tag = ( '' !== $token && '<' === $token[0] );

				if ( $is_tag ) {
					if ( preg_match( '/^<p[\s>]/i', $token ) ) {
						$in_p = true;
					} elseif ( preg_match( '/^<\/p\s*>/i', $token ) ) {
						if ( false !== $at && false === $end ) {
							// This is the paragraph that held the match.
							$end = $offset;
						}

						$in_p = false;
					}
				} elseif ( false === $at && $in_p ) {
					$local = strpos( $token, $insert_after );

					if ( false !== $local ) {
						$at = $offset + $local;
					}
				}

				$offset += $len;

				if ( false !== $end ) {
					break;
				}
			}

			if ( false === $at ) {
				return self::refuse( $content, 'not_found' );
			}

			if ( false === $end ) {
				return self::refuse( $content, 'no_paragraph_end' );
			}

			$anchor_at = strpos( $sentence, $anchor_text );

			if ( '' === trim( $anchor_text ) || false === $anchor_at ) {
				return self::refuse( $content, 'anchor_not_in_sentence' );
			}

			$link     = '<a href="' . esc_attr( esc_url_raw( $target_url ) ) . '">' . $anchor_text . '</a>';
			$linked   = substr( $sentence, 0, $anchor_at ) . $link . substr( $sentence, $anchor_at + strlen( $anchor_text ) );
			$addition = ' ' . $linked;

			return self::done(
				substr( $content, 0, $end ) . $addition . substr( $content, $end ),
				$addition,
				'',
				$end
			);
		}

		/**
		 * Remove the anchor around some text and keep the words.
		 *
		 * @since 3.0.8
		 *
		 * @param string $content       Raw post_content.
		 * @param string $existing_text The linked text.
		 *
		 * @return array
		 */
		public static function unlink_text( $content, $existing_text ) {
			$content       = (string) $content;
			$existing_text = (string) $existing_text;

			if ( '' === trim( $existing_text ) ) {
				return self::refuse( $content, 'not_found' );
			}

			$pattern = '/<a\b[^>]*>\s*' . preg_quote( $existing_text, '/' ) . '\s*<\/a>/i';

			if ( ! preg_match( $pattern, $content, $matches ) ) {
				return self::refuse( $content, 'not_found' );
			}

			$whole = $matches[0];
			$at    = strpos( $content, $whole );

			return self::done(
				substr( $content, 0, $at ) . $existing_text . substr( $content, $at + strlen( $whole ) ),
				$existing_text,
				$whole,
				$at
			);
		}

		/**
		 * Reverse one applied edit.
		 *
		 * Exact match, like everything else here, extended with a byte
		 * offset. If the fragment the apply added is still sitting at the
		 * offset the apply recorded, that is unambiguously the edit to
		 * reverse, however many other copies of the same text exist
		 * elsewhere in the post. If the content shifted since the apply
		 * and the offset no longer lines up, the fragment is only safe to
		 * reverse when it is unique in the whole document - two or more
		 * candidates and there is no way to know which one was ours, so
		 * this refuses rather than guessing.
		 *
		 * @since 3.0.8
		 *
		 * @param string $content Current raw post_content.
		 * @param array  $undo    The payload the apply returned.
		 *
		 * @return array
		 */
		public static function undo( $content, $undo ) {
			$content = (string) $content;
			$search  = isset( $undo['search'] ) ? (string) $undo['search'] : '';
			$replace = isset( $undo['replace'] ) ? (string) $undo['replace'] : '';
			$offset  = isset( $undo['offset'] ) ? (int) $undo['offset'] : -1;

			if ( '' === $search ) {
				return self::refuse( $content, 'not_found' );
			}

			$search_len = strlen( $search );

			if ( $offset >= 0 && $offset <= strlen( $content ) - $search_len
				&& substr( $content, $offset, $search_len ) === $search ) {
				return self::done(
					substr_replace( $content, $replace, $offset, $search_len ),
					$replace,
					$search,
					$offset
				);
			}

			if ( 1 === substr_count( $content, $search ) ) {
				$at = strpos( $content, $search );

				return self::done(
					substr_replace( $content, $replace, $at, $search_len ),
					$replace,
					$search,
					$at
				);
			}

			return self::refuse( $content, 'not_found' );
		}
	}

endif;
