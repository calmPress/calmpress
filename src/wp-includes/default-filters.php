<?php
/**
 * Sets up the default filters and actions for most
 * of the WordPress hooks.
 *
 * If you need to remove a default hook, this file will
 * give you the priority for which to use to remove the
 * hook.
 *
 * Not all of the default hooks are found in default-filters.php
 *
 * @package WordPress
 */

// Strip, trim, kses, special chars for string saves.
foreach ( array( 'pre_term_name', 'pre_comment_author_name', 'pre_user_display_name' ) as $filter ) {
	add_filter( $filter, 'sanitize_text_field'  );
	add_filter( $filter, 'wp_filter_kses'       );
	add_filter( $filter, '_wp_specialchars', 30 );
}

// Strip, kses, special chars for string display.
foreach ( array( 'term_name', 'comment_author_name', 'user_display_name' ) as $filter ) {
	if ( is_admin() ) {
		// These are expensive. Run only on admin pages for defense in depth.
		add_filter( $filter, 'sanitize_text_field' );
		add_filter( $filter, 'wp_kses_data' );
	}
	add_filter( $filter, '_wp_specialchars', 30 );
}

// Kses only for textarea saves.
foreach ( array( 'pre_term_description', 'pre_user_description' ) as $filter ) {
	add_filter( $filter, 'wp_filter_kses' );
}

// Kses only for textarea admin displays.
if ( is_admin() ) {
	foreach ( array( 'term_description', 'user_description' ) as $filter ) {
		add_filter( $filter, 'wp_kses_data' );
	}
	add_filter( 'comment_text', 'wp_kses_post' );
}

// Email saves.
foreach ( array( 'pre_comment_author_email', 'pre_user_email' ) as $filter ) {
	add_filter( $filter, 'trim' );
	add_filter( $filter, 'sanitize_email' );
	add_filter( $filter, 'wp_filter_kses' );
}

// Email admin display.
foreach ( array( 'comment_author_email', 'user_email' ) as $filter ) {
	add_filter( $filter, 'sanitize_email' );
	if ( is_admin() ) {
		add_filter( $filter, 'wp_kses_data' );
	}
}

// Save URL.
foreach ( array(
	'pre_comment_author_url',
	'pre_post_guid',
) as $filter ) {
	add_filter( $filter, 'wp_strip_all_tags' );
	add_filter( $filter, 'esc_url_raw' );
	add_filter( $filter, 'wp_filter_kses' );
}

// Display URL.
foreach ( array( 'comment_url', 'post_guid' ) as $filter ) {
	if ( is_admin() ) {
		add_filter( $filter, 'wp_strip_all_tags' );
	}
	add_filter( $filter, 'esc_url' );
	if ( is_admin() ) {
		add_filter( $filter, 'wp_kses_data' );
	}
}

// Slugs.
add_filter( 'pre_term_slug', 'sanitize_title' );
add_filter( 'wp_insert_post_data', '_wp_customize_changeset_filter_insert_post_data', 10, 2 );

// Keys.
foreach ( array( 'pre_post_type', 'pre_post_status', 'pre_post_comment_status', 'pre_post_ping_status' ) as $filter ) {
	add_filter( $filter, 'sanitize_key' );
}

// Mime types.
add_filter( 'pre_post_mime_type', 'sanitize_mime_type' );
add_filter( 'post_mime_type', 'sanitize_mime_type' );

// Meta.
add_filter( 'register_meta_args', '_wp_register_meta_args_allowed_list', 10, 2 );

// Post meta.
add_action( 'added_post_meta', 'wp_cache_set_posts_last_changed' );
add_action( 'updated_post_meta', 'wp_cache_set_posts_last_changed' );
add_action( 'deleted_post_meta', 'wp_cache_set_posts_last_changed' );

// Term meta.
add_action( 'added_term_meta', 'wp_cache_set_terms_last_changed' );
add_action( 'updated_term_meta', 'wp_cache_set_terms_last_changed' );
add_action( 'deleted_term_meta', 'wp_cache_set_terms_last_changed' );

