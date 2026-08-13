<?php
/**
 * General settings administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

/** WordPress Translation Installation API */
require_once ABSPATH . 'wp-admin/includes/translation-install.php';

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( __( 'Sorry, you are not allowed to manage options for this site.' ) );
}

// Used in the HTML title tag.
$title       = __( 'General Settings' );
$parent_file = 'options-general.php';
/* translators: Date and time format for exact current time, mainly about timezones, see https://www.php.net/manual/datetime.format.php */
$timezone_format = _x( 'Y-m-d H:i:s', 'timezone date format' );

add_action( 'admin_head', 'options_general_add_js' );

require_once ABSPATH . 'wp-admin/admin-header.php';
wp_enqueue_media();
wp_enqueue_script( 'site-icon' );
wp_enqueue_script( 'calm-logo-selection' );
?>

<div class="wrap">
<h1><?php echo esc_html( $title ); ?></h1>

<form method="post" action="options.php" novalidate="novalidate">
<?php settings_fields( 'general' ); ?>

<table class="form-table" role="presentation">

<tr>
<th scope="row"><label for="blogname"><?php _e( 'Site Title' ); ?></label></th>
<td><input name="blogname" type="text" id="blogname" value="<?php form_option( 'blogname' ); ?>" class="regular-text" /></td>
</tr>

<?php
if ( ! is_multisite() ) {
	/* translators: Site tagline. */
	$sample_tagline = __( 'Just another WordPress site' );
} else {
	/* translators: %s: Network title. */
	$sample_tagline = sprintf( __( 'Just another %s site' ), get_network()->site_name );
}
$tagline_description = sprintf(
	/* translators: %s: Site tagline example. */
	__( 'In a few words, explain what this site is about. Example: &#8220;%s.&#8221;' ),
	$sample_tagline
);
?>
<tr>
<th scope="row"><label for="blogdescription"><?php _e( 'Tagline' ); ?></label></th>
<td><input name="blogdescription" type="text" id="blogdescription" aria-describedby="tagline-description" value="<?php form_option( 'blogdescription' ); ?>" class="regular-text" />
<p class="description" id="tagline-description"><?php echo $tagline_description; ?></p></td>
</tr>

<tr class="logo-section">
<th scope="row"><?php _e( 'Logo' ); ?></th>
<td>
	<?php
	$classes_for_upload_button = 'upload-button button';
	$classes_for_update_button = 'button';
	$classes_for_wrapper       = '';

	if ( has_custom_logo() ) {
		$classes_for_wrapper         .= ' has-logo';
		$classes_for_button           = $classes_for_update_button;
		$classes_for_button_on_change = $classes_for_upload_button;
	} else {
		$classes_for_wrapper         .= ' hidden';
		$classes_for_button           = $classes_for_upload_button;
		$classes_for_button_on_change = $classes_for_update_button;
	}

	$logo_image_id = (int) get_option( 'custom_logo' );
	$logo_url =    '';
	if ( $logo_image_id ) {
		$logo_url =  wp_get_attachment_image_url( $logo_image_id, 'full' );
	}
	?>

	<div id="logo-preview-container" class="settings <?php echo esc_attr( $classes_for_wrapper ); ?>">
		<img id="logo-preview" style="display:block;max-width:100%;height:auto;max-height:150px; margin-bottom:16px;" src="<?php echo esc_url( $logo_url ); ?>" alt="" />
	</div>

	<input type="hidden" name="custom_logo" id="logo_hidden_field" value="<?php form_option( 'custom_logo' ); ?>" />
	<div class="logo-action-buttons">
		<button type="button"
			id="choose-logo-from-library-button"
			class="<?php echo esc_attr( $classes_for_button ); ?>"
			data-alt-classes="<?php echo esc_attr( $classes_for_button_on_change ); ?>"
			data-choose-text="<?php esc_attr_e( 'Choose a Logo' ); ?>"
			data-update-text="<?php esc_attr_e( 'Change Logo' ); ?>"
			data-update="<?php esc_attr_e( 'Set as Logo' ); ?>"
			data-state="<?php echo esc_attr( has_custom_logo() ); ?>"

		>
			<?php if ( has_custom_logo() ) { ?>
				<?php esc_html_e( 'Change Logo' ); ?>
			<?php } else { ?>
				<?php esc_html_e( 'Choose a Logo' ); ?>
			<?php } ?>
		</button>
		<button
			id="js-remove-logo"
			type="button"
			<?php echo has_custom_logo() ? 'class="button button-secondary reset remove-logo"' : 'class="button button-secondary reset hidden"'; ?>
		>
			<?php _e( 'Remove Logo' ); ?>
		</button>
	</div>

	<p class="description">
		<?php
			esc_html_e( 'The logo image is used to represent your site for branding purposes. It may be displayed by your theme, often in the header and linked to your homepage.' );
			echo '<br />';
			if ( current_theme_supports( 'custom-logo' ) ) {
				$customizer_url = add_query_arg(
					array(
						'autofocus[control]' => 'custom_logo',
					),
					admin_url( 'customize.php' )
				);
				printf(
					/* translators: 1: start of <a> element pointing to Customizer URL, 2: </a> */
					esc_html__( 'You can preview logo changes in the %1$sCustomizer%2$s.' ),
					'<a href="' . esc_url( $customizer_url ) . '">',
					'</a>'
				);
			} else {
				esc_html_e( 'Your current theme may not display the site logo, but the logo will still be available to themes and features that support it.' );
			}
		?>
	</p>

