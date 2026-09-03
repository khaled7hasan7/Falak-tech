<?php
$title = 'دخول';
$narrow = true;
?>

<div style="text-align:center;margin-bottom:28px">
    <svg width="46" height="46" viewBox="0 0 32 32" aria-hidden="true">
        <ellipse cx="16" cy="16" rx="14" ry="6.5" fill="none" stroke="#f5c24b" stroke-width="1.4"
                 opacity=".5" transform="rotate(-25 16 16)"/>
        <circle cx="16" cy="16" r="5.5" fill="#f5c24b"/>
        <circle cx="27.5" cy="10.5" r="2" fill="#e9ecf7"/>
    </svg>
    <h1 style="margin-top:12px">لوحة فلك</h1>
</div>

<?php if ( $error ) : ?>
    <div class="note bad"><div><?= e( $error ) ?></div></div>
<?php endif; ?>

<form method="post" class="panel">
    <div class="body">
        <input type="hidden" name="_token" value="<?= e( csrf() ) ?>">

        <div class="field">
            <label for="password">كلمة المرور</label>
            <input type="password" id="password" name="password" autocomplete="current-password" autofocus required>
        </div>

        <button class="btn primary wide" type="submit">دخول</button>
    </div>
</form>
