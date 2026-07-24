<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#05080F">
  <title>Pay in one scan — Spin Klean Laundry JP</title>
  <!-- Vite placeholder (no actual assets) -->
  <!-- Fonts & base styles -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Instrument+Sans:wght@400;500;600&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
  <style>
    /* ---- reset & variables (same as original) ---- */
    :root {
      --ink: #05080F;
      --deep: #0B1524;
      --aqua: #3DE0C0;
      --azure: #5B9BFF;
      --paper: #FBFCFE;
      --night: #0A1220;
      --slate: #5C6B7F;
      --hair: rgba(255,255,255,.10);
      --r-card: clamp(20px,2.4vw,30px);
      --pad: clamp(22px,3.4vw,54px);
      --display: "Bricolage Grotesque","Instrument Sans",system-ui,sans-serif;
      --body: "Instrument Sans",Inter,system-ui,-apple-system,sans-serif;
      --mono: "JetBrains Mono",ui-monospace,SFMono-Regular,Menlo,monospace;
    }
    * { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
    html { -webkit-text-size-adjust:100%; }
    body {
      font-family: var(--body);
      color: #EAF1FA;
      background:
        radial-gradient(1100px 720px at 6% -12%, rgba(61,224,192,.16), transparent 62%),
        radial-gradient(900px 760px at 104% 112%, rgba(91,155,255,.22), transparent 60%),
        linear-gradient(158deg,#05080F 0%,#0B1524 55%,#070C16 100%);
      background-attachment: fixed;
      min-height: 100svh;
      display: grid;
      place-items: center;
      padding: clamp(10px,2.4vw,36px);
      padding-top: max(clamp(10px,2.4vw,36px), env(safe-area-inset-top));
      padding-bottom: max(clamp(10px,2.4vw,36px), env(safe-area-inset-bottom));
      overflow-x: hidden;
    }
    body::after {
      content: "";
      position: fixed;
      inset: 0;
      pointer-events: none;
      opacity: .035;
      z-index: 5;
      background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='140' height='140'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='3'/></filter><rect width='140' height='140' filter='url(%23n)'/></svg>");
    }
    /* ---- card ---- */
    .card {
      width: 100%;
      max-width: 1080px;
      display: grid;
      grid-template-columns: minmax(0,1fr) clamp(340px,36vw,430px);
      border-radius: var(--r-card);
      border: 1px solid var(--hair);
      background: rgba(255,255,255,.05);
      backdrop-filter: blur(22px) saturate(140%);
      -webkit-backdrop-filter: blur(22px) saturate(140%);
      box-shadow: 0 40px 120px -34px rgba(0,0,0,.85), inset 0 1px 0 rgba(255,255,255,.14);
      overflow: hidden;
      animation: rise .7s cubic-bezier(.2,.7,.2,1) both;
    }
    @keyframes rise { from { opacity:0; transform:translateY(14px) } to { opacity:1; transform:none } }
    /* ---- pitch (left) ---- */
    .pitch {
      padding: var(--pad);
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: clamp(14px,1.6vw,22px);
    }
    .mark {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .mark span {
      width: 46px; height: 46px;
      flex: none;
      display: grid;
      place-items: center;
      border-radius: 14px;
      font-family: var(--display);
      font-size: 24px;
      font-weight: 700;
      color: #04121A;
      background: linear-gradient(145deg, var(--aqua), #7FE7FF);
      box-shadow: 0 10px 26px -10px rgba(61,224,192,.7);
    }
    .mark b {
      font-family: var(--display);
      font-size: 17px;
      font-weight: 600;
      letter-spacing: -.01em;
    }
    .eyebrow {
      font-family: var(--mono);
      font-size: 11px;
      letter-spacing: .18em;
      text-transform: uppercase;
      color: var(--aqua);
    }
    h1 {
      font-family: var(--display);
      font-size: clamp(30px,4.4vw,56px);
      font-weight: 800;
      line-height: .98;
      letter-spacing: -.035em;
    }
    h1 em {
      font-style: normal;
      background: linear-gradient(96deg, var(--aqua), var(--azure));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }
    .lede {
      max-width: 44ch;
      font-size: clamp(14px,1.15vw,16.5px);
      line-height: 1.65;
      color: #A9BCD2;
    }
    .lede-short { display: none; }
    .chips {
      display: grid;
      grid-template-columns: repeat(2,minmax(0,1fr));
      gap: 10px;
      max-width: 440px;
    }
    .chip {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 11px 13px;
      border-radius: 13px;
      border: 1px solid var(--hair);
      background: rgba(255,255,255,.04);
      font-size: 13.5px;
      font-weight: 500;
      color: #D3E1F0;
    }
    .chip svg { width:16px; height:16px; flex:none; stroke: var(--aqua); }
    /* ---- paper (right) ---- */
    .paper {
      background: linear-gradient(180deg,#FFFFFF 0%,#EEF2F8 100%);
      color: var(--night);
      padding: clamp(22px,3vw,38px);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 14px;
      position: relative;
    }
    .tag {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      font-family: var(--mono);
      font-size: 10.5px;
      letter-spacing: .14em;
      text-transform: uppercase;
      color: #0E7A66;
      background: #DFF7F0;
      border: 1px solid #B9EADD;
      padding: 6px 12px;
      border-radius: 999px;
    }
    .tag i { width:6px; height:6px; border-radius:50%; background:#12B892; box-shadow:0 0 0 3px rgba(18,184,146,.18); }
    .qr {
      position: relative;
      padding: 14px;
      border-radius: 22px;
      background: #fff;
      border: 1px solid rgba(10,18,32,.07);
      box-shadow: 0 26px 50px -24px rgba(10,18,32,.45);
      overflow: hidden;
    }
    .qr img {
      display: block;
      width: min(272px,58vw);
      height: auto;
      aspect-ratio: 1/1;
      object-fit: contain;
    }
    /* corner accents */
    .qr b {
      position: absolute;
      width: 24px; height: 24px;
      border: 2.5px solid var(--aqua);
      border-radius: 5px;
    }
    .qr b:nth-child(1) { top:9px; left:9px; border-right:0; border-bottom:0; }
    .qr b:nth-child(2) { top:9px; right:9px; border-left:0; border-bottom:0; }
    .qr b:nth-child(3) { bottom:9px; left:9px; border-right:0; border-top:0; }
    .qr b:nth-child(4) { bottom:9px; right:9px; border-left:0; border-top:0; }
    /* sweep animation */
    .qr::after {
      content: "";
      position: absolute;
      left: 0; right: 0;
      height: 34%;
      top: -34%;
      background: linear-gradient(180deg, transparent, rgba(61,224,192,.28));
      animation: sweep 4.5s cubic-bezier(.5,0,.5,1) infinite;
    }
    @keyframes sweep { 0% { top:-34% } 55% { top:100% } 100% { top:100% } }
    .perf {
      width: 100%;
      height: 1px;
      margin-top: 4px;
      background: repeating-linear-gradient(90deg, rgba(10,18,32,.22) 0 5px, transparent 5px 11px);
    }
    .store {
      font-family: var(--display);
      font-size: clamp(20px,2.2vw,26px);
      font-weight: 700;
      letter-spacing: -.02em;
      text-align: center;
    }
    .hint {
      margin-top: -8px;
      font-size: 13.5px;
      color: var(--slate);
      text-align: center;
    }
    /* ---- steps (collapsible) ---- */
    .steps {
      width: 100%;
      border-top: 1px solid rgba(10,18,32,.09);
      padding-top: 12px;
    }
    .steps summary {
      list-style: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      font-family: var(--mono);
      font-size: 11px;
      letter-spacing: .14em;
      text-transform: uppercase;
      color: #33475F;
      padding: 4px 0;
    }
    .steps summary::-webkit-details-marker { display: none; }
    .steps summary svg { width:15px; height:15px; stroke:#7A8CA1; transition: transform .25s ease; }
    .steps[open] summary svg { transform: rotate(180deg); }
    .steps ol {
      list-style: none;
      counter-reset: s;
      margin-top: 12px;
      display: grid;
      gap: 9px;
    }
    .steps li {
      counter-increment: s;
      display: flex;
      gap: 11px;
      align-items: flex-start;
      font-size: 13.5px;
      line-height: 1.45;
      color: #3B4C61;
    }
    .steps li::before {
      content: counter(s, decimal-leading-zero);
      font-family: var(--mono);
      font-size: 10.5px;
      color: #0E7A66;
      background: #E4F7F1;
      border-radius: 7px;
      padding: 3px 6px;
      flex: none;
    }
    .steps strong { color: var(--night); font-weight: 600; }
    .foot {
      font-family: var(--mono);
      font-size: 10px;
      letter-spacing: .16em;
      text-transform: uppercase;
      color: #93A2B4;
    }
    /* ---- download button (new) ---- */
    .dl-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-top: 4px;
      padding: 8px 18px;
      border-radius: 40px;
      font-family: var(--body);
      font-weight: 600;
      font-size: 13px;
      color: #0B1524;
      background: linear-gradient(145deg, #E0F7F0, #C5EDE0);
      border: 1px solid #B0DDCC;
      box-shadow: 0 6px 14px -8px rgba(0,0,0,.2);
      text-decoration: none;
      transition: all .15s ease;
      cursor: pointer;
    }
    .dl-btn:hover {
      background: #CDF3E8;
      transform: translateY(-1px);
      box-shadow: 0 10px 20px -12px rgba(0,0,0,.3);
    }
    .dl-btn:active { transform: translateY(0); }
    .dl-btn svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

    /* ---- responsive (same as original) ---- */
    @media (max-width:980px) {
      .card { grid-template-columns:1fr; max-width:560px; }
      .pitch { gap:12px; padding:clamp(20px,4vw,30px); }
      h1 { font-size:clamp(26px,6.4vw,34px); }
      .lede { max-width:none; }
      .chips { grid-template-columns:repeat(2,1fr); gap:8px; }
      .chip { padding:9px 11px; font-size:12.5px; }
    }
    @media (max-width:640px) {
      .card { border-radius:24px; }
      .pitch {
        flex-direction: row;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px 12px;
        padding: 16px 18px;
        border-bottom: 1px solid var(--hair);
      }
      .mark { flex:none; }
      .mark span { width:40px; height:40px; font-size:21px; border-radius:12px; }
      .eyebrow { display:none; }
      h1 { font-size:24px; flex:1; min-width:150px; text-align:right; }
      .lede { flex:1 0 100%; font-size:13px; line-height:1.5; color:#9FB3CA; }
      .lede-long { display:none; }
      .lede-short { display:inline; }
      .chips { display:none; }
      .paper { padding:22px 18px 20px; gap:12px; }
      .qr { padding:11px; border-radius:18px; }
      .qr img { width:min(230px,60vw); }
      .hint { font-size:12.5px; }
      .steps li { font-size:13px; }
    }
    @media (max-width:380px) {
      h1 { font-size:21px; }
      .qr img { width:200px; }
    }
    @media (prefers-reduced-motion:reduce) {
      *, *::after { animation:none!important; transition:none!important; }
    }
  </style>
</head>
<body>

<main class="card">

  <!-- LEFT: pitch -->
  <section class="pitch">
    <div class="mark">
      <span>&#8369;</span>
      <b>Spin Klean Laundry JP</b>
    </div>
    <p class="eyebrow">QRPH &middot; Cashless payment</p>
    <h1>Pay in <em>one scan</em>.</h1>
    <p class="lede">
      <span class="lede-long">Use any QRPH-enabled app — GCash, Maya, BPI, BDO, Metrobank, LandBank, UnionBank and more. No cash to count, no card to swipe.</span>
      <span class="lede-short">Works with GCash, Maya and any QRPH bank app.</span>
    </p>
    <div class="chips">
      <div class="chip"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg> Instant transfer</div>
      <div class="chip"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg> Bank-grade security</div>
      <div class="chip"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2.5"/><path d="M11 18h2"/></svg> Any QRPH app</div>
      <div class="chip"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Receipt on your phone</div>
    </div>
  </section>

  <!-- RIGHT: paper / QR -->
  <section class="paper">

    <p class="tag"><i></i>QRPH accepted</p>

    <div class="qr" id="qrContainer">
      <b></b><b></b><b></b><b></b>
      <!-- QR image – replace 'uploads/pay.png' with your actual image path -->
      <img id="qrImage" src="{{ asset('uploads/pay.png') }}" alt="QR code for paying Spin Klean Laundry JP">
    </div>

    <div class="perf"></div>

    <p class="store">Spin Klean Laundry JP</p>
    <p class="hint">Scan the code to pay. Amount is entered in your app.</p>

    <!-- DOWNLOAD BUTTON (added) -->
    <a id="downloadQrBtn" class="dl-btn" download="SpinKlean_QR.png" href="#">
      <svg viewBox="0 0 24 24"><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/><polyline points="8 12 12 16 16 12"/><line x1="12" y1="2" x2="12" y2="16"/></svg>
      Download QR
    </a>

    <details class="steps" id="steps">
      <summary>
        Payment steps
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
      </summary>
      <ol>
        <li>Open your bank or e-wallet app.</li>
        <li>Tap <strong>Scan QR</strong>.</li>
        <li>Point your camera at the code above.</li>
        <li>Enter the amount.</li>
        <li>Confirm the payment.</li>
        <li>Show the receipt to our cashier.</li>
      </ol>
    </details>

    <p class="foot">Powered by QRPH</p>
  </section>
</main>

<script>
  (function() {
    // ---- steps: open on wide, closed on mobile ----
    var steps = document.getElementById('steps'),
        wide  = window.matchMedia('(min-width: 641px)');

    function syncSteps(e) { steps.open = e.matches; }
    syncSteps(wide);
    wide.addEventListener('change', syncSteps);

    // ---- QR download: fetch image and trigger download ----
    var downloadBtn = document.getElementById('downloadQrBtn');
    var qrImg = document.getElementById('qrImage');

    // Ensure the download link points to the actual image source
    // If the image src uses a placeholder or dynamic route, we handle it.
    function setDownloadLink() {
      // Use the current src of the img element.
      var imgSrc = qrImg.getAttribute('src');
      if (imgSrc) {
        // If it's a relative path, make it absolute for download.
        var absUrl = new URL(imgSrc, window.location.href).href;
        downloadBtn.setAttribute('href', absUrl);
        // Ensure the filename is meaningful
        downloadBtn.setAttribute('download', 'SpinKlean_QR.png');
      } else {
        // fallback: if no src, use a data-uri placeholder (should not happen)
        downloadBtn.setAttribute('href', '#');
        downloadBtn.setAttribute('download', '');
      }
    }

    // In case the image loads lazily or changes, we set the link after load.
    if (qrImg.complete) {
      setDownloadLink();
    } else {
      qrImg.addEventListener('load', setDownloadLink);
    }
    // Also if the image src is updated later (e.g., by Vite), we watch.
    // A simple MutationObserver can catch attribute changes.
    var observer = new MutationObserver(function(mutations) {
      mutations.forEach(function(mutation) {
        if (mutation.attributeName === 'src') {
          setDownloadLink();
        }
      });
    });
    observer.observe(qrImg, { attributes: true });

    // If for any reason the download still points to a placeholder (like {{ asset }})
    // we can force a canvas-based fallback: but we keep it simple, the download uses the img src.
    // Also handle when the image fails to load.
    qrImg.addEventListener('error', function() {
      // Optionally set a fallback QR (but we keep the broken link)
      console.warn('QR image failed to load. Download may not work.');
    });

    // For extra safety, also set the link on page load.
    window.addEventListener('load', function() {
      setDownloadLink();
    });

  })();
</script>

<!-- small note: the QR image is fetched from 'uploads/pay.png' – you can replace with your own -->
</body>
</html>