</td>
</tr>

<tr class="site-icon-section">
<th scope="row"><?php _e( 'Site Icon' ); ?></th>
<td>
	<?php
	$classes_for_upload_button = 'upload-button button';
	$classes_for_update_button = 'button';
	$classes_for_wrapper       = '';
	$site_icon_id              = (int) get_option( 'site_icon' );
	$site_icon_url             = ( 0 === $site_icon_id ) ? '' : wp_get_attachment_image_url( $site_icon_id, 'full' );
	$site_icon_url             = ( is_string( $site_icon_url ) ) ? $site_icon_url : '';
	$network_site_icon_url     = '';
	$network_app_icon_alt      = '';
	$network_browser_icon_alt  = '';

	if ( is_multisite() ) {
		switch_to_blog( get_main_site_id() );

		$network_site_icon_id  = (int) get_network_option( 0, 'site_icon', 0 );
		$network_site_icon_url = ( 0 === $network_site_icon_id ) ? '' : wp_get_attachment_image_url( $network_site_icon_id, 'full' );
		$network_site_icon_url = ( is_string( $network_site_icon_url ) ) ? $network_site_icon_url : '';

		restore_current_blog();

		if ( $network_site_icon_url ) {
			$network_site_icon_filename = wp_basename( $network_site_icon_url );
			$network_app_icon_alt       = sprintf(
				/* translators: %s: The Network Site Icon filename. */
				__( 'App icon preview: Network Site Icon: %s' ),
				$network_site_icon_filename
			);
			$network_browser_icon_alt   = sprintf(
				/* translators: %s: The Network Site Icon filename. */
				__( 'Browser icon preview: Network Site Icon: %s' ),
				$network_site_icon_filename
			);
		}
	}

	if ( ( 0 === $site_icon_id ) && ( $network_site_icon_url ) ) {
		$site_icon_url         = $network_site_icon_url;
		$classes_for_wrapper  .= ' has-site-icon';
	} elseif ( $site_icon_id ) {
		$classes_for_wrapper .= ' has-site-icon';
	} else {
		$classes_for_wrapper .= ' hidden';
	}

	if ( $site_icon_id ) {
		$classes_for_button           = $classes_for_update_button;
		$classes_for_button_on_change = $classes_for_upload_button;
	} else {
		$classes_for_button           = $classes_for_upload_button;
		$classes_for_button_on_change = $classes_for_update_button;
	}

	// Handle alt text for site icon on page load.
	$app_icon_alt_value     = '';
	$browser_icon_alt_value = '';

	if ( $site_icon_id ) {
		$img_alt            = get_post_meta( $site_icon_id, '_wp_attachment_image_alt', true );
		$filename           = wp_basename( $site_icon_url );
		$app_icon_alt_value = sprintf(
			/* translators: %s: The selected image filename. */
			__( 'App icon preview: The current image has no alternative text. The file name is: %s' ),
			$filename
		);

		$browser_icon_alt_value = sprintf(
			/* translators: %s: The selected image filename. */
			__( 'Browser icon preview: The current image has no alternative text. The file name is: %s' ),
			$filename
		);

		if ( $img_alt ) {
			$app_icon_alt_value = sprintf(
				/* translators: %s: The selected image alt text. */
				__( 'App icon preview: Current image: %s' ),
				$img_alt
			);

			$browser_icon_alt_value = sprintf(
				/* translators: %s: The selected image alt text. */
				__( 'Browser icon preview: Current image: %s' ),
				$img_alt
			);
		}
	} elseif ( $network_site_icon_url ) {
		$app_icon_alt_value     = $network_app_icon_alt;
		$browser_icon_alt_value = $network_browser_icon_alt;
	}
	?>

	<style>
	:root {
		--site-icon-url: url( '<?php echo esc_url( $site_icon_url ); ?>' );
	}
	</style>

	<?php if ( $network_site_icon_url ) { ?>
		<p
			id="network-site-icon-label"
			class="description<?php echo ( $site_icon_id ) ? ' hidden' : ''; ?>"
			data-pending-text="<?php esc_attr_e( 'The Network Site Icon will be used after saving' ); ?>"
		>
			<strong><?php esc_html_e( 'Currently using the Network Site Icon' ); ?></strong>
		</p>
	<?php } ?>
	<div id="site-icon-preview" class="site-icon-preview settings <?php echo esc_attr( $classes_for_wrapper ); ?>">
		<div class="direction-wrap">
			<img id="app-icon-preview" src="<?php echo esc_url( $site_icon_url ); ?>" class="app-icon-preview" alt="<?php echo esc_attr( $app_icon_alt_value ); ?>" />
			<div class="site-icon-preview-browser">
				<svg role="img" aria-hidden="true" fill="none" xmlns="http://www.w3.org/2000/svg" class="browser-buttons"><path fill-rule="evenodd" clip-rule="evenodd" d="M0 20a6 6 0 1 1 12 0 6 6 0 0 1-12 0Zm18 0a6 6 0 1 1 12 0 6 6 0 0 1-12 0Zm24-6a6 6 0 1 0 0 12 6 6 0 0 0 0-12Z" /></svg>
				<div class="site-icon-preview-tab">
					<img id="browser-icon-preview" src="<?php echo esc_url( $site_icon_url ); ?>" class="browser-icon-preview" alt="<?php echo esc_attr( $browser_icon_alt_value ); ?>" />
					<div class="site-icon-preview-site-title" id="site-icon-preview-site-title" aria-hidden="true"><?php bloginfo( 'name' ); ?></div>
						<svg role="img" aria-hidden="true" fill="none" xmlns="http://www.w3.org/2000/svg" class="close-button">
							<path d="M12 13.0607L15.7123 16.773L16.773 15.7123L13.0607 12L16.773 8.28772L15.7123 7.22706L12 10.9394L8.28771 7.22705L7.22705 8.28771L10.9394 12L7.22706 15.7123L8.28772 16.773L12 13.0607Z" />
						</svg>
					</div>
				</div>
			</div>
		</div>
	</div>

	<input type="hidden" name="site_icon" id="site_icon_hidden_field" value="<?php form_option( 'site_icon' ); ?>" />
	<div class="site-icon-action-buttons">
		<button type="button"
			id="choose-from-library-button"
			class="<?php echo esc_attr( $classes_for_button ); ?>"
			data-alt-classes="<?php echo esc_attr( $classes_for_button_on_change ); ?>"
			data-size="512"
			data-choose-text="<?php echo esc_attr( ( $network_site_icon_url ) ? __( 'Use a Site-Specific Icon' ) : __( 'Choose a Site Icon' ) ); ?>"
			data-update-text="<?php esc_attr_e( 'Change Site Icon' ); ?>"
			data-update="<?php esc_attr_e( 'Set as Site Icon' ); ?>"
			data-state="<?php echo esc_attr( (bool) $site_icon_id ); ?>"
			data-network-icon-url="<?php echo esc_url( $network_site_icon_url ); ?>"
			data-network-app-icon-alt="<?php echo esc_attr( $network_app_icon_alt ); ?>"
			data-network-browser-icon-alt="<?php echo esc_attr( $network_browser_icon_alt ); ?>"

		>
			<?php if ( $site_icon_id ) : ?>
				<?php _e( 'Change Site Icon' ); ?>
			<?php elseif ( $network_site_icon_url ) : ?>
				<?php esc_html_e( 'Use a Site-Specific Icon' ); ?>
			<?php else : ?>
				<?php _e( 'Choose a Site Icon' ); ?>
			<?php endif; ?>
		</button>
		<button
			id="js-remove-site-icon"
			type="button"
			<?php echo ( $site_icon_id ) ? 'class="button button-secondary reset"' : 'class="button button-secondary reset hidden"'; ?>
		>
			<?php if ( $network_site_icon_url ) { ?>
				<?php esc_html_e( 'Use Network Site Icon' ); ?>
			<?php } else { ?>
				<?php esc_html_e( 'Stop Using Site Icon' ); ?>
			<?php } ?>
		</button>
	</div>

	<p class="description">
		<?php esc_html_e( 'The Site Icon may appear in browser tabs, bookmarks, and other places where browsers identify the site. Other applications and services may also use it when representing the site. For best results across different uses, choose a square image. The recommended size is 512 by 512 pixels.' ); ?>
	</p>