// Comment meta.
add_action( 'added_comment_meta', 'wp_cache_set_comments_last_changed' );
add_action( 'updated_comment_meta', 'wp_cache_set_comments_last_changed' );
add_action( 'deleted_comment_meta', 'wp_cache_set_comments_last_changed' );

// Convert invalid entities on input.
foreach ( array( 'content_save_pre', 'excerpt_save_pre', 'comment_save_pre', 'pre_comment_content' ) as $filter ) {
	add_filter( $filter, 'convert_invalid_entities' );
}

// Add proper rel values for links with target.
add_action( 'init', 'wp_init_targeted_link_rel_filters' );

// Format strings for display.
foreach ( array( 'comment_author', 'term_name', 'bloginfo', 'wp_title', 'document_title', 'widget_title' ) as $filter ) {
	add_filter( $filter, 'wptexturize' );
	add_filter( $filter, 'convert_chars' );
	add_filter( $filter, 'esc_html' );
}

// Format titles.
foreach ( array( 'single_post_title', 'single_cat_title', 'single_tag_title', 'single_month_title', 'nav_menu_attr_title', 'nav_menu_description' ) as $filter ) {
	add_filter( $filter, 'wptexturize' );
	add_filter( $filter, 'strip_tags' );
}

// Format text area for display.
foreach ( array( 'term_description', 'get_the_post_type_description' ) as $filter ) {
	add_filter( $filter, 'wptexturize'      );
	add_filter( $filter, 'convert_chars'    );
	add_filter( $filter, 'wpautop'          );
	add_filter( $filter, 'shortcode_unautop');
}

// Format for RSS.
add_filter( 'term_name_rss', 'convert_chars' );

// Pre save hierarchy.
add_filter( 'wp_insert_post_parent', 'wp_check_post_hierarchy_for_loops', 10, 2 );
add_filter( 'wp_update_term_parent', 'wp_check_term_hierarchy_for_loops', 10, 3 );

// Display filters.
add_filter( 'the_title', 'wptexturize' );
add_filter( 'the_title', 'convert_chars' );
add_filter( 'the_title', 'trim' );

add_filter( 'the_content', 'wptexturize' );
add_filter( 'the_content', 'wpautop' );
add_filter( 'the_content', 'shortcode_unautop' );
add_filter( 'the_content', 'prepend_attachment' );
add_filter( 'the_content', 'wp_filter_content_tags' );
add_filter( 'the_content', 'wp_replace_insecure_home_url' );

add_filter( 'the_excerpt', 'wptexturize' );
add_filter( 'the_excerpt', 'convert_chars' );
add_filter( 'the_excerpt', 'wpautop' );
add_filter( 'the_excerpt', 'shortcode_unautop' );
add_filter( 'the_excerpt', 'wp_filter_content_tags' );
add_filter( 'the_excerpt', 'wp_replace_insecure_home_url' );
add_filter( 'get_the_excerpt', 'wp_trim_excerpt', 10, 2 );

add_filter( 'the_post_thumbnail_caption', 'wptexturize'     );
add_filter( 'the_post_thumbnail_caption', 'convert_chars'   );

add_filter( 'comment_text', 'wptexturize' );
add_filter( 'comment_text', 'convert_chars' );
add_filter( 'comment_text', 'make_clickable', 9 );
add_filter( 'comment_text', 'force_balance_tags', 25 );
add_filter( 'comment_text', 'wpautop',            30 );

add_filter( 'comment_excerpt', 'convert_chars' );

add_filter( 'list_cats', 'wptexturize' );

add_filter( 'wp_sprintf', 'wp_sprintf_l', 10, 2 );

add_filter( 'widget_text_content', 'wptexturize' );
add_filter( 'widget_text_content', 'wpautop' );
add_filter( 'widget_text_content', 'shortcode_unautop' );
add_filter( 'widget_text_content', 'wp_filter_content_tags' );
add_filter( 'widget_text_content', 'wp_replace_insecure_home_url' );
add_filter( 'widget_text_content', 'do_shortcode', 11 ); // Runs after wpautop(); note that $post global will be null when shortcodes run.

