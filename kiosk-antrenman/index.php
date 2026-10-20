<?php
/**
 * Antrenman kiosku — ekran. Kart / QR okuyucu klavye gibi davranır: numarayı yazıp Enter'a basar.
 * Sayfa tuş vuruşlarını dinler (odak gerekmez), veriyi api.php'den alır. API anahtarı burada YOK.
 */
declare(strict_types=1);
$ayar = require __DIR__ . '/config.php';
function e($d): string { return htmlspecialchars((string)$d, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    <title><?= e($ayar['baslik']) ?></title>
    <style>
        :root { --zemin: #0f172a; --kart: #1e293b; --kart2: #273449; --yazi: #f1f5f9; --soluk: #94a3b8; --ana: #22c55e; --hata: #f87171; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { margin: 0; height: 100%; background: var(--zemin); color: var(--yazi);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; user-select: none; overflow: hidden; }
        .ekran { position: absolute; inset: 0; display: none; flex-direction: column; padding: 32px 40px; }
        .ekran.acik { display: flex; }
        /* Bekleme */
        #bekle { align-items: center; justify-content: center; text-align: center; gap: 28px; }
        #bekle h1 { font-size: 56px; margin: 0; }
        #bekle p { font-size: 28px; color: var(--soluk); margin: 0; }
        .halka { width: 180px; height: 180px; border-radius: 50%; border: 6px solid var(--ana); display: grid; place-items: center;
            animation: nabiz 2s ease-in-out infinite; font-size: 72px; }
        @keyframes nabiz { 0%,100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.08); opacity: .7; } }
        /* Ortak üst çubuk */
        .ust { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 24px; }
        .ust h2 { font-size: 40px; margin: 0; }
        .ust small { display: block; color: var(--soluk); font-size: 20px; margin-top: 4px; }
        button { font: inherit; color: inherit; border: 0; cursor: pointer; }
        .dugme { background: var(--kart2); border-radius: 14px; padding: 16px 26px; font-size: 22px; font-weight: 600; }
        .dugme.ana { background: var(--ana); color: #052e16; }
        .kaydir { overflow-y: auto; flex: 1; padding-bottom: 24px; }
        /* Programlar */
        .plan { background: var(--kart); border-radius: 20px; padding: 24px; margin-bottom: 20px; }
        .plan h3 { margin: 0 0 6px; font-size: 30px; }
        .plan .bilgi { color: var(--soluk); font-size: 20px; margin-bottom: 18px; }
        .gunler { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; }
        .gun { background: var(--kart2); border-radius: 16px; padding: 22px; text-align: left; font-size: 24px; font-weight: 700; min-height: 96px; }
        .gun span { display: block; font-size: 17px; font-weight: 400; color: var(--soluk); margin-top: 6px; }
        .bos { color: var(--soluk); font-size: 26px; text-align: center; margin-top: 60px; }
        /* Hareketler */
        .hareketler { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 18px; }
        .hareket { background: var(--kart); border-radius: 18px; overflow: hidden; display: flex; flex-direction: column; }
        .hareket img { width: 100%; height: 190px; object-fit: cover; background: var(--kart2); }
        .hareket .icerik { padding: 18px 20px; }
        .hareket h4 { margin: 0 0 4px; font-size: 24px; }
        .hareket .kas { color: var(--soluk); font-size: 16px; margin-bottom: 12px; }
        .etiketler { display: flex; flex-wrap: wrap; gap: 8px; }
        .etiket { background: var(--kart2); border-radius: 10px; padding: 8px 12px; font-size: 18px; }
        .etiket b { color: var(--ana); }
        .not { margin-top: 12px; color: var(--soluk); font-size: 17px; }
        /* Uyarı ve sayaç */
        #uyari { position: fixed; left: 50%; bottom: 40px; transform: translateX(-50%); background: #450a0a; color: var(--hata);
            border-radius: 14px; padding: 18px 28px; font-size: 24px; display: none; max-width: 90vw; text-align: center; }
        #sayac { position: fixed; left: 0; bottom: 0; height: 6px; background: var(--ana); width: 0; transition: width 1s linear; }
        #yukleniyor { position: fixed; inset: 0; background: rgba(15,23,42,.6); display: none; place-items: center; font-size: 30px; }
        @media (max-width: 700px) { .ekran { padding: 20px 16px; } #bekle h1 { font-size: 36px; } .ust h2 { font-size: 28px; } }
    </style>
</head>
<body>
<section id="bekle" class="ekran acik">
    <div class="halka" aria-hidden="true">💪</div>
    <h1><?= e($ayar['baslik']) ?></h1>
    <p><?= e($ayar['alt_yazi']) ?></p>
</section>

<section id="programlar" class="ekran">
    <div class="ust">
        <div><h2 id="merhaba"></h2><small>Bir gün seçin</small></div>
        <button class="dugme" data-cikis>Çıkış</button>
    </div>
    <div class="kaydir" id="planListe"></div>
</section>

<section id="gun" class="ekran">
    <div class="ust">
        <div><h2 id="gunBaslik"></h2><small id="gunAlt"></small></div>
        <div style="display:flex;gap:12px">
            <button class="dugme" id="geri">← Programlar</button>
            <button class="dugme" data-cikis>Çıkış</button>
        </div>
    </div>
    <div class="kaydir"><div class="hareketler" id="hareketListe"></div></div>
</section>

<div id="uyari" role="alert"></div>
<div id="yukleniyor">Yükleniyor…</div>
<div id="sayac"></div>

<script>
(() => {
    const BEKLEME = <?= (int)$ayar['bekleme_saniye'] ?>;
    const $ = (id) => document.getElementById(id);
    const el = (etiket, sinif, metin) => {
        const d = document.createElement(etiket);
        if (sinif) d.className = sinif;
        if (metin !== undefined && metin !== null) d.textContent = String(metin);
        return d;
    };

    let planlar = [];
    let kalan = 0;
    let mesgul = false;

    function goster(id) {
        document.querySelectorAll('.ekran').forEach(s => s.classList.toggle('acik', s.id === id));
    }

    function uyar(metin) {
        const u = $('uyari');
        u.textContent = metin;
        u.style.display = 'block';
        clearTimeout(uyar.t);
        uyar.t = setTimeout(() => { u.style.display = 'none'; }, 4000);
    }

    async function istek(islem, govde) {
        $('yukleniyor').style.display = 'grid';
        try {
            const r = await fetch('api.php?islem=' + islem, {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(govde || {}), credentials: 'same-origin',
            });
            const veri = await r.json().catch(() => ({}));
            if (!r.ok) throw new Error(veri.hata || 'Bir sorun oluştu.');
            return veri;
        } finally {
            $('yukleniyor').style.display = 'none';
        }
    }

    // --- Hareketsizlik sayacı ---------------------------------------------
    function sayaciBaslat() { kalan = BEKLEME; ciz(); }
    function ciz() { $('sayac').style.width = (kalan > 0 ? (kalan / BEKLEME) * 100 : 0) + 'vw'; }
    setInterval(() => {
        if (kalan <= 0) return;
        kalan--; ciz();
        if (kalan === 0) cikis();
    }, 1000);
    ['pointerdown', 'touchstart'].forEach(o => document.addEventListener(o, () => { if (kalan > 0) sayaciBaslat(); }, {passive: true}));

    async function cikis() {
        kalan = 0; ciz();
        planlar = [];
        goster('bekle');
        try { await fetch('api.php?islem=cikis', {method: 'POST', credentials: 'same-origin'}); } catch (e) {}
    }
    document.querySelectorAll('[data-cikis]').forEach(b => b.addEventListener('click', cikis));
    $('geri').addEventListener('click', () => goster('programlar'));

    // --- Kart / QR okuyucu (klavye gibi yazar + Enter) --------------------
    let tampon = '';
    let sonTus = 0;
    document.addEventListener('keydown', (ev) => {
        const simdi = Date.now();
        if (simdi - sonTus > 300) tampon = '';   // okuyucu hızlı yazar; yavaş yazılanlar birikmez
        sonTus = simdi;
        if (ev.key === 'Enter') {
            const kod = tampon.trim();
            tampon = '';
            if (kod.length >= 4) kartOkundu(kod);
            ev.preventDefault();
            return;
        }
        if (ev.key.length === 1) tampon += ev.key;
    });

    async function kartOkundu(kod) {
        if (mesgul) return;
        mesgul = true;
        try {
            const veri = await istek('kart', {kod});
            planlar = veri.planlar || [];
            programlariCiz(veri.ad || '');
            goster('programlar');
            sayaciBaslat();
        } catch (e) {
            uyar(e.message);
        } finally {
            mesgul = false;
        }
    }

    function programlariCiz(ad) {
        $('merhaba').textContent = 'Merhaba ' + ad;
        const liste = $('planListe');
        liste.replaceChildren();
        if (planlar.length === 0) {
            liste.append(el('div', 'bos', 'Size tanımlı güncel bir antrenman programı yok. Antrenörünüze başvurun.'));
            return;
        }
        for (const p of planlar) {
            const kutu = el('div', 'plan');
            kutu.append(el('h3', '', p.ad));
            const bilgi = [p.hedef, p.seviye, p.bitis ? 'Bitiş: ' + p.bitis : ''].filter(Boolean).join(' · ');
            if (bilgi) kutu.append(el('div', 'bilgi', bilgi));
            const gunler = el('div', 'gunler');
            for (const g of p.gunler) {
                const b = el('button', 'gun', g.ad);
                if (g.aciklama) b.append(el('span', '', g.aciklama));
                b.addEventListener('click', () => gunAc(p, g));
                gunler.append(b);
            }
            if (p.gunler.length === 0) gunler.append(el('div', 'bilgi', 'Bu programda gün tanımlı değil.'));
            kutu.append(gunler);
            liste.append(kutu);
        }
    }

    async function gunAc(plan, gun) {
        try {
            const veri = await istek('gun', {planId: plan.planId, grupId: gun.grupId});
            $('gunBaslik').textContent = gun.ad;
            $('gunAlt').textContent = plan.ad + (gun.aciklama ? ' · ' + gun.aciklama : '');
            const liste = $('hareketListe');
            liste.replaceChildren();
            const hareketler = veri.hareketler || [];
            if (hareketler.length === 0) liste.append(el('div', 'bos', 'Bu günde hareket yok.'));
            for (const h of hareketler) {
                const kart = el('div', 'hareket');
                if (h.gorsel) {
                    const img = el('img');
                    img.src = h.gorsel; img.alt = ''; img.loading = 'lazy';
                    img.onerror = () => img.remove();
                    kart.append(img);
                }
                const ic = el('div', 'icerik');
                ic.append(el('h4', '', h.ad));
                if (h.kasGrubu) ic.append(el('div', 'kas', h.kasGrubu));
                const et = el('div', 'etiketler');
                const ekle = (deger, birim) => {
                    if (deger === null || deger === undefined || deger === '' || deger === 0) return;
                    const s = el('span', 'etiket');
                    s.append(el('b', '', deger));
                    s.append(document.createTextNode(' ' + birim));
                    et.append(s);
                };
                // Panelle aynı: süreli harekette (sure = dakika) set/tekrar gösterilmez.
                if (h.sure) {
                    ekle(h.sure, 'dk');
                } else {
                    ekle(h.set, 'set');
                    ekle(h.tekrar, 'tekrar');
                }
                ekle(h.agirlik, 'kg');
                ekle(h.mola, 'sn mola');
                ic.append(et);
                if (h.not) ic.append(el('div', 'not', h.not));
                kart.append(ic);
                liste.append(kart);
            }
            goster('gun');
            sayaciBaslat();
        } catch (e) {
            uyar(e.message);
            if (/Süre doldu/.test(e.message)) cikis();
        }
    }

    // Kiosk tarayıcısında sağ tık / sürükleme menüsü açılmasın.
    document.addEventListener('contextmenu', e => e.preventDefault());
})();
</script>
</body>
</html>
