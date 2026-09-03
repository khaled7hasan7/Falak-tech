<?php

declare( strict_types=1 );

require __DIR__ . '/../src/bootstrap.php';

session_boot();

$path = rtrim( parse_url( $_SERVER[ 'REQUEST_URI' ] ?? '/', PHP_URL_PATH ) ?: '/', '/' ) ?: '/';
$post = ( $_SERVER[ 'REQUEST_METHOD' ] ?? 'GET' ) === 'POST';

/* --------------------------------------------------------------------------
 * First run — there is no password and no signing key yet. Everything else is
 * unreachable until both exist, because a console with neither is a console
 * anybody sitting at this machine can mint licences from.
 * ----------------------------------------------------------------------- */

$installed = config_get( 'password' ) !== null;

if ( ! $installed && $path !== '/setup' ) {
    redirect( '/setup' );
}

if ( $path === '/setup' ) {
    if ( $installed && ! signed_in() ) {
        redirect( '/login' );
    }

    $errors = [];

    if ( $post ) {
        csrf_check();

        $password = (string) ( $_POST[ 'password' ] ?? '' );
        $confirm = (string) ( $_POST[ 'password_confirm' ] ?? '' );

        if ( ! $installed ) {
            if ( strlen( $password ) < 8 ) {
                $errors[] = 'كلمة المرور يجب أن تكون ٨ أحرف على الأقل.';
            } elseif ( $password !== $confirm ) {
                $errors[] = 'كلمتا المرور غير متطابقتين.';
            }
        }

        if ( ! $errors && ! Licence::hasKey() ) {
            if ( ( $_POST[ 'key_mode' ] ?? 'import' ) === 'generate' ) {
                [ $private ] = Licence::keygen();
                Licence::importKey( $private );
            } else {
                [ $ok, $reason ] = Licence::importKey( (string) ( $_POST[ 'private_key' ] ?? '' ) );

                if ( ! $ok ) {
                    $errors[] = $reason;
                }
            }
        }

        if ( ! $errors ) {
            if ( ! $installed ) {
                config_put( [
                    'password' => password_hash( $password, PASSWORD_DEFAULT ),
                    'vendor' => trim( (string) ( $_POST[ 'vendor' ] ?? '' ) ) ?: 'Falak',
                    'created_at' => date( 'c' ),
                ] );

                $_SESSION[ 'auth' ] = true;
            }

            flash( 'تم الإعداد. المفتاح العام أدناه — ضعه في ملف ‎.env‎ عند كل عميل.' );
            redirect( '/keys' );
        }
    }

    view( 'setup', [ 'errors' => $errors, 'installed' => $installed ] );
    exit;
}

/* --------------------------------------------------------------------------
 * Sign in.
 * ----------------------------------------------------------------------- */

if ( $path === '/login' ) {
    if ( $post ) {
        csrf_check();

        /**
         * A fixed delay on every attempt. The console is bound to localhost, so
         * this is not defending against the internet — it is defending against
         * somebody sitting at your laptop for ten minutes.
         */
        usleep( 300000 );

        if ( password_verify( (string) ( $_POST[ 'password' ] ?? '' ), (string) config_get( 'password' ) ) ) {
            session_regenerate_id( true );
            $_SESSION[ 'auth' ] = true;

            redirect( '/' );
        }

        view( 'login', [ 'error' => 'كلمة المرور غير صحيحة.' ] );
        exit;
    }

    view( 'login', [ 'error' => null ] );
    exit;
}

if ( $path === '/logout' ) {
    $_SESSION = [];
    session_destroy();

    redirect( '/login' );
}

if ( ! signed_in() ) {
    redirect( '/login' );
}

/* --------------------------------------------------------------------------
 * The register.
 * ----------------------------------------------------------------------- */

if ( $path === '/' ) {
    $clients = Store::clients();

    view( 'dashboard', [
        'clients' => $clients,
        'summary' => Store::summary( $clients ),
    ] );
    exit;
}

if ( $path === '/clients/new' ) {
    if ( $post ) {
        csrf_check();

        $shop = trim( (string) ( $_POST[ 'shop' ] ?? '' ) );

        if ( $shop === '' ) {
            view( 'client-form', [ 'client' => $_POST, 'error' => 'اسم المحل مطلوب.' ] );
            exit;
        }

        $id = Store::addClient( client_input( $shop ) );

        flash( 'أُضيف المحل. أصدر له مفتاحاً الآن.' );
        redirect( '/clients/' . $id );
    }

    view( 'client-form', [ 'client' => null, 'error' => null ] );
    exit;
}

if ( preg_match( '#^/clients/(\d+)$#', $path, $m ) ) {
    $client = Store::client( (int) $m[ 1 ] );

    if ( ! $client ) {
        http_response_code( 404 );
        view( 'notfound', [] );
        exit;
    }

    if ( $post ) {
        csrf_check();

        Store::updateClient(
            (int) $client[ 'id' ],
            client_input( trim( (string) ( $_POST[ 'shop' ] ?? '' ) ) ?: $client[ 'shop' ] )
        );

        flash( 'حُفظت البيانات.' );
        redirect( '/clients/' . $client[ 'id' ] );
    }

    view( 'client', [
        'client' => $client,
        'licences' => Store::licences( (int) $client[ 'id' ] ),
    ] );
    exit;
}

if ( preg_match( '#^/clients/(\d+)/delete$#', $path, $m ) && $post ) {
    csrf_check();

    Store::deleteClient( (int) $m[ 1 ] );

    flash( 'حُذف المحل وسجلّ مفاتيحه.' );
    redirect( '/' );
}

