<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0d1b2a">
    <title>{{ $title }} · SportsUniverse</title>
    <link rel="icon" href="/images/logo/favicon.ico" sizes="any">
    <style>
        :root{color-scheme:light;--navy:#0d1b2a;--ink:#172033;--muted:#65748a;--line:#dfe6f0;--blue:#2563eb;--pink:#ec3d91}*{box-sizing:border-box}html,body{margin:0;min-height:100%;font-family:Inter,ui-sans-serif,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:var(--ink);background:#f4f7fb}body{min-height:100vh;min-height:100dvh}.error-shell{position:relative;display:grid;min-height:100vh;min-height:100dvh;place-items:center;overflow:hidden;padding:28px}.error-shell:before,.error-shell:after{position:absolute;width:440px;height:440px;border-radius:50%;content:"";filter:blur(4px);opacity:.16}.error-shell:before{top:-220px;right:-130px;background:#2563eb}.error-shell:after{bottom:-260px;left:-130px;background:#ec3d91}.error-card{position:relative;z-index:1;display:grid;width:min(100%,1050px);min-height:600px;grid-template-columns:.9fr 1.1fr;overflow:hidden;border:1px solid rgba(211,220,233,.95);border-radius:30px;background:#fff;box-shadow:0 32px 90px rgba(19,38,71,.14)}.error-visual{position:relative;display:flex;min-height:420px;flex-direction:column;justify-content:space-between;overflow:hidden;padding:42px;color:#fff;background:radial-gradient(circle at 22% 22%,rgba(37,99,235,.6),transparent 34%),radial-gradient(circle at 84% 82%,rgba(236,61,145,.42),transparent 35%),linear-gradient(150deg,#0d1b2a,#050915 76%)}.logo{display:block;width:225px;height:auto}.orbit{position:relative;display:grid;width:min(100%,360px);aspect-ratio:1;margin:auto;place-items:center}.orbit:before,.orbit:after{position:absolute;border:1px solid rgba(255,255,255,.16);border-radius:50%;content:""}.orbit:before{inset:2%;animation:spin 22s linear infinite}.orbit:after{inset:19%;animation:spin 16s linear infinite reverse}.orbit-dot{position:absolute;top:4%;left:48%;width:13px;height:13px;border-radius:50%;background:var(--pink);box-shadow:0 0 24px rgba(236,61,145,.8)}.status-code{position:relative;font-size:clamp(5rem,12vw,8.6rem);font-weight:900;line-height:1;letter-spacing:-.09em;text-shadow:0 12px 40px rgba(0,0,0,.28)}.status-code small{position:absolute;right:-22px;bottom:10px;width:14px;height:14px;border-radius:50%;background:#36d399;box-shadow:0 0 0 8px rgba(54,211,153,.12)}.visual-caption{color:#aebace;font-size:.74rem;font-weight:650;letter-spacing:.06em;text-transform:uppercase}.error-content{display:flex;flex-direction:column;justify-content:center;padding:clamp(36px,7vw,76px)}.eyebrow{display:flex;gap:9px;align-items:center;margin-bottom:22px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.1em;text-transform:uppercase}.eyebrow:before{width:28px;height:3px;border-radius:5px;background:linear-gradient(90deg,var(--blue),var(--pink));content:""}.error-content h1{max-width:560px;margin:0;font-size:clamp(2.1rem,4.5vw,3.5rem);line-height:1.04;letter-spacing:-.055em}.error-content>p{max-width:540px;margin:20px 0 30px;color:var(--muted);font-size:.98rem;line-height:1.7}.actions{display:flex;flex-wrap:wrap;gap:12px}.action{display:inline-flex;min-height:48px;align-items:center;justify-content:center;padding:0 20px;border:1px solid var(--line);border-radius:13px;color:var(--ink);background:#fff;font-size:.78rem;font-weight:800;text-decoration:none;transition:.2s ease}.action:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(24,45,79,.1)}.action-primary{color:#fff;border-color:transparent;background:linear-gradient(100deg,var(--blue),#3979f8);box-shadow:0 12px 28px rgba(37,99,235,.22)}.support{margin-top:36px;padding-top:22px;border-top:1px solid #edf1f6;color:#8a96a8;font-size:.7rem;line-height:1.6}.support a{color:var(--blue);font-weight:750;text-decoration:none}@keyframes spin{to{transform:rotate(360deg)}}@media(prefers-reduced-motion:reduce){.orbit:before,.orbit:after{animation:none}}@media(max-width:760px){.error-shell{padding:0}.error-card{min-height:100dvh;grid-template-columns:1fr;border:0;border-radius:0}.error-visual{min-height:300px;padding:28px}.logo{width:190px}.orbit{position:absolute;right:-35px;bottom:-58px;width:260px;opacity:.75}.status-code{font-size:5.5rem}.visual-caption{position:relative;z-index:2}.error-content{justify-content:flex-start;padding:38px 25px 44px}.error-content h1{font-size:2.35rem}.error-content>p{margin:16px 0 26px}.actions{display:grid}.action{width:100%}.support{margin-top:28px}}
    </style>
</head>
<body>
<main class="error-shell">
    <article class="error-card" role="alert">
        <section class="error-visual" aria-hidden="true">
            <img class="logo" src="/images/logo/sportuniverse-logo-horizontal-transparent.png" alt="">
            <div class="orbit"><i class="orbit-dot"></i><strong class="status-code">{{ $status }}<small></small></strong></div>
            <span class="visual-caption">Talent · Opportunity · Community</span>
        </section>
        <section class="error-content">
            <span class="eyebrow">{{ $label }}</span>
            <h1>{{ $heading }}</h1>
            <p>{{ $message }}</p>
            <nav class="actions" aria-label="Error recovery actions">
                <a class="action action-primary" href="{{ $primaryUrl }}">{{ $primaryLabel }}</a>
                @if($showBack ?? true)<a class="action" href="javascript:history.back()">Go back</a>@endif
            </nav>
            <p class="support">{{ $support }} @if($status >= 500) If this continues, contact <a href="mailto:support@sportsuniverse.co.za">SportsUniverse support</a>.@endif</p>
        </section>
    </article>
</main>
</body>
</html>
