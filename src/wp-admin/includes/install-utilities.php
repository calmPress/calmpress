<?php
/**
 * Shared installer utilities for credentials, directories, and configuration files.
 *
 * @package calmPress
 * @since calmPress 1.0.0
 */

declare(strict_types=1);

/**
 * Build installation cookie options with the supplied expiration,
 * this installation's admin path, and Secure, HttpOnly, and SameSite attributes.
 *
 * @since calmPress 1.0.0
 *
 * @param int $expires Cookie expiration timestamp.
 *
 * @return array Cookie attributes.
 */
function installation_cookie_options( int $expires ): array {
	$path = wp_parse_url( wp_guess_url(), PHP_URL_PATH );

	return [
		'expires'  => $expires,
		'path'     => rtrim( $path ?? '', '/' ) . '/wp-admin/',
		'secure'   => true,
		'httponly' => true,
		'samesite' => 'Strict',
	];
}

/**
 * Write an installation file through a temporary file, set permissions to 0644, and replace its destination.
 *
 * Failures include the reported filesystem error when available, with credentials removed from stream URLs.
 *
 * @since calmPress 1.0.0
 *
 * @param \calmpress\filesystem\Filesystem_Access $filesystem_access Access used to write and replace the file.
 * @param string                                  $file              Absolute destination path.
 * @param string                                  $contents          Contents to write.
 *
 * @return WP_Error Empty on success, or the failure preventing the update.
 */
function write_installation_file( \calmpress\filesystem\Filesystem_Access $filesystem_access, string $file, string $contents ): WP_Error {
	$temporary_file = dirname( $file ) . '/.calmpress-install-' . wp_generate_uuid4() . '.php';
	$updated        = false;
	$reported_error = '';

	// Write a separate file so a failed write cannot damage the existing destination.
	try {
		$url           = $filesystem_access->stream_url_from_path( $file );
		$temporary_url = $filesystem_access->stream_url_from_path( $temporary_file );
		$context       = $filesystem_access->stream_context();

		error_clear_last();
		$written = @file_put_contents( $temporary_url, $contents, 0, $context );

		if ( $written ) {
			if ( $filesystem_access->change_permissions( $temporary_file, 0644 ) ) {

				// Replace the destination after writing the contents and setting their permissions.
				$updated = @rename( $temporary_url, $url, $context );
			}
		}

		if ( ! $updated ) {

			// Capture the failed operation's warning before cleanup can replace it.
			$reported_error = error_get_last()['message'] ?? '';
			@unlink( $temporary_url, $context );
		}
	} catch ( \Throwable $exception ) {
		if ( '' === $reported_error ) {
			$reported_error = $exception->getMessage();
		}
	}

	if ( $updated ) {
		return new WP_Error();
	}

	$message = sprintf(
		/* translators: %s: Installation file name. */
		__( '%s could not be written.' ),
		basename( $file )
	);

	if ( '' !== $reported_error ) {

		// FTP stream warnings can contain URLs with the account's username and password.
		$reported_error = preg_replace( '~((?:ftp|sftp)://)[^/\s]*@~i', '$1', $reported_error );
		$message       .= ' ' . sprintf(
			/* translators: %s: Error reported by the filesystem operation. */
			__( 'Reported error: %s' ),
			$reported_error
		);
	}

	return new WP_Error( 'installation_file_write_failed', $message );
}

/**
 * Ensure PHP can store and manage runtime files in named subdirectories of a root directory.
 *
 * Existing directories are removed, then directories are created by PHP so the web-server account owns them.
 *
 * If the root is not writable by PHP, the supplied filesystem access temporarily grants
 * write access for the creation loop, then restores the root's original permissions.
 *
 * After restoring permissions, each directory is checked by listing it and creating,
 * writing, and deleting a temporary file through PHP.
 *
 * @since calmPress 1.0.0
 *
 * @param \calmpress\filesystem\Filesystem_Access $credentials    Filesystem access used to change root directory permissions.
 * @param string                              $root           Absolute path to the existing root directory.
 * @param string[]                            $subdirectories Subdirectory names or paths relative to the root.
 *
 * @return \WP_Error Collected creation, permission, or PHP access errors; empty when all directories are ready.
 *
 * @throws \DomainException If the supplied root is not an existing directory.
 * @throws \RuntimeException If root permissions cannot be read or a missing directory cannot be created.
 */
