<?php

declare( strict_types=1 );

/**
 * Signing and reading a Falak licence.
 *
 * The format is deliberately identical to app/Console/Commands/FalakLicenseIssueCommand.php
 * in the product — same payload keys, same order, same JSON flags. That is not
 * a nicety: the signature covers the encoded bytes, so a different key order
 * here would produce licences the shop's FalakLicenseService rejects as
 * forged. If you ever change one side, change both.
 *
 *     FALAK1.<base64url(json payload)>.<base64url(ed25519 signature)>
 */
final class Licence
{
    /**
     * Make a new signing pair. Returns [private_b64, public_b64].
     */
    public static function keygen(): array
    {
        $pair = sodium_crypto_sign_keypair();

        return [
            base64_encode( sodium_crypto_sign_secretkey( $pair ) ),
            base64_encode( sodium_crypto_sign_publickey( $pair ) ),
        ];
    }

    public static function hasKey(): bool
    {
        return is_file( FALAK_KEY ) && self::secret() !== null;
    }

    /**
     * The private key bytes, or null if it is absent or corrupt.
     */
    public static function secret(): ?string
    {
        if ( ! is_file( FALAK_KEY ) ) {
            return null;
        }

        $raw = base64_decode( trim( (string) file_get_contents( FALAK_KEY ) ), true );

        if ( $raw === false || strlen( $raw ) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES ) {
            return null;
        }

        return $raw;
    }

    /**
     * The public half, derived from the private key every time rather than
     * stored — so the two can never drift apart and hand you a public key
     * that does not match the licences you are signing.
     */
    public static function publicKey(): ?string
    {
        $secret = self::secret();

        return $secret === null
            ? null
            : base64_encode( sodium_crypto_sign_publickey_from_secretkey( $secret ) );
    }

    /**
     * Store a private key, refusing anything that is not one.
     */
    public static function importKey( string $base64 ): array
    {
        $raw = base64_decode( trim( $base64 ), true );

        if ( $raw === false || strlen( $raw ) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES ) {
            return [ false, 'هذا ليس مفتاحاً خاصاً صالحاً (المتوقع ' . SODIUM_CRYPTO_SIGN_SECRETKEYBYTES . ' بايت بصيغة base64).' ];
        }

        file_put_contents( FALAK_KEY, base64_encode( $raw ) );
        @chmod( FALAK_KEY, 0600 );

        return [ true, null ];
    }

    /**
     * Sign a licence.
     *
     * $install is the id from the shop's own subscription screen. "*" issues
     * one that runs on any install, which is right for a demo and wrong for a
     * sale — a single unbound licence can be copied around a dozen groceries.
     */
    public static function issue( string $shop, string $install, string $expires, string $plan = 'annual' ): string
    {
        $secret = self::secret();

        if ( $secret === null ) {
            throw new RuntimeException( 'لا يوجد مفتاح خاص.' );
        }

        $payload = [
            'v' => 1,
            'shop' => $shop,
            'install' => $install,
            'issued' => today()->format( 'Y-m-d' ),
            'expires' => $expires,
            'plan' => $plan,
        ];

        /** the exact bytes that are signed are the exact bytes that are sent */
        $body = json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        $signature = sodium_crypto_sign_detached( $body, $secret );

        return 'FALAK1.' . self::b64( $body ) . '.' . self::b64( $signature );
    }

    /**
     * Read a licence back and say whether it is ours.
     *
     * This is the support tool. When a shopkeeper says "the key you sent does
     * not work", paste it here: it tells you whether the string survived
     * WhatsApp intact, which install it is bound to, and when it runs out —
     * which is nearly always the answer.
     *
     * @return array{ok: bool, reason: ?string, payload: ?array}
     */
    public static function inspect( string $license ): array
    {
        $license = trim( $license );
        $public = self::publicKey();

        if ( $public === null ) {
            return [ 'ok' => false, 'reason' => 'لا يوجد مفتاح خاص على هذا الجهاز للتحقق به.', 'payload' => null ];
        }

        $parts = explode( '.', $license );

        if ( count( $parts ) !== 3 || $parts[ 0 ] !== 'FALAK1' ) {
            return [ 'ok' => false, 'reason' => 'الصيغة غير صحيحة — يجب أن يبدأ بـ FALAK1 ويتكوّن من ثلاثة أجزاء. غالباً نُسخ ناقصاً.', 'payload' => null ];
        }

        $body = self::unb64( $parts[ 1 ] );
        $signature = self::unb64( $parts[ 2 ] );

        if ( $body === null || $signature === null || strlen( $signature ) !== SODIUM_CRYPTO_SIGN_BYTES ) {
            return [ 'ok' => false, 'reason' => 'المفتاح تالف أو منقوص — أعد إرساله كاملاً.', 'payload' => null ];
        }

        if ( ! sodium_crypto_sign_verify_detached( $signature, $body, (string) base64_decode( $public, true ) ) ) {
            return [ 'ok' => false, 'reason' => 'التوقيع لا يطابق مفتاحك. هذا المفتاح لم يصدر عن هذا الجهاز، أو جرى تعديله.', 'payload' => null ];
        }

        $payload = json_decode( $body, true );

        if ( ! is_array( $payload ) ) {
            return [ 'ok' => false, 'reason' => 'محتوى المفتاح غير مقروء.', 'payload' => null ];
        }

        return [ 'ok' => true, 'reason' => null, 'payload' => $payload ];
    }

    private static function b64( string $raw ): string
    {
        return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
    }

    private static function unb64( string $value ): ?string
    {
        $decoded = base64_decode( strtr( $value, '-_', '+/' ), true );

        return $decoded === false ? null : $decoded;
    }
}