</td>
</tr>

<?php

if ( ! is_multisite() ) {
	$wp_site_url_class = '';
	$wp_home_class     = '';
	if ( defined( 'WP_SITEURL' ) ) {
		$wp_site_url_class = ' disabled';
	}
	if ( defined( 'WP_HOME' ) ) {
		$wp_home_class = ' disabled';
	}
	?>

<tr>
<th scope="row"><label for="home"><?php _e( 'Site Address (URL)' ); ?></label></th>
<td><input name="home" type="url" id="home" aria-describedby="home-description" value="<?php form_option( 'home' ); ?>"<?php disabled( defined( 'WP_HOME' ) ); ?> class="regular-text code<?php echo $wp_home_class; ?>" />
</td>
</tr>

<tr>
	<th scope="row"><label for="admin_email"><?php esc_html_e( 'System notifications recipient' ); ?></label></th>
	<td>
		<?php
			$email = get_option( 'admin_email' );
			echo '<select name="admin_email">';
			foreach ( WP_User::administrators() as $user ) {
				$selected = '';
				if ( $user->user_email === $email ) {
					$selected = ' selected';
				}
				echo '<option value="' . esc_attr( $user->user_email ) . '"' . $selected .'>'
				     . esc_html( $user->display_name . ' ('. $user->user_email . ')' ) . '</option>';
			}
			echo '</select>';
		?>
		<p class="description">
			<?php esc_html_e( 'The administrator who will receive email notifications for site administration related events.' ); ?>
		</p>
	</td>
</tr>
<?php } ?>

