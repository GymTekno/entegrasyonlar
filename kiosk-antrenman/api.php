<?php
/**
 * Kiosk ekranının çağırdığı sunucu ucu. GymTekno API anahtarı yalnız burada kullanılır.
 *
 *   POST api.php?islem=kart   {kod}              → ad + programlar + günleri
 *   POST api.php?islem=gun    {planId, grupId}   → o günün hareketleri (yalnız okutulan üyenin planıysa)
 *   POST api.php?islem=cikis                     → oturumu kapatır
 *
 * Okutulan üye ve ona ait plan/gün kimlikleri PHP oturumunda tutulur; tarayıcı başka üyenin
 * planını isteyemez. Oturum `bekleme_saniye` sonra kendiliğinden düşer.
 */
declare(strict_types=1);

require __DIR__ . '/GymTeknoApi.php';
$ayar = require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function cevap(int $kod, array $veri): void
{
    http_response_code($kod);
    echo json_encode($veri, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $ayar['izinli_ipler'], true)) {
    cevap(403, ['hata' => 'Bu cihazdan erişim yok.']);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    cevap(405, ['hata' => 'Yalnız POST.']);
}

session_start();
$girdi = json_decode((string)file_get_contents('php://input'), true) ?: [];
$api = new GymTeknoApi($ayar['api_kok'], $ayar['tesis'], $ayar['api_anahtari']);
$islem = (string)($_GET['islem'] ?? '');

/** Plan bugün geçerli mi: üyeye görünür + başlamış + bitmemiş (tarih yoksa sınırsız). */
function planGecerli(array $p): bool
{
    if (($p['active'] ?? false) !== true) {
        return false;
    }
    // Tarihler İstanbul gece yarısı olarak saklanır; bitiş günü dahil sayılır.
    $tz = new DateTimeZone('Europe/Istanbul');
    $simdi = new DateTimeImmutable('now', $tz);
    $bugun = new DateTimeImmutable('today', $tz);
    if (!empty($p['startDate']) && new DateTimeImmutable($p['startDate']) > $simdi) {
        return false;
    }
    if (!empty($p['expiryDate']) && new DateTimeImmutable($p['expiryDate']) < $bugun) {
        return false;
    }
    return true;
}

try {
    if ($islem === 'kart') {
        $kod = trim((string)($girdi['kod'] ?? ''));
        if ($kod === '' || strlen($kod) > 64) {
            cevap(400, ['hata' => 'Kart okunamadı, tekrar deneyin.']);
        }
        try {
            $uye = $api->kartSorgula($kod);
        } catch (GymTeknoApiHatasi $ex) {
            if ($ex->hataKodu === 'kart_bulunamadi') {
                cevap(404, ['hata' => 'Bu kart tanınmadı. Lütfen resepsiyona başvurun.']);
            }
            throw $ex;
        }

        $planlar = array_values(array_filter($api->planlar($uye['userId']), 'planGecerli'));
        usort($planlar, fn($a, $b) => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
        $planlar = array_slice($planlar, 0, (int)$ayar['en_fazla_plan']);

        $izinli = [];
        $cikti = [];
        foreach ($planlar as $p) {
            $gunler = [];
            foreach ($api->gunler($p['objectId']) as $g) {
                $izinli[$p['objectId']][] = $g['objectId'];
                $gunler[] = [
                    'grupId'  => $g['objectId'],
                    'ad'      => (string)($g['name'] ?? ''),
                    'aciklama' => (string)($g['aciklama'] ?? ''),
                ];
            }
            $cikti[] = [
                'planId'  => $p['objectId'],
                'ad'      => (string)($p['name'] ?? ''),
                'hedef'   => (string)($p['hedef'] ?? ''),
                'seviye'  => (string)($p['seviye'] ?? ''),
                'bitis'   => !empty($p['expiryDate']) ? (new DateTimeImmutable($p['expiryDate']))->format('d.m.Y') : '',
                'aciklama' => (string)($p['description'] ?? ''),
                'gunler'  => $gunler,
            ];
        }

        session_regenerate_id(true);
        $_SESSION['kiosk'] = ['izinli' => $izinli, 'bitis' => time() + (int)$ayar['bekleme_saniye']];
        cevap(200, ['ad' => $uye['ad'] ?? '', 'planlar' => $cikti]);
    }

    if ($islem === 'gun') {
        $oturum = $_SESSION['kiosk'] ?? null;
        $planId = (string)($girdi['planId'] ?? '');
        $grupId = (string)($girdi['grupId'] ?? '');
        if (!$oturum || $oturum['bitis'] < time()) {
            unset($_SESSION['kiosk']);
            cevap(401, ['hata' => 'Süre doldu, kartınızı yeniden okutun.']);
        }
        if (!in_array($grupId, $oturum['izinli'][$planId] ?? [], true)) {
            cevap(403, ['hata' => 'Bu program size ait değil.']);
        }
        $_SESSION['kiosk']['bitis'] = time() + (int)$ayar['bekleme_saniye'];

        $hareketler = [];
        foreach ($api->hareketler($planId, $grupId) as $h) {
            $katalog = is_array($h['hareket'] ?? null) ? $h['hareket'] : [];
            $youtube = (string)($h['youtubeCode'] ?? '') ?: (string)($katalog['youtubeCode'] ?? '');
            // Görsel sırası: plandaki hareketin kendi görseli → katalog görseli → katalog resimleri (ilki çoğu zaman animasyon) → YouTube kapağı.
            $gorsel = (string)($h['imageUrl'] ?? '') ?: (string)($katalog['imageUrl'] ?? '');
            if ($gorsel === '' && !empty($katalog['resimler']) && is_array($katalog['resimler'])) {
                $gorsel = (string)$katalog['resimler'][0];
            }
            if ($gorsel === '' && preg_match('/^[\w-]{6,20}$/', $youtube)) {
                $gorsel = 'https://img.youtube.com/vi/' . $youtube . '/hqdefault.jpg';
            }
            $hareketler[] = [
                'ad'       => (string)($h['name'] ?? '') ?: (string)($katalog['name'] ?? ''),
                'kasGrubu' => (string)($h['kasgrubu']['name'] ?? ''),
                'set'      => $h['setSayisi'] ?? null,
                'tekrar'   => $h['tekrarSayisi'] ?? null,
                'sure'     => $h['sure'] ?? null,
                'agirlik'  => $h['agirlik'] ?? null,
                'mola'     => $h['mola'] ?? null,
                'not'      => (string)($h['aciklama'] ?? ''),
                'gorsel'   => preg_match('#^https://#', $gorsel) ? $gorsel : '',
            ];
        }
        cevap(200, ['hareketler' => $hareketler]);
    }

    if ($islem === 'cikis') {
        unset($_SESSION['kiosk']);
        cevap(200, ['tamam' => true]);
    }

    cevap(400, ['hata' => 'Bilinmeyen işlem.']);
} catch (GymTeknoApiHatasi $ex) {
    error_log('[kiosk] api hatasi: http=' . $ex->httpKodu . ' ' . $ex->hata . ' ' . $ex->hataKodu);
    cevap(502, ['hata' => 'Şu an programınıza ulaşılamıyor. Lütfen biraz sonra tekrar deneyin.']);
}
