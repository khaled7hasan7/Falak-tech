<?php
$title = 'فحص مفتاح';
$nav = 'verify';
?>

<div class="head">
    <div>
        <h1>فحص مفتاح</h1>
        <p class="sub">
            العميل يقول إن المفتاح لا يعمل؟ الصقه هنا كما وصله — تعرف فوراً إن كان
            وصل ناقصاً، أو صادراً لتنصيب آخر، أو منتهياً.
        </p>
    </div>
</div>

<form method="post" class="panel" style="margin-bottom:20px">
    <div class="body">
        <input type="hidden" name="_token" value="<?= e( csrf() ) ?>">

        <div class="field">
            <label for="license">المفتاح</label>
            <textarea id="license" name="license" rows="4" dir="ltr" class="mono-in"
                      style="font-size:12.5px;letter-spacing:0;text-transform:none"
                      placeholder="FALAK1...."><?= e( $input ) ?></textarea>
        </div>

        <button class="btn primary" type="submit">افحص</button>
    </div>
</form>

<?php if ( $result === null ) : ?>
    <?php /* nothing submitted yet */ ?>
<?php elseif ( ! $result[ 'ok' ] ) : ?>
    <div class="note bad">
        <div><strong>لا يصلح.</strong><?= e( $result[ 'reason' ] ) ?></div>
    </div>
<?php else :
    $payload = $result[ 'payload' ];
    $days = days_until( $payload[ 'expires' ] ?? null );
    $mark = standing( $days );
?>
    <div class="note ok">
        <div><strong>التوقيع صحيح.</strong>هذا المفتاح صادر عن جهازك ولم يُعدَّل.</div>
    </div>

    <div class="panel">
        <div class="body">
            <dl class="facts">
                <dt>المحل</dt><dd><?= e( $payload[ 'shop' ] ?? '—' ) ?></dd>
                <dt>التنصيب</dt>
                <dd class="num">
                    <?= ( $payload[ 'install' ] ?? '' ) === '*' ? 'أي تنصيب — غير مقيَّد' : e( $payload[ 'install' ] ?? '—' ) ?>
                </dd>
                <dt>أُصدر</dt><dd class="num"><?= e( $payload[ 'issued' ] ?? '—' ) ?></dd>
                <dt>ينتهي</dt><dd class="num"><?= e( $payload[ 'expires' ] ?? '—' ) ?></dd>
                <dt>الباقة</dt><dd class="num"><?= e( $payload[ 'plan' ] ?? '—' ) ?></dd>
                <dt>الحالة</dt>
                <dd>
                    <span class="pill tone-<?= $mark[ 'tone' ] ?>"><i class="dot"></i><span><?= $mark[ 'label' ] ?></span></span>
                    <?php if ( $days !== null ) : ?>
                        <span class="tiny" style="margin-inline-start:6px">
                            <?= $days >= 0 ? 'بقي ' . days_word( $days ) : 'انتهى منذ ' . days_word( $days, true ) ?>
                        </span>
                    <?php endif; ?>
                </dd>
            </dl>

            <?php if ( ( $payload[ 'install' ] ?? '' ) !== '*' ) : ?>
                <hr>
                <p class="tiny">
                    المفتاح مقيَّد بالتنصيب أعلاه. إن كان معرّف العميل مختلفاً عنه — ولو بحرف —
                    فسيُرفض عنده مهما كان التوقيع صحيحاً. قارنه بما يقرؤه على شاشته.
                </p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