<?php
$languages    = get_available_languages();
$translations = wp_get_available_translations();
if ( ! is_multisite() && defined( 'WPLANG' ) && '' !== WPLANG && 'en_US' !== WPLANG && ! in_array( WPLANG, $languages, true ) ) {
	$languages[] = WPLANG;
}
if ( ! empty( $languages ) || ! empty( $translations ) ) {
	?>
	<tr>
		<th scope="row"><label for="WPLANG"><?php _e( 'Site Language' ); ?><span class="dashicons dashicons-translation" aria-hidden="true"></span></label></th>
		<td>
			<?php
			$locale = get_locale();
			if ( ! in_array( $locale, $languages, true ) ) {
				$locale = '';
			}

			wp_dropdown_languages(
				array(
					'name'                        => 'WPLANG',
					'id'                          => 'WPLANG',
					'selected'                    => $locale,
					'languages'                   => $languages,
					'translations'                => $translations,
					'show_available_translations' => current_user_can( 'install_languages' ) && wp_can_install_language_pack(),
				)
			);
			?>
		</td>
	</tr>
	<?php
}
?>
<tr>
<?php
$current_offset = get_option( 'gmt_offset' );
$tzstring       = get_option( 'timezone_string' );

$check_zone_info = true;

// Remove old Etc mappings. Fallback to gmt_offset.
if ( str_contains( $tzstring, 'Etc/GMT' ) ) {
	$tzstring = '';
}

