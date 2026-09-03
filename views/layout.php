<?php $notice = flash(); ?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= isset( $title ) ? e( $title ) . ' · ' : '' ?>لوحة فلك</title>
    <link rel="stylesheet" href="/assets/app.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><circle cx='16' cy='16' r='6' fill='%23f5c24b'/><ellipse cx='16' cy='16' rx='14' ry='7' fill='none' stroke='%23f5c24b' stroke-width='2' transform='rotate(-25 16 16)'/></svg>">
</head>
<body>

<?php if ( signed_in() ) : ?>
<nav class="top">
    <a class="brand" href="/">
        <svg width="26" height="26" viewBox="0 0 32 32" aria-hidden="true">
            <ellipse cx="16" cy="16" rx="14" ry="6.5" fill="none" stroke="#f5c24b" stroke-width="1.6"
                     opacity=".55" transform="rotate(-25 16 16)"/>
            <circle cx="16" cy="16" r="5.5" fill="#f5c24b"/>
            <circle cx="27.5" cy="10.5" r="2" fill="#e9ecf7"/>
        </svg>
        فلك <small>console</small>
    </a>

    <div class="nav">
        <a href="/" <?= ( $nav ?? '' ) === 'home' ? 'aria-current="page"' : '' ?>>المشتركون</a>
        <a href="/clients/new" <?= ( $nav ?? '' ) === 'new' ? 'aria-current="page"' : '' ?>>محل جديد</a>
        <a href="/verify" <?= ( $nav ?? '' ) === 'verify' ? 'aria-current="page"' : '' ?>>فحص مفتاح</a>
        <a href="/keys" <?= ( $nav ?? '' ) === 'keys' ? 'aria-current="page"' : '' ?>>مفتاح التوقيع</a>
        <a href="/logout">خروج</a>
    </div>
</nav>
<?php endif; ?>

<main class="wrap <?= ( $narrow ?? false ) ? 'narrow' : '' ?>">
    <?php if ( $notice ) : ?>
        <div class="note <?= e( $notice[ 'kind' ] ) ?>">
            <div><?= e( $notice[ 'message' ] ) ?></div>
        </div>
    <?php endif; ?>

    <?= $content ?>
</main>

<script>
/**
 * Copy-to-clipboard. The licence key is the one thing on this whole console
 * that must survive the journey to WhatsApp intact — a hand-selected copy that
 * clips the last character produces a key the shop cannot install and neither
 * of you can explain.
 */
document.addEventListener('click', async (event) => {
    const trigger = event.target.closest('[data-copy]');
    if (!trigger) return;

    const source = document.querySelector(trigger.dataset.copy);
    if (!source) return;

    const text = (source.textContent || source.value || '').trim();

    try {
        await navigator.clipboard.writeText(text);
    } catch {
        /* clipboard API needs a secure context; select the text so ctrl+C works */
        const range = document.createRange();
        range.selectNodeContents(source);
        getSelection().removeAllRanges();
        getSelection().addRange(range);
        return;
    }

    const was = trigger.textContent;
    trigger.textContent = 'تم النسخ ✓';
    trigger.disabled = true;

    setTimeout(() => { trigger.textContent = was; trigger.disabled = false; }, 1600);
});

/* Install ids are always upper case; typing them in lower case is not an error worth reporting. */
document.addEventListener('input', (event) => {
    if (event.target.matches('input.mono')) {
        const pos = event.target.selectionStart;
        event.target.value = event.target.value.toUpperCase();
        event.target.setSelectionRange(pos, pos);
    }
});
</script>

</body>
</html>
