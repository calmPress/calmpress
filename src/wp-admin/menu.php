<?php
/**
 * Build Administration Menu.
 *
 * @package WordPress
 * @subpackage Administration
 */

// Don't load directly.
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

/**
 * Constructs the admin menu.
 *
 * The elements in the array are:
 *     0: Menu item name.
 *     1: Minimum level or capability required.
 *     2: The URL of the item's file.
 *     3: Page title.
 *     4: Classes.
 *     5: ID.
 *     6: Icon for top level menu.
 *
 * @global array $menu
 */

$menu[2] = array( __( 'Dashboard' ), 'read', 'index.php', '', 'menu-top menu-top-first menu-icon-dashboard', 'menu-dashboard', 'dashicons-dashboard' );

$submenu['index.php'][0] = array( __( 'Home' ), 'read', 'index.php' );

if ( is_multisite() ) {
	$submenu['index.php'][5] = array( __( 'My Sites' ), 'read', 'my-sites.php' );
}

if ( ! is_multisite() || current_user_can( 'update_core' ) ) {
	$update_data = wp_get_update_data();
}

if ( ! is_multisite() ) {
	if ( current_user_can( 'update_core' ) ) {
		$capability = 'update_core';
	} elseif ( current_user_can( 'update_plugins' ) ) {
		$capability = 'update_plugins';
	} elseif ( current_user_can( 'update_themes' ) ) {
		$capability = 'update_themes';
	} else {
		$capability = 'update_languages';
	}

	$submenu['index.php'][10] = array(
		sprintf(
			/* translators: %s: Number of pending updates. */
			__( 'Updates %s' ),
			sprintf(
				'<span class="update-plugins count-%s"><span class="update-count">%s</span></span>',
				$update_data['counts']['total'],
				number_format_i18n( $update_data['counts']['total'] )
			)
		),
		$capability,
		'update-core.php',
	);

	unset( $capability );
}

$menu[4] = array( '', 'read', 'separator1', '', 'wp-menu-separator' );

// $menu[5] = Posts.

$menu[10] = array( __( 'Media' ), 'upload_files', 'upload.php', '', 'menu-top menu-icon-media', 'menu-media', 'dashicons-admin-media' );

	$submenu['upload.php'][5]  = array( __( 'Library' ), 'upload_files', 'upload.php' );
	$submenu['upload.php'][10] = array( __( 'Add Media File' ), 'upload_files', 'media-new.php' );
	$submenu_index             = 15;

foreach ( get_taxonomies_for_attachments( 'objects' ) as $taxonomy ) {
	if ( ! $taxonomy->show_ui || ! $taxonomy->show_in_menu ) {
			continue;
	}

	$submenu['upload.php'][ $submenu_index++ ] = array( esc_attr( $taxonomy->labels->menu_name ), $taxonomy->cap->manage_terms, 'edit-tags.php?taxonomy=' . $taxonomy->name . '&amp;post_type=attachment' );
}

// $menu[20] = Pages.

// Avoid the comment count query for users who cannot edit_posts.
if ( current_user_can( 'edit_posts' ) ) {
	$awaiting_moderation      = wp_count_comments();
	$awaiting_moderation      = $awaiting_moderation->moderated;
	$awaiting_moderation_i18n = number_format_i18n( $awaiting_moderation );
	/* translators: %s: Number of comments. */
	$awaiting_moderation_text = sprintf( _n( '%s Comment in moderation', '%s Comments in moderation', $awaiting_moderation ), $awaiting_moderation_i18n );

	$menu[25] = array(
		/* translators: %s: Number of comments. */
		sprintf( __( 'Comments %s' ), '<span class="awaiting-mod count-' . absint( $awaiting_moderation ) . '"><span class="pending-count" aria-hidden="true">' . $awaiting_moderation_i18n . '</span><span class="comments-in-moderation-text screen-reader-text">' . $awaiting_moderation_text . '</span></span>' ),
		'edit_posts',
		'edit-comments.php',
		'',
		'menu-top menu-icon-comments',
		'menu-comments',
		'dashicons-admin-comments',
	);

	unset( $awaiting_moderation );
}

$submenu['edit-comments.php'][0] = array( __( 'All Comments' ), 'edit_posts', 'edit-comments.php' );

$_wp_last_object_menu = 25; // The index of the last top-level menu in the object menu group.

$post_types = (array) get_post_types(
	array(
		'show_ui'      => true,
		'_builtin'     => false,
		'show_in_menu' => true,
	)
);
$builtin    = array( 'post', 'page' );