if ( empty( $tzstring ) ) { // Create a UTC+- zone if no timezone string exists.
	$check_zone_info = false;
	if ( 0 === (int) $current_offset ) {
		$tzstring = 'UTC+0';
	} elseif ( $current_offset < 0 ) {
		$tzstring = 'UTC' . $current_offset;
	} else {
		$tzstring = 'UTC+' . $current_offset;
	}
}

?>
<th scope="row"><label for="timezone_string"><?php _e( 'Timezone' ); ?></label></th>
<td>

<select id="timezone_string" name="timezone_string" aria-describedby="timezone-description">
	<?php echo wp_timezone_choice( $tzstring, get_user_locale() ); ?>
</select>

<p class="description" id="timezone-description">
<?php
	printf(
		/* translators: %s: UTC abbreviation */
		__( 'Choose either a city in the same timezone as you or a %s (Coordinated Universal Time) time offset.' ),
		'<abbr>UTC</abbr>'
	);
	?>
</p>

<p class="timezone-info">
	<span id="utc-time">
	<?php
		printf(
			/* translators: %s: UTC time. */
			__( 'Universal time is %s.' ),
			'<code>' . date_i18n( $timezone_format, false, true ) . '</code>'
		);
		?>
	</span>
<?php if ( get_option( 'timezone_string' ) || ! empty( $current_offset ) ) : ?>
	<span id="local-time">
	<?php
		printf(
			/* translators: %s: Local time. */
			__( 'Local time is %s.' ),
			'<code>' . date_i18n( $timezone_format ) . '</code>'
		);
	?>
	</span>
<?php endif; ?>
</p>

<?php if ( $check_zone_info && $tzstring ) : ?>
<p class="timezone-info">
<span>
	<?php
	$now = new DateTime( 'now', new DateTimeZone( $tzstring ) );
	$dst = (bool) $now->format( 'I' );

	if ( $dst ) {
		_e( 'This timezone is currently in daylight saving time.' );
	} else {
		_e( 'This timezone is currently in standard time.' );
	}
	?>
	<br />
	<?php
	if ( in_array( $tzstring, timezone_identifiers_list( DateTimeZone::ALL_WITH_BC ), true ) ) {
		$transitions = timezone_transitions_get( timezone_open( $tzstring ), time() );

		// 0 index is the state at current time, 1 index is the next transition, if any.
		if ( ! empty( $transitions[1] ) ) {
			echo ' ';
			$message = $transitions[1]['isdst'] ?
				/* translators: %s: Date and time. */
				__( 'Daylight saving time begins on: %s.' ) :
				/* translators: %s: Date and time. */
				__( 'Standard time begins on: %s.' );
			printf(
				$message,
				/* translators: Localized date and time format, see https://www.php.net/manual/datetime.format.php */
				'<code>' . wp_date( __( 'F j, Y g:i a' ), $transitions[1]['ts'] ) . '</code>'
			);
		} else {
			_e( 'This timezone does not observe daylight saving time.' );
		}
	}
	?>
	</span>
</p>
<?php endif; ?>
</td>
</tr>

<?php $date_format_title = __( 'Date Format' ); ?>
<tr>
<th scope="row"><?php echo $date_format_title; ?></th>
<td>
	<fieldset><legend class="screen-reader-text"><span><?php echo $date_format_title; ?></span></legend>
<?php
	/**
	 * Filters the default date formats.
	 *
	 * @since 2.7.0
	 * @since 4.0.0 Replaced the `Y/m/d` format with `Y-m-d` (ISO date standard YYYY-MM-DD).
	 * @since 6.8.0 Added the `d.m.Y` format.
	 *
	 * @param string[] $default_date_formats Array of default date formats.
	 */
	$date_formats = array_unique( apply_filters( 'date_formats', array( __( 'F j, Y' ), 'Y-m-d', 'm/d/Y', 'd/m/Y', 'd.m.Y' ) ) );

	$custom = true;

