<?php
/**
 * Deprecated admin functions from past WordPress versions. You shouldn't use these
 * functions and look for the alternatives instead. The functions will be removed
 * in a later version.
 *
 * @package WordPress
 * @subpackage Deprecated
 */

/*
 * Deprecated functions come here to die.
 */

/**
 * Add a top-level menu page in the 'objects' section.
 *
 * This function takes a capability which will be used to determine whether
 * or not a page is included in the menu.
 *
 * The function which is hooked in to handle the output of the page must check
 * that the user has the required capability as well.
 *
 * @since 2.7.0
 *
 * @deprecated 4.5.0 Use add_menu_page()
 * @see add_menu_page()
 * @global int $_wp_last_object_menu
 *
 * @param string   $page_title The text to be displayed in the title tags of the page when the menu is selected.
 * @param string   $menu_title The text to be used for the menu.
 * @param string   $capability The capability required for this menu to be displayed to the user.
 * @param string   $menu_slug  The slug name to refer to this menu by (should be unique for this menu).
 * @param callable $function   The function to be called to output the content for this page.
 * @param string   $icon_url   The url to the icon to be used for this menu.
 * @return string The resulting page's hook_suffix.
 */
function add_object_page( $page_title, $menu_title, $capability, $menu_slug, $function = '', $icon_url = '') {
	_deprecated_function( __FUNCTION__, '4.5.0', 'add_menu_page()' );

	global $_wp_last_object_menu;

	$_wp_last_object_menu++;

	return add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $_wp_last_object_menu);
}

/**
 * Add a top-level menu page in the 'utility' section.
 *
 * This function takes a capability which will be used to determine whether
 * or not a page is included in the menu.
 *
 * The function which is hooked in to handle the output of the page must check
 * that the user has the required capability as well.
 *
 * @since 2.7.0
 *
 * @deprecated 4.5.0 Use add_menu_page()
 * @see add_menu_page()
 * @global int $_wp_last_utility_menu
 *
 * @param string   $page_title The text to be displayed in the title tags of the page when the menu is selected.
 * @param string   $menu_title The text to be used for the menu.
 * @param string   $capability The capability required for this menu to be displayed to the user.
 * @param string   $menu_slug  The slug name to refer to this menu by (should be unique for this menu).
 * @param callable $function   The function to be called to output the content for this page.
 * @param string   $icon_url   The url to the icon to be used for this menu.
 * @return string The resulting page's hook_suffix.
 */
function add_utility_page( $page_title, $menu_title, $capability, $menu_slug, $function = '', $icon_url = '') {
	_deprecated_function( __FUNCTION__, '4.5.0', 'add_menu_page()' );

	global $_wp_last_utility_menu;

	$_wp_last_utility_menu++;

	return add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $_wp_last_utility_menu);
}

/**
 * Disables autocomplete on the 'post' form (Add/Edit Post screens) for WebKit browsers,
 * as they disregard the autocomplete setting on the editor textarea. That can break the editor
 * when the user navigates to it with the browser's Back button. See #28037
 *
 * Replaced with wp_page_reload_on_back_button_js() that also fixes this problem.
 *
 * @since 4.0.0
 * @deprecated 4.6.0
 *
 * @link https://core.trac.wordpress.org/ticket/35852
 *
 * @global bool $is_safari
 * @global bool $is_chrome
 */
function post_form_autocomplete_off() {
	global $is_safari, $is_chrome;

	_deprecated_function( __FUNCTION__, '4.6.0' );

	if ( $is_safari || $is_chrome ) {
		echo ' autocomplete="off"';
	}
}

/**
 * Display JavaScript on the page.
 *
 * @since 3.5.0
 * @deprecated 4.9.0
 */
function options_permalink_add_js() {
	?>
	<script type="text/javascript">
		jQuery(document).ready(function() {
			jQuery('.permalink-structure input:radio').change(function() {
				if ( 'custom' == this.value )
					return;
				jQuery('#permalink_structure').val( this.value );
			});
			jQuery( '#permalink_structure' ).on( 'click input', function() {
				jQuery( '#custom_selection' ).prop( 'checked', true );
			});
		});
	</script>
	<?php
}

/**
 * Previous class for list table for privacy data export requests.
 *
 * @since 4.9.6
 * @deprecated 5.3.0
 */
class WP_Privacy_Data_Export_Requests_Table extends WP_Privacy_Data_Export_Requests_List_Table {
	function __construct( $args ) {
		_deprecated_function( __CLASS__, '5.3.0', 'WP_Privacy_Data_Export_Requests_List_Table' );

		if ( ! isset( $args['screen'] ) || $args['screen'] === 'export_personal_data' ) {
			$args['screen'] = 'export-personal-data';
		}

		parent::__construct( $args );	
	}
}

/**
 * Previous class for list table for privacy data erasure requests.
 *
 * @since 4.9.6
 * @deprecated 5.3.0
 */
class WP_Privacy_Data_Removal_Requests_Table extends WP_Privacy_Data_Removal_Requests_List_Table {
	function __construct( $args ) {
		_deprecated_function( __CLASS__, '5.3.0', 'WP_Privacy_Data_Removal_Requests_List_Table' );

		if ( ! isset( $args['screen'] ) || $args['screen'] === 'remove_personal_data' ) {
			$args['screen'] = 'erase-personal-data';
		}

		parent::__construct( $args );
	}
}

/**
 * Was used to add options for the privacy requests screens before they were separate files.
 *
 * @since 4.9.8
 * @access private
 * @deprecated 5.3.0
 */
function _wp_privacy_requests_screen_options() {
	_deprecated_function( __FUNCTION__, '5.3.0' );
}
