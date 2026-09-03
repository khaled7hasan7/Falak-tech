<?php
$title = 'الإعداد';
$narrow = true;
$hasKey = Licence::hasKey();
?>

<div style="text-align:center;margin-bottom:28px">
    <svg width="46" height="46" viewBox="0 0 32 32" aria-hidden="true">
        <ellipse cx="16" cy="16" rx="14" ry="6.5" fill="none" stroke="#f5c24b" stroke-width="1.4"
                 opacity=".5" transform="rotate(-25 16 16)"/>
        <circle cx="16" cy="16" r="5.5" fill="#f5c24b"/>
        <circle cx="27.5" cy="10.5" r="2" fill="#e9ecf7"/>
    </svg>
    <h1 style="margin-top:12px">لوحة فلك</h1>
    <p class="tiny" style="margin-top:6px">إعداد أول مرة — على جهازك وحده.</p>
</div>

<?php foreach ( $errors as $error ) : ?>
    <div class="note bad"><div><?= e( $error ) ?></div></div>
<?php endforeach; ?>

<form method="post" class="panel">
    <div class="body">
        <input type="hidden" name="_token" value="<?= e( csrf() ) ?>">

        <?php if ( ! $installed ) : ?>
            <div class="field">
                <label for="vendor">اسم شركتك <span class="hint">يظهر في الترويسة</span></label>
                <input type="text" id="vendor" name="vendor" value="Falak" autocomplete="off">
            </div>

            <div class="field">
                <label for="password">كلمة مرور اللوحة <span class="hint">٨ أحرف فأكثر</span></label>
                <input type="password" id="password" name="password" autocomplete="new-password" required>
            </div>

            <div class="field">
                <label for="password_confirm">أعد كتابتها</label>
                <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
            </div>

            <hr>
        <?php endif; ?>

        <?php if ( $hasKey ) : ?>
            <div class="note ok" style="margin-bottom:0">
                <div><strong>مفتاح التوقيع موجود.</strong>لن يُمسّ.</div>
            </div>
        <?php else : ?>
            <h3 style="margin-bottom:4px">مفتاح التوقيع</h3>
            <p class="tiny" style="margin-bottom:14px">
                هو ما يوقّع به كل ترخيص تبيعه. إن كنت قد أصدرت تراخيص من قبل فاستورد
                مفتاحك القديم — توليد مفتاح جديد يُبطل كل ما أصدرته.
            </p>

            <div class="field">
                <label for="private_key">المفتاح الخاص (base64)</label>
                <textarea id="private_key" name="private_key" rows="3" dir="ltr"
                          placeholder="محتوى storage/app/falak-license-private.key"></textarea>
                <p class="tiny" style="margin-top:6px">
                    من مجلد المشروع: <code class="num">storage/app/falak-license-private.key</code>
                </p>
            </div>

            <div class="check">
                <input type="checkbox" id="generate" name="key_mode" value="generate">
                <label for="generate">
                    وليس لدي مفتاح — ولّد لي واحداً جديداً
                    <span class="hint">اختر هذا فقط إن لم تبع ترخيصاً بعد.</span>
                </label>
            </div>
        <?php endif; ?>
    </div>

    <div class="body" style="border-top:1px solid var(--line-soft)">
        <button class="btn primary wide" type="submit">ابدأ</button>
    </div>
</form>