function create_directories_writable_by_web_server( \calmpress\filesystem\Filesystem_Access $credentials, string $root, array $subdirectories ): \WP_Error {
	$subdirectories = array_unique( $subdirectories );

	if ( ! is_dir( $root ) ) {
		throw new \DomainException( 'Runtime directory root must be an existing directory: ' . $root );
	}

	$errors = new \WP_Error();
	$root   = rtrim( $root, '/\\' );
	clearstatcache( true, $root );

	if ( is_writable( $root ) ) {
		$errors = create_subdirectories_in_writable_root( $root, $subdirectories );
	} else {
		$permissions = @fileperms( $root );

		if ( false === $permissions ) {
			throw new \RuntimeException( 'Failed to read permissions for runtime directory root: ' . $root );
		}

		$permissions &= 07777;

		try {
			$access_granted = $credentials->change_permissions( $root, $permissions | 0777 );
		} catch ( \calmpress\filesystem\Filesystem_Access_Exception $error ) {
			$access_granted = false;
		}

		if ( ! $access_granted ) {

			/* translators: %s: Root directory path. */
			$errors->add( 'runtime_parent_permissions_change_failed', sprintf( __( 'Failed to grant the web server temporary write access to directory: %s.' ), $root ) );
			return $errors;
		}

		try {
			$errors = create_subdirectories_in_writable_root( $root, $subdirectories );
		} finally {

			// Restore the original mode even if creating directories fails.
			try {
				$restored = $credentials->change_permissions( $root, $permissions );
			} catch ( \calmpress\filesystem\Filesystem_Access_Exception $error ) {
				$restored = false;
			}

			clearstatcache( true, $root );
			$actual_permissions = @fileperms( $root );

			if ( false === $actual_permissions ) {
				$restored = false;
			} elseif ( ( $actual_permissions & 07777 ) !== $permissions ) {
				$restored = false;
			}

			if ( ! $restored ) {

				/* translators: 1: Directory path, 2: Unix permissions in octal notation. */
				$errors->add( 'runtime_parent_permissions_restore_failed', sprintf( __( 'Ownership and/or permissions for directory %1$s must be corrected to restore its original protection before installation can continue. Original permissions: %2$s.' ), $root, sprintf( '%04o', $permissions ) ) );
			}
		}
	}

	if ( $errors->has_errors() ) {
		return $errors;
	}

	// Test access with the root's original permissions back in effect.
	foreach ( $subdirectories as $subdirectory ) {
		$directory = $root . '/' . $subdirectory;
		$errors->merge_from( check_directory_writable_by_web_server( $directory ) );
	}

	return $errors;
}

/**
 * Recreate named subdirectories through PHP inside a writable root directory.
 *
 * Stop with an error if an existing path is not a directory.
 *
 * @since calmPress 1.0.0
 *
 * @param string    $root           Absolute path to the writable root directory.
 * @param string[]  $subdirectories Subdirectory names or paths relative to the root.
 *
 * @return \WP_Error An existing-path error, or an empty error object on success.
 *
 * @throws \RuntimeException If a missing directory cannot be created.
 */
function create_subdirectories_in_writable_root( string $root, array $subdirectories ): \WP_Error {
	$errors = new \WP_Error();

	// Replace existing directories and reject existing non-directory paths.
	foreach ( $subdirectories as $subdirectory ) {
		$directory = $root . '/' . $subdirectory;
		clearstatcache( true, $directory );

		if ( is_dir( $directory ) ) {
			try {

				// A new installation must not reuse uploads, cache, or private state from an earlier site.
				\calmpress\utils\delete_directory( $directory );
			} catch ( \RuntimeException $error ) {

				/* translators: %s: Directory that must be removed before installation can continue. */
				$errors->add( 'runtime_directory_removal_failed', sprintf( __( 'Directory %s could not be removed. Remove it before retrying installation.' ), $directory ) );
				return $errors;
			}

			clearstatcache( true, $directory );
		}

		if ( file_exists( $directory ) ) {

			/* translators: %s: Directory path. */
			$errors->add( 'runtime_path_not_directory', sprintf( __( 'The path %s already exists and is not a directory.' ), $directory ) );
			return $errors;

		} elseif ( ! @mkdir( $directory, 0755, true ) ) {
			throw new \RuntimeException( 'Failed to create runtime directory: ' . $directory );
		}
	}

	return $errors;
}