add_filter( 'wp_get_custom_css', 'wp_replace_insecure_home_url' );

// RSS filters.
add_filter( 'the_title_rss', 'strip_tags' );
add_filter( 'the_title_rss', 'ent2ncr', 8 );
add_filter( 'the_title_rss', 'esc_html' );
add_filter( 'the_content_rss', 'ent2ncr', 8 );
add_filter( 'the_content_feed', '_oembed_filter_feed_content' );
add_filter( 'the_excerpt_rss', 'convert_chars' );
add_filter( 'the_excerpt_rss', 'ent2ncr', 8 );
add_filter( 'bloginfo_rss', 'ent2ncr', 8 );
add_filter( 'the_author', 'ent2ncr', 8 );
add_filter( 'the_guid', 'esc_url' );

// Robots filters.
add_filter( 'wp_robots', 'wp_robots_noindex' );
add_filter( 'wp_robots', 'wp_robots_noindex_embeds' );
add_filter( 'wp_robots', 'wp_robots_noindex_search' );
add_filter( 'wp_robots', 'wp_robots_max_image_preview_large' );

// Mark site as no longer fresh.
foreach (
	array(
		'publish_post',
		'publish_page',
		'wp_ajax_save-widget',
		'wp_ajax_widgets-order',
		'customize_save_after',
		'rest_after_save_widget',
		'rest_delete_widget',
		'rest_save_sidebar',
	) as $action
) {
	add_action( $action, '_delete_option_fresh_site', 0 );
}

// Misc filters.
add_filter( 'option_blog_charset', '_wp_specialchars' ); // IMPORTANT: This must not be wp_specialchars() or esc_html() or it'll cause an infinite loop.
add_filter( 'option_blog_charset', '_canonical_charset' );
add_filter( 'option_home', '_config_wp_home' );
add_filter( 'option_siteurl', '_config_wp_siteurl' );
add_filter( 'tiny_mce_before_init', '_mce_set_direction' );
add_filter( 'teeny_mce_before_init', '_mce_set_direction' );
add_filter( 'pre_kses', 'wp_pre_kses_less_than' );
add_filter( 'sanitize_title', 'sanitize_title_with_dashes', 10, 3 );
add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );
add_filter( 'comment_flood_filter', 'wp_throttle_comment_flood', 10, 3 );
add_filter( 'pre_comment_content', 'wp_rel_ugc', 15 );
add_filter( 'comment_email', 'antispambot' );
add_filter( 'option_tag_base', '_wp_filter_taxonomy_base' );
add_filter( 'option_category_base', '_wp_filter_taxonomy_base' );
add_filter( 'the_posts', '_close_comments_for_old_posts', 10, 2 );
add_filter( 'comments_open', '_close_comments_for_old_post', 10, 2 );
add_filter( 'editable_slug', 'urldecode' );
add_filter( 'editable_slug', 'esc_textarea' );
add_filter( 'nav_menu_meta_box_object', '_wp_nav_menu_meta_box_object' );
add_filter( 'title_save_pre', 'trim' );

add_action( 'transition_comment_status', '_clear_modified_cache_on_transition_comment_status', 10, 2 );

add_filter( 'http_request_host_is_external', 'allowed_http_request_hosts', 10, 2 );

// REST API filters.
add_action( 'wp_head', 'rest_output_link_wp_head', 10, 0 );
add_action( 'template_redirect', 'rest_output_link_header', 11, 0 );
add_action( 'auth_cookie_malformed', 'rest_cookie_collect_status' );
add_action( 'auth_cookie_expired', 'rest_cookie_collect_status' );
add_action( 'auth_cookie_bad_username', 'rest_cookie_collect_status' );
add_action( 'auth_cookie_bad_hash', 'rest_cookie_collect_status' );
add_action( 'auth_cookie_valid', 'rest_cookie_collect_status' );
add_action( 'application_password_failed_authentication', 'rest_application_password_collect_status' );
add_action( 'application_password_did_authenticate', 'rest_application_password_collect_status', 10, 2 );
add_filter( 'rest_authentication_errors', 'rest_application_password_check_errors', 90 );
add_filter( 'rest_authentication_errors', 'rest_cookie_check_errors', 100 );