foreach ( array_merge( $builtin, $post_types ) as $post_type ) {
	$post_type_obj = get_post_type_object( $post_type );

	// Check if it should be a submenu.
	if ( true !== $post_type_obj->show_in_menu ) {
		continue;
	}

	$post_type_menu_position = is_int( $post_type_obj->menu_position )
		? $post_type_obj->menu_position
		: ++$_wp_last_object_menu; // If we're to use $_wp_last_object_menu, increment it first.
	$post_type_for_id        = sanitize_html_class( $post_type );

	$menu_icon = 'dashicons-admin-post';
	if ( is_string( $post_type_obj->menu_icon ) ) {
		// Special handling for an empty div.wp-menu-image, data:image/svg+xml, and Dashicons.
		if ( 'none' === $post_type_obj->menu_icon || 'div' === $post_type_obj->menu_icon
			|| str_starts_with( $post_type_obj->menu_icon, 'data:image/svg+xml;base64,' )
			|| str_starts_with( $post_type_obj->menu_icon, 'dashicons-' )
		) {
			$menu_icon = $post_type_obj->menu_icon;
		} else {
			$menu_icon = esc_url( $post_type_obj->menu_icon );
		}
	} elseif ( in_array( $post_type, $builtin, true ) ) {
		$menu_icon = 'dashicons-admin-' . $post_type;
	}

	$menu_class = 'menu-top menu-icon-' . $post_type_for_id;
	// 'post' special case.
	if ( 'post' === $post_type ) {
		$menu_class    .= ' open-if-no-js';
		$post_type_file = 'edit.php';
		$post_new_file  = 'post-new.php';
		$edit_tags_file = 'edit-tags.php?taxonomy=%s';
	} else {
		$post_type_file = "edit.php?post_type=$post_type";
		$post_new_file  = "post-new.php?post_type=$post_type";
		$edit_tags_file = "edit-tags.php?taxonomy=%s&amp;post_type=$post_type";
	}

	if ( in_array( $post_type, $builtin, true ) ) {
		$post_type_menu_id = 'menu-' . $post_type_for_id . 's';
	} else {
		$post_type_menu_id = 'menu-posts-' . $post_type_for_id;
	}

	/*
	 * If $post_type_menu_position is already populated or will be populated
	 * by a hard-coded value below, increment the position.
	 */
	$core_menu_positions = array( 59, 60, 65, 70, 75, 80, 85, 99 );
	while ( isset( $menu[ $post_type_menu_position ] ) || in_array( $post_type_menu_position, $core_menu_positions, true ) ) {
		++$post_type_menu_position;
	}

	$menu[ $post_type_menu_position ] = array( esc_attr( $post_type_obj->labels->menu_name ), $post_type_obj->cap->edit_posts, $post_type_file, '', $menu_class, $post_type_menu_id, $menu_icon );
	$submenu[ $post_type_file ][5]    = array( $post_type_obj->labels->all_items, $post_type_obj->cap->edit_posts, $post_type_file );
	$submenu[ $post_type_file ][10]   = array( $post_type_obj->labels->add_new_item, $post_type_obj->cap->create_posts, $post_new_file );

	$submenu_index = 15;

	foreach ( get_taxonomies( array(), 'objects' ) as $taxonomy ) {
		if ( ! $taxonomy->show_ui || ! $taxonomy->show_in_menu || ! in_array( $post_type, (array) $taxonomy->object_type, true ) ) {
			continue;
		}

		$submenu[ $post_type_file ][ $submenu_index++ ] = array( esc_attr( $taxonomy->labels->menu_name ), $taxonomy->cap->manage_terms, sprintf( $edit_tags_file, $taxonomy->name ) );
	}
}

unset( $post_type, $post_type_obj, $post_type_for_id, $post_type_menu_position, $menu_icon, $submenu_index, $taxonomy, $post_new_file );

$menu[59] = array( '', 'read', 'separator2', '', 'wp-menu-separator' );

$appearance_capability = current_user_can( 'switch_themes' ) ? 'switch_themes' : 'edit_theme_options';

$menu[60] = array( __( 'Appearance' ), $appearance_capability, 'themes.php', '', 'menu-top menu-icon-appearance', 'menu-appearance', 'dashicons-admin-appearance' );

$count = '';
if ( ! is_multisite() && current_user_can( 'update_themes' ) ) {
	if ( ! isset( $update_data ) ) {
		$update_data = wp_get_update_data();
	}

	$count = sprintf(
		'<span class="update-plugins count-%s"><span class="theme-count">%s</span></span>',
		$update_data['counts']['themes'],
		number_format_i18n( $update_data['counts']['themes'] )
	);
}

/* translators: %s: Number of available theme updates. */
$submenu['themes.php'][5] = array( sprintf( __( 'Themes %s' ), $count ), $appearance_capability, 'themes.php' );

$customize_url = add_query_arg( 'return', urlencode( remove_query_arg( wp_removable_query_args(), wp_unslash( $_SERVER['REQUEST_URI'] ) ) ), 'customize.php' );

