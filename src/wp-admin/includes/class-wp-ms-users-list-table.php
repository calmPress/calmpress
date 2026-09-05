<?php
/**
 * List Table API: WP_MS_Users_List_Table class
 *
 * @package WordPress
 * @subpackage Administration
 * @since 3.1.0
 */

/**
 * Core class used to implement displaying users in a list table for the network admin.
 *
 * @since 3.1.0
 *
 * @see WP_List_Table
 */
class WP_MS_Users_List_Table extends WP_List_Table {
	/**
	 * @return bool
	 */
	public function ajax_user_can() {
		return current_user_can( 'manage_network_users' );
	}

	/**
	 * @global string $mode       List table view mode.
	 * @global string $usersearch
	 * @global string $role
	 */
	public function prepare_items() {
		global $mode, $usersearch, $role;

		if ( ! empty( $_REQUEST['mode'] ) ) {
			$mode = 'excerpt' === $_REQUEST['mode'] ? 'excerpt' : 'list';
			set_user_setting( 'network_users_list_mode', $mode );
		} else {
			$mode = get_user_setting( 'network_users_list_mode', 'list' );
		}

		$usersearch = isset( $_REQUEST['s'] ) ? wp_unslash( trim( $_REQUEST['s'] ) ) : '';

		$users_per_page = $this->get_items_per_page( 'users_network_per_page' );

		$role = isset( $_REQUEST['role'] ) ? $_REQUEST['role'] : '';

		$paged = $this->get_pagenum();

		if ( 'pending' === $role ) {
			$users       = $this->users_with_pending_invites();
			$total_items = count( $users );
			$this->items = array_slice( $users, ( $paged - 1 ) * $users_per_page, $users_per_page );

			$this->set_pagination_args(
				array(
					'total_items' => $total_items,
					'per_page'    => $users_per_page,
				)
			);
			return;
		}

		// Include users associated with the current network, except for users whose
		// invitations to this network are still pending.
		$pending_user_ids = wp_list_pluck( $this->users_with_pending_invites(), 'ID' );
		$network_user_ids = array_values( array_diff( get_network()->user_ids(), $pending_user_ids ) );

		// WP_User_Query treats an empty include array as unrestricted; user ID 0
		// ensures that an empty network returns no users.
		$network_user_ids = [] === $network_user_ids ? [ 0 ] : $network_user_ids;

		$args = array(
			'number'     => $users_per_page,
			'offset'     => ( $paged - 1 ) * $users_per_page,
			'search'     => $usersearch,
			'blog_id'    => 0,
			'fields'     => 'all_with_meta',
			'include'    => $network_user_ids,
		);

		if ( wp_is_large_network( 'users' ) ) {
			$args['search'] = ltrim( $args['search'], '*' );
		} elseif ( '' !== $args['search'] ) {
			$args['search'] = trim( $args['search'], '*' );
			$args['search'] = '*' . $args['search'] . '*';
		}

		if ( 'super' === $role ) {
			$args['login__in'] = get_super_admins();
		}

		/*
		 * If the network is large and a search is not being performed,
		 * show only the latest users with no paging in order to avoid
		 * expensive count queries.
		 */
		if ( ! $usersearch && wp_is_large_network( 'users' ) ) {
			if ( ! isset( $_REQUEST['orderby'] ) ) {
				$_GET['orderby']     = 'id';
				$_REQUEST['orderby'] = 'id';
			}
			if ( ! isset( $_REQUEST['order'] ) ) {
				$_GET['order']     = 'DESC';
				$_REQUEST['order'] = 'DESC';
			}
			$args['count_total'] = false;
		}

		if ( isset( $_REQUEST['orderby'] ) ) {
			$args['orderby'] = $_REQUEST['orderby'];
		}

		if ( isset( $_REQUEST['order'] ) ) {
			$args['order'] = $_REQUEST['order'];
		}

		/** This filter is documented in wp-admin/includes/class-wp-users-list-table.php */
		$args = apply_filters( 'users_list_table_query_args', $args );

		// Query the user IDs for this page.
		$wp_user_search = new WP_User_Query( $args );

		$this->items = $wp_user_search->get_results();

		$this->set_pagination_args(
			array(
				'total_items' => $wp_user_search->get_total(),
				'per_page'    => $users_per_page,
			)
		);
	}

	/**
	 * @return array
	 */
	protected function get_bulk_actions() {
		global $role;

		if ( 'pending' === $role ) {
			return array();
		}

		$actions = array();
		if ( current_user_can( 'delete_users' ) ) {
			$actions['delete'] = __( 'Delete' );
		}

		return $actions;
	}

	/**
	 */
	public function no_items() {
		global $role;

		if ( 'pending' === $role ) {
			_e( 'No pending invitations found.' );
			return;
		}

		_e( 'No users found.' );
	}