// Actions.
add_action( 'wp_head', '_wp_render_title_tag', 1 );
add_action( 'wp_head', 'wp_enqueue_scripts', 1 );
add_action( 'wp_head', 'wp_resource_hints', 2 );
add_action( 'wp_head', 'feed_links', 2 );
add_action( 'wp_head', 'feed_links_extra', 3 );
add_action( 'wp_head', 'locale_stylesheet' );
add_action( 'publish_future_post', 'check_and_publish_future_post', 10, 1 );
add_action( 'wp_head', 'wp_robots', 1 );
add_action( 'wp_head', 'wp_print_styles', 8 );
add_action( 'wp_head', 'wp_print_head_scripts', 9 );
add_action( 'wp_head', 'rel_canonical' );
add_action( 'wp_head', 'wp_site_icon', 99 );
add_action( 'wp_footer', 'wp_print_footer_scripts', 20 );
add_action( 'wp_print_footer_scripts', '_wp_footer_scripts' );
add_action( 'init', 'check_theme_switched', 99 );
add_action( 'switch_theme', array( 'WP_Theme_JSON_Resolver', 'clean_cached_data' ) );
add_action( 'start_previewing_theme', array( 'WP_Theme_JSON_Resolver', 'clean_cached_data' ) );
add_action( 'after_switch_theme', '_wp_menus_changed' );
add_action( 'after_switch_theme', '_wp_sidebars_changed' );

if ( isset( $_GET['replytocom'] ) ) {
	add_filter( 'wp_robots', 'wp_robots_no_robots' );
}

// Login actions.
add_action( 'login_head', 'wp_robots', 1 );
add_filter( 'login_head', 'wp_resource_hints', 8 );
add_action( 'login_head', 'wp_print_head_scripts', 9 );
add_action( 'login_head', 'print_admin_styles', 9 );
add_action( 'login_head', 'wp_site_icon', 99 );
add_action( 'login_footer', 'wp_print_footer_scripts', 20 );
add_action( 'login_init', 'send_frame_options_header', 10, 0 );

// Prevent embeding front end as iframe in other sites.
add_action( 'template_redirect', 'send_frame_options_header', 10, 0 );

// Feed Site Icon.
add_action( 'rss2_head', 'rss2_site_icon' );


// WP Cron.
if ( ! defined( 'DOING_CRON' ) ) {
	add_action( 'init', 'wp_cron' );
}

// HTTPS detection.
add_action( 'init', 'wp_schedule_https_detection' );
add_action( 'wp_https_detection', 'wp_update_https_detection_errors' );
add_filter( 'cron_request', 'wp_cron_conditionally_prevent_sslverify', 9999 );

// HTTPS migration.
add_action( 'update_option_home', 'wp_update_https_migration_required', 10, 2 );

// 2 Actions 2 Furious.
add_action( 'do_feed_rss2', 'do_feed_rss2', 10, 1 );
add_action( 'do_robots', 'do_robots' );
add_action( 'do_favicon', 'do_favicon' );
add_action( 'set_comment_cookies', 'wp_set_comment_cookies', 10, 3 );
add_action( 'sanitize_comment_cookies', 'sanitize_comment_cookies' );
add_action( 'admin_print_scripts', 'print_head_scripts', 20 );
add_action( 'admin_print_footer_scripts', '_wp_footer_scripts' );
add_action( 'admin_print_styles', 'print_admin_styles', 20 );
add_action( 'plugins_loaded', 'wp_maybe_load_widgets', 0 );
add_action( 'plugins_loaded', 'wp_maybe_load_embeds', 0 );
add_action( 'shutdown', 'wp_ob_end_flush_all', 1 );
// Create a revision whenever a post is updated.
add_action( 'post_updated', 'wp_save_post_revision', 10, 1 );
add_action( 'publish_post', '_publish_post_hook', 5, 1 );
add_action( 'transition_post_status', '_transition_post_status', 5, 3 );
add_action( 'transition_post_status', '_update_term_count_on_transition_post_status', 10, 3 );
add_action( 'comment_form', 'wp_comment_form_unfiltered_html_nonce' );
add_action( 'admin_init', 'send_frame_options_header', 10, 0 );

