<?php
/**
 * GymTekno API ile web sitesi kayıt formu — örnek.
 *
 * Akış: şube listesi + alan şeması API'den okunur (önbellekli) → ziyaretçi formu doldurur →
 * sunucu doğrular → POST musteri/create-customer → kayıt panelde "Potansiyel müşteriler"de görünür.
 *
 * API anahtarı yalnız bu sunucuda kullanılır; tarayıcıya gitmez.
 */
declare(strict_types=1);

require __DIR__ . '/GymTeknoApi.php';

$ayar = require __DIR__ . '/config.php';
$api = new GymTeknoApi($ayar['api_kok'], $ayar['tesis'], $ayar['api_anahtari']);

session_start();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function e($deger): string
{
    return htmlspecialchars((string)$deger, ENT_QUOTES, 'UTF-8');
}

/** API'den gelen sabit verileri (şube, şema) dosyada önbellekler. */
function onbellekli(string $ad, int $saniye, callable $uret): array
{
    $dosya = sys_get_temp_dir() . '/gt_form_' . md5($ad) . '.json';
    if (is_file($dosya) && filemtime($dosya) > time() - $saniye) {
        $veri = json_decode((string)file_get_contents($dosya), true);
        if (is_array($veri)) {
            return $veri;
        }
    }
    $veri = $uret();
    @file_put_contents($dosya, json_encode($veri, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $veri;
}

/** IP başına basit deneme sınırı (bot / kötüye kullanım için). */
function denemeSiniriAsildi(array $ayar): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    $dosya = sys_get_temp_dir() . '/gt_form_ip_' . md5($ip) . '.json';
    $simdi = time();
    $kayitlar = is_file($dosya) ? (json_decode((string)file_get_contents($dosya), true) ?: []) : [];
    $kayitlar = array_values(array_filter($kayitlar, fn($t) => $t > $simdi - $ayar['deneme_penceresi']));
    if (count($kayitlar) >= $ayar['deneme_siniri']) {
        return true;
    }
    $kayitlar[] = $simdi;
    @file_put_contents($dosya, json_encode($kayitlar), LOCK_EX);
    return false;
}

/** reCAPTCHA cevabını Google'a doğrulatır. Ayar boşsa (kapalı) true döner. */
function recaptchaGecerli(array $ayar, string $cevap): bool
{
    if ($ayar['recaptcha_secret'] === '' || $ayar['recaptcha_site_key'] === '') {
        return true;
    }
    if ($cevap === '') {
        return false;
    }
    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => $ayar['recaptcha_secret'],
            'response' => $cevap,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $yanit = curl_exec($ch);
    curl_close($ch);
    $veri = is_string($yanit) ? json_decode($yanit, true) : null;
    if (!is_array($veri)) {
        error_log('[kayit-formu] recaptcha dogrulanamadi (baglanti)');
        return false; // doğrulanamıyorsa kayıt alınmaz
    }
    // Başka sitede çözülmüş bir cevap kullanılmasın: hostname bu isteğin adresiyle eşleşmeli.
    $host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    return ($veri['success'] ?? false) === true
        && ($host === '' || strtolower((string)($veri['hostname'] ?? '')) === $host);
}

function tcGecerli(string $tc): bool
{
    if (!preg_match('/^[1-9][0-9]{10}$/', $tc)) {
        return false;
    }
    $h = array_map('intval', str_split($tc));
    $tek = $h[0] + $h[2] + $h[4] + $h[6] + $h[8];
    $cift = $h[1] + $h[3] + $h[5] + $h[7];
    if ((($tek * 7) - $cift) % 10 !== $h[9]) {
        return false;
    }
    return array_sum(array_slice($h, 0, 10)) % 10 === $h[10];
}

/** 05xx, 5xx, +905xx, 905xx biçimlerini +905XXXXXXXXX'e çevirir; geçersizse null. */
function telefonDuzelt(string $girdi): ?string
{
    $r = preg_replace('/\D/', '', $girdi);
    if (strlen($r) === 12 && strpos($r, '90') === 0) {
        $r = substr($r, 2);
    } elseif (strlen($r) === 11 && $r[0] === '0') {
        $r = substr($r, 1);
    }
    return (strlen($r) === 10 && $r[0] === '5') ? '+90' . $r : null;
}

// --- API'den sabit veriler ---------------------------------------------------
$hataMesaji = null;
$alanHatalari = [];
$subeler = [];
$sema = [];
try {
    $subeler = onbellekli($ayar['tesis'] . ':subeler', $ayar['onbellek_saniye'], fn() => $api->subeler());
    $sema = onbellekli($ayar['tesis'] . ':sema', $ayar['onbellek_saniye'], fn() => $api->alanSemasi());
} catch (GymTeknoApiHatasi $ex) {
    error_log('[kayit-formu] sema okunamadi: ' . $ex->hata . ' ' . $ex->hataKodu);
    $hataMesaji = 'Kayıt formu şu an kullanılamıyor. Lütfen daha sonra tekrar deneyin.';
}

// Tesisin zorunlu tuttuğu alanlar (bireysel kayıt). Yalnız formda SORULAN alanlar dikkate alınır;
// panelin iç alanları (sözleşme no, referans, müşteri grubu ...) web ziyaretçisine sorulmaz.
$zorunlu = [];
foreach ($sema['requiredFields'] ?? [] as $r) {
    if (!empty($r['requiredForIndividual'])) {
        $zorunlu[$r['fieldKey']] = true;
    }
}
$z = fn(string $anahtar) => isset($zorunlu[$anahtar]);

// Tesise özel alanlar: aktif, bireysel kayıtta görünen, şubeye bağlı olmayan (ya da sabit şubenin).
$ozelAlanlar = array_values(array_filter($sema['customFields'] ?? [], function ($a) use ($ayar) {
    return !empty($a['isActive']) && !empty($a['showForIndividual']) && empty($a['remove'])
        && (empty($a['branchId']) || $a['branchId'] === $ayar['sabit_sube']);
}));
usort($ozelAlanlar, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

$meslekler = $sema['meslekler'] ?? [];
$v = $_POST; // formu hata sonrası yeniden doldurmak için

// --- Gönderim ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hataMesaji === null) {
    $ad = trim((string)($v['ad'] ?? ''));
    $soyad = trim((string)($v['soyad'] ?? ''));
    $telefon = trim((string)($v['telefon'] ?? ''));
    $eposta = trim((string)($v['eposta'] ?? ''));
    $dogum = trim((string)($v['dogum'] ?? ''));
    $cinsiyet = (string)($v['cinsiyet'] ?? '');
    $tc = trim((string)($v['tc'] ?? ''));
    $adres = trim((string)($v['adres'] ?? ''));
    $meslek = (string)($v['meslek'] ?? '');
    $sube = $ayar['sabit_sube'] !== '' ? $ayar['sabit_sube'] : (string)($v['sube'] ?? '');

    if (!hash_equals($_SESSION['csrf'], (string)($v['csrf'] ?? ''))) {
        $hataMesaji = 'Oturum süresi doldu, lütfen formu yeniden gönderin.';
    } elseif (($v['web_adresi'] ?? '') !== '') {
        // Gizli tuzak alanı doldurulmuş: bot. Sessizce "tamam" deyip bırakıyoruz.
        header('Location: ?tamam=1');
        exit;
    } else {
        if ($ad === '' || mb_strlen($ad) > 60) $alanHatalari['ad'] = 'Adınızı yazın.';
        if ($soyad === '' || mb_strlen($soyad) > 60) $alanHatalari['soyad'] = 'Soyadınızı yazın.';

        $telefonDuz = $telefon === '' ? null : telefonDuzelt($telefon);
        if ($telefon !== '' && $telefonDuz === null) $alanHatalari['telefon'] = 'Telefonu 05XX XXX XX XX biçiminde yazın.';
        elseif ($telefon === '' && $z('gsm_no')) $alanHatalari['telefon'] = 'Telefon zorunludur.';

        if ($eposta !== '' && !filter_var($eposta, FILTER_VALIDATE_EMAIL)) $alanHatalari['eposta'] = 'Geçerli bir e-posta yazın.';
        elseif ($eposta === '' && $z('eposta')) $alanHatalari['eposta'] = 'E-posta zorunludur.';

        if ($dogum !== '') {
            $t = DateTime::createFromFormat('!Y-m-d', $dogum);
            if (!$t || $t > new DateTime('today') || $t < new DateTime('-100 years')) $alanHatalari['dogum'] = 'Geçerli bir doğum tarihi seçin.';
        } elseif ($z('dogum_tarih')) {
            $alanHatalari['dogum'] = 'Doğum tarihi zorunludur.';
        }

        if (!in_array($cinsiyet, ['', 'Kadın', 'Erkek'], true)) $cinsiyet = '';
        if ($cinsiyet === '' && $z('cinsiyet')) $alanHatalari['cinsiyet'] = 'Cinsiyet seçin.';

        if ($tc !== '' && !tcGecerli($tc)) $alanHatalari['tc'] = 'T.C. kimlik numarası geçersiz.';
        elseif ($tc === '' && $z('vatandaslik_no')) $alanHatalari['tc'] = 'T.C. kimlik numarası zorunludur.';

        if ($adres === '' && $z('adres')) $alanHatalari['adres'] = 'Adres zorunludur.';
        if (mb_strlen($adres) > 500) $alanHatalari['adres'] = 'Adres çok uzun.';

        $meslekIdler = array_column($meslekler, 'objectId');
        if ($meslek !== '' && !in_array($meslek, $meslekIdler, true)) $meslek = '';
        if ($meslek === '' && $z('meslek') && $meslekler) $alanHatalari['meslek'] = 'Meslek seçin.';

        if (!in_array($sube, array_column($subeler, 'objectId'), true)) $alanHatalari['sube'] = 'Şube seçin.';

        // Tesise özel alanlar
        $ozelDegerler = [];
        foreach ($ozelAlanlar as $a) {
            $k = $a['fieldKey'];
            $girdi = $v['ozel'][$k] ?? '';
            if (($a['fieldType'] ?? '') === 'checkbox') {
                $deger = $girdi === '1';
                if (!$deger && !empty($a['isRequired'])) $alanHatalari['ozel_' . $k] = 'Bu alan zorunludur.';
                $ozelDegerler[$k] = $deger;
                continue;
            }
            $deger = trim((string)$girdi);
            if (($a['fieldType'] ?? '') === 'dropdown' && $deger !== ''
                && !in_array($deger, array_column($a['options'] ?? [], 'value'), true)) {
                $deger = '';
            }
            if ($deger === '' && !empty($a['isRequired'])) {
                $alanHatalari['ozel_' . $k] = 'Bu alan zorunludur.';
            } elseif ($deger !== '' && !empty($a['validationRegex'])
                && @preg_match('/' . str_replace('/', '\/', $a['validationRegex']) . '/u', $deger) === 0) {
                $alanHatalari['ozel_' . $k] = $a['validationMessage'] ?: 'Geçersiz değer.';
            }
            if ($deger !== '') $ozelDegerler[$k] = $deger;
        }

        if (($v['kvkk'] ?? '') !== '1') $alanHatalari['kvkk'] = 'Devam etmek için aydınlatma metnini okuduğunuzu işaretleyin.';

        if (!$alanHatalari && !recaptchaGecerli($ayar, (string)($v['g-recaptcha-response'] ?? ''))) {
            $alanHatalari['recaptcha'] = 'Lütfen "Ben robot değilim" kutusunu işaretleyin.';
        }

        if (!$alanHatalari) {
            if (denemeSiniriAsildi($ayar)) {
                $hataMesaji = 'Çok fazla deneme yapıldı. Lütfen biraz sonra tekrar deneyin.';
            } else {
                $govde = [
                    'branch'        => $sube,
                    'name'          => $ad,
                    'surname'       => $soyad,
                    'gsmNo'         => $telefonDuz,
                    'eposta'        => $eposta !== '' ? mb_strtolower($eposta) : null,
                    'birthday'      => $dogum !== '' ? $dogum : null,
                    'gender'        => $cinsiyet !== '' ? $cinsiyet : null,
                    'vatandaslikNo' => $tc !== '' ? $tc : null,
                    'adres'         => $adres !== '' ? $adres : null,
                    'meslekId'      => $meslek !== '' ? $meslek : null,
                    'satisKanali'   => $ayar['satis_kanali'] !== '' ? $ayar['satis_kanali'] : null,
                    'aboneAciklama' => $ayar['aciklama_notu'],
                    'customFields'  => $ozelDegerler ?: null,
                ];
                $govde = array_filter($govde, fn($d) => $d !== null);

                try {
                    $api->musteriEkle($govde);
                    unset($_SESSION['csrf']);
                    header('Location: ?tamam=1'); // yenileyince ikinci kayıt açılmasın
                    exit;
                } catch (GymTeknoApiHatasi $ex) {
                    // API metnini ziyaretçiye GÖSTERMEYİN: mükerrer kayıt hatası diğer üyenin adını içerir.
                    error_log('[kayit-formu] kayit acilamadi: http=' . $ex->httpKodu . ' ' . $ex->hata . ' ' . $ex->hataKodu);
                    if (in_array($ex->hata, ['session_error', 'kapsam_disi', 'api_kapali', 'istemci_engellendi'], true) || $ex->httpKodu === 0) {
                        $hataMesaji = 'Kayıt şu an alınamıyor. Lütfen daha sonra tekrar deneyin ya da tesisimizi arayın.';
                    } else {
                        $hataMesaji = 'Kaydınız alınamadı. Bu telefon, e-posta ya da T.C. ile daha önce kayıt olmuş olabilirsiniz; lütfen tesisimizle iletişime geçin.';
                    }
                }
            }
        }
    }
}

$tamam = isset($_GET['tamam']);
$hataGoster = fn(string $alan) => isset($alanHatalari[$alan]) ? '<small class="hata">' . e($alanHatalari[$alan]) . '</small>' : '';
$yildiz = fn(bool $zorunluMu) => $zorunluMu ? ' <span class="yildiz">*</span>' : '';
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Üyelik Ön Kayıt</title>
    <style>
        :root { --ana: #1f6feb; --yazi: #1d2433; --soluk: #6b7280; --cizgi: #d9dde5; --hata: #c62828; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f4f6fa; color: var(--yazi); }
        .kutu { max-width: 640px; margin: 32px auto; background: #fff; border-radius: 14px; padding: 28px; box-shadow: 0 6px 24px rgba(16,24,40,.08); }
        h1 { margin: 0 0 4px; font-size: 24px; }
        p.alt { margin: 0 0 24px; color: var(--soluk); }
        .izgara { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 16px; }
        .tam { grid-column: 1 / -1; }
        label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 6px; }
        input[type=text], input[type=email], input[type=tel], input[type=date], select, textarea {
            width: 100%; padding: 10px 12px; border: 1px solid var(--cizgi); border-radius: 8px; font: inherit; background: #fff;
        }
        textarea { min-height: 72px; resize: vertical; }
        input:focus, select:focus, textarea:focus { outline: 2px solid var(--ana); outline-offset: -1px; border-color: var(--ana); }
        .secim { display: flex; gap: 18px; padding-top: 8px; font-weight: 400; }
        .secim label, .onay { font-weight: 400; display: flex; gap: 8px; align-items: flex-start; }
        .yildiz { color: var(--hata); }
        small.hata { color: var(--hata); display: block; margin-top: 4px; }
        .uyari { padding: 12px 14px; border-radius: 8px; background: #fdecea; color: var(--hata); margin-bottom: 18px; }
        .basari { text-align: center; padding: 24px 0; }
        .basari h1 { color: #1b7f3b; }
        button { margin-top: 20px; width: 100%; padding: 12px; border: 0; border-radius: 10px; background: var(--ana); color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        .tuzak { position: absolute; left: -10000px; }
        .recaptcha { margin-top: 18px; }
        a { color: var(--ana); }
        @media (max-width: 560px) { .kutu { margin: 0; border-radius: 0; padding: 20px 16px; } .izgara { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="kutu">
<?php if ($tamam): ?>
    <div class="basari">
        <h1>Teşekkürler!</h1>
        <p>Ön kaydınız alındı. Ekibimiz en kısa sürede sizinle iletişime geçecek.</p>
    </div>
<?php else: ?>
    <h1>Üyelik Ön Kayıt</h1>
    <p class="alt">Bilgilerinizi bırakın, sizi arayalım.</p>

    <?php if ($hataMesaji): ?><div class="uyari"><?= e($hataMesaji) ?></div><?php endif; ?>

    <?php if (!$subeler && $hataMesaji === null): ?>
        <div class="uyari">Kayıt formu şu an kullanılamıyor.</div>
    <?php elseif ($subeler): ?>
    <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <div class="tuzak" aria-hidden="true">
            <label>Web adresiniz <input type="text" name="web_adresi" tabindex="-1" autocomplete="off"></label>
        </div>

        <div class="izgara">
            <div>
                <label for="ad">Ad<?= $yildiz(true) ?></label>
                <input id="ad" type="text" name="ad" maxlength="60" autocomplete="given-name" value="<?= e($v['ad'] ?? '') ?>">
                <?= $hataGoster('ad') ?>
            </div>
            <div>
                <label for="soyad">Soyad<?= $yildiz(true) ?></label>
                <input id="soyad" type="text" name="soyad" maxlength="60" autocomplete="family-name" value="<?= e($v['soyad'] ?? '') ?>">
                <?= $hataGoster('soyad') ?>
            </div>
            <div>
                <label for="telefon">Cep telefonu<?= $yildiz($z('gsm_no')) ?></label>
                <input id="telefon" type="tel" name="telefon" placeholder="05XX XXX XX XX" autocomplete="tel" value="<?= e($v['telefon'] ?? '') ?>">
                <?= $hataGoster('telefon') ?>
            </div>
            <div>
                <label for="eposta">E-posta<?= $yildiz($z('eposta')) ?></label>
                <input id="eposta" type="email" name="eposta" autocomplete="email" value="<?= e($v['eposta'] ?? '') ?>">
                <?= $hataGoster('eposta') ?>
            </div>
            <div>
                <label for="dogum">Doğum tarihi<?= $yildiz($z('dogum_tarih')) ?></label>
                <input id="dogum" type="date" name="dogum" max="<?= date('Y-m-d') ?>" value="<?= e($v['dogum'] ?? '') ?>">
                <?= $hataGoster('dogum') ?>
            </div>
            <div>
                <label>Cinsiyet<?= $yildiz($z('cinsiyet')) ?></label>
                <div class="secim">
                    <?php foreach (['Kadın', 'Erkek'] as $c): ?>
                        <label><input type="radio" name="cinsiyet" value="<?= e($c) ?>" <?= ($v['cinsiyet'] ?? '') === $c ? 'checked' : '' ?>> <?= e($c) ?></label>
                    <?php endforeach; ?>
                </div>
                <?= $hataGoster('cinsiyet') ?>
            </div>
            <div>
                <label for="tc">T.C. kimlik no<?= $yildiz($z('vatandaslik_no')) ?></label>
                <input id="tc" type="text" name="tc" inputmode="numeric" maxlength="11" value="<?= e($v['tc'] ?? '') ?>">
                <?= $hataGoster('tc') ?>
            </div>
            <?php if ($meslekler): ?>
            <div>
                <label for="meslek">Meslek<?= $yildiz($z('meslek')) ?></label>
                <select id="meslek" name="meslek">
                    <option value="">Seçin</option>
                    <?php foreach ($meslekler as $m): ?>
                        <option value="<?= e($m['objectId']) ?>" <?= ($v['meslek'] ?? '') === $m['objectId'] ? 'selected' : '' ?>><?= e($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $hataGoster('meslek') ?>
            </div>
            <?php endif; ?>
            <?php if ($ayar['sabit_sube'] === ''): ?>
            <div class="tam">
                <label for="sube">Şube<?= $yildiz(true) ?></label>
                <select id="sube" name="sube">
                    <?php if (count($subeler) > 1): ?><option value="">Seçin</option><?php endif; ?>
                    <?php foreach ($subeler as $s): ?>
                        <option value="<?= e($s['objectId']) ?>" <?= ($v['sube'] ?? '') === $s['objectId'] ? 'selected' : '' ?>><?= e($s['branchName']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $hataGoster('sube') ?>
            </div>
            <?php endif; ?>
            <div class="tam">
                <label for="adres">Adres<?= $yildiz($z('adres')) ?></label>
                <textarea id="adres" name="adres" maxlength="500" autocomplete="street-address"><?= e($v['adres'] ?? '') ?></textarea>
                <?= $hataGoster('adres') ?>
            </div>

            <?php foreach ($ozelAlanlar as $a):
                $k = $a['fieldKey'];
                $id = 'ozel_' . md5($k);
                $alanAdi = 'ozel[' . e($k) . ']';
                $girdi = $v['ozel'][$k] ?? '';
                $tip = $a['fieldType'] ?? 'text'; ?>
            <div class="tam">
                <?php if ($tip === 'checkbox'): ?>
                    <label class="onay"><input type="checkbox" name="<?= $alanAdi ?>" value="1" <?= $girdi === '1' ? 'checked' : '' ?>> <?= e($a['fieldLabel']) ?><?= $yildiz(!empty($a['isRequired'])) ?></label>
                <?php else: ?>
                    <label for="<?= $id ?>"><?= e($a['fieldLabel']) ?><?= $yildiz(!empty($a['isRequired'])) ?></label>
                    <?php if ($tip === 'dropdown'): ?>
                        <select id="<?= $id ?>" name="<?= $alanAdi ?>">
                            <option value="">Seçin</option>
                            <?php foreach ($a['options'] ?? [] as $o): ?>
                                <option value="<?= e($o['value']) ?>" <?= $girdi === $o['value'] ? 'selected' : '' ?>><?= e($o['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input id="<?= $id ?>" type="text" name="<?= $alanAdi ?>" placeholder="<?= e($a['placeholder'] ?? '') ?>" value="<?= e($girdi) ?>">
                    <?php endif; ?>
                <?php endif; ?>
                <?= $hataGoster('ozel_' . $k) ?>
            </div>
            <?php endforeach; ?>

            <div class="tam">
                <label class="onay">
                    <input type="checkbox" name="kvkk" value="1" <?= ($v['kvkk'] ?? '') === '1' ? 'checked' : '' ?>>
                    <span><a href="<?= e($ayar['kvkk_url']) ?>" target="_blank" rel="noopener">KVKK aydınlatma metnini</a> okudum, bilgilendirildim.</span>
                </label>
                <?= $hataGoster('kvkk') ?>
            </div>
        </div>

        <?php if ($ayar['recaptcha_site_key'] !== '' && $ayar['recaptcha_secret'] !== ''): ?>
            <div class="recaptcha">
                <div class="g-recaptcha" data-sitekey="<?= e($ayar['recaptcha_site_key']) ?>" data-hl="tr"></div>
                <?= $hataGoster('recaptcha') ?>
            </div>
            <script src="https://www.google.com/recaptcha/api.js?hl=tr" async defer></script>
        <?php endif; ?>

        <button type="submit">Ön kaydımı gönder</button>
    </form>
    <?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
