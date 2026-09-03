<?php
$title = $client[ 'shop' ];

$current = null;

foreach ( $licences as $row ) {
    if ( $current === null || $row[ 'expires_on' ] > $current[ 'expires_on' ] ) {
        $current = $row;
    }
}

$days = days_until( $current[ 'expires_on' ] ?? null );
$mark = standing( $days );
?>

<div class="head">
    <div>
        <a class="crumb" href="/">‹ المشتركون</a>
        <h1><?= e( $client[ 'shop' ] ) ?></h1>
        <p class="sub">
            <span class="pill tone-<?= $mark[ 'tone' ] ?>"><i class="dot"></i><span><?= $mark[ 'label' ] ?></span></span>
            <?php if ( $current ) : ?>
                <span style="margin-inline-start:8px">
                    حتى <span class="num"><?= e( $current[ 'expires_on' ] ) ?></span>
                    <?= $days >= 0 ? '— بقي ' . days_word( $days ) : '— انتهى منذ ' . days_word( $days, true ) ?>
                </span>
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="grid split">

    <!-- ---------------------------------------------------------------- -->
    <!-- Issue                                                             -->
    <!-- ---------------------------------------------------------------- -->
    <form class="panel" method="post" action="/clients/<?= (int) $client[ 'id' ] ?>/issue">
        <header><h2><?= $current ? 'تجديد الاشتراك' : 'إصدار المفتاح' ?></h2></header>

        <div class="body">
            <input type="hidden" name="_token" value="<?= e( csrf() ) ?>">

            <div class="field">
                <label for="install_id">
                    معرّف التنصيب
                    <span class="hint">— من شاشة الاشتراك عند العميل</span>
                </label>
                <input type="text" id="install_id" name="install_id" class="mono" dir="ltr"
                       placeholder="XXXX-XXXX-XXXX"
                       value="<?= e( $client[ 'install_id' ] ) ?>">
            </div>

            <div class="check" style="margin-bottom:15px">
                <input type="checkbox" id="unbound" name="unbound" value="1">
                <label for="unbound">
                    مفتاح غير مقيَّد بتنصيب
                    <span class="hint">يعمل على أي جهاز — للعروض التجريبية فقط، ولا يُباع.</span>
                </label>
            </div>

            <div class="field">
                <label>المدة</label>
                <div class="row">
                    <input type="number" name="length" value="12" min="1" max="120" required>
                    <select name="unit">
                        <option value="months">شهر</option>
                        <option value="days">يوم</option>
                    </select>
                </div>
            </div>

            <?php if ( $days !== null && $days > 0 ) : ?>
                <div class="field">
                    <label for="start">تبدأ من</label>
                    <select id="start" name="start">
                        <option value="continue">نهاية الاشتراك الحالي (<?= e( $current[ 'expires_on' ] ) ?>)</option>
                        <option value="today">اليوم</option>
                    </select>
                    <p class="tiny" style="margin-top:6px">
                        التجديد المبكر لا يُضيّع الأيام المدفوعة — يُكمل من حيث ينتهي الحالي.
                    </p>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="field">
                    <label for="plan">الباقة <span class="hint">وصف</span></label>
                    <input type="text" id="plan" name="plan" value="annual" dir="ltr">
                </div>
                <div class="field">
                    <label for="amount">المبلغ <span class="hint">لسجلّك</span></label>
                    <input type="text" id="amount" name="amount" placeholder="مثال: ٦٠٠ ₪">
                </div>
            </div>
        </div>

        <div class="body" style="border-top:1px solid var(--line-soft)">
            <button class="btn primary wide" type="submit">وقّع المفتاح</button>
        </div>
    </form>

    <div>
        <!-- ------------------------------------------------------------ -->
        <!-- Who they are                                                  -->
        <!-- ------------------------------------------------------------ -->
        <form class="panel" method="post" action="/clients/<?= (int) $client[ 'id' ] ?>" style="margin-bottom:20px">
            <header>
                <h2>بيانات المحل</h2>
                <button class="btn sm" type="submit">حفظ</button>
            </header>

            <div class="body">
                <input type="hidden" name="_token" value="<?= e( csrf() ) ?>">

                <div class="field">
                    <label for="shop">اسم المحل <span class="hint">— كما يظهر على شاشته</span></label>
                    <input type="text" id="shop" name="shop" value="<?= e( $client[ 'shop' ] ) ?>" required>
                </div>

                <div class="row">
                    <div class="field">
                        <label for="contact">المسؤول</label>
                        <input type="text" id="contact" name="contact" value="<?= e( $client[ 'contact' ] ) ?>">
                    </div>
                    <div class="field">
                        <label for="city">البلدة</label>
                        <input type="text" id="city" name="city" value="<?= e( $client[ 'city' ] ) ?>">
                    </div>
                </div>

                <div class="field">
                    <label for="phone">الهاتف <span class="hint">— لإرسال المفتاح على واتساب</span></label>
                    <input type="text" id="phone" name="phone" dir="ltr" placeholder="970599123456"
                           value="<?= e( $client[ 'phone' ] ) ?>">
                </div>

                <input type="hidden" name="install_id" value="<?= e( $client[ 'install_id' ] ) ?>">

                <div class="field">
                    <label for="notes">ملاحظات</label>
                    <textarea id="notes" name="notes" rows="2"><?= e( $client[ 'notes' ] ) ?></textarea>
                </div>
            </div>
        </form>

        <!-- ------------------------------------------------------------ -->
        <!-- Every key ever signed for them                                -->
        <!-- ------------------------------------------------------------ -->
        <div class="panel">
            <header><h2>سجلّ المفاتيح</h2></header>

            <?php if ( ! $licences ) : ?>
                <div class="body"><p class="tiny">لم يُصدَر مفتاح لهذا المحل بعد.</p></div>
            <?php else : ?>
                <div class="scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>أُصدر</th>
                                <th>ينتهي</th>
                                <th>التنصيب</th>
                                <th>المبلغ</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $licences as $row ) : ?>
                            <tr>
                                <td class="num"><?= e( $row[ 'issued_on' ] ) ?></td>
                                <td class="num"><?= e( $row[ 'expires_on' ] ) ?></td>
                                <td class="num" style="color:var(--dim)"><?= e( $row[ 'install_id' ] ) ?></td>
                                <td class="tiny"><?= $row[ 'amount' ] ? e( $row[ 'amount' ] ) : '—' ?></td>
                                <td style="text-align:end">
                                    <a class="btn sm ghost" href="/licence/<?= (int) $row[ 'id' ] ?>">عرض</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <form method="post" action="/clients/<?= (int) $client[ 'id' ] ?>/delete" style="margin-top:16px;text-align:end"
              onsubmit="return confirm('حذف <?= e( $client[ 'shop' ] ) ?> وكل سجلّ مفاتيحه؟ لا يمكن التراجع.')">
            <input type="hidden" name="_token" value="<?= e( csrf() ) ?>">
            <button class="btn danger sm" type="submit">حذف المحل</button>
        </form>
    </div>
</div>
