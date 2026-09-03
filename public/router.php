<?php

/**
 * Router for PHP's built-in server.
 *
 * Static files under public/ are served as they are; everything else goes to
 * the front controller. The realpath check is what stops a request for
 * /../data/private.key walking out of the document root and handing the
 * signing key to anyone who can reach the port.
 */

declare( strict_types=1 );

$root = __DIR__;
$path = parse_url( $_SERVER[ 'REQUEST_URI' ] ?? '/', PHP_URL_PATH ) ?: '/';
$candidate = realpath( $root . urldecode( $path ) );

if ( $candidate !== false && is_file( $candidate ) && str_starts_with( $candidate, $root . DIRECTORY_SEPARATOR ) ) {
    /** index.php is the application, never a downloadable file */
    if ( basename( $candidate ) !== 'router.php' && basename( $candidate ) !== 'index.php' ) {
        return false;
    }
}

require $root . '/index.php';
