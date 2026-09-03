<?php

declare( strict_types=1 );

/**
 * The register of shops and the licences issued to them.
 *
 * Every licence ever signed is kept, not just the current one. That history is
 * what lets you answer "when did I last renew them?" and "what did they pay?"
 * a year later, and it costs nothing — a busy vendor signs a few hundred rows
 * in a lifetime.
 */
final class Store
{
    private static ?PDO $pdo = null;

    public static function db(): PDO
    {
        if ( self::$pdo instanceof PDO ) {
            return self::$pdo;
        }

        $pdo = new PDO( 'sqlite:' . FALAK_DB, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ] );

        $pdo->exec( 'PRAGMA journal_mode = WAL' );
        $pdo->exec( 'PRAGMA foreign_keys = ON' );

        $pdo->exec( <<<SQL
            CREATE TABLE IF NOT EXISTS clients (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                shop        TEXT NOT NULL,
                contact     TEXT,
                phone       TEXT,
                city        TEXT,
                install_id  TEXT,
                notes       TEXT,
                created_at  TEXT NOT NULL
            )
        SQL );

        $pdo->exec( <<<SQL
            CREATE TABLE IF NOT EXISTS licences (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id   INTEGER NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
                shop        TEXT NOT NULL,
                install_id  TEXT NOT NULL,
                plan        TEXT NOT NULL,
                issued_on   TEXT NOT NULL,
                expires_on  TEXT NOT NULL,
                amount      TEXT,
                license     TEXT NOT NULL,
                created_at  TEXT NOT NULL
            )
        SQL );

        $pdo->exec( 'CREATE INDEX IF NOT EXISTS licences_client ON licences (client_id, expires_on DESC)' );

        /**
         * What each shop last said about itself.
         *
         * One row per install, replaced each time — this is a "where is
         * everything right now" table, not a log. A history of heartbeats
         * would grow without bound on a machine nobody administers, to answer
         * a question nobody asks: what matters is whether a shop is alive
         * today and which version it took, not that it was alive at 04:17 on
         * a Tuesday in March.
         *
         * Keyed by install id rather than client id because a report arrives
         * before anyone has matched it to a client — a shop being set up, or
         * one whose install id changed. Unmatched rows are worth seeing;
         * silently dropping them would hide exactly the case worth noticing.
         */
        $pdo->exec( <<<SQL
            CREATE TABLE IF NOT EXISTS reports (
                install_id  TEXT PRIMARY KEY,
                shop        TEXT,
                state       TEXT,
                expires_on  TEXT,
                days        INTEGER,
                plan        TEXT,
                version     TEXT,
                commit_ref  TEXT,
                shop_time   TEXT,
                timezone    TEXT,
                seen_at     TEXT NOT NULL,
                first_seen  TEXT NOT NULL,
                seen_count  INTEGER NOT NULL DEFAULT 1
            )
        SQL );

