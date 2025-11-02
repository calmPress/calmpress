<?php
/**
 * Implementation of utilities functions for QR code generation.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\QR;

use chillerlan\QRCode\{QRCode, QROptions};

/**
 * An implementation of utility functions for QR code generation.
 *
 * @since 1.0.0
 */
class Utils {

	/**
	 * Generate an img QR for a specific URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL for which to generate the QR code image.
	 * 
	 * @return string The IMG element for the QR code.
	 */
	public static function image_data_url_for_url( string $url ): string {
		return (new QRCode)->render( $url );
	}

}