/**
 * Check whether a directory is writable by the web-server user.
 *
 * Verify access by listing the directory and creating, writing, and deleting a
 * temporary file inside it.
 *
 * @since calmPress 1.0.0
 *
 * @param string $directory Absolute directory path.
 *
 * @return \WP_Error Access errors, or an empty error object when all checks succeed.
 */
function check_directory_writable_by_web_server( string $directory ): \WP_Error {
	$errors = new \WP_Error();

	$listing = @opendir( $directory );

	if ( false === $listing ) {

		/* translators: %s: Directory path. */
		$errors->add( 'runtime_directory_not_readable', sprintf( __( 'The web server cannot read directory: %s. Check its ownership and permissions.' ), $directory ) );
		return $errors;
	}

	closedir( $listing );
	$probe  = rtrim( $directory, '/\\' ) . '/.calmpress-access-' . wp_generate_uuid4();
	$stream = @fopen( $probe, 'xb' );

	if ( false === $stream ) {

		/* translators: %s: Directory path. */
		$errors->add( 'runtime_directory_file_creation_failed', sprintf( __( 'The web server cannot create files in directory: %s. Check its ownership and permissions.' ), $directory ) );
		return $errors;
	}

	$written = fwrite( $stream, 'calmPress' );
	fclose( $stream );
	@unlink( $probe );

	if ( false === $written ) {
		$errors->add( 'runtime_directory_not_writable', sprintf( __( 'The web server cannot read and write to directory: %s. Check its ownership and permissions.' ), $directory ) );
	}

	return $errors;
}

/**
 * Create a directory while trying to prevent the web server from writing to it.
 *
 * @since calmPress 1.0.0
 *
 * @param \calmpress\filesystem\Filesystem_Access $filesystem_access Filesystem access supplied for installation.
 * @param string                                    $directory         Absolute directory path.
 *
 * @return \WP_Error Directory creation, permission, or read-access problems.
 *
 * @throws \DomainException If the directory path is invalid.
 * @throws \RuntimeException If filesystem access fails.
 */
function create_directory_protected_from_web_server_writes( \calmpress\filesystem\Filesystem_Access $filesystem_access, string $directory ): \WP_Error {
	$errors = new \WP_Error();
	clearstatcache( true, $directory );

	if ( is_dir( $directory ) ) {

		/*
		 * A writable directory under a protected parent may be left over from an earlier installation.
		 *
		 * Its ownership or permissions need correction to prevent the web server from changing files inside it.
		 */
		if ( ! is_writable( dirname( $directory ) ) && is_writable( $directory ) ) {

			/* translators: %s: Directory path. */
			$errors->add( 'protected_directory_writable', sprintf( __( 'Ownership and/or permissions for directory %s must allow the web server to read it while keeping it protected from web-server writes before installation can continue.' ), $directory ) );
		}

		return $errors;
	}

	$url     = $filesystem_access->stream_url_from_path( $directory );
	$context = $filesystem_access->stream_context();

	if ( ! @mkdir( $url, 0755, true, $context ) ) {

		/* translators: %s: Directory path. */
		$errors->add( 'protected_directory_creation_failed', sprintf( __( 'Failed to create directory: %s. Check its path and permissions.' ), $directory ) );
		return $errors;
	}

	// Set permissions explicitly because directory creation may use the server's defaults.
	if ( ! $filesystem_access->change_permissions( $directory, 0755 ) ) {

		/* translators: %s: Directory path. */
		$errors->add( 'protected_directory_permissions_failed', sprintf( __( 'Ownership and/or permissions for directory %s must allow the web server to read it while keeping it protected from web-server writes before installation can continue.' ), $directory ) );
		return $errors;
	}

	clearstatcache( true, $directory );

	if ( ! is_readable( $directory ) ) {

		/* translators: %s: Directory path. */
		$errors->add( 'protected_directory_not_readable', sprintf( __( 'The web server cannot read directory: %s. Check its ownership and permissions.' ), $directory ) );
	}

	return $errors;
}

/**
 * Generate the network constants for the selected installation type.
 *
 * @since calmPress 1.0.0
 *
 * @param string $install_type One of 'standalone', 'network_subdirectory', or 'network_subdomain'.
 * @param string $domain       Installation hostname.
 * @param string $path         Installation URL path, with leading and trailing slashes.
 *
 * @return string PHP statements for the configuration template.
 *
 * @throws UnexpectedValueException If the installation type is unsupported.
 */
