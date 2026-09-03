<?php
$title = 'المشتركون';
$nav = 'home';

$tiles = [
    'active' => [ 'ساري', 'good' ],
    'soon' => [ 'ينتهي خلال أسبوعين', 'warn' ],
    'grace' => [ 'في مهلة السماح', 'bad' ],
    'locked' => [ 'مقفل', 'bad' ],
    'none' => [ 'بلا مفتاح', 'idle' ],
];
?>

<div class="head">
    <div>
        <h1>المشتركون</h1>
        <p class="sub"><?= count( $clients ) ?> محل — مرتّبة بالأقرب انتهاءً.</p>
    </div>
    <a class="btn primary" href="/clients/new">+ محل جديد</a>
</div>

<div class="stats">
    <?php foreach ( $tiles as $key => [ $label, $tone ] ) : ?>
        <div class="stat <?= $tone ?>">
            <div class="n"><?= (int) $summary[ $key ] ?></div>
            <div class="k"><i class="dot tone-<?= $tone ?>"></i><?= $label ?></div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ( ! $clients ) : ?>
    <div class="panel">
        <div class="empty">
            <svg width="44" height="44" viewBox="0 0 32 32" aria-hidden="true">
                <ellipse cx="16" cy="16" rx="14" ry="6.5" fill="none" stroke="#838dae" stroke-width="1.3"
                         transform="rotate(-25 16 16)"/>
                <circle cx="16" cy="16" r="4.5" fill="#838dae"/>
            </svg>
            <p>لا يوجد مشتركون بعد.</p>
            <a class="btn primary" href="/clients/new">أضف أول محل</a>
        </div>
    </div>
<?php else : ?>
    <div class="panel scroll">
        <table>
            <thead>
                <tr>
                    <th>المحل</th>
                    <th>معرّف التنصيب</th>
                    <th>ينتهي</th>
                    <th>المتبقّي</th>
                    <th>الحالة</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $clients as $client ) :
                $days = days_until( $client[ 'expires_on' ] ?? null );
                $mark = standing( $days );

                /**
                 * The runway is the share of the paid term still ahead. It is
                 * read at a glance, before the numbers are — a short bar on a
                 * row means "this one is due", without arithmetic.
                 */
                $span = null;

                if ( ! empty( $client[ 'issued_on' ] ) && ! empty( $client[ 'expires_on' ] ) ) {
                    $total = (int) ( new DateTimeImmutable( $client[ 'issued_on' ] ) )
                        ->diff( new DateTimeImmutable( $client[ 'expires_on' ] ) )->format( '%a' );

                    $span = $total > 0 ? max( 0, min( 100, (int) round( ( $days / $total ) * 100 ) ) ) : null;
                }
            ?>
                <tr>
                    <td class="shop">
                        <a href="/clients/<?= (int) $client[ 'id' ] ?>"><?= e( $client[ 'shop' ] ) ?></a>
                        <?php if ( $client[ 'city' ] || $client[ 'contact' ] ) : ?>
                            <div class="meta"><?= e( trim( implode( ' — ', array_filter( [ $client[ 'city' ], $client[ 'contact' ] ] ) ) ) ) ?></div>
                        <?php endif; ?>
                    </td>

                    <td class="num" style="color:var(--dim)">
                        <?php /* the shop's own record first, else whatever the live licence was signed for */ ?>
                        <?= e( $client[ 'install_id' ] ?: ( $client[ 'licensed_install' ] ?? '' ) ) ?: '—' ?>
                    </td>

                    <td class="num"><?= $client[ 'expires_on' ] ? e( $client[ 'expires_on' ] ) : '—' ?></td>

                    <td>
                        <?php if ( $days === null ) : ?>
                            <span class="tiny">—</span>
                        <?php else : ?>
                            <div style="display:flex;align-items:center;gap:10px" class="tone-<?= $mark[ 'tone' ] ?>">
                                <span class="num" style="color:var(--ink);min-width:58px">
                                    <?= $days >= 0 ? days_word( $days ) : 'منذ ' . days_word( $days, true ) ?>
                                </span>
                                <?php if ( $span !== null ) : ?>
                                    <span class="runway"><i style="width:<?= $span ?>%"></i></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <span class="pill tone-<?= $mark[ 'tone' ] ?>">
                            <i class="dot"></i><span><?= $mark[ 'label' ] ?></span>
                        </span>
                    </td>

                    <td style="text-align:end">
                        <a class="btn sm" href="/clients/<?= (int) $client[ 'id' ] ?>">
                            <?= $days === null ? 'إصدار' : 'تجديد' ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