// Privacy.
add_action( 'user_request_action_confirmed', '_wp_privacy_account_request_confirmed' );
add_action( 'user_request_action_confirmed', '_wp_privacy_send_request_confirmation_notification', 12 ); // After request marked as completed.
add_filter( 'wp_privacy_personal_data_exporters', 'wp_register_comment_personal_data_exporter' );
add_filter( 'wp_privacy_personal_data_exporters', 'wp_register_media_personal_data_exporter' );
add_filter( 'wp_privacy_personal_data_exporters', 'wp_register_user_personal_data_exporter', 1 );
add_filter( 'wp_privacy_personal_data_erasers', 'wp_register_comment_personal_data_eraser' );
add_action( 'init', 'wp_schedule_delete_old_privacy_export_files' );
add_action( 'wp_privacy_delete_old_export_files', 'wp_privacy_delete_old_export_files' );

// Cron tasks.
add_action( 'wp_scheduled_delete', 'wp_scheduled_delete' );
add_action( 'wp_scheduled_auto_draft_delete', 'wp_delete_auto_drafts' );
add_action( 'upgrader_scheduled_cleanup', 'wp_delete_attachment' );
add_action( 'delete_expired_transients', 'delete_expired_transients' );

// Navigation menu actions.
add_action( 'delete_post', '_wp_delete_post_menu_item' );
add_action( 'delete_term', '_wp_delete_tax_menu_item', 10, 3 );
add_action( 'transition_post_status', '_wp_auto_add_pages_to_menu', 10, 3 );
add_action( 'delete_post', '_wp_delete_customize_changeset_dependent_auto_drafts' );

// Post Thumbnail CSS class filtering.
add_action( 'begin_fetch_post_thumbnail_html', '_wp_post_thumbnail_class_filter_add' );
add_action( 'end_fetch_post_thumbnail_html', '_wp_post_thumbnail_class_filter_remove' );

// Redirect old slugs.
add_action( 'template_redirect', 'wp_old_slug_redirect' );
add_action( 'post_updated', 'wp_check_for_changed_slugs', 12, 3 );
add_action( 'attachment_updated', 'wp_check_for_changed_slugs', 12, 3 );

// Redirect old dates.
add_action( 'post_updated', 'wp_check_for_changed_dates', 12, 3 );
add_action( 'attachment_updated', 'wp_check_for_changed_dates', 12, 3 );

// Nonce check for post previews.
add_action( 'init', '_show_post_preview' );

// Output JS to reset window.name for previews.
add_action( 'wp_head', 'wp_post_preview_js', 1 );

// Timezone.
add_filter( 'pre_option_gmt_offset', 'wp_timezone_override_offset' );

// Admin color schemes.
add_action( 'admin_init', 'register_admin_color_schemes', 1 );
add_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );

// This option no longer exists; tell plugins we always support auto-embedding.
add_filter( 'pre_option_embed_autourls', '__return_true' );

// Default settings for heartbeat.
add_filter( 'heartbeat_settings', 'wp_heartbeat_settings' );

// Check if the user is logged out.
add_action( 'admin_enqueue_scripts', 'wp_auth_check_load' );
add_filter( 'heartbeat_send', 'wp_auth_check' );
add_filter( 'heartbeat_nopriv_send', 'wp_auth_check' );

