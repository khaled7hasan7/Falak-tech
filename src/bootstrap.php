<?php

/**
 * Falak Console — the vendor's side of the subscription.
 *
 * This runs on YOUR machine and nowhere else. It holds the Ed25519 private key
 * that signs every customer licence, which makes this directory the single
 * most valuable thing in the business: whoever has data/private.key can mint
 * free licences for every shop you have ever sold to, and there is no way to
 * revoke that except visiting each shop and changing its public key by hand.
 *
 * Which is why it lives OUTSIDE the NexoPOS working tree. deploy/update.sh
 * pulls origin/main onto customer servers; anything inside that repository
 * eventually lands on a customer's disk. This does not.
 */

declare( strict_types=1 );

const FALAK_ROOT = __DIR__ . '/..';

/**
 * Where the register and the signing key live. Overridable so the whole of it
 * can sit on an encrypted volume, or on a USB key you carry — nothing here
 * assumes the data is beside the code.
 */
define( 'FALAK_DATA', rtrim( (string) ( getenv( 'FALAK_DATA_DIR' ) ?: FALAK_ROOT . '/data' ), "/\\" ) );

const FALAK_CONFIG = FALAK_DATA . '/config.json';
const FALAK_KEY = FALAK_DATA . '/private.key';
const FALAK_DB = FALAK_DATA . '/console.sqlite';

require __DIR__ . '/Store.php';
require __DIR__ . '/Licence.php';

if ( ! is_dir( FALAK_DATA ) ) {
    mkdir( FALAK_DATA, 0700, true );
}

/* --------------------------------------------------------------------------
 * Config — a small JSON file rather than a table, because the password hash
 * has to be readable before the database is opened.
 * ----------------------------------------------------------------------- */

function config_all(): array
{
    if ( ! is_file( FALAK_CONFIG ) ) {
        return [];
    }

    $raw = json_decode( (string) file_get_contents( FALAK_CONFIG ), true );

    return is_array( $raw ) ? $raw : [];
}

function config_get( string $key, mixed $default = null ): mixed
{
    return config_all()[ $key ] ?? $default;
}

function config_put( array $values ): void
{
    $merged = array_merge( config_all(), $values );

    file_put_contents( FALAK_CONFIG, json_encode( $merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
    @chmod( FALAK_CONFIG, 0600 );
}

/* --------------------------------------------------------------------------
 * Session and CSRF.
 * ----------------------------------------------------------------------- */

function session_boot(): void
{
    if ( session_status() === PHP_SESSION_ACTIVE ) {
        return;
    }

    session_set_cookie_params( [
        'lifetime' => 60 * 60 * 24 * 30,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ] );

    session_name( 'falak_console' );
    session_start();
}

function csrf(): string
{
    if ( empty( $_SESSION[ 'csrf' ] ) ) {
        $_SESSION[ 'csrf' ] = bin2hex( random_bytes( 32 ) );
    }

    return $_SESSION[ 'csrf' ];
}

function csrf_check(): void
{
    $sent = (string) ( $_POST[ '_token' ] ?? '' );

    if ( ! hash_equals( (string) ( $_SESSION[ 'csrf' ] ?? '' ), $sent ) ) {
        http_response_code( 419 );
        exit( 'انتهت الجلسة. أعد تحميل الصفحة.' );
    }
}

function signed_in(): bool
{
    return ! empty( $_SESSION[ 'auth' ] );
}

/* --------------------------------------------------------------------------
 * Views.
 * ----------------------------------------------------------------------- */

function e( ?string $value ): string
{
    return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function view( string $name, array $data = [] ): void
{
    extract( $data, EXTR_SKIP );

    ob_start();
    require FALAK_ROOT . '/views/' . $name . '.php';
    $content = ob_get_clean();

    require FALAK_ROOT . '/views/layout.php';
}

function redirect( string $to ): never
{
    header( 'Location: ' . $to );
    exit;
}

function flash( ?string $message = null, string $kind = 'ok' ): ?array
{
    if ( $message !== null ) {
        $_SESSION[ 'flash' ] = [ 'message' => $message, 'kind' => $kind ];

        return null;
    }

    $out = $_SESSION[ 'flash' ] ?? null;
    unset( $_SESSION[ 'flash' ] );

    return $out;
}

/* --------------------------------------------------------------------------
 * Dates. Everything the console shows is a plain Y-m-d in the local timezone;
 * a subscription is measured in days, and an hour either way never matters.
 * ----------------------------------------------------------------------- */

function today(): DateTimeImmutable
{
    return new DateTimeImmutable( 'today' );
}

function days_until( ?string $date ): ?int
{
    if ( ! $date ) {
        return null;
    }

    try {
        $target = new DateTimeImmutable( $date );
    } catch ( Throwable ) {
        return null;
    }

    return (int) today()->diff( $target->setTime( 0, 0 ) )->format( '%r%a' );
}

/**
 * "9 أيام", "20 يوماً" — Arabic counts days differently at 1, 2, 3–10 and 11+,
 * and getting it wrong is the kind of small wrongness a shopkeeper reads as
 * carelessness. $oblique gives the accusative dual for phrases like "منذ يومين".
 */
function days_word( int $n, bool $oblique = false ): string
{
    $n = abs( $n );

    return match ( true ) {
        $n === 0 => 'اليوم',
        $n === 1 => 'يوم واحد',
        $n === 2 => $oblique ? 'يومين' : 'يومان',
        $n <= 10 => $n . ' أيام',
        default => $n . ' يوماً',
    };
}

/**
 * How a shop stands, in one word, from the days it has left.
 *
 * These thresholds mirror config/falak.php on the customer side — 14 days of
 * warning, 7 of grace after expiry — so what you read here is what the
 * shopkeeper is seeing on their own screen.
 */
function standing( ?int $days ): array
{
    if ( $days === null ) {
        return [ 'key' => 'none', 'label' => 'لم يُصدَر مفتاح', 'tone' => 'idle' ];
    }

    if ( $days < -7 ) {
        return [ 'key' => 'locked', 'label' => 'مقفل', 'tone' => 'bad' ];
    }

    if ( $days < 0 ) {
        return [ 'key' => 'grace', 'label' => 'مهلة سماح', 'tone' => 'bad' ];
    }

    if ( $days <= 14 ) {
        return [ 'key' => 'soon', 'label' => 'ينتهي قريباً', 'tone' => 'warn' ];
    }

    return [ 'key' => 'active', 'label' => 'ساري', 'tone' => 'good' ];
}