foreach ( $date_formats as $format ) {
	echo "\t<label><input type='radio' name='date_format' value='" . esc_attr( $format ) . "'";
	if ( get_option( 'date_format' ) === $format ) { // checked() uses "==" rather than "===".
		echo " checked='checked'";
		$custom = false;
	}
	echo ' /> <span class="date-time-text format-i18n">' . date_i18n( $format ) . '</span><code>' . esc_html( $format ) . "</code></label><br />\n";
}

	echo '<label><input type="radio" name="date_format" id="date_format_custom_radio" value="\c\u\s\t\o\m"';
	checked( $custom );
	echo '/> <span class="date-time-text date-time-custom-text">' . __( 'Custom:' ) . '<span class="screen-reader-text"> ' .
			/* translators: Hidden accessibility text. */
			__( 'enter a custom date format in the following field' ) .
		'</span></span></label>' .
		'<label for="date_format_custom" class="screen-reader-text">' .
			/* translators: Hidden accessibility text. */
			__( 'Custom date format:' ) .
		'</label>' .
		'<input type="text" name="date_format_custom" id="date_format_custom" value="' . esc_attr( get_option( 'date_format' ) ) . '" class="small-text" />' .
		'<br />' .
		'<p><strong>' . __( 'Preview:' ) . '</strong> <span class="example">' . date_i18n( get_option( 'date_format' ) ) . '</span>' .
		"<span class='spinner'></span>\n" . '</p>';
?>
	</fieldset>
</td>
</tr>

<?php $time_format_title = __( 'Time Format' ); ?>
<tr>
<th scope="row"><?php echo $time_format_title; ?></th>
<td>
	<fieldset><legend class="screen-reader-text"><span><?php echo $time_format_title; ?></span></legend>
<?php
	/**
	 * Filters the default time formats.
	 *
	 * @since 2.7.0
	 *
	 * @param string[] $default_time_formats Array of default time formats.
	 */
	$time_formats = array_unique( apply_filters( 'time_formats', array( __( 'g:i a' ), 'g:i A', 'H:i' ) ) );

	$custom = true;

foreach ( $time_formats as $format ) {
	echo "\t<label><input type='radio' name='time_format' value='" . esc_attr( $format ) . "'";
	if ( get_option( 'time_format' ) === $format ) { // checked() uses "==" rather than "===".
		echo " checked='checked'";
		$custom = false;
	}
	echo ' /> <span class="date-time-text format-i18n">' . date_i18n( $format ) . '</span><code>' . esc_html( $format ) . "</code></label><br />\n";
}

	echo '<label><input type="radio" name="time_format" id="time_format_custom_radio" value="\c\u\s\t\o\m"';
	checked( $custom );
	echo '/> <span class="date-time-text date-time-custom-text">' . __( 'Custom:' ) . '<span class="screen-reader-text"> ' .
			/* translators: Hidden accessibility text. */
			__( 'enter a custom time format in the following field' ) .
		'</span></span></label>' .
		'<label for="time_format_custom" class="screen-reader-text">' .
			/* translators: Hidden accessibility text. */
			__( 'Custom time format:' ) .
		'</label>' .
		'<input type="text" name="time_format_custom" id="time_format_custom" value="' . esc_attr( get_option( 'time_format' ) ) . '" class="small-text" />' .
		'<br />' .
		'<p><strong>' . __( 'Preview:' ) . '</strong> <span class="example">' . date_i18n( get_option( 'time_format' ) ) . '</span>' .
		"<span class='spinner'></span>\n" . '</p>';

	echo "\t<p class='date-time-doc'>" . __( '<a href="https://wordpress.org/documentation/article/customize-date-and-time-format/">Documentation on date and time formatting</a>.' ) . "</p>\n";
?>
	</fieldset>
</td>
</tr>
<tr>
<th scope="row"><label for="start_of_week"><?php _e( 'Week Starts On' ); ?></label></th>
<td><select name="start_of_week" id="start_of_week">
<?php
/**
 * @global WP_Locale $wp_locale WordPress date and time locale object.
 */
global $wp_locale;

for ( $day_index = 0; $day_index <= 6; $day_index++ ) :
	$selected = ( (int) get_option( 'start_of_week' ) === $day_index ) ? 'selected="selected"' : '';
	echo "\n\t<option value='" . esc_attr( $day_index ) . "' $selected>" . $wp_locale->get_weekday( $day_index ) . '</option>';
endfor;
?>
</select></td>
</tr>
<?php do_settings_fields( 'general', 'default' ); ?>
</table>

<?php do_settings_sections( 'general' ); ?>

<?php submit_button(); ?>
</form>

</div>

<?php require_once ABSPATH . 'wp-admin/admin-footer.php'; ?>
