<?php
$title = 'مفتاح التوقيع';
$nav = 'keys';
?>

<div class="head">
    <div>
        <h1>مفتاح التوقيع</h1>
        <p class="sub">النصف العام يُوزَّع على العملاء. النصف الخاص لا يغادر هذا الجهاز.</p>
    </div>
</div>

<?php if ( $public === null ) : ?>
    <div class="note bad">
        <div>
            <strong>لا يوجد مفتاح خاص صالح.</strong>
            لا يمكن إصدار أي ترخيص قبل استيراده. <a href="/setup">أضفه الآن</a>.
        </div>
    </div>
<?php else : ?>

<div class="panel" style="margin-bottom:20px">
    <header><h2>المفتاح العام — يوضع عند كل عميل</h2></header>
    <div class="body">
        <p class="tiny" style="margin-bottom:12px">
            أضف هذا السطر إلى ملف <code class="num">.env</code> في تنصيب العميل. بدونه لا توجد
            بوابة اشتراك أصلاً والنظام يعمل بلا قيد — وهو أكثر خطأ يُنسى عند أول تركيب.
        </p>

        <div class="keybox plain" id="envline">FALAK_LICENSE_KEY=<?= e( $public ) ?></div>

        <div class="actions" style="margin-top:12px">
            <button class="btn" type="button" data-copy="#envline">نسخ السطر</button>
        </div>

        <hr>

        <p class="tiny">وبجانبه، لتظهر بياناتك على شاشة القفل حين ينتهي اشتراك عميل:</p>
        <div class="keybox plain" style="font-size:12.5px">FALAK_VENDOR_NAME=<?= e( (string) config_get( 'vendor', 'Falak' ) ) ?>
FALAK_VENDOR_PHONE=+970 5X XXX XXXX</div>
    </div>
</div>

<div class="panel">
    <header><h2>المفتاح الخاص</h2></header>
    <div class="body">
        <dl class="facts">
            <dt>مكانه</dt><dd class="num" style="font-size:12.5px"><?= e( $path ) ?></dd>
            <dt>الحجم</dt><dd class="num"><?= SODIUM_CRYPTO_SIGN_SECRETKEYBYTES ?> بايت — Ed25519</dd>
        </dl>

        <hr>

        <div class="note bad" style="margin:0">
            <div>
                <strong>خذ منه نسخة احتياطية خارج هذا الجهاز.</strong>
                فقدانه يعني أنك لا تستطيع تجديد اشتراك أي عميل بعد اليوم — ولا حلّ إلا زيارة
                كل محل وتغيير مفتاحه العام. وتسريبه يعني أن من يحصل عليه يُصدر تراخيص مجانية
                باسمك، بلا طريقة لإبطالها عن بُعد. عامله معاملة بيانات بنك.
            </div>
        </div>

        <p class="tiny" style="margin-top:14px">
            المحتوى لا يُعرض هنا عمداً. انسخ الملف نفسه إن احتجت نقله.
        </p>
    </div>
</div>

<?php endif; ?>
