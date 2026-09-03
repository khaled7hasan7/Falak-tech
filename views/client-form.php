<?php
$title = 'محل جديد';
$nav = 'new';
$narrow = true;
$v = fn( string $k ) => e( $client[ $k ] ?? '' );
?>

<div class="head">
    <div>
        <a class="crumb" href="/">‹ المشتركون</a>
        <h1>محل جديد</h1>
        <p class="sub">سجّله الآن، وأصدر مفتاحه متى قرأ لك معرّف التنصيب.</p>
    </div>
</div>

<?php if ( $error ) : ?>
    <div class="note bad"><div><?= e( $error ) ?></div></div>
<?php endif; ?>

<form method="post" class="panel">
    <div class="body">
        <input type="hidden" name="_token" value="<?= e( csrf() ) ?>">

        <div class="field">
            <label for="shop">اسم المحل <span class="hint">— كما تريده أن يظهر على شاشته</span></label>
            <input type="text" id="shop" name="shop" value="<?= $v( 'shop' ) ?>" autofocus required>
        </div>

        <div class="row">
            <div class="field">
                <label for="contact">المسؤول</label>
                <input type="text" id="contact" name="contact" value="<?= $v( 'contact' ) ?>">
            </div>
            <div class="field">
                <label for="city">البلدة</label>
                <input type="text" id="city" name="city" value="<?= $v( 'city' ) ?>">
            </div>
        </div>

        <div class="field">
            <label for="phone">الهاتف <span class="hint">— لإرسال المفتاح على واتساب</span></label>
            <input type="text" id="phone" name="phone" dir="ltr" placeholder="0599123456" value="<?= $v( 'phone' ) ?>">
        </div>

        <div class="field">
            <label for="install_id">معرّف التنصيب <span class="hint">— إن كنت تعرفه الآن</span></label>
            <input type="text" id="install_id" name="install_id" class="mono" dir="ltr"
                   placeholder="XXXX-XXXX-XXXX" value="<?= $v( 'install_id' ) ?>">
        </div>

        <div class="field">
            <label for="notes">ملاحظات</label>
            <textarea id="notes" name="notes" rows="2"><?= $v( 'notes' ) ?></textarea>
        </div>
    </div>

    <div class="body" style="border-top:1px solid var(--line-soft)">
        <div class="actions">
            <button class="btn primary" type="submit">أضف</button>
            <a class="btn ghost" href="/">إلغاء</a>
        </div>
    </div>
</form>
