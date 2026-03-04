<?php
/**
 * List Table API: WP_Posts_List_Table class
 *
 * @package WordPress
 * @subpackage Administration
 * @since 3.1.0
 */

/**
 * Core class used to implement displaying posts in a list table.
 *
 * @since 3.1.0
 *
 * @see WP_List_Table
 */
class WP_Posts_List_Table extends WP_List_Table {

	/**
	 * Whether the items should be displayed hierarchically or linearly.
	 *
	 * @since 3.1.0
	 * @var bool
	 */
	protected $hierarchical_display;

	/**
	 * Holds the number of pending comments for each post.
	 *
	 * @since 3.1.0
	 * @var array
	 */
	protected $comment_pending_count;

	/**
	 * Holds the number of posts for this user.
	 *
	 * @since 3.1.0
	 * @var int
	 */
	private $user_posts_count;

	/**
	 * Holds the number of posts which are sticky.
	 *
	 * @since 3.1.0
	 * @var int
	 */
	private $sticky_posts_count = 0;

	private $is_trash;

	/**
	 * Current level for output.
	 *
	 * @since 4.3.0
	 * @var int
	 */
	protected $current_level = 0;

	/**
	 * Constructor.
	 *
	 * @since 3.1.0
	 *
	 * @see WP_List_Table::__construct() for more information on default arguments.
	 *
	 * @global WP_Post_Type $post_type_object Global post type object.
	 * @global wpdb         $wpdb             WordPress database abstraction object.
	 *
	 * @param array $args An associative array of arguments.
	 */
	public function __construct( $args = array() ) {
		global $post_type_object, $wpdb;

		parent::__construct(
			array(
				'plural' => 'posts',
				'screen' => isset( $args['screen'] ) ? $args['screen'] : null,
			)
		);

		$post_type        = $this->screen->post_type;
		$post_type_object = get_post_type_object( $post_type );

		$exclude_states = get_post_stati(
			array(
				'show_in_admin_all_list' => false,
			)
		);

		$this->user_posts_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT( 1 )
				FROM $wpdb->posts
				WHERE post_type = %s
				AND post_status NOT IN ( '" . implode( "','", $exclude_states ) . "' )
				AND post_author = %d",
				$post_type,
				get_current_user_id()
			)
		);

		if ( $this->user_posts_count
			&& ! current_user_can( $post_type_object->cap->edit_others_posts )
			&& empty( $_REQUEST['post_status'] ) && empty( $_REQUEST['all_posts'] )
			&& empty( $_REQUEST['author'] ) && empty( $_REQUEST['show_sticky'] )
		) {
			$_GET['author'] = get_current_user_id();
		}

		$sticky_posts = get_option( 'sticky_posts' );

		if ( 'post' === $post_type && $sticky_posts ) {
			$sticky_posts = implode( ', ', array_map( 'absint', (array) $sticky_posts ) );

			$this->sticky_posts_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT( 1 )
					FROM $wpdb->posts
					WHERE post_type = %s
					AND post_status NOT IN ('trash', 'auto-draft')
					AND ID IN ($sticky_posts)",
					$post_type
				)
			);
		}
	}

	/**
	 * Sets whether the table layout should be hierarchical or not.
	 *
	 * @since 4.2.0
	 *
	 * @param bool $display Whether the table layout should be hierarchical.
	 */
	public function set_hierarchical_display( $display ) {
		$this->hierarchical_display = $display;
	}

	/**
	 * @return bool
	 */
	public function ajax_user_can() {
		return current_user_can( get_post_type_object( $this->screen->post_type )->cap->edit_posts );
	}

	/**
	 * @global array    $avail_post_stati
	 * @global WP_Query $wp_query         WordPress Query object.
	 * @global int      $per_page
	 */
	public function prepare_items() {
		global $avail_post_stati, $wp_query, $per_page;

		// Is going to call wp().
		$avail_post_stati = wp_edit_posts_query();

		$this->set_hierarchical_display(
			is_post_type_hierarchical( $this->screen->post_type )
			&& 'menu_order title' === $wp_query->query['orderby']
		);

		$post_type = $this->screen->post_type;
		$per_page  = $this->get_items_per_page( 'edit_' . $post_type . '_per_page' );

		/** This filter is documented in wp-admin/includes/post.php */
		$per_page = apply_filters( 'edit_posts_per_page', $per_page, $post_type );

		if ( $this->hierarchical_display ) {
			$total_items = $wp_query->post_count;
		} elseif ( $wp_query->found_posts || $this->get_pagenum() === 1 ) {
			$total_items = $wp_query->found_posts;
		} else {
			$post_counts = (array) wp_count_posts( $post_type, 'readable' );

			if ( isset( $_REQUEST['post_status'] ) && in_array( $_REQUEST['post_status'], $avail_post_stati, true ) ) {
				$total_items = $post_counts[ $_REQUEST['post_status'] ];
			} elseif ( isset( $_REQUEST['show_sticky'] ) && $_REQUEST['show_sticky'] ) {
				$total_items = $this->sticky_posts_count;
			} elseif ( isset( $_GET['author'] ) && get_current_user_id() === (int) $_GET['author'] ) {
				$total_items = $this->user_posts_count;
			} else {
				$total_items = array_sum( $post_counts );

				// Subtract post types that are not included in the admin all list.
				foreach ( get_post_stati( array( 'show_in_admin_all_list' => false ) ) as $state ) {
					$total_items -= $post_counts[ $state ];
				}
			}
		}

		$this->is_trash = isset( $_REQUEST['post_status'] ) && 'trash' === $_REQUEST['post_status'];

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * @return bool
	 */
	public function has_items() {
		return have_posts();
	}

	/**
	 */
	public function no_items() {
		if ( isset( $_REQUEST['post_status'] ) && 'trash' === $_REQUEST['post_status'] ) {
			echo get_post_type_object( $this->screen->post_type )->labels->not_found_in_trash;
		} else {
			echo get_post_type_object( $this->screen->post_type )->labels->not_found;
		}
	}

	/**
	 * Determines if the current view is the "All" view.
	 *
	 * @since 4.2.0
	 *
	 * @return bool Whether the current view is the "All" view.
	 */
	protected function is_base_request() {
		$vars = $_GET;
		unset( $vars['paged'] );

		if ( empty( $vars ) ) {
			return true;
		} elseif ( 1 === count( $vars ) && ! empty( $vars['post_type'] ) ) {
			return $this->screen->post_type === $vars['post_type'];
		}

		return 1 === count( $vars ) && ! empty( $vars['mode'] );
	}

	/**
	 * Creates a link to edit.php with params.
	 *
	 * @since 4.4.0
	 *
	 * @param string[] $args      Associative array of URL parameters for the link.
	 * @param string   $link_text Link text.
	 * @param string   $css_class Optional. Class attribute. Default empty string.
	 * @return string The formatted link string.
	 */
	protected function get_edit_link( $args, $link_text, $css_class = '' ) {
		$url = add_query_arg( $args, 'edit.php' );

		$class_html   = '';
		$aria_current = '';

		if ( ! empty( $css_class ) ) {
			$class_html = sprintf(
				' class="%s"',
				esc_attr( $css_class )
			);

			if ( 'current' === $css_class ) {
				$aria_current = ' aria-current="page"';
			}
		}

		return sprintf(
			'<a href="%s"%s%s>%s</a>',
			esc_url( $url ),
			$class_html,
			$aria_current,
			$link_text
		);
	}

	/**
	 * @global array $locked_post_status This seems to be deprecated.
	 * @global array $avail_post_stati
	 * @return array
	 */
	protected function get_views() {
		global $locked_post_status, $avail_post_stati;

		$post_type = $this->screen->post_type;

		if ( ! empty( $locked_post_status ) ) {
			return array();
		}

		$status_links = array();
		$num_posts    = wp_count_posts( $post_type, 'readable' );
		$total_posts  = array_sum( (array) $num_posts );
		$class        = '';

		$current_user_id = get_current_user_id();
		$all_args        = array( 'post_type' => $post_type );
		$mine            = '';

		// Subtract post types that are not included in the admin all list.
		foreach ( get_post_stati( array( 'show_in_admin_all_list' => false ) ) as $state ) {
			$total_posts -= $num_posts->$state;
		}

		if ( $this->user_posts_count && $this->user_posts_count !== $total_posts ) {
			if ( isset( $_GET['author'] ) && ( $current_user_id === (int) $_GET['author'] ) ) {
				$class = 'current';
			}

			$mine_args = array(
				'post_type' => $post_type,
				'author'    => $current_user_id,
			);

			$mine_inner_html = sprintf(
				/* translators: %s: Number of posts. */
				_nx(
					'Mine <span class="count">(%s)</span>',
					'Mine <span class="count">(%s)</span>',
					$this->user_posts_count,
					'posts'
				),
				number_format_i18n( $this->user_posts_count )
			);

			$mine = array(
				'url'     => esc_url( add_query_arg( $mine_args, 'edit.php' ) ),
				'label'   => $mine_inner_html,
				'current' => isset( $_GET['author'] ) && ( $current_user_id === (int) $_GET['author'] ),
			);

			$all_args['all_posts'] = 1;
			$class                 = '';
		}

		$all_inner_html = sprintf(
			/* translators: %s: Number of posts. */
			_nx(
				'All <span class="count">(%s)</span>',
				'All <span class="count">(%s)</span>',
				$total_posts,
				'posts'
			),
			number_format_i18n( $total_posts )
		);

		$status_links['all'] = array(
			'url'     => esc_url( add_query_arg( $all_args, 'edit.php' ) ),
			'label'   => $all_inner_html,
			'current' => empty( $class ) && ( $this->is_base_request() || isset( $_REQUEST['all_posts'] ) ),
		);

		if ( $mine ) {
			$status_links['mine'] = $mine;
		}

		foreach ( get_post_stati( array( 'show_in_admin_status_list' => true ), 'objects' ) as $status ) {
			$class = '';

			$status_name = $status->name;

			if ( ! in_array( $status_name, $avail_post_stati, true ) || empty( $num_posts->$status_name ) ) {
				continue;
			}

			if ( isset( $_REQUEST['post_status'] ) && $status_name === $_REQUEST['post_status'] ) {
				$class = 'current';
			}

			$status_args = array(
				'post_status' => $status_name,
				'post_type'   => $post_type,
			);

			$status_label = sprintf(
				translate_nooped_plural( $status->label_count, $num_posts->$status_name ),
				number_format_i18n( $num_posts->$status_name )
			);

			$status_links[ $status_name ] = array(
				'url'     => esc_url( add_query_arg( $status_args, 'edit.php' ) ),
				'label'   => $status_label,
				'current' => isset( $_REQUEST['post_status'] ) && $status_name === $_REQUEST['post_status'],
			);
		}

		if ( ! empty( $this->sticky_posts_count ) ) {
			$class = ! empty( $_REQUEST['show_sticky'] ) ? 'current' : '';

			$sticky_args = array(
				'post_type'   => $post_type,
				'show_sticky' => 1,
			);

			$sticky_inner_html = sprintf(
				/* translators: %s: Number of posts. */
				_nx(
					'Sticky <span class="count">(%s)</span>',
					'Sticky <span class="count">(%s)</span>',
					$this->sticky_posts_count,
					'posts'
				),
				number_format_i18n( $this->sticky_posts_count )
			);

			$sticky_link = array(
				'sticky' => array(
					'url'     => esc_url( add_query_arg( $sticky_args, 'edit.php' ) ),
					'label'   => $sticky_inner_html,
					'current' => ! empty( $_REQUEST['show_sticky'] ),
				),
			);

			// Sticky comes after Publish, or if not listed, after All.
			$split        = 1 + array_search( ( isset( $status_links['publish'] ) ? 'publish' : 'all' ), array_keys( $status_links ), true );
			$status_links = array_merge( array_slice( $status_links, 0, $split ), $sticky_link, array_slice( $status_links, $split ) );
		}

		return $this->get_views_links( $status_links );
	}

	/**
	 * @return array
	 */
	protected function get_bulk_actions() {
		$actions       = array();
		$post_type_obj = get_post_type_object( $this->screen->post_type );

		if ( current_user_can( $post_type_obj->cap->edit_posts ) ) {
			if ( $this->is_trash ) {
				$actions['untrash'] = __( 'Restore' );
			}
		}

		if ( current_user_can( $post_type_obj->cap->delete_posts ) ) {
			if ( $this->is_trash || ! EMPTY_TRASH_DAYS ) {
				$actions['delete'] = __( 'Delete permanently' );
			} else {
				$actions['trash'] = __( 'Move to Trash' );
			}
		}

		return $actions;
	}

	/**
	 * Displays a categories drop-down for filtering on the Posts list table.
	 *
	 * @since 4.6.0
	 *
	 * @global int $cat Currently selected category.
	 *
	 * @param string $post_type Post type slug.
	 */
	protected function categories_dropdown( $post_type ) {
		global $cat;

		/**
		 * Filters whether to remove the 'Categories' drop-down from the post list table.
		 *
		 * @since 4.6.0
		 *
		 * @param bool   $disable   Whether to disable the categories drop-down. Default false.
		 * @param string $post_type Post type slug.
		 */
		if ( false !== apply_filters( 'disable_categories_dropdown', false, $post_type ) ) {
			return;
		}

		if ( is_object_in_taxonomy( $post_type, 'category' ) ) {
			$dropdown_options = array(
				'show_option_all' => get_taxonomy( 'category' )->labels->all_items,
				'hide_empty'      => 0,
				'hierarchical'    => 1,
				'show_count'      => 0,
				'orderby'         => 'name',
				'selected'        => $cat,
				'echo'            => false,
			);

			$dropdown = wp_dropdown_categories( $dropdown_options );
			if ( false !== strpos( $dropdown, 'option' ) ) {
				echo '<label class="screen-reader-text" for="cat">' . get_taxonomy( 'category' )->labels->filter_by_item . '</label>';
				echo $dropdown;
			}
		}
	}

	/**
	 * @param string $which
	 */
	protected function extra_tablenav( $which ) {
		?>
		<div class="alignleft actions">
		<?php
		if ( 'top' === $which ) {
			ob_start();

			$this->categories_dropdown( $this->screen->post_type );

			/**
			 * Fires before the Filter button on the Posts and Pages list tables.
			 *
			 * The Filter button allows sorting by date and/or category on the
			 * Posts list table, and sorting by date on the Pages list table.
			 *
			 * @since 2.1.0
			 * @since 4.4.0 The `$post_type` parameter was added.
			 * @since 4.6.0 The `$which` parameter was added.
			 *
			 * @param string $post_type The post type slug.
			 * @param string $which     The location of the extra table nav markup:
			 *                          'top' or 'bottom' for WP_Posts_List_Table,
			 *                          'bar' for WP_Media_List_Table.
			 */
			do_action( 'restrict_manage_posts', $this->screen->post_type, $which );

			$output = ob_get_clean();

			if ( ! empty( $output ) ) {
				echo $output;
				submit_button( __( 'Filter' ), '', 'filter_action', false, array( 'id' => 'post-query-submit' ) );
			}
		}

		if ( $this->is_trash && $this->has_items()
			&& current_user_can( get_post_type_object( $this->screen->post_type )->cap->edit_others_posts )
		) {
			submit_button( __( 'Empty Trash' ), 'apply', 'delete_all', false );
		}
		?>
		</div>
		<?php
		/**
		 * Fires immediately following the closing "actions" div in the tablenav for the posts
		 * list table.
		 *
		 * @since 4.4.0
		 *
		 * @param string $which The location of the extra table nav markup: 'top' or 'bottom'.
		 */
		do_action( 'manage_posts_extra_tablenav', $which );
	}

	/**
	 * @return string
	 */
	public function current_action() {
		if ( isset( $_REQUEST['delete_all'] ) || isset( $_REQUEST['delete_all2'] ) ) {
			return 'delete_all';
		}

		return parent::current_action();
	}

	/**
	 * @return array
	 */
	protected function get_table_classes() {
		$mode_class = esc_attr( 'table-view-list' );

		return array(
			'widefat',
			'fixed',
			'striped',
			$mode_class,
			is_post_type_hierarchical( $this->screen->post_type ) ? 'pages' : 'posts',
		);
	}

	/**
	 * @return string[] Array of column titles keyed by their column name.
	 */
	public function get_columns() {
		$post_type = $this->screen->post_type;

		$posts_columns = array();

		$posts_columns['cb'] = '<input type="checkbox" />';

		/* translators: Posts screen column name. */
		$posts_columns['title'] = _x( 'Title', 'column name' );

		if ( post_type_supports( $post_type, 'author' ) ) {
			$posts_columns['author'] = __( 'Editor' );
		}

		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		$taxonomies = wp_filter_object_list( $taxonomies, array( 'show_admin_column' => true ), 'and', 'name' );

		/**
		 * Filters the taxonomy columns in the Posts list table.
		 *
		 * The dynamic portion of the hook name, `$post_type`, refers to the post
		 * type slug.
		 *
		 * Possible hook names include:
		 *
		 *  - `manage_taxonomies_for_post_columns`
		 *  - `manage_taxonomies_for_page_columns`
		 *
		 * @since 3.5.0
		 *
		 * @param string[] $taxonomies Array of taxonomy names to show columns for.
		 * @param string   $post_type  The post type.
		 */
		$taxonomies = apply_filters( "manage_taxonomies_for_{$post_type}_columns", $taxonomies, $post_type );
		$taxonomies = array_filter( $taxonomies, 'taxonomy_exists' );

		foreach ( $taxonomies as $taxonomy ) {
			if ( 'category' === $taxonomy ) {
				$column_key = 'categories';
			} elseif ( 'post_tag' === $taxonomy ) {
				$column_key = 'tags';
			} else {
				$column_key = 'taxonomy-' . $taxonomy;
			}

			$posts_columns[ $column_key ] = get_taxonomy( $taxonomy )->labels->name;
		}

		$post_status = ! empty( $_REQUEST['post_status'] ) ? $_REQUEST['post_status'] : 'all';

		if ( post_type_supports( $post_type, 'comments' )
			&& ! in_array( $post_status, array( 'pending', 'draft', 'future' ), true )
		) {
			$posts_columns['comments'] = sprintf(
				'<span class="vers comment-grey-bubble" title="%1$s" aria-hidden="true"></span><span class="screen-reader-text">%2$s</span>',
				esc_attr__( 'Comments' ),
				/* translators: Hidden accessibility text. */
				__( 'Comments' )
			);
		}

		$posts_columns['date'] = __( 'Date' );

		if ( 'page' === $post_type ) {

			/**
			 * Filters the columns displayed in the Pages list table.
			 *
			 * @since 2.5.0
			 *
			 * @param string[] $posts_columns An associative array of column headings.
			 */
			$posts_columns = apply_filters( 'manage_pages_columns', $posts_columns );
		} else {

			/**
			 * Filters the columns displayed in the Posts list table.
			 *
			 * @since 1.5.0
			 *
			 * @param string[] $posts_columns An associative array of column headings.
			 * @param string   $post_type     The post type slug.
			 */
			$posts_columns = apply_filters( 'manage_posts_columns', $posts_columns, $post_type );
		}

		/**
		 * Filters the columns displayed in the Posts list table for a specific post type.
		 *
		 * The dynamic portion of the hook name, `$post_type`, refers to the post type slug.
		 *
		 * Possible hook names include:
		 *
		 *  - `manage_post_posts_columns`
		 *  - `manage_page_posts_columns`
		 *
		 * @since 3.0.0
		 *
		 * @param string[] $posts_columns An associative array of column headings.
		 */
		return apply_filters( "manage_{$post_type}_posts_columns", $posts_columns );
	}

	/**
	 * @return array
	 */
	protected function get_sortable_columns() {

		$post_type = $this->screen->post_type;

		if ( 'page' === $post_type ) {
			if ( isset( $_GET['orderby'] ) ) {
				$title_orderby_text = __( 'Table ordered by Title.' );
			} else {
				$title_orderby_text = __( 'Table ordered by Hierarchical Menu Order and Title.' );
			}

			$sortables = array(
				'title'    => array( 'title', false, __( 'Title' ), $title_orderby_text, 'asc' ),
				'parent'   => array( 'parent', false ),
				'comments' => array( 'comment_count', false, __( 'Comments' ), __( 'Table ordered by Comments.' ) ),
				'date'     => array( 'date', true, __( 'Date' ), __( 'Table ordered by Date.' ) ),
			);
		} else {
			$sortables = array(
				'title'    => array( 'title', false, __( 'Title' ), __( 'Table ordered by Title.' ) ),
				'parent'   => array( 'parent', false ),
				'comments' => array( 'comment_count', false, __( 'Comments' ), __( 'Table ordered by Comments.' ) ),
				'date'     => array( 'date', true, __( 'Date' ), __( 'Table ordered by Date.' ), 'desc' ),
			);
		}
		// Custom Post Types: there's a filter for that, see get_column_info().

		return $sortables;
	}

	/**
	 * Generates the list table rows.
	 *
	 * @since 3.1.0
	 *
	 * @global WP_Query $wp_query WordPress Query object.
	 * @global int      $per_page
	 *
	 * @param array $posts
	 * @param int   $level
	 */
	public function display_rows( $posts = array(), $level = 0 ) {
		global $wp_query, $per_page;

		if ( empty( $posts ) ) {
			$posts = $wp_query->posts;
		}

		add_filter( 'the_title', 'esc_html' );

		if ( $this->hierarchical_display ) {
			$this->_display_rows_hierarchical( $posts, $this->get_pagenum(), $per_page );
		} else {
			$this->_display_rows( $posts, $level );
		}
	}

	/**
	 * @param array $posts
	 * @param int   $level
	 */
	private function _display_rows( $posts, $level = 0 ) {
		$post_type = $this->screen->post_type;

		// Create array of post IDs.
		$post_ids = array();

		foreach ( $posts as $a_post ) {
			$post_ids[] = $a_post->ID;
		}

		if ( post_type_supports( $post_type, 'comments' ) ) {
			$this->comment_pending_count = get_pending_comments_num( $post_ids );
		}
		update_post_author_caches( $posts );

		foreach ( $posts as $post ) {
			$this->single_row( $post, $level );
		}
	}

	/**
	 * @global wpdb    $wpdb WordPress database abstraction object.
	 * @global WP_Post $post Global post object.
	 * @param array $pages
	 * @param int   $pagenum
	 * @param int   $per_page
	 */
	private function _display_rows_hierarchical( $pages, $pagenum = 1, $per_page = 20 ) {
		global $wpdb;

		$level = 0;

		if ( ! $pages ) {
			$pages = get_pages( array( 'sort_column' => 'menu_order' ) );

			if ( ! $pages ) {
				return;
			}
		}

		/*
		 * Arrange pages into two parts: top level pages and children_pages.
		 * children_pages is two dimensional array. Example:
		 * children_pages[10][] contains all sub-pages whose parent is 10.
		 * It only takes O( N ) to arrange this and it takes O( 1 ) for subsequent lookup operations
		 * If searching, ignore hierarchy and treat everything as top level
		 */
		if ( empty( $_REQUEST['s'] ) ) {
			$top_level_pages = array();
			$children_pages  = array();

			foreach ( $pages as $page ) {
				// Catch and repair bad pages.
				if ( $page->post_parent === $page->ID ) {
					$page->post_parent = 0;
					$wpdb->update( $wpdb->posts, array( 'post_parent' => 0 ), array( 'ID' => $page->ID ) );
					clean_post_cache( $page );
				}

				if ( $page->post_parent > 0 ) {
					$children_pages[ $page->post_parent ][] = $page;
				} else {
					$top_level_pages[] = $page;
				}
			}

			$pages = &$top_level_pages;
		}

		$count      = 0;
		$start      = ( $pagenum - 1 ) * $per_page;
		$end        = $start + $per_page;
		$to_display = array();

		foreach ( $pages as $page ) {
			if ( $count >= $end ) {
				break;
			}

			if ( $count >= $start ) {
				$to_display[ $page->ID ] = $level;
			}

			++$count;

			if ( isset( $children_pages ) ) {
				$this->_page_rows( $children_pages, $count, $page->ID, $level + 1, $pagenum, $per_page, $to_display );
			}
		}

		// If it is the last pagenum and there are orphaned pages, display them with paging as well.
		if ( isset( $children_pages ) && $count < $end ) {
			foreach ( $children_pages as $orphans ) {
				foreach ( $orphans as $op ) {
					if ( $count >= $end ) {
						break;
					}

					if ( $count >= $start ) {
						$to_display[ $op->ID ] = 0;
					}

					++$count;
				}
			}
		}

		$ids = array_keys( $to_display );
		_prime_post_caches( $ids );
		$_posts = array_map( 'get_post', $ids );
		update_post_author_caches( $_posts );

		if ( ! isset( $GLOBALS['post'] ) ) {
			$GLOBALS['post'] = reset( $ids );
		}

		foreach ( $to_display as $page_id => $level ) {
			echo "\t";
			$this->single_row( $page_id, $level );
		}
	}

	/**
	 * Displays the nested hierarchy of sub-pages together with paging
	 * support, based on a top level page ID.
	 *
	 * @since 3.1.0 (Standalone function exists since 2.6.0)
	 * @since 4.2.0 Added the `$to_display` parameter.
	 *
	 * @param array $children_pages
	 * @param int   $count
	 * @param int   $parent_page
	 * @param int   $level
	 * @param int   $pagenum
	 * @param int   $per_page
	 * @param array $to_display List of pages to be displayed. Passed by reference.
	 */
	private function _page_rows( &$children_pages, &$count, $parent_page, $level, $pagenum, $per_page, &$to_display ) {
		if ( ! isset( $children_pages[ $parent_page ] ) ) {
			return;
		}

		$start = ( $pagenum - 1 ) * $per_page;
		$end   = $start + $per_page;

		foreach ( $children_pages[ $parent_page ] as $page ) {
			if ( $count >= $end ) {
				break;
			}

			// If the page starts in a subtree, print the parents.
			if ( $count === $start && $page->post_parent > 0 ) {
				$my_parents = array();
				$my_parent  = $page->post_parent;

				while ( $my_parent ) {
					// Get the ID from the list or the attribute if my_parent is an object.
					$parent_id = $my_parent;

					if ( is_object( $my_parent ) ) {
						$parent_id = $my_parent->ID;
					}

					$my_parent    = get_post( $parent_id );
					$my_parents[] = $my_parent;

					if ( ! $my_parent->post_parent ) {
						break;
					}

					$my_parent = $my_parent->post_parent;
				}

				$num_parents = count( $my_parents );

				while ( $my_parent = array_pop( $my_parents ) ) {
					$to_display[ $my_parent->ID ] = $level - $num_parents;
					--$num_parents;
				}
			}

			if ( $count >= $start ) {
				$to_display[ $page->ID ] = $level;
			}

			++$count;

			$this->_page_rows( $children_pages, $count, $page->ID, $level + 1, $pagenum, $per_page, $to_display );
		}

		unset( $children_pages[ $parent_page ] ); // Required in order to keep track of orphans.
	}

	/**
	 * Handles the checkbox column output.
	 *
	 * @since 4.3.0
	 * @since 5.9.0 Renamed `$post` to `$item` to match parent class for PHP 8 named parameter support.
	 *
	 * @param WP_Post $item The current WP_Post object.
	 */
	public function column_cb( $item ) {
		// Restores the more descriptive, specific name for use within this method.
		$post = $item;

		$show = current_user_can( 'edit_post', $post->ID );

		/**
		 * Filters whether to show the bulk edit checkbox for a post in its list table.
		 *
		 * By default the checkbox is only shown if the current user can edit the post.
		 *
		 * @since 5.7.0
		 *
		 * @param bool    $show Whether to show the checkbox.
		 * @param WP_Post $post The current WP_Post object.
		 */
		if ( apply_filters( 'wp_list_table_show_post_checkbox', $show, $post ) ) :
			?>
			<input id="cb-select-<?php the_ID(); ?>" type="checkbox" name="post[]" value="<?php the_ID(); ?>" />
			<label for="cb-select-<?php the_ID(); ?>">
				<span class="screen-reader-text">
				<?php
					/* translators: %s: Post title. */
					printf( __( 'Select %s' ), _draft_or_post_title() );
				?>
				</span>
			</label>
			<div class="locked-indicator">
				<span class="locked-indicator-icon" aria-hidden="true"></span>
				<span class="screen-reader-text">
				<?php
				printf(
					/* translators: Hidden accessibility text. %s: Post title. */
					__( '&#8220;%s&#8221; is locked' ),
					_draft_or_post_title()
				);
				?>
				</span>
			</div>
			<?php
		endif;
	}

	/**
	 * @since 4.3.0
	 *
	 * @param WP_Post $post
	 * @param string  $classes
	 * @param string  $data
	 * @param string  $primary
	 */
	protected function _column_title( $post, $classes, $data, $primary ) {
		echo '<td class="' . $classes . ' page-title" ', $data, '>';
		echo $this->column_title( $post );
		echo $this->handle_row_actions( $post, 'title', $primary );
		echo '</td>';
	}

	/**
	 * Handles the title column output.
	 *
	 * @since 4.3.0
	 *
	 * @param WP_Post $post The current WP_Post object.
	 */
	public function column_title( $post ) {

		if ( $this->hierarchical_display ) {
			if ( 0 === $this->current_level && (int) $post->post_parent > 0 ) {
				// Sent level 0 by accident, by default, or because we don't know the actual level.
				$find_main_page = (int) $post->post_parent;

				while ( $find_main_page > 0 ) {
					$parent = get_post( $find_main_page );

					if ( is_null( $parent ) ) {
						break;
					}

					++$this->current_level;
					$find_main_page = (int) $parent->post_parent;

					if ( ! isset( $parent_name ) ) {
						/** This filter is documented in wp-includes/post-template.php */
						$parent_name = apply_filters( 'the_title', $parent->post_title, $parent->ID );
					}
				}
			}
		}

		$can_edit_post = current_user_can( 'edit_post', $post->ID );

		if ( $can_edit_post && 'trash' !== $post->post_status ) {
			$lock_holder = wp_check_post_lock( $post->ID );

			if ( $lock_holder ) {
				$lock_holder   = get_userdata( $lock_holder );
				$locked_avatar = get_avatar( $lock_holder->ID, 18 );
				/* translators: %s: User's display name. */
				$locked_text = esc_html( sprintf( __( '%s is currently editing' ), $lock_holder->display_name ) );
			} else {
				$locked_avatar = '';
				$locked_text   = '';
			}

			echo '<div class="locked-info"><span class="locked-avatar">' . $locked_avatar . '</span> <span class="locked-text">' . $locked_text . "</span></div>\n";
		}

		$pad = str_repeat( '&#8212; ', $this->current_level );
		echo '<strong>';

		$title = _draft_or_post_title();

		if ( $can_edit_post && 'trash' !== $post->post_status ) {
			printf(
				'<a class="row-title" href="%s" aria-label="%s">%s%s</a>',
				get_edit_post_link( $post->ID ),
				/* translators: %s: Post title. */
				esc_attr( sprintf( __( '&#8220;%s&#8221; (Edit)' ), $title ) ),
				$pad,
				$title
			);
		} else {
			printf(
				'<span>%s%s</span>',
				$pad,
				$title
			);
		}
		_post_states( $post );

		if ( isset( $parent_name ) ) {
			$post_type_object = get_post_type_object( $post->post_type );
			echo ' | ' . $post_type_object->labels->parent_item_colon . ' ' . esc_html( $parent_name );
		}

		echo "</strong>\n";
	}

	/**
	 * Handles the post date column output.
	 *
	 * @since 4.3.0
	 *
	 * @param WP_Post $post The current WP_Post object.
	 */
	public function column_date( $post ) {

		if ( '0000-00-00 00:00:00' === $post->post_date ) {
			$t_time    = __( 'Unpublished' );
			$time_diff = 0;
		} else {
			$t_time = sprintf(
				/* translators: 1: Post date, 2: Post time. */
				__( '%1$s at %2$s' ),
				/* translators: Post date format. See https://www.php.net/manual/datetime.format.php */
				get_the_time( __( 'Y/m/d' ), $post ),
				/* translators: Post time format. See https://www.php.net/manual/datetime.format.php */
				get_the_time( __( 'g:i a' ), $post )
			);

			$time      = get_post_timestamp( $post );
			$time_diff = time() - $time;
		}

		if ( 'publish' === $post->post_status ) {
			$status = __( 'Published' );
		} elseif ( 'future' === $post->post_status ) {
			if ( $time_diff > 0 ) {
				$status = '<strong class="error-message">' . __( 'Missed schedule' ) . '</strong>';
			} else {
				$status = __( 'Scheduled' );
			}
		} else {
			$status = __( 'Last Modified' );
		}

		/**
		 * Filters the status text of the post.
		 *
		 * The only mode calmPress suports is "list".
		 *
		 * @since 4.8.0
		 * @since calmPress 1.0.0
		 *
		 * @param string  $status      The status text.
		 * @param WP_Post $post        Post object.
		 * @param string  $column_name The column name.
		 * @param string  $mode        The list display mode 'list'.
		 */
		$status = apply_filters( 'post_date_column_status', $status, $post, 'date', 'list' );

		if ( $status ) {
			echo $status . '<br />';
		}

		/**
		 * Filters the published, scheduled, or unpublished time of the post.
		 *
		 * @since 2.5.1
		 * @since 5.5.0 Removed the difference between 'excerpt' and 'list' modes.
		 *              The published time and date are both displayed now,
		 *              which is equivalent to the previous 'excerpt' mode.
		 *
		 * @param string  $t_time      The published time.
		 * @param WP_Post $post        Post object.
		 * @param string  $column_name The column name.
		 * @param string  $mode        The list display mode 'list'.
		 */
		echo apply_filters( 'post_date_column_time', $t_time, $post, 'date', 'list' );
	}

	/**
	 * Handles the comments column output.
	 *
	 * @since 4.3.0
	 *
	 * @param WP_Post $post The current WP_Post object.
	 */
	public function column_comments( $post ) {
		?>
		<div class="post-com-count-wrapper">
		<?php
			$pending_comments = isset( $this->comment_pending_count[ $post->ID ] ) ? $this->comment_pending_count[ $post->ID ] : 0;

			$this->comments_bubble( $post->ID, $pending_comments );
		?>
		</div>
		<?php
	}

	/**
	 * Handles the post author column output.
	 *
	 * @since 4.3.0
	 * @since 6.8.0 Added fallback text when author's name is unknown.
	 *
	 * @param WP_Post $post The current WP_Post object.
	 */
	public function column_author( $post ) {
		$author = _get_the_editor();

		if ( ! empty( $author ) ) {
			$args = array(
				'post_type' => $post->post_type,
				'author'    => _get_the_editor_meta( 'ID' ),
			);
			echo $this->get_edit_link( $args, esc_html( $author ) );
		} else {
			echo '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">' . __( '(no editor)' ) . '</span>';
		}
	}

	/**
	 * Handles the default column output.
	 *
	 * @since 4.3.0
	 * @since 5.9.0 Renamed `$post` to `$item` to match parent class for PHP 8 named parameter support.
	 *
	 * @param WP_Post $item        The current WP_Post object.
	 * @param string  $column_name The current column name.
	 */
	public function column_default( $item, $column_name ) {
		// Restores the more descriptive, specific name for use within this method.
		$post = $item;

		if ( 'categories' === $column_name ) {
			$taxonomy = 'category';
		} elseif ( 'tags' === $column_name ) {
			$taxonomy = 'post_tag';
		} elseif ( str_starts_with( $column_name, 'taxonomy-' ) ) {
			$taxonomy = substr( $column_name, 9 );
		} else {
			$taxonomy = false;
		}

		if ( $taxonomy ) {
			$taxonomy_object = get_taxonomy( $taxonomy );
			$terms           = get_the_terms( $post->ID, $taxonomy );

			if ( is_array( $terms ) ) {
				$term_links = array();

				foreach ( $terms as $t ) {
					$posts_in_term_qv = array();

					if ( 'post' !== $post->post_type ) {
						$posts_in_term_qv['post_type'] = $post->post_type;
					}

					if ( $taxonomy_object->query_var ) {
						$posts_in_term_qv[ $taxonomy_object->query_var ] = $t->slug;
					} else {
						$posts_in_term_qv['taxonomy'] = $taxonomy;
						$posts_in_term_qv['term']     = $t->slug;
					}

					$label = esc_html( sanitize_term_field( 'name', $t->name, $t->term_id, $taxonomy, 'display' ) );

					$term_links[] = $this->get_edit_link( $posts_in_term_qv, $label );
				}

				/**
				 * Filters the links in `$taxonomy` column of edit.php.
				 *
				 * @since 5.2.0
				 *
				 * @param string[]  $term_links Array of term editing links.
				 * @param string    $taxonomy   Taxonomy name.
				 * @param WP_Term[] $terms      Array of term objects appearing in the post row.
				 */
				$term_links = apply_filters( 'post_column_taxonomy_links', $term_links, $taxonomy, $terms );

				echo implode( wp_get_list_item_separator(), $term_links );
			} else {
				echo '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">' . $taxonomy_object->labels->no_terms . '</span>';
			}
			return;
		}

		if ( is_post_type_hierarchical( $post->post_type ) ) {

			/**
			 * Fires in each custom column on the Posts list table.
			 *
			 * This hook only fires if the current post type is hierarchical,
			 * such as pages.
			 *
			 * @since 2.5.0
			 *
			 * @param string $column_name The name of the column to display.
			 * @param int    $post_id     The current post ID.
			 */
			do_action( 'manage_pages_custom_column', $column_name, $post->ID );
		} else {

			/**
			 * Fires in each custom column in the Posts list table.
			 *
			 * This hook only fires if the current post type is non-hierarchical,
			 * such as posts.
			 *
			 * @since 1.5.0
			 *
			 * @param string $column_name The name of the column to display.
			 * @param int    $post_id     The current post ID.
			 */
			do_action( 'manage_posts_custom_column', $column_name, $post->ID );
		}

		/**
		 * Fires for each custom column of a specific post type in the Posts list table.
		 *
		 * The dynamic portion of the hook name, `$post->post_type`, refers to the post type.
		 *
		 * Possible hook names include:
		 *
		 *  - `manage_post_posts_custom_column`
		 *  - `manage_page_posts_custom_column`
		 *
		 * @since 3.1.0
		 *
		 * @param string $column_name The name of the column to display.
		 * @param int    $post_id     The current post ID.
		 */
		do_action( "manage_{$post->post_type}_posts_custom_column", $column_name, $post->ID );
	}

	/**
	 * @global WP_Post $post Global post object.
	 *
	 * @param int|WP_Post $post
	 * @param int         $level
	 */
	public function single_row( $post, $level = 0 ) {
		$global_post = get_post();

		$post                = get_post( $post );
		$this->current_level = $level;

		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		$classes = 'iedit author-' . ( get_current_user_id() === (int) $post->post_author ? 'self' : 'other' );

		$lock_holder = wp_check_post_lock( $post->ID );

		if ( $lock_holder ) {
			$classes .= ' wp-locked';
		}

		if ( $post->post_parent ) {
			$count    = count( get_post_ancestors( $post->ID ) );
			$classes .= ' level-' . $count;
		} else {
			$classes .= ' level-0';
		}
		?>
		<tr id="post-<?php echo $post->ID; ?>" class="<?php echo implode( ' ', get_post_class( $classes, $post->ID ) ); ?>">
			<?php $this->single_row_columns( $post ); ?>
		</tr>
		<?php
		$GLOBALS['post'] = $global_post;
	}

	/**
	 * Gets the name of the default primary column.
	 *
	 * @since 4.3.0
	 *
	 * @return string Name of the default primary column, in this case, 'title'.
	 */
	protected function get_default_primary_column_name() {
		return 'title';
	}

	/**
	 * Generates and displays row action links.
	 *
	 * @since 4.3.0
	 * @since 5.9.0 Renamed `$post` to `$item` to match parent class for PHP 8 named parameter support.
	 *
	 * @param WP_Post $item        Post being acted upon.
	 * @param string  $column_name Current column name.
	 * @param string  $primary     Primary column name.
	 * @return string Row actions output for posts, or an empty string
	 *                if the current column is not the primary column.
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		// Restores the more descriptive, specific name for use within this method.
		$post = $item;

		$post_type_object = get_post_type_object( $post->post_type );
		$can_edit_post    = current_user_can( 'edit_post', $post->ID );
		$actions          = array();
		$title            = _draft_or_post_title();

		if ( $can_edit_post && 'trash' !== $post->post_status ) {
			$actions['edit'] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				get_edit_post_link( $post->ID ),
				/* translators: %s: Post title. */
				esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;' ), $title ) ),
				__( 'Edit' )
			);
		}

		if ( current_user_can( 'delete_post', $post->ID ) ) {
			if ( 'trash' === $post->post_status ) {
				$actions['untrash'] = sprintf(
					'<a href="%s" aria-label="%s">%s</a>',
					wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $post->ID ) ), 'untrash-post_' . $post->ID ),
					/* translators: %s: Post title. */
					esc_attr( sprintf( __( 'Restore &#8220;%s&#8221; from the Trash' ), $title ) ),
					__( 'Restore' )
				);
			} elseif ( EMPTY_TRASH_DAYS ) {
				$actions['trash'] = sprintf(
					'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
					get_delete_post_link( $post->ID ),
					/* translators: %s: Post title. */
					esc_attr( sprintf( __( 'Move &#8220;%s&#8221; to the Trash' ), $title ) ),
					_x( 'Trash', 'verb' )
				);
			}

			if ( 'trash' === $post->post_status || ! EMPTY_TRASH_DAYS ) {
				$actions['delete'] = sprintf(
					'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
					get_delete_post_link( $post->ID, '', true ),
					/* translators: %s: Post title. */
					esc_attr( sprintf( __( 'Delete &#8220;%s&#8221; permanently' ), $title ) ),
					__( 'Delete Permanently' )
				);
			}
		}

		if ( is_post_type_viewable( $post_type_object ) ) {
			if ( in_array( $post->post_status, array( 'pending', 'draft', 'future' ), true ) ) {
				if ( $can_edit_post ) {
					$preview_link    = get_preview_post_link( $post );
					$actions['view'] = sprintf(
						'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
						esc_url( $preview_link ),
						/* translators: %s: Post title. */
						esc_attr( sprintf( __( 'Preview &#8220;%s&#8221;' ), $title ) ),
						__( 'Preview' )
					);
				}
			} elseif ( 'trash' !== $post->post_status ) {
				$actions['view'] = sprintf(
					'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
					get_permalink( $post->ID ),
					/* translators: %s: Post title. */
					esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $title ) ),
					__( 'View' )
				);
			}
		}

		if ( is_post_type_hierarchical( $post->post_type ) ) {

			/**
			 * Filters the array of row action links on the Pages list table.
			 *
			 * The filter is evaluated only for hierarchical post types.
			 *
			 * @since 2.8.0
			 *
			 * @param string[] $actions An array of row action links. Defaults are
			 *                          'Edit', 'Restore', 'Trash',
			 *                          'Delete Permanently', 'Preview', and 'View'.
			 * @param WP_Post  $post    The post object.
			 */
			$actions = apply_filters( 'page_row_actions', $actions, $post );
		} else {

			/**
			 * Filters the array of row action links on the Posts list table.
			 *
			 * The filter is evaluated only for non-hierarchical post types.
			 *
			 * @since 2.8.0
			 *
			 * @param string[] $actions An array of row action links. Defaults are
			 *                          'Edit', 'Restore', 'Trash',
			 *                          'Delete Permanently', 'Preview', and 'View'.
			 * @param WP_Post  $post    The post object.
			 */
			$actions = apply_filters( 'post_row_actions', $actions, $post );
		}

		return $this->row_actions( $actions );
	}
}