	/**
	 * @global string $role
	 * @return array
	 */
	protected function get_views() {
		global $role;

		$pending_users = $this->users_with_pending_invites();
		$total_users   = count( get_network()->user_ids() );
		$super_admins  = get_super_admins();
		$total_admins  = count( $super_admins );
		$total_pending = count( $pending_users );

		$role_links        = array();
		$role_links['all'] = array(
			'url'     => network_admin_url( 'users.php' ),
			'label'   => sprintf(
				/* translators: Number of users. */
				_nx(
					'All <span class="count">(%s)</span>',
					'All <span class="count">(%s)</span>',
					$total_users,
					'users'
				),
				number_format_i18n( $total_users )
			),
			'current' => ! in_array( $role, array( 'super', 'pending' ), true ),
		);

		$role_links['super'] = array(
			'url'     => network_admin_url( 'users.php?role=super' ),
			'label'   => sprintf(
				/* translators: Number of users. */
				_n(
					'Super Admin <span class="count">(%s)</span>',
					'Super Admins <span class="count">(%s)</span>',
					$total_admins
				),
				number_format_i18n( $total_admins )
			),
			'current' => 'super' === $role,
		);

		$role_links['pending'] = array(
			'url'     => network_admin_url( 'users.php?role=pending' ),
			'label'   => sprintf(
				/* translators: Number of pending user invitations. */
				_n(
					'Pending Invitation <span class="count">(%s)</span>',
					'Pending Invitations <span class="count">(%s)</span>',
					$total_pending
				),
				number_format_i18n( $total_pending )
			),
			'current' => 'pending' === $role,
		);

		return $this->get_views_links( $role_links );
	}

	/**
	 * @global string $mode List table view mode.
	 *
	 * @param string $which
	 */
	protected function pagination( $which ) {
		global $mode;

		parent::pagination( $which );

		if ( 'top' === $which ) {
			$this->view_switcher( $mode );
		}
	}

	/**
	 * @return string[] Array of column titles keyed by their column name.
	 */
	public function get_columns() {
		global $role;

		if ( 'pending' === $role ) {
			return array(
				'email'      => __( 'Email' ),
				'registered' => __( 'Invited' ),
			);
		}

		$users_columns = array(
			'cb'         => '<input type="checkbox" />',
			'name'       => __( 'Name' ),
			'email'      => __( 'Email' ),
			'registered' => _x( 'Registered', 'user' ),
			'blogs'      => __( 'Sites' ),
		);
		/**
		 * Filters the columns displayed in the Network Admin Users list table.
		 *
		 * @since MU (3.0.0)
		 *
		 * @param string[] $users_columns An array of user columns. Default 'cb',
		 *                             'name', 'email', 'registered', 'blogs'.
		 */
		return apply_filters( 'wpmu_users_columns', $users_columns );
	}

	/**
	 * @return array
	 */
	protected function get_sortable_columns() {
		global $role;

		if ( 'pending' === $role ) {
			return array();
		}

		return array(
			'name'       => array( 'name', false, __( 'Name' ), __( 'Table ordered by Name.' ) ),
			'email'      => array( 'email', false, __( 'E-mail' ), __( 'Table ordered by E-mail.' ) ),
			'registered' => array( 'id', false, _x( 'Registered', 'user' ), __( 'Table ordered by User Registered Date.' ) ),
		);
	}

	/**
	 * Handles the checkbox column output.
	 *
	 * @since 4.3.0
	 * @since 5.9.0 Renamed `$user` to `$item` to match parent class for PHP 8 named parameter support.
	 *
	 * @param WP_User $item The current WP_User object.
	 */
	public function column_cb( $item ) {
		// Restores the more descriptive, specific name for use within this method.
		$user = $item;

		if ( is_super_admin( $user->ID ) ) {
			return;
		}
		?>
		<input type="checkbox" id="blog_<?php echo $user->ID; ?>" name="allusers[]" value="<?php echo esc_attr( $user->ID ); ?>" />
		<label for="blog_<?php echo $user->ID; ?>">
			<span class="screen-reader-text">
			<?php
			/* translators: Hidden accessibility text. %s: User email. */
			printf( __( 'Select %s' ), $user->user_email );
			?>
			</span>
		</label>
		<?php
	}

	/**
	 * Handles the ID column output.
	 *
	 * @since 4.4.0
	 *
	 * @param WP_User $user The current WP_User object.
	 */
	public function column_id( $user ) {
		echo $user->ID;
	}

