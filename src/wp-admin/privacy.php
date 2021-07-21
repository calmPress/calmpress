<?php
/**
 * Privacy administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

$title = __( 'Privacy' );

list( $display_version ) = explode( '-', get_bloginfo( 'version' ) );

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap about__container">

	<div class="about__header">
		<div class="about__header-title">
			<h1>
				<?php _e( 'Privacy' ); ?>
			</h1>
		</div>

		<div class="about__header-text">
			<?php _e( 'We take privacy and transparency very seriously' ); ?>
		</div>

	</div>

	<div class="about__section has-2-columns is-wider-right">
		<div class="column about__image">
			<img class="privacy-image" src="<?php echo esc_url( admin_url( 'images/privacy.svg' ) ); ?>" alt="" />
		</div>
		<div class="column is-vertically-aligned-center">
			<p><?php _e( 'From time to time, your WordPress site may send data to WordPress.org &#8212; including, but not limited to &#8212; the version of WordPress you are using, and a list of installed plugins and themes.' ); ?></p>

			<p>
				<?php
				printf(
					/* translators: %s: https://wordpress.org/about/stats/ */
					__( 'This data is used to provide general enhancements to WordPress, which includes helping to protect your site by finding and automatically installing new updates. It is also used to calculate statistics, such as those shown on the <a href="%s">WordPress.org stats page</a>.' ),
					__( 'https://wordpress.org/about/stats/' )
				);
				?>
			</p>

			<p>
				<?php
				printf(
					/* translators: %s: https://wordpress.org/about/privacy/ */
					__( 'We take privacy and transparency very seriously. To learn more about what data we collect, and how we use it, please visit <a href="%s">our Privacy Policy</a>.' ),
					__( 'https://wordpress.org/about/privacy/' )
				);
				?>
			</p>
		</div>
	</div>

</div>
<?php require_once ABSPATH . 'wp-admin/admin-footer.php'; ?>
