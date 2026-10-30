<?php
/**
 * Parsed connection credential parameters.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\credentials;

/**
 * Common return type for parsed connection credentials.
 *
 * A credentials form parser returns either a WP_Error or a parameter object for
 * its selected protocol. FTP and SFTP, for example, require different fields, so this interface
 * lets Connection_Credentials declare the successful return type without naming
 * either protocol's parameter class. It has no methods because those fields are
 * specific to each protocol.
 *
 * @since 1.0.0
 */
interface Connection_Credentials_Parameters {
}