// Hide Customize link on block themes unless a plugin or theme
// is using 'customize_register' to add a setting.
if ( has_action( 'customize_register' ) ) {
	$submenu['themes.php'][7] = array( __( 'Customize' ), 'customize', esc_url( $customize_url ), '', 'hide-if-no-customize' );
}

if ( current_theme_supports( 'menus' ) || current_theme_supports( 'widgets' ) ) {
	$submenu['themes.php'][10] = array( __( 'Menus' ), 'edit_theme_options', 'nav-menus.php' );
}

if ( current_theme_supports( 'custom-header' ) && current_user_can( 'customize' ) ) {
	$customize_header_url      = add_query_arg( array( 'autofocus' => array( 'control' => 'header_image' ) ), $customize_url );
	$submenu['themes.php'][15] = array( _x( 'Header', 'custom image header' ), $appearance_capability, esc_url( $customize_header_url ), '', 'hide-if-no-customize' );
}

if ( current_theme_supports( 'custom-background' ) && current_user_can( 'customize' ) ) {
	$customize_background_url  = add_query_arg( array( 'autofocus' => array( 'control' => 'background_image' ) ), $customize_url );
	$submenu['themes.php'][20] = array( _x( 'Background', 'custom background' ), $appearance_capability, esc_url( $customize_background_url ), '', 'hide-if-no-customize' );
}

unset( $customize_url, $appearance_capability );

$count = '';
if ( ! is_multisite() && current_user_can( 'update_plugins' ) ) {
	if ( ! isset( $update_data ) ) {
		$update_data = wp_get_update_data();
	}
	$count = sprintf(
		'<span class="update-plugins count-%s"><span class="plugin-count">%s</span></span>',
		$update_data['counts']['plugins'],
		number_format_i18n( $update_data['counts']['plugins'] )
	);
}

/* translators: %s: Number of available plugin updates. */
$menu[65] = array( sprintf( __( 'Plugins %s' ), $count ), 'activate_plugins', 'plugins.php', '', 'menu-top menu-icon-plugins', 'menu-plugins', 'dashicons-admin-plugins' );

$submenu['plugins.php'][5] = array( __( 'Installed Plugins' ), 'activate_plugins', 'plugins.php' );

if ( ! is_multisite() ) {
	$submenu['plugins.php'][10] = array( _x( 'Add Plugin' ), 'install_plugins', 'plugin-install.php' );
}

unset( $update_data );

if ( current_user_can( 'list_users' ) ) {
	$menu[70] = array( __( 'Users' ), 'list_users', 'users.php', '', 'menu-top menu-icon-users', 'menu-users', 'dashicons-admin-users' );

	$submenu['users.php'][5]             = array( __( 'All Users' ), 'list_users', 'users.php' );
	if ( current_user_can( 'create_users' ) ) {
		$submenu['users.php'][10] = array( __( 'Add User' ), 'create_users', 'user-new.php' );
	} elseif ( is_multisite() ) {
		$submenu['users.php'][10] = array( __( 'Add User' ), 'promote_users', 'user-new.php' );
	}
}

// Menu for when admin is editing a user.
if ( array_key_exists( 'user_id', $_GET ) && current_user_can( 'edit_user', $_GET['user_id'] ) ) {
	$user_id  = intval( $_GET['user_id'] );
	$display_name = get_userdata( $user_id)->display_name;
	/* translators: %s is the display name of the user being edited */
	$top_menu_label = sprintf( 'User: %s', $display_name );
	$menu[71] = array( $top_menu_label, 'edited-user', 'user-edit.php', '', 'menu-top menu-icon-users', 'menu-users', 'dashicons-admin-users' );
		$submenu['edited-user'][5] = array( __( 'Account' ), 'edited-user', 'user-edit.php?user_id=' . $user_id );
}

$menu[75]                     = array( __( 'Tools' ), 'tools_menu', 'tools.php', '', 'menu-top menu-icon-tools', 'menu-tools', 'dashicons-admin-tools' );
	$submenu['tools.php'][10] = array( __( 'Site Health' ), 'manage_server', 'site-health.php' );
	$submenu['tools.php'][20] = array( __( 'Maintenance Mode' ), 'maintenance_mode', 'maintenance-mode.php' );
	if ( ! is_multisite() ) {
		$submenu['tools.php'][21] = array( __( 'Opcode Cache' ), 'manage_server', 'opcache.php' );
	}
	if ( ! is_multisite() && \calmpress\apcu\APCu::APCu_is_avaialable() ) {
		$submenu['tools.php'][22] = array( __( 'APCu Cache' ), 'manage_server', 'apcu.php' );
	}
	$submenu['tools.php'][23] = array( __( 'Object Cache' ), 'manage_server', 'object-cache.php' );
	$submenu['tools.php'][25] = array( __( 'Export Personal Data' ), 'export_others_personal_data', 'export-personal-data.php' );
	$submenu['tools.php'][30] = array( __( 'Erase Personal Data' ), 'erase_others_personal_data', 'erase-personal-data.php' );