/* --------------------------------------------------------------------------
 * Issuing.
 * ----------------------------------------------------------------------- */

if ( preg_match( '#^/clients/(\d+)/issue$#', $path, $m ) && $post ) {
    csrf_check();

    $client = Store::client( (int) $m[ 1 ] );

    if ( ! $client ) {
        http_response_code( 404 );
        view( 'notfound', [] );
        exit;
    }

    $back = '/clients/' . $client[ 'id' ];

    if ( ! Licence::hasKey() ) {
        flash( 'لا يوجد مفتاح توقيع على هذا الجهاز.', 'bad' );
        redirect( '/keys' );
    }

    $unbound = ! empty( $_POST[ 'unbound' ] );
    $install = $unbound ? '*' : strtoupper( trim( (string) ( $_POST[ 'install_id' ] ?? '' ) ) );

    if ( $install === '' ) {
        flash( 'معرّف التنصيب مطلوب — اقرأه من شاشة الاشتراك عند العميل.', 'bad' );
        redirect( $back );
    }

    /**
     * An install id read down a phone line is the likeliest thing in this
     * whole flow to go wrong, and a wrong one produces a licence that verifies
     * perfectly and still refuses to run — the worst kind of failure to
     * diagnose from a distance. Checking the shape here costs nothing.
     */
    if ( ! $unbound && ! preg_match( '/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $install ) ) {
        flash( 'معرّف التنصيب لا يطابق الصيغة المتوقعة (XXXX-XXXX-XXXX). راجعه مع العميل قبل الإصدار.', 'bad' );
        redirect( $back );
    }

    $length = max( 1, (int) ( $_POST[ 'length' ] ?? 12 ) );
    $unit = ( $_POST[ 'unit' ] ?? 'months' ) === 'days' ? 'days' : 'months';
    $plan = trim( (string) ( $_POST[ 'plan' ] ?? '' ) ) ?: 'annual';
    $shop = trim( (string) ( $_POST[ 'shop' ] ?? '' ) ) ?: $client[ 'shop' ];

    /**
     * Renewing early must not cost the shop the days it has already paid for.
     * If the current licence still has time on it the new one starts where the
     * old one ends, unless you deliberately say otherwise.
     */
    $latest = null;

    foreach ( Store::licences( (int) $client[ 'id' ] ) as $row ) {
        if ( $latest === null || $row[ 'expires_on' ] > $latest ) {
            $latest = $row[ 'expires_on' ];
        }
    }

    $from = today();

    if ( ( $_POST[ 'start' ] ?? 'continue' ) === 'continue' && $latest !== null && $latest > $from->format( 'Y-m-d' ) ) {
        $from = new DateTimeImmutable( $latest );
    }

    $expires = $from->modify( sprintf( '+%d %s', $length, $unit ) );

    try {
        $license = Licence::issue( $shop, $install, $expires->format( 'Y-m-d' ), $plan );
    } catch ( Throwable $e ) {
        flash( $e->getMessage(), 'bad' );
        redirect( '/keys' );
    }

    /** remember the id we actually signed for, so the card is right next time */
    if ( ! $unbound && $install !== (string) ( $client[ 'install_id' ] ?? '' ) ) {
        Store::updateClient( (int) $client[ 'id' ], array_merge( $client, [ 'install_id' => $install ] ) );
    }

    $id = Store::recordLicence( [
        'client_id' => (int) $client[ 'id' ],
        'shop' => $shop,
        'install_id' => $install,
        'plan' => $plan,
        'issued_on' => today()->format( 'Y-m-d' ),
        'expires_on' => $expires->format( 'Y-m-d' ),
        'amount' => trim( (string) ( $_POST[ 'amount' ] ?? '' ) ),
        'license' => $license,
    ] );

    redirect( '/licence/' . $id );
}

if ( preg_match( '#^/licence/(\d+)$#', $path, $m ) ) {
    $licence = Store::licence( (int) $m[ 1 ] );

    if ( ! $licence ) {
        http_response_code( 404 );
        view( 'notfound', [] );
        exit;
    }

    view( 'licence', [
        'licence' => $licence,
        'client' => Store::client( (int) $licence[ 'client_id' ] ),
    ] );
    exit;
}

/* --------------------------------------------------------------------------
 * Support tools.
 * ----------------------------------------------------------------------- */

if ( $path === '/verify' ) {
    $input = '';
    $result = null;

    if ( $post ) {
        csrf_check();

        $input = trim( (string) ( $_POST[ 'license' ] ?? '' ) );
        $result = $input === '' ? null : Licence::inspect( $input );
    }

    view( 'verify', [ 'result' => $result, 'input' => $input ] );
    exit;
}

if ( $path === '/keys' ) {
    view( 'keys', [
        'public' => Licence::publicKey(),
        'path' => str_replace( '\\', '/', realpath( FALAK_KEY ) ?: FALAK_KEY ),
    ] );
    exit;
}

http_response_code( 404 );
view( 'notfound', [] );

/* ----------------------------------------------------------------------- */

function client_input( string $shop ): array
{
    return [
        'shop' => $shop,
        'contact' => trim( (string) ( $_POST[ 'contact' ] ?? '' ) ),
        'phone' => trim( (string) ( $_POST[ 'phone' ] ?? '' ) ),
        'city' => trim( (string) ( $_POST[ 'city' ] ?? '' ) ),
        'install_id' => strtoupper( trim( (string) ( $_POST[ 'install_id' ] ?? '' ) ) ),
        'notes' => trim( (string) ( $_POST[ 'notes' ] ?? '' ) ),
    ];
}