// Default authentication filters.
add_filter( 'authenticate', 'wp_authenticate_email_password', 20, 3 );
add_filter( 'authenticate', 'wp_authenticate_application_password', 20, 3 );
add_filter( 'authenticate', 'wp_authenticate_spam_check', 99 );
add_filter( 'determine_current_user', 'wp_validate_auth_cookie' );
add_filter( 'determine_current_user', 'wp_validate_logged_in_cookie', 20 );
add_filter( 'determine_current_user', 'wp_validate_application_password', 20 );

// Comment type updates.
add_action( 'admin_init', '_wp_check_for_scheduled_update_comment_type' );
add_action( 'wp_update_comment_type_batch', '_wp_batch_update_comment_type' );

// Email notifications.
add_action( 'comment_post', 'wp_new_comment_notify_moderator' );
add_action( 'comment_post', 'wp_new_comment_notify_postauthor' );
add_action( 'register_new_user', 'wp_send_new_user_notifications' );
add_action( 'edit_user_created_user', 'wp_send_new_user_notifications', 10, 2 );

// REST API actions.
add_action( 'init', 'rest_api_init' );
add_action( 'rest_api_init', 'rest_api_default_filters', 10, 1 );
add_action( 'rest_api_init', 'register_initial_settings', 10 );
add_action( 'rest_api_init', 'create_initial_rest_routes', 99 );
add_action( 'parse_request', 'rest_api_loaded' );

// Sitemaps actions.
add_action( 'init', 'wp_sitemaps_get_server' );

/**
 * Filters formerly mixed into wp-includes.
 */
// Theme.
add_action( 'setup_theme', 'create_initial_theme_features', 0 );
add_action( 'setup_theme', '_add_default_theme_supports', 1 );
add_action( 'wp_loaded', '_custom_header_background_just_in_time' );
add_action( 'wp_head', '_custom_logo_header_styles' );
add_action( 'plugins_loaded', '_wp_customize_include' );
add_action( 'transition_post_status', '_wp_customize_publish_changeset', 10, 3 );
add_action( 'admin_enqueue_scripts', '_wp_customize_loader_settings' );
add_action( 'delete_attachment', '_delete_attachment_theme_mod' );
add_action( 'transition_post_status', '_wp_keep_alive_customize_changeset_dependent_auto_drafts', 20, 3 );

// Author.
add_action( 'transition_post_status', '__clear_multi_author_cache' );

// Post.
add_action( 'init', 'create_initial_post_types', 0 ); // Highest priority.
add_action( 'admin_menu', '_add_post_type_submenus' );
add_action( 'before_delete_post', '_reset_front_page_settings_for_post' );
add_action( 'wp_trash_post', '_reset_front_page_settings_for_post' );
add_action( 'change_locale', 'create_initial_post_types' );

// KSES.
add_action( 'init', 'kses_init' );
add_action( 'set_current_user', 'kses_init' );

// Script Loader.
add_action( 'wp_default_scripts', 'wp_default_scripts' );
add_action( 'wp_default_scripts', 'wp_default_packages' );

add_action( 'wp_enqueue_scripts', 'wp_localize_jquery_ui_datepicker', 1000 );
add_action( 'admin_enqueue_scripts', 'wp_localize_jquery_ui_datepicker', 1000 );
add_filter( 'wp_print_scripts', 'wp_just_in_time_script_localization' );
add_filter( 'print_scripts_array', 'wp_prototype_before_jquery' );
add_filter( 'customize_controls_print_styles', 'wp_resource_hints', 1 );
add_action( 'admin_head', 'wp_check_widget_editor_deps' );

add_action( 'wp_default_styles', 'wp_default_styles' );
add_filter( 'style_loader_src', 'wp_style_loader_src', 10, 2 );

add_action( 'wp_head', 'wp_maybe_inline_styles', 1 ); // Run for styles enqueued in <head>.
add_action( 'wp_footer', 'wp_maybe_inline_styles', 1 ); // Run for late-loaded styles in the footer.

// Taxonomy.
add_action( 'init', 'create_initial_taxonomies', 0 ); // Highest priority.
add_action( 'change_locale', 'create_initial_taxonomies' );