if ( is_multisite() && ! is_main_site() && '1' !== get_site()->deleted ) {
	$submenu['tools.php'][35] = array( __( 'Delete Site' ), 'delete_site', 'ms-delete-site.php' );
}
if ( ! is_multisite() && defined( 'WP_ALLOW_MULTISITE' ) && WP_ALLOW_MULTISITE ) {
	$submenu['tools.php'][50] = array( __( 'Network Setup' ), 'setup_network', 'network.php' );
}
if ( ! is_multisite() ) {
	if ( \calmpress\calmpress\Safe_Mode::current_user_in_safe_mode() ) {
		$submenu['tools.php'][55] = array( __( 'Safe Mode' ), 'safe_mode', 'safe-mode.php' );
	}
}
if ( is_super_admin() ) { 
	$submenu['tools.php'][56] = array( __( 'Switch user' ), 'delete_users', 'switch-user.php' );
	$submenu['tools.php'][57] = array( __( 'Test Email Delivery' ), 'delete_users', 'test-email-delivery.php' );
}

$menu[80]                               = array( __( 'Settings' ), 'manage_options', 'options-general.php', '', 'menu-top menu-icon-settings', 'menu-settings', 'dashicons-admin-settings' );
	$submenu['options-general.php'][10] = array( _x( 'General', 'settings screen' ), 'manage_options', 'options-general.php' );
	$submenu['options-general.php'][15] = array( __( 'Writing' ), 'manage_options', 'options-writing.php' );
	$submenu['options-general.php'][20] = array( __( 'Reading' ), 'manage_options', 'options-reading.php' );
	$submenu['options-general.php'][25] = array( __( 'Discussion' ), 'manage_options', 'options-discussion.php' );
	$submenu['options-general.php'][30] = array( __( 'Media' ), 'manage_options', 'options-media.php' );
	$submenu['options-general.php'][40] = array( __( 'Permalinks' ), 'manage_options', 'options-permalink.php' );
	if ( is_super_admin() ) {
		$submenu['options-general.php'][41] = array( __( 'wp-config.php' ), 'manage_options', 'options-wp-config.php' );
	}

	if ( is_apache() && is_super_admin() ) {
		$submenu['options-general.php'][42] = array( __( '.htaccess' ), 'manage_options', 'options-htaccess.php' );
	}
	if ( is_super_admin() ) {
		$submenu['options-general.php'][43] = array( __( 'robots.txt' ), 'manage_options', 'options-robots-txt.php' );
		$submenu['options-general.php'][44] = array( __( 'Email Delivery'),  'manage_options', 'options-email.php' );
	}

if ( ! is_multisite() ) {
$menu[85]                       = array( __( 'Backups' ), 'backup', 'backups.php', '', 'menu-top menu-icon-settings', 'menu-settings', 'dashicons-database' );
	$submenu['backups.php'][10] = array( __( 'Backups' ), 'backup', 'backups.php' );
	$submenu['backups.php'][15] = array( __( 'Create New' ), 'backup', 'backup-new.php' );
}

$_wp_last_utility_menu = 90; // The index of the last top-level menu in the utility menu group.

// Try to keep the profile menu as the last admin menu.
$menu[900] = array( __( 'My Profile' ), 'read', 'my-profile', '', 'menu-top menu-icon-users', 'menu-users', 'dashicons-admin-users' );
	$submenu['my-profile'][5] = array( __( 'Account' ), 'read', 'user-edit.php' );
	$submenu['my-profile'][10] = array( __( 'Device Login' ), 'read', 'webauthn.php' );

$menu[999] = array( '', 'read', 'separator-last', '', 'wp-menu-separator' );

// Back-compat for old top-levels.
$_wp_real_parent_file['post.php']       = 'edit.php';
$_wp_real_parent_file['post-new.php']   = 'edit.php';
$_wp_real_parent_file['edit-pages.php'] = 'edit.php?post_type=page';
$_wp_real_parent_file['page-new.php']   = 'edit.php?post_type=page';
$_wp_real_parent_file['wpmu-admin.php'] = 'tools.php';
$_wp_real_parent_file['ms-admin.php']   = 'tools.php';

// Ensure backward compatibility.
$compat = array(
	'index'           => 'dashboard',
	'edit'            => 'posts',
	'post'            => 'posts',
	'upload'          => 'media',
	'edit-pages'      => 'pages',
	'page'            => 'pages',
	'edit-comments'   => 'comments',
	'options-general' => 'settings',
	'themes'          => 'appearance',
);

require_once ABSPATH . 'wp-admin/includes/menu.php';
