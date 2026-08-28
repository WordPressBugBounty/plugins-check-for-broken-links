<?php
/**
 * The WPCBL_Check_Broken_Links_Admin_Links_List_Table class.
 *
 * @package WPCBL_Check_Broken_Links/Admin
 * @author Norse Digital Group LLC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'WPCBL_Check_Broken_Links_Admin_Links_List_Table' ) ) :

	/**
	 * Links list table.
	 *
	 * Renders links in the back-end.
	 *
	 * @since 1.0.0
	 *
	 * @see WP_List_Table
	 */
	class WPCBL_Check_Broken_Links_Admin_Links_List_Table extends WP_List_Table {
		/**
		 * The constructor.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function __construct() {
			parent::__construct(
				array(
					'singular' => 'link',
					'plural'   => 'links',
					'ajax'     => false,
				)
			);
		}

		/**
		 * Prepares the list of items for displaying.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function prepare_items() {
			// Verify the nonce.
			if ( isset( $_REQUEST['wpcbl_type_filter'] ) || isset( $_REQUEST['wpcbl_location_filter'] ) || isset( $_REQUEST['wpcbl_status_filter'] ) ) {
				if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'wpcbl_filter_action' ) ) {
					wp_die( esc_html__( 'Cheatin&#8217; huh?', 'check-for-broken-links' ) );}
			}

			$columns  = $this->get_columns();
			$hidden   = array();
			$sortable = $this->get_sortable_columns();

			$this->_column_headers = array( $columns, $hidden, $sortable );

			$filter = array(
				'type'     => '',
				'location' => '',
				'status'   => '',
			);

			if ( isset( $_REQUEST['wpcbl_type_filter'] ) && '' !== $_REQUEST['wpcbl_type_filter'] ) {
				$filter['type'] = sanitize_text_field( wp_unslash( $_REQUEST['wpcbl_type_filter'] ) );
			}

			if ( isset( $_REQUEST['wpcbl_location_filter'] ) && '' !== $_REQUEST['wpcbl_location_filter'] ) {
				$filter['location'] = sanitize_text_field( wp_unslash( $_REQUEST['wpcbl_location_filter'] ) );
			}

			if ( isset( $_REQUEST['wpcbl_status_filter'] ) && '' !== $_REQUEST['wpcbl_status_filter'] ) {
				$filter['status'] = sanitize_text_field( wp_unslash( $_REQUEST['wpcbl_status_filter'] ) );
			}

			$per_page     = absint( $this->get_items_per_page( 'links_per_page', 20 ) );
			$per_page     = min( max( $per_page, 1 ), 500 );
			$current_page = $this->get_pagenum();
			$total_items  = $this->record_count( $filter );

			$this->set_pagination_args(
				array(
					'total_items' => $total_items,
					'per_page'    => $per_page,
				)
			);

			$this->items = $this->get_links( $per_page, $current_page, $filter );
		}

		/**
		 * Get a simple user-facing content type key.
		 *
		 * @param array $link Link item.
		 *
		 * @return string
		 */
		private function get_content_type_key( $link ) {
			if ( isset( $link['is_slider'] ) && $link['is_slider'] ) {
				return 'slider';
			}

			if ( isset( $link['is_comment'] ) && $link['is_comment'] ) {
				return 'comment';
			}

			if ( ! isset( $link['ID'] ) ) {
				return 'custom';
			}

			$post_type = get_post_type( $link['ID'] );

			if ( 'post' === $post_type ) {
				return 'post';
			}

			if ( 'page' === $post_type ) {
				return 'page';
			}

			return 'custom';
		}


		/**
		 * Get the links from the database.
		 *
		 * @param int   $per_page     Number of items to display per page.
		 * @param int   $page_number  Current page number.
		 * @param array $filter     The filter to apply.
		 *
		 * @since 1.0.0
		 *
		 * @return array
		 */
		public function get_links( $per_page = 5, $page_number = 1, $filter = array() ) {
			$items = $this->get_filtered_items( $filter );

			// Apply pagination to the array.
			return array_slice( $items, ( ( $page_number - 1 ) * $per_page ), $per_page );
		}

		/**
		 * Get broken and warning results, minus dismissed rows, with filters applied.
		 *
		 * @param array $filter The filter to apply (type/location/status).
		 *
		 * @since 3.0.0
		 *
		 * @return array
		 */
		private function get_filtered_items( $filter ) {
			$links = get_option( 'wpcbl_check_for_broken_links_links', array() );

			$items = array_merge(
				isset( $links['broken'] ) ? array_values( $links['broken'] ) : array(),
				isset( $links['warning'] ) ? array_values( $links['warning'] ) : array(),
				isset( $links['redirect'] ) ? array_values( $links['redirect'] ) : array() // Pro bucket.
			);

			// Rows dismissed from the current results (cleared on the next scan).
			$session_dismissed = (array) get_option( 'wpcbl_session_dismissed', array() );
			if ( ! empty( $session_dismissed ) ) {
				$items = array_filter(
					$items,
					function ( $link ) use ( $session_dismissed ) {
						return ! in_array( $link['link'], $session_dismissed, true );
					}
				);
			}

			foreach ( $filter as $key => $value ) {
				if ( '' === $value ) {
					continue;
				}

				switch ( $key ) {
					case 'type':
						$items = array_filter(
							$items,
							function ( $link ) use ( $value ) {
								return isset( $link['link_source'] ) && $link['link_source'] === $value;
							}
						);
						break;

					case 'location':
						$items = array_filter(
							$items,
							function ( $link ) use ( $value ) {
								return $this->get_content_type_key( $link ) === $value;
							}
						);
						break;

					case 'status':
						$items = array_filter(
							$items,
							function ( $link ) use ( $value ) {
								return isset( $link['type'] ) && $link['type'] === $value;
							}
						);
						break;
				}
			}

			return array_values( $items );
		}

		/**
		 * Get the total number of records.
		 *
		 * @param array $filter The filter to apply.
		 *
		 * @since 1.0.0
		 *
		 * @return int
		 */
		public function record_count( $filter ) {
			return count( $this->get_filtered_items( $filter ) );
		}

		/**
		 * Returns the list of columns.
		 *
		 * @since 1.0.0
		 *
		 * @return array
		 */
		public function get_columns() {
			return array(
				'link'        => esc_html__( 'Broken URL', 'check-for-broken-links' ),
				'type'        => esc_html__( 'Status', 'check-for-broken-links' ),
				'element'     => esc_html__( 'Type', 'check-for-broken-links' ),
				'source'      => esc_html__( 'Found in', 'check-for-broken-links' ),
				'post_type'   => esc_html__( 'Content Type', 'check-for-broken-links' ),
				'detected_at' => esc_html__( 'Date', 'check-for-broken-links' ),
			);
		}

		/**
		 * Renders the element type column as an icon (link or image).
		 *
		 * @since 3.0.0
		 *
		 * @param array $item The current item.
		 *
		 * @return string
		 */
		public function column_element( $item ) {
			$element = isset( $item['element'] ) ? $item['element'] : 'link';

			$image_svg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>';

			if ( 'image' === $element ) {
				return $this->element_icon( __( 'Image', 'check-for-broken-links' ), $image_svg );
			}

			// File-type detection from the URL extension: a link to a PDF is a
			// document, not a generic link.
			$path = (string) wp_parse_url( isset( $item['link'] ) ? $item['link'] : '', PHP_URL_PATH );
			$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

			$file_doc = static function ( $tag ) {
				return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><text x="12" y="19" text-anchor="middle" font-size="6.5" font-weight="700" stroke="none" fill="currentColor">' . $tag . '</text></svg>';
			};

			$types = array(
				'pdf'  => array( __( 'PDF document', 'check-for-broken-links' ), $file_doc( 'PDF' ) ),
				'doc'  => array( __( 'Word document (DOC)', 'check-for-broken-links' ), $file_doc( 'DOC' ) ),
				'docx' => array( __( 'Word document (DOCX)', 'check-for-broken-links' ), $file_doc( 'DOC' ) ),
				'xls'  => array( __( 'Excel spreadsheet (XLS)', 'check-for-broken-links' ), $file_doc( 'XLS' ) ),
				'xlsx' => array( __( 'Excel spreadsheet (XLSX)', 'check-for-broken-links' ), $file_doc( 'XLS' ) ),
				'csv'  => array( __( 'CSV file', 'check-for-broken-links' ), $file_doc( 'CSV' ) ),
				'ppt'  => array( __( 'PowerPoint presentation (PPT)', 'check-for-broken-links' ), $file_doc( 'PPT' ) ),
				'pptx' => array( __( 'PowerPoint presentation (PPTX)', 'check-for-broken-links' ), $file_doc( 'PPT' ) ),
				'js'   => array( __( 'JavaScript file', 'check-for-broken-links' ), $file_doc( 'JS' ) ),
				'mjs'  => array( __( 'JavaScript file', 'check-for-broken-links' ), $file_doc( 'JS' ) ),
				'css'  => array( __( 'Stylesheet', 'check-for-broken-links' ), $file_doc( 'CSS' ) ),
				'zip'  => array( __( 'Archive (ZIP)', 'check-for-broken-links' ), $file_doc( 'ZIP' ) ),
				'rar'  => array( __( 'Archive (RAR)', 'check-for-broken-links' ), $file_doc( 'RAR' ) ),
				'mp3'  => array( __( 'Audio file', 'check-for-broken-links' ), '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>' ),
				'mp4'  => array( __( 'Video file', 'check-for-broken-links' ), '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>' ),
			);
			$types['wav']  = $types['mp3'];
			$types['ogg']  = $types['mp3'];
			$types['m4a']  = $types['mp3'];
			$types['webm'] = $types['mp4'];
			$types['mov']  = $types['mp4'];
			$types['avi']  = $types['mp4'];

			$image_exts = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif', 'bmp', 'ico' );

			if ( in_array( $ext, $image_exts, true ) ) {
				return $this->element_icon( __( 'Image', 'check-for-broken-links' ), $image_svg );
			}

			if ( isset( $types[ $ext ] ) ) {
				return $this->element_icon( $types[ $ext ][0], $types[ $ext ][1] );
			}

			return $this->element_icon(
				__( 'Link', 'check-for-broken-links' ),
				'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>'
			);
		}

		/**
		 * Wraps an element-type icon with its accessible label.
		 *
		 * @since 3.0.0
		 *
		 * @param string $label Human label for the type.
		 * @param string $icon  Inline SVG markup.
		 *
		 * @return string
		 */
		private function element_icon( $label, $icon ) {
			return sprintf( '<span class="cbl-element-icon" title="%1$s" aria-label="%1$s" role="img">%2$s</span>', esc_attr( $label ), $icon );
		}

		/**
		 * Returns the list of sortable columns.
		 *
		 * @since 1.0.0
		 *
		 * @return array
		 */
		public function get_sortable_columns() {
			return array();
		}

		/**
		 * Renders the default column.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $item        The current item.
		 * @param string $column_name The current column name.
		 *
		 * @return string
		 */
		public function column_default( $item, $column_name ) {
			switch ( $column_name ) {
				case 'type':
				case 'source':
				case 'post_type':
				case 'code':
				case 'text':
				case 'link':
				case 'detected_at':
					return $item[ $column_name ];

				default:
					return esc_html__( 'Data not available.', 'check-for-broken-links' );
			}
		}

		/**
		 * Renders the source column.
		 *
		 * @since 1.0.0
		 *
		 * @param array $item The current item.
		 *
		 * @return string
		 */
		public function column_source( $item ) {
			// Custom sources (e.g. sliders) may provide a pre-built label.
			if ( isset( $item['source_label'] ) ) {
				$label = $item['source_label'];
				if ( isset( $item['source_url'] ) && ! empty( $item['source_url'] ) ) {
					return sprintf( '<strong><a href="%s" target="_blank">%s</a></strong>', esc_url( $item['source_url'] ), esc_html( $label ) );
				}
				return sprintf( '<strong>%s</strong>', esc_html( $label ) );
			}

			$actions = array(
				'edit' => sprintf(
					'<a href="%s" target="_blank">%s</a>',
					esc_url( wpcbl_get_post_or_comment_edit_link( $item ) ),
					esc_html__( 'Edit', 'check-for-broken-links' )
				),
				'view' => sprintf(
					'<a href="%s" target="_blank">%s</a>',
					esc_url( wpcbl_get_post_or_comment_link( $item ) ),
					esc_html__( 'View', 'check-for-broken-links' )
				),
			);

			return sprintf( '<strong><a href="%1$s" target="_blank">%2$s</a></strong> %3$s', wpcbl_get_post_or_comment_link( $item ), wpcbl_get_post_or_comment_title( $item ), $this->row_actions( $actions ) );
		}

		/**
		 * Renders the link column.
		 *
		 * @since 1.0.0
		 *
		 * @param array $item The current item.
		 *
		 * @return string
		 */
		public function column_link( $item ) {
			$is_comment   = ! empty( $item['is_comment'] );
			$is_slider    = ! empty( $item['is_slider'] );
			$post_id      = isset( $item['ID'] ) ? (int) $item['ID'] : 0;
			$data_attrs   = sprintf( ' data-wpcbl-url="%s" data-wpcbl-post="%d"', esc_attr( $item['link'] ), $post_id );
			$can_edit_content = ! $is_comment && ! $is_slider && $post_id > 0;

			// Only offer content edits when the URL really is in the raw post
			// content. Links rendered by shortcodes and builders cannot be
			// edited here, so those rows keep Find / Not broken / Dismiss only.
			if ( $can_edit_content ) {
				$action_post      = get_post( $post_id );
				$can_edit_content = $action_post && false !== WPCBL_Check_Broken_Links_Utilities::find_url_in_content( $action_post->post_content, $item['link'] );
			}

			$actions = array(
				'find' => sprintf(
					'<a href="%s" target="_blank">%s</a>',
					esc_url( add_query_arg( 'broken-link', $item['link'], wpcbl_get_post_or_comment_link( $item ) ) ),
					esc_html__( 'Find', 'check-for-broken-links' )
				),
			);

			if ( $can_edit_content ) {
				$actions['edit-url'] = sprintf(
					'<a href="#" class="wpcbl-action-edit-url"%s>%s</a>',
					$data_attrs,
					esc_html__( 'Edit URL', 'check-for-broken-links' )
				);

				if ( 'image' !== ( isset( $item['element'] ) ? $item['element'] : 'link' ) ) {
					$actions['unlink'] = sprintf(
						'<a href="#" class="wpcbl-action-unlink"%s>%s</a>',
						$data_attrs,
						esc_html__( 'Unlink', 'check-for-broken-links' )
					);
				}
			}

			// Fix with AI: the flagship Pro action stays visible in the free
			// plugin as a locked upsell pointing at the Upgrade page.
			if ( ! wpcbl_has_pro() ) {
				$actions['fix-ai'] = sprintf(
					'<a href="%s" class="wpcbl-action-fix-ai-locked">%s <span class="cbl-pro-chip">%s</span></a>',
					esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-upgrade' ) ),
					esc_html__( 'Fix with AI', 'check-for-broken-links' ),
					esc_html__( 'PRO', 'check-for-broken-links' )
				);
			}

			// Pro actions, rendered only when the Pro add-on unlocks them.
			if ( wpcbl_has_pro() ) {
				if ( $can_edit_content && 'on' === wpcbl_get_option( 'ai_fix', '' ) && 'broken' === $item['type'] && 'image' !== ( isset( $item['element'] ) ? $item['element'] : 'link' ) ) {
					$actions['fix-ai'] = sprintf(
						'<a href="#" class="wpcbl-action-fix-ai"%s data-wpcbl-code="%d">%s</a>',
						$data_attrs,
						isset( $item['code'] ) ? (int) $item['code'] : 0,
						esc_html__( 'Fix with AI', 'check-for-broken-links' )
					);
				}

				if ( $can_edit_content && 'on' === wpcbl_get_option( 'fix_redirects', '' ) && in_array( (int) $item['code'], array( 301, 308 ), true ) ) {
					$actions['fix-redirect'] = sprintf(
						'<a href="#" class="wpcbl-action-fix-redirect"%s>%s</a>',
						$data_attrs,
						esc_html__( 'Fix redirect', 'check-for-broken-links' )
					);
				}

				if ( 'on' === wpcbl_get_option( 'wayback_suggestions', '' ) && isset( $item['link_source'] ) && 'external' === $item['link_source'] && 'broken' === $item['type'] ) {
					$actions['wayback'] = sprintf(
						'<a href="#" class="wpcbl-action-wayback"%s>%s</a>',
						$data_attrs,
						esc_html__( 'View archived version', 'check-for-broken-links' )
					);
				}
			}

			$actions['not-broken'] = sprintf(
				'<a href="#" class="wpcbl-action-not-broken"%s>%s</a>',
				$data_attrs,
				esc_html__( 'Not broken', 'check-for-broken-links' )
			);

			$actions['dismiss'] = sprintf(
				'<a href="#" class="wpcbl-action-dismiss"%s>%s</a>',
				$data_attrs,
				esc_html__( 'Dismiss', 'check-for-broken-links' )
			);

			$display_url = esc_url( $item['link'] );

			// esc_url() refuses invalid schemes (htp://, mailto typos) and
			// returns an empty string, which used to render a blank cell.
			// Show the raw URL as plain text instead.
			if ( '' === $display_url ) {
				return sprintf( '<strong><span class="cbl-url-clamp" title="%1$s">%1$s</span></strong> %2$s', esc_html( $item['link'] ), $this->row_actions( $actions ) );
			}

			return sprintf( '<strong><a href="%1$s" target="_blank" class="cbl-url-clamp" title="%1$s">%1$s</a></strong> %2$s', $display_url, $this->row_actions( $actions ) );
		}

		/**
		 * Renders the type column.
		 *
		 * @since 1.0.0
		 *
		 * @param array $item The current item.
		 *
		 * @return string
		 */
		public function column_type( $item ) {
			$status = isset( $item['type'] ) ? $item['type'] : 'broken';
			$code   = isset( $item['code'] ) ? (int) $item['code'] : 0;
			$text   = isset( $item['text'] ) ? (string) $item['text'] : '';

			// First badge token: the HTTP code, or the failure reason when
			// there is no meaningful code.
			if ( 666 === $code ) {
				$reason = __( 'Invalid', 'check-for-broken-links' );
			} elseif ( 408 === $code ) {
				$reason = __( 'Timeout', 'check-for-broken-links' );
			} elseif ( in_array( $code, array( 495, 496, 497, 525, 526 ), true ) ) {
				$reason = __( 'SSL', 'check-for-broken-links' );
			} elseif ( 0 === $code ) {
				if ( false !== stripos( $text, 'ssl' ) || false !== stripos( $text, 'certificate' ) ) {
					$reason = __( 'SSL', 'check-for-broken-links' );
				} elseif ( false !== stripos( $text, 'time' ) ) {
					$reason = __( 'Timeout', 'check-for-broken-links' );
				} else {
					$reason = __( 'Error', 'check-for-broken-links' );
				}
			} else {
				$reason = (string) $code;
			}

			if ( 'redirect' === $status || in_array( $code, array( 301, 308 ), true ) ) {
				$class          = 'cbl-badge-redirect';
				$classification = __( 'Redirect', 'check-for-broken-links' );
			} elseif ( 'warning' === $status ) {
				$class          = 'cbl-badge-warning';
				$classification = __( 'Warning', 'check-for-broken-links' );
			} else {
				$class          = 'cbl-badge-broken';
				$classification = __( 'Broken', 'check-for-broken-links' );
			}

			// The old Details text survives as a tooltip.
			$badges = sprintf(
				'<span class="cbl-badge %1$s" title="%2$s">%3$s %4$s</span>',
				esc_attr( $class ),
				esc_attr( $code . ' - ' . $text ),
				esc_html( $reason ),
				esc_html( $classification )
			);

			// Internal or external link, matching the existing type filter.
			if ( isset( $item['link_source'] ) && '' !== $item['link_source'] ) {
				$source_label = 'internal' === $item['link_source'] ? __( 'Internal', 'check-for-broken-links' ) : __( 'External', 'check-for-broken-links' );
				$badges      .= sprintf( ' <span class="cbl-badge cbl-badge-source">%s</span>', esc_html( $source_label ) );
			}

			return $badges;
		}

		/**
		 * Renders the post type column.
		 *
		 * @since 1.0.0
		 *
		 * @param array $item The current item.
		 *
		 * @return string
		 */
		public function column_post_type( $item ) {
			return wpcbl_get_post_or_comment_type( $item );
		}

		/**
		 * Renders the filter dropdown form.
		 *
		 * @param string $which The current navigation position.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function extra_tablenav( $which ) {
			// Verify the nonce.
			if ( isset( $_REQUEST['wpcbl_type_filter'] ) || isset( $_REQUEST['wpcbl_location_filter'] ) || isset( $_REQUEST['wpcbl_status_filter'] ) ) {
				if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'wpcbl_filter_action' ) ) {
					wp_die( esc_html__( 'Cheatin&#8217; huh?', 'check-for-broken-links' ) );}
			}

			if ( 'top' === $which ) {
				// The code that goes before the table is here.
				$selected_type     = isset( $_REQUEST['wpcbl_type_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpcbl_type_filter'] ) ) : '';
				$selected_location = isset( $_REQUEST['wpcbl_location_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpcbl_location_filter'] ) ) : '';
				$selected_status   = isset( $_REQUEST['wpcbl_status_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpcbl_status_filter'] ) ) : '';
				?>
					<div class="alignleft actions">
						<select name="wpcbl_status_filter">
							<option value="" <?php selected( $selected_status, '' ); ?>><?php esc_html_e( 'All Statuses', 'check-for-broken-links' ); ?></option>
							<option value="broken" <?php selected( $selected_status, 'broken' ); ?>><?php esc_html_e( 'Broken', 'check-for-broken-links' ); ?></option>
							<option value="warning" <?php selected( $selected_status, 'warning' ); ?>><?php esc_html_e( 'Warnings', 'check-for-broken-links' ); ?></option>
							<option value="redirect" <?php selected( $selected_status, 'redirect' ); ?>><?php esc_html_e( 'Redirects', 'check-for-broken-links' ); ?></option>
						</select>

						<select name="wpcbl_type_filter">
							<option value="" <?php selected( $selected_type, '' ); ?>"><?php esc_html_e( 'All Types', 'check-for-broken-links' ); ?></option>
							<option value="internal" <?php selected( $selected_type, 'internal' ); ?>"><?php esc_html_e( 'Internal', 'check-for-broken-links' ); ?></option>
							<option value="external" <?php selected( $selected_type, 'external' ); ?>"><?php esc_html_e( 'External', 'check-for-broken-links' ); ?></option>
						</select>

						<select name="wpcbl_location_filter">
							<option value="" <?php selected( $selected_location, '' ); ?>"><?php esc_html_e( 'All Content Types', 'check-for-broken-links' ); ?></option>
							<option value="post" <?php selected( $selected_location, 'post' ); ?>"><?php esc_html_e( 'Posts', 'check-for-broken-links' ); ?></option>
							<option value="page" <?php selected( $selected_location, 'page' ); ?>"><?php esc_html_e( 'Pages', 'check-for-broken-links' ); ?></option>
							<option value="custom" <?php selected( $selected_location, 'custom' ); ?>"><?php esc_html_e( 'Custom Content', 'check-for-broken-links' ); ?></option>
							<option value="comment" <?php selected( $selected_location, 'comment' ); ?>"><?php esc_html_e( 'Comments', 'check-for-broken-links' ); ?></option>
							<option value="slider" <?php selected( $selected_location, 'slider' ); ?>"><?php esc_html_e( 'Slider Content', 'check-for-broken-links' ); ?></option>
						</select>

						<?php
						// This table only renders on Broken link scan today, but the
						// filter form must post back to whatever page is actually
						// showing it (it used to be the combined Dashboard & Scan
						// page) rather than a hardcoded slug, or the filter silently
						// lands the admin on a different page with no table at all.
						$wpcbl_filter_page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						if ( '' === $wpcbl_filter_page || 0 !== strpos( $wpcbl_filter_page, 'wpcbl-check-for-broken-links' ) ) {
							$wpcbl_filter_page = 'wpcbl-check-for-broken-links-scan';
						}
						?>
						<input type="hidden" name="page" value="<?php echo esc_attr( $wpcbl_filter_page ); ?>">
						<input type="hidden" name="tab" value="scan">

						<?php wp_nonce_field( 'wpcbl_filter_action', '_wpnonce' ); ?>
						<?php submit_button( 'Filter', 'button', 'filter_action', false ); ?>
					</div>
				<?php
			}
		}

		/**
		 * Renders the table navigation.
		 *
		 * @since 1.0.0
		 *
		 * @param string $which The current navigation position.
		 *
		 * @return void
		 */
		public function display_tablenav( $which ) {
			echo '<div class="tablenav ' . esc_attr( $which ) . '">';

			$this->extra_tablenav( $which );
			$this->pagination( $which );

			echo '<br class="clear" />';
			echo '</div>';
		}
	}

endif;