// Canonical.
add_action( 'template_redirect', 'redirect_canonical' );
add_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );

// Shortcodes.
add_filter( 'the_content', 'do_shortcode', 11 ); // AFTER wpautop().

// Media.
add_action( 'wp_playlist_scripts', 'wp_playlist_scripts' );
add_action( 'customize_controls_enqueue_scripts', 'wp_plupload_default_settings' );
add_action( 'plugins_loaded', '_wp_add_additional_image_sizes', 0 );
add_filter( 'plupload_default_settings', 'wp_show_heic_upload_error' );

// Nav menu.
add_filter( 'nav_menu_item_id', '_nav_menu_item_id_use_once', 10, 2 );

// Widgets.
add_action( 'init', 'wp_widgets_init', 1 );

// Admin Bar.
// Don't remove. Wrong way to disable.
add_action( 'template_redirect', '_wp_admin_bar_init', 0 );
add_action( 'admin_init', '_wp_admin_bar_init' );
add_action( 'before_signup_header', '_wp_admin_bar_init' );
add_action( 'activate_header', '_wp_admin_bar_init' );
add_action( 'wp_body_open', 'wp_admin_bar_render', 0 );
add_action( 'wp_footer', 'wp_admin_bar_render', 1000 ); // Back-compat for themes not using `wp_body_open`.
add_action( 'in_admin_header', 'wp_admin_bar_render', 0 );

// Former admin filters that can also be hooked on the front end.
add_action( 'media_buttons', 'media_buttons' );
add_filter( 'image_send_to_editor', 'image_add_caption', 20, 8 );
add_filter( 'media_send_to_editor', 'image_media_send_to_editor', 10, 3 );

// Embeds.
add_action( 'rest_api_init', 'wp_oembed_register_route' );
add_filter( 'rest_pre_serve_request', '_oembed_rest_pre_serve_request', 10, 4 );

add_action( 'wp_head', 'wp_oembed_add_discovery_links' );
add_action( 'wp_head', 'wp_oembed_add_host_js' ); // Back-compat for sites disabling oEmbed host JS by removing action.
add_filter( 'embed_oembed_html', 'wp_maybe_enqueue_oembed_host_js' );

add_action( 'embed_head', 'enqueue_embed_scripts', 1 );
add_action( 'embed_head', 'print_embed_styles' );
add_action( 'embed_head', 'wp_print_head_scripts', 20 );
add_action( 'embed_head', 'wp_print_styles', 20 );
add_action( 'embed_head', 'wp_robots' );
add_action( 'embed_head', 'rel_canonical' );
add_action( 'embed_head', 'locale_stylesheet', 30 );

add_action( 'embed_content_meta', 'print_embed_comments_button' );
add_action( 'embed_content_meta', 'print_embed_sharing_button' );

add_action( 'embed_footer', 'print_embed_sharing_dialog' );
add_action( 'embed_footer', 'print_embed_scripts' );
add_action( 'embed_footer', 'wp_print_footer_scripts', 20 );

add_filter( 'excerpt_more', 'wp_embed_excerpt_more', 20 );
add_filter( 'the_excerpt_embed', 'wptexturize' );
add_filter( 'the_excerpt_embed', 'convert_chars' );
add_filter( 'the_excerpt_embed', 'wpautop' );
add_filter( 'the_excerpt_embed', 'shortcode_unautop' );
add_filter( 'the_excerpt_embed', 'wp_embed_excerpt_attachment' );

add_filter( 'oembed_dataparse', 'wp_filter_oembed_iframe_title_attribute', 5, 3 );
add_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10, 3 );
add_filter( 'oembed_response_data', 'get_oembed_response_data_rich', 10, 4 );
add_filter( 'pre_oembed_result', 'wp_filter_pre_oembed_result', 10, 3 );

// Capabilities.
add_filter( 'user_has_cap', 'wp_maybe_grant_install_languages_cap', 1 );

unset( $filter, $action );
