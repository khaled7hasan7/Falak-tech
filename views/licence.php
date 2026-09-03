<?php
$title = 'مفتاح ' . $licence[ 'shop' ];
$days = days_until( $licence[ 'expires_on' ] );

/**
 * The message that actually goes to the shopkeeper. It carries the key and
 * the three words of instruction they need, because a bare 254-character
 * string arriving on WhatsApp reads like a scam and gets ignored.
 */
$message = "مفتاح اشتراك «{$licence[ 'shop' ]}»\n"
    . "صالح حتى {$licence[ 'expires_on' ]}\n\n"
    . "الإعدادات ← النظام ← الاشتراك، الصق المفتاح واضغط تفعيل:\n\n"
    . $licence[ 'license' ];

$digits = preg_replace( '/\D+/', '', (string) ( $client[ 'phone' ] ?? '' ) );

/** a local 05xx number is the common case; WhatsApp needs it in full */
if ( $digits !== '' && str_starts_with( $digits, '0' ) ) {
    $digits = '970' . ltrim( $digits, '0' );
}
?>

<div class="head">
    <div>
        <a class="crumb" href="/clients/<?= (int) $licence[ 'client_id' ] ?>">‹ <?= e( $client[ 'shop' ] ?? '' ) ?></a>
        <h1>المفتاح جاهز</h1>
        <p class="sub">وُقّع لـ <strong><?= e( $licence[ 'shop' ] ) ?></strong> — أرسله للعميل.</p>
    </div>
</div>

<div class="panel" style="margin-bottom:20px">
    <div class="body">
        <dl class="facts" style="margin-bottom:16px">
            <dt>المحل</dt><dd><?= e( $licence[ 'shop' ] ) ?></dd>
            <dt>التنصيب</dt>
            <dd class="num"><?= $licence[ 'install_id' ] === '*' ? 'أي تنصيب — غير مقيَّد' : e( $licence[ 'install_id' ] ) ?></dd>
            <dt>يبدأ</dt><dd class="num"><?= e( $licence[ 'issued_on' ] ) ?></dd>
            <dt>ينتهي</dt><dd class="num"><?= e( $licence[ 'expires_on' ] ) ?> <span class="tiny">(<?= days_word( (int) $days ) ?>)</span></dd>
            <?php if ( $licence[ 'amount' ] ) : ?>
                <dt>المبلغ</dt><dd><?= e( $licence[ 'amount' ] ) ?></dd>
            <?php endif; ?>
        </dl>

        <div class="keybox" id="thekey"><?= e( $licence[ 'license' ] ) ?></div>

        <div class="actions" style="margin-top:14px">
            <button class="btn primary" type="button" data-copy="#thekey">نسخ المفتاح</button>

            <?php if ( $digits !== '' ) : ?>
                <a class="btn" target="_blank" rel="noopener"
                   href="https://wa.me/<?= e( $digits ) ?>?text=<?= rawurlencode( $message ) ?>">
                    إرسال على واتساب
                </a>
            <?php endif; ?>

            <button class="btn ghost" type="button" data-copy="#themessage">نسخ الرسالة كاملة</button>
            <a class="btn ghost" href="/clients/<?= (int) $licence[ 'client_id' ] ?>">رجوع</a>
        </div>

        <div id="themessage" hidden><?= e( $message ) ?></div>
    </div>
</div>

<?php if ( $licence[ 'install_id' ] === '*' ) : ?>
    <div class="note warn">
        <div>
            <strong>هذا المفتاح يعمل على أي جهاز.</strong>
            من يحصل عليه يشغّل النظام في أي محل. أصدره للعروض فقط، ولا تسلّمه مع بيع.
        </div>
    </div>
<?php endif; ?>

<div class="note info">
    <div>
        <strong>ماذا يفعل العميل؟</strong>
        يفتح <span style="color:var(--ink)">الإعدادات ← النظام ← الاشتراك</span>، يلصق المفتاح، يضغط تفعيل.
        وإن كانت الشاشة مقفلة فهي أول ما يراه عند الدخول، والمفتاح يُلصق فيها مباشرة.
    </div>
</div>