        return self::$pdo = $pdo;
    }

    /**
     * Every shop, with the licence that currently governs it.
     *
     * "Current" is the one that runs out last, not the one signed last — if
     * you re-issue an old key to fix a typo, the shop is still covered by the
     * longer one, and the dashboard should say so.
     */
    public static function clients(): array
    {
        return self::db()->query( <<<SQL
            SELECT  c.*,
                    l.expires_on,
                    l.issued_on,
                    l.plan,
                    l.install_id AS licensed_install
            FROM    clients c
            LEFT JOIN licences l
                   ON l.id = (
                        SELECT id FROM licences
                        WHERE client_id = c.id
                        ORDER BY expires_on DESC, id DESC
                        LIMIT 1
                   )
            ORDER BY
                    CASE WHEN l.expires_on IS NULL THEN 1 ELSE 0 END,
                    l.expires_on ASC,
                    c.shop ASC
        SQL )->fetchAll();
    }

    /**
     * Record what a shop just said about itself.
     *
     * Upserts on the install id: the row is the shop's current state, and the
     * two counters are the only history kept — when it was first heard from,
     * and how many times since. Enough to tell a shop that reports every six
     * hours from one that has phoned home exactly once and never again.
     */
    public static function recordReport( array $r ): void
    {
        $stmt = self::db()->prepare( <<<SQL
            INSERT INTO reports
                (install_id, shop, state, expires_on, days, plan, version, commit_ref,
                 shop_time, timezone, seen_at, first_seen, seen_count)
            VALUES
                (:install, :shop, :state, :expires, :days, :plan, :version, :commit,
                 :shop_time, :tz, :seen, :seen, 1)
            ON CONFLICT (install_id) DO UPDATE SET
                shop       = excluded.shop,
                state      = excluded.state,
                expires_on = excluded.expires_on,
                days       = excluded.days,
                plan       = excluded.plan,
                version    = excluded.version,
                commit_ref = excluded.commit_ref,
                shop_time  = excluded.shop_time,
                timezone   = excluded.timezone,
                seen_at    = excluded.seen_at,
                seen_count = reports.seen_count + 1
        SQL );

        $stmt->execute( [
            ':install' => $r[ 'install' ],
            ':shop' => $r[ 'shop' ] ?? null,
            ':state' => $r[ 'state' ] ?? null,
            ':expires' => $r[ 'expires' ] ?? null,
            ':days' => isset( $r[ 'days' ] ) ? (int) $r[ 'days' ] : null,
            ':plan' => $r[ 'plan' ] ?? null,
            ':version' => $r[ 'version' ] ?? null,
            ':commit' => $r[ 'commit' ] ?? null,
            ':shop_time' => $r[ 'at' ] ?? null,
            ':tz' => $r[ 'tz' ] ?? null,
            ':seen' => date( 'Y-m-d H:i:s' ),
        ] );
    }

    /**
     * Every report, keyed by install id, for joining onto a list of clients.
     */
    public static function reports(): array
    {
        $out = [];

        foreach ( self::db()->query( 'SELECT * FROM reports' )->fetchAll() as $row ) {
            $out[ $row[ 'install_id' ] ] = $row;
        }

        return $out;
    }

    public static function report( ?string $installId ): ?array
    {
        if ( $installId === null || $installId === '' ) {
            return null;
        }

        $stmt = self::db()->prepare( 'SELECT * FROM reports WHERE install_id = ?' );
        $stmt->execute( [ $installId ] );

        return $stmt->fetch() ?: null;
    }

    /**
     * Reports from installs that match no client on file.
     *
     * A shop being set up, an install id that changed under a client, or a
     * licence issued outside the console. All three are worth a glance and
     * none of them should be invisible.
     */
    public static function strayReports(): array
    {
        return self::db()->query( <<<SQL
            SELECT r.* FROM reports r
            WHERE NOT EXISTS (
                SELECT 1 FROM licences l WHERE l.install_id = r.install_id
            )
            ORDER BY r.seen_at DESC
        SQL )->fetchAll();
    }

    public static function client( int $id ): ?array
    {
        $row = self::db()->prepare( 'SELECT * FROM clients WHERE id = ?' );
        $row->execute( [ $id ] );

        return $row->fetch() ?: null;
    }

    public static function addClient( array $data ): int
    {
        $stmt = self::db()->prepare( <<<SQL
            INSERT INTO clients (shop, contact, phone, city, install_id, notes, created_at)
            VALUES (:shop, :contact, :phone, :city, :install_id, :notes, :created_at)
        SQL );

        $stmt->execute( [
            ':shop' => $data[ 'shop' ],
            ':contact' => $data[ 'contact' ] ?: null,
            ':phone' => $data[ 'phone' ] ?: null,
            ':city' => $data[ 'city' ] ?: null,
            ':install_id' => $data[ 'install_id' ] ?: null,
            ':notes' => $data[ 'notes' ] ?: null,
            ':created_at' => date( 'Y-m-d H:i:s' ),
        ] );

        return (int) self::db()->lastInsertId();
    }

    public static function updateClient( int $id, array $data ): void
    {
        $stmt = self::db()->prepare( <<<SQL
            UPDATE clients
               SET shop = :shop, contact = :contact, phone = :phone,
                   city = :city, install_id = :install_id, notes = :notes
             WHERE id = :id
        SQL );

        $stmt->execute( [
            ':shop' => $data[ 'shop' ],
            ':contact' => $data[ 'contact' ] ?: null,
            ':phone' => $data[ 'phone' ] ?: null,
            ':city' => $data[ 'city' ] ?: null,
            ':install_id' => $data[ 'install_id' ] ?: null,
            ':notes' => $data[ 'notes' ] ?: null,
            ':id' => $id,
        ] );
    }

    public static function deleteClient( int $id ): void
    {
        self::db()->prepare( 'DELETE FROM clients WHERE id = ?' )->execute( [ $id ] );
    }

    public static function licences( int $clientId ): array
    {
        $stmt = self::db()->prepare( 'SELECT * FROM licences WHERE client_id = ? ORDER BY id DESC' );
        $stmt->execute( [ $clientId ] );

        return $stmt->fetchAll();
    }

    public static function licence( int $id ): ?array
    {
        $stmt = self::db()->prepare( 'SELECT * FROM licences WHERE id = ?' );
        $stmt->execute( [ $id ] );

        return $stmt->fetch() ?: null;
    }

    public static function recordLicence( array $data ): int
    {
        $stmt = self::db()->prepare( <<<SQL
            INSERT INTO licences (client_id, shop, install_id, plan, issued_on, expires_on, amount, license, created_at)
            VALUES (:client_id, :shop, :install_id, :plan, :issued_on, :expires_on, :amount, :license, :created_at)
        SQL );

        $stmt->execute( [
            ':client_id' => $data[ 'client_id' ],
            ':shop' => $data[ 'shop' ],
            ':install_id' => $data[ 'install_id' ],
            ':plan' => $data[ 'plan' ],
            ':issued_on' => $data[ 'issued_on' ],
            ':expires_on' => $data[ 'expires_on' ],
            ':amount' => $data[ 'amount' ] ?: null,
            ':license' => $data[ 'license' ],
            ':created_at' => date( 'Y-m-d H:i:s' ),
        ] );

        return (int) self::db()->lastInsertId();
    }

    /**
     * Headline numbers for the dashboard.
     */
    public static function summary( array $clients ): array
    {
        $counts = [ 'active' => 0, 'soon' => 0, 'grace' => 0, 'locked' => 0, 'none' => 0 ];

        foreach ( $clients as $client ) {
            $counts[ standing( days_until( $client[ 'expires_on' ] ?? null ) )[ 'key' ] ]++;
        }

        return $counts;
    }
}