	/**
	 * Handles the name column output.
	 *
	 * @since 4.3.0
	 *
	 * @param WP_User $user The current WP_User object.
	 */
	public function column_name( $user ) {
		$name = '';
		if ( $user->display_name ) {
			$name = esc_html( $user->display_name );
		} else {
			$name = '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">' . _x( 'Anonymous', 'name' ) . '</span>';
		}

		$super_admins  = get_super_admins();
		$avatar        = get_avatar( $user, 32 );
		$extended_info = array();

		if ( in_array( $user->user_login, $super_admins, true ) ) {
			$extended_info[] = esc_html__( 'Super Admin' );
		}

		if ( $user->is_system_notification_recipient( get_network() ) ) {
			$extended_info[] = esc_html__( 'System notifications' );
		}

		echo $avatar;

		if ( current_user_can( 'edit_user', $user->ID ) ) {
			$edit_link = esc_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), get_edit_user_link( $user->ID ) ) );
			$edit      = "<a href=\"{$edit_link}\">{$name}</a>";
		} else {
			$edit = $name;
		}

		?>
		<strong>
			<?php
			echo $edit;

			if ( $extended_info ) {
				echo ' &mdash; ' . implode( ', ', $extended_info );
			}
			?>
		</strong>
		<?php
	}

	/**
	 * Handles the email column output.
	 *
	 * @since 4.3.0
	 *
	 * @param WP_User $user The current WP_User object.
	 */
	public function column_email( $user ) {
		echo '<a href="' . esc_url( "mailto:$user->user_email" ) . '">' . esc_html( $user->user_email ) . '</a>';
	}

	/**
	 * Handles the registered date column output.
	 *
	 * @since 4.3.0
	 *
	 * @global string $mode List table view mode.
	 *
	 * @param WP_User $user The current WP_User object.
	 */
	public function column_registered( $user ) {
		global $mode;

		$registered = isset( $user->registered ) ? $user->registered : $user->user_registered;

		if ( 'list' === $mode ) {
			$date = __( 'Y/m/d' );
		} else {
			$date = __( 'Y/m/d g:i:s a' );
		}
		echo mysql2date( $date, $registered );
	}

	/**
	 * @since 4.3.0
	 *
	 * @param WP_User $user
	 * @param string  $classes
	 * @param string  $data
	 * @param string  $primary
	 */
	protected function _column_blogs( $user, $classes, $data, $primary ) {
		echo '<td class="', $classes, ' has-row-actions" ', $data, '>';
		echo $this->column_blogs( $user );
		echo $this->handle_row_actions( $user, 'blogs', $primary );
		echo '</td>';
	}

	/**
	 * Handles the sites column output.
	 *
	 * @since 4.3.0
	 *
	 * @param WP_User $user The current WP_User object.
	 */
	public function column_blogs( $user ) {
		$blogs = get_blogs_of_user( $user->ID, true );
		if ( ! is_array( $blogs ) ) {
			return;
		}

		foreach ( $blogs as $site ) {
			if ( ! can_edit_network( $site->site_id ) ) {
				continue;
			}

			$path         = ( '/' === $site->path ) ? '' : $site->path;
			$site_classes = array( 'site-' . $site->site_id );

			/**
			 * Filters the span class for a site listing on the multisite user list table.
			 *
			 * @since 5.2.0
			 *
			 * @param string[] $site_classes Array of class names used within the span tag.
			 *                               Default "site-#" with the site's network ID.
			 * @param int      $site_id      Site ID.
			 * @param int      $network_id   Network ID.
			 * @param WP_User  $user         WP_User object.
			 */
			$site_classes = apply_filters( 'ms_user_list_site_class', $site_classes, $site->userblog_id, $site->site_id, $user );

			if ( is_array( $site_classes ) && ! empty( $site_classes ) ) {
				$site_classes = array_map( 'sanitize_html_class', array_unique( $site_classes ) );
				echo '<span class="' . esc_attr( implode( ' ', $site_classes ) ) . '">';
			} else {
				echo '<span>';
			}

			echo '<a href="' . esc_url( network_admin_url( 'site-info.php?id=' . $site->userblog_id ) ) . '">' . str_replace( '.' . get_network()->domain, '', $site->domain . $path ) . '</a>';
			echo ' <small class="row-actions">';

			$actions         = array();
			$actions['edit'] = '<a href="' . esc_url( network_admin_url( 'site-info.php?id=' . $site->userblog_id ) ) . '">' . __( 'Edit' ) . '</a>';

			$actions['view'] = '<a href="' . esc_url( get_home_url( $site->userblog_id ) ) . '">' . __( 'View' ) . '</a>';

			/**
			 * Filters the action links displayed next the sites a user belongs to
			 * in the Network Admin Users list table.
			 *
			 * @since 3.1.0
			 *
			 * @param string[] $actions     An array of action links to be displayed. Default 'Edit', 'View'.
			 * @param int      $userblog_id The site ID.
			 */
			$actions = apply_filters( 'ms_user_list_site_actions', $actions, $site->userblog_id );

			$action_count = count( $actions );

			$i = 0;

			foreach ( $actions as $action => $link ) {
				++$i;

				$separator = ( $i < $action_count ) ? ' | ' : '';

				echo "<span class='$action'>{$link}{$separator}</span>";
			}

			echo '</small></span><br />';
		}
	}

	/**
	 * Handles the default column output.
	 *
	 * @since 4.3.0
	 * @since 5.9.0 Renamed `$user` to `$item` to match parent class for PHP 8 named parameter support.
	 *
	 * @param WP_User $item        The current WP_User object.
	 * @param string  $column_name The current column name.
	 */
	public function column_default( $item, $column_name ) {
		// Restores the more descriptive, specific name for use within this method.
		$user = $item;

		/** This filter is documented in wp-admin/includes/class-wp-users-list-table.php */
		$column_output = apply_filters( 'manage_users_custom_column', '', $column_name, $user->ID );

		/**
		 * Filters the display output of custom columns in the Network Users list table.
		 *
		 * @since 6.8.0
		 *
		 * @param string $output      Custom column output. Default empty.
		 * @param string $column_name Name of the custom column.
		 * @param int    $user_id     ID of the currently-listed user.
		 */
		echo apply_filters( 'manage_users-network_custom_column', $column_output, $column_name, $user->ID );
	}

	/**
	 * Generates the list table rows.
	 *
	 * @since 3.1.0
	 */
	public function display_rows() {
		foreach ( $this->items as $user ) {
			$class = '';

			?>
			<tr class="<?php echo trim( $class ); ?>">
				<?php $this->single_row_columns( $user ); ?>
			</tr>
			<?php
		}
	}

	/**
	 * Gets the name of the default primary column.
	 *
	 * @since 4.3.0
	 *
	 * @return string Name of the default primary column, in this case, 'name'.
	 */
	protected function get_default_primary_column_name() {
		global $role;

		if ( 'pending' === $role ) {
			return 'email';
		}

		return 'name';
	}

	/**
	 * Generates and displays row action links.
	 *
	 * @since 4.3.0
	 * @since 5.9.0 Renamed `$user` to `$item` to match parent class for PHP 8 named parameter support.
	 *
	 * @param WP_User $item        User being acted upon.
	 * @param string  $column_name Current column name.
	 * @param string  $primary     Primary column name.
	 * @return string Row actions output for users in Multisite, or an empty string
	 *                if the current column is not the primary column.
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		// Restores the more descriptive, specific name for use within this method.
		$user = $item;

		if ( $user->has_network_invite( get_network() ) ) {
			$resend_url = wp_nonce_url(
				network_admin_url( 'users.php?role=pending&action=resend-invitation&user_id=' . $user->ID ),
				'resend-network-invitation-' . $user->ID
			);
			$cancel_url = wp_nonce_url(
				network_admin_url( 'users.php?role=pending&action=cancel-invitation&user_id=' . $user->ID ),
				'cancel-network-invitation-' . $user->ID
			);

			return $this->row_actions(
				array(
					'resend' => '<a href="' . esc_url( $resend_url ) . '">' . __( 'Resend' ) . '</a>',
					'cancel' => '<a class="delete" href="' . esc_url( $cancel_url ) . '">' . __( 'Cancel' ) . '</a>',
				)
			);
		}

		$super_admins = get_super_admins();
		$actions      = array();

		if ( current_user_can( 'edit_user', $user->ID ) ) {
			$edit_link       = esc_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), get_edit_user_link( $user->ID ) ) );
			$actions['edit'] = '<a href="' . $edit_link . '">' . __( 'Edit' ) . '</a>';
		}

		if ( current_user_can( 'delete_user', $user->ID ) && ! in_array( $user->user_login, $super_admins, true ) ) {
			$actions['delete'] = '<a href="' . esc_url( network_admin_url( add_query_arg( '_wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), wp_nonce_url( 'users.php', 'deleteuser' ) . '&amp;action=deleteuser&amp;id=' . $user->ID ) ) ) . '" class="delete">' . __( 'Delete' ) . '</a>';
		}

		/**
		 * Filters the action links displayed under each user in the Network Admin Users list table.
		 *
		 * @since 3.2.0
		 *
		 * @param string[] $actions An array of action links to be displayed. Default 'Edit', 'Delete'.
		 * @param WP_User  $user    WP_User object.
		 */
		$actions = apply_filters( 'ms_user_row_actions', $actions, $user );

		return $this->row_actions( $actions );
	}

	/**
	 * The users with pending invitations to the current network.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return WP_User[] Users with pending invitations.
	 */
	public function users_with_pending_invites(): array {
		return get_network()->users_with_pending_invites();
	}
}