function installation_network_settings( string $install_type, string $domain, string $path ): string {
	if ( ! in_array( $install_type, [ 'standalone', 'network_subdirectory', 'network_subdomain' ], true ) ) {
		throw new UnexpectedValueException( 'Unsupported installation type.' );
	}

	$domain = addcslashes( $domain, "\\'" );
	$path   = addcslashes( $path, "\\'" );

	$multisite_constants = match ( $install_type ) {
		'network_subdirectory' => sprintf(
			"define( 'MULTISITE', true );\n" .
			"define( 'SUBDOMAIN_INSTALL', false );\n" .
			"define( 'DOMAIN_CURRENT_SITE', '%s' );\n" .
			"define( 'PATH_CURRENT_SITE', '%s' );\n" .
			"define( 'SITE_ID_CURRENT_SITE', 1 );\n" .
			"define( 'BLOG_ID_CURRENT_SITE', 1 );\n",
			$domain,
			$path
		),
		'network_subdomain'    => sprintf(
			"define( 'MULTISITE', true );\n" .
			"define( 'SUBDOMAIN_INSTALL', true );\n" .
			"define( 'DOMAIN_CURRENT_SITE', '%s' );\n" .
			"define( 'PATH_CURRENT_SITE', '/' );\n" .
			"define( 'SITE_ID_CURRENT_SITE', 1 );\n" .
			"define( 'BLOG_ID_CURRENT_SITE', 1 );\n",
			$domain
		),
		default                => "// None.\n",
	};

	return $multisite_constants;
}

/**
 * Generate .htaccess contents for the selected installation type.
 *
 * @since calmPress 1.0.0
 *
 * @param string $install_type One of 'standalone', 'network_subdirectory', or 'network_subdomain'.
 * @param string $path         Installation URL path, with leading and trailing slashes.
 *
 * @return string Configuration contents including the selected rewrite rules.
 *
 * @throws UnexpectedValueException If the installation type is unsupported.
 */
function installation_htaccess_contents( string $install_type, string $path ): string {
	if ( ! in_array( $install_type, [ 'standalone', 'network_subdirectory', 'network_subdomain' ], true ) ) {
		throw new UnexpectedValueException( 'Unsupported installation type.' );
	}

	$htaccess_rewrite     = match ( $install_type ) {
		'network_subdirectory' => <<<HTACCESS
	RewriteEngine On
	RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
	RewriteBase {$path}
	RewriteRule ^index\.php$ - [L]
	RewriteRule (^|/)\. - [F]
	RewriteRule ^(?:[_0-9a-zA-Z-]+/)?wp-includes/.*\.php$ - [F]
	RewriteRule ^(?:[_0-9a-zA-Z-]+/)?wp-content/.*\.php$ - [F]
	RewriteRule ^([_0-9a-zA-Z-]+/)?wp-admin$ \$1wp-admin/ [R=301,L]
	RewriteCond %{REQUEST_FILENAME} -f [OR]
	RewriteCond %{REQUEST_FILENAME} -d
	RewriteRule ^ - [L]
	RewriteRule ^[_0-9a-zA-Z-]+/(wp-(content|admin|includes).*) {$path}\$1 [L]
	RewriteRule ^[_0-9a-zA-Z-]+/(.*\.php)$ {$path}\$1 [L]
	RewriteRule . {$path}index.php [L]
	HTACCESS,
		'network_subdomain'    => <<<HTACCESS
	RewriteEngine On
	RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
	RewriteBase /
	RewriteRule ^index\.php$ - [L]
	RewriteRule (^|/)\. - [F]
	RewriteRule ^wp-includes/(.*)\.php$ - [F]
	RewriteRule ^wp-content/(.*)\.php$ - [F]
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteRule . index.php [L]
	HTACCESS,
		default                => <<<HTACCESS
	RewriteEngine On
	RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
	RewriteBase {$path}
	RewriteRule ^index\.php$ - [L]
	RewriteRule (^|/)\. - [F]
	RewriteRule ^wp-includes/(.*)\.php$ - [F]
	RewriteRule ^wp-content/(.*)\.php$ - [F]
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteRule . {$path}index.php [L]
	HTACCESS
	};

	return str_replace( '# REWRITE_PLACE_HOLDER', $htaccess_rewrite, file_get_contents( ABSPATH . 'wp-admin/.htaccess.sample' ) );
}

