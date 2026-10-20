<?php
/**
 * GymTekno public API için küçük istemci (PHP 7.4+, curl eklentisi).
 * Doküman: https://open-api.gymteknonetwork.com/api/<tesis>/api/docs
 */
class GymTeknoApiHatasi extends RuntimeException
{
    /** @var string API hata anahtarı (session_error, kapsam_disi, kart_bulunamadi ...) ya da mesaj */
    public $hata;
    /** @var string|null */
    public $hataKodu;
    /** @var int 0 = bağlantı kurulamadı */
    public $httpKodu;

    public function __construct(string $hata, ?string $hataKodu, int $httpKodu)
    {
        parent::__construct($hata);
        $this->hata = $hata;
        $this->hataKodu = $hataKodu;
        $this->httpKodu = $httpKodu;
    }
}

class GymTeknoApi
{
    private $kok;
    private $anahtar;

    public function __construct(string $apiKok, string $tesis, string $anahtar)
    {
        $this->kok = rtrim($apiKok, '/') . '/' . rawurlencode($tesis);
        $this->anahtar = $anahtar;
    }

    /** Okutulan kart / uygulama QR kodu → {userId, ad, kaynak, personel, branchId, branchName}. Kapsam: kart-sorgu */
    public function kartSorgula(string $kod): array
    {
        return $this->istek('POST', '/musteri/kart-sorgula', ['kod' => $kod])['result'] ?? [];
    }

    /** Üyenin antrenman planları. Kapsam: antreman-plan */
    public function planlar(string $userId): array
    {
        return $this->istek('GET', '/antreman/profil/antreman-planlari-public/' . rawurlencode($userId))['results'] ?? [];
    }

    /** Planın günleri (grupları). */
    public function gunler(string $planId): array
    {
        return $this->istek('GET', '/antreman/profil/plan-gruplari-public/' . rawurlencode($planId))['results'] ?? [];
    }

    /** Bir günün hareketleri. */
    public function hareketler(string $planId, string $grupId): array
    {
        return $this->istek('GET', '/antreman/profil/plan-grup-hareketleri-public/'
            . rawurlencode($planId) . '/' . rawurlencode($grupId))['results'] ?? [];
    }

    private function istek(string $yontem, string $yol, ?array $govde = null): array
    {
        $ch = curl_init($this->kok . $yol);
        $basliklar = ['Authorization: Bearer ' . $this->anahtar, 'Accept: application/json'];
        if ($govde !== null) {
            $basliklar[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($govde, JSON_UNESCAPED_UNICODE));
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $yontem,
            CURLOPT_HTTPHEADER     => $basliklar,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'TesisKiosk-Antrenman/1.0',
        ]);
        $yanit = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $baglantiHatasi = curl_error($ch);
        curl_close($ch);

        if ($yanit === false) {
            throw new GymTeknoApiHatasi('baglanti_hatasi: ' . $baglantiHatasi, null, 0);
        }
        $veri = json_decode($yanit, true);
        if (!is_array($veri)) {
            throw new GymTeknoApiHatasi('gecersiz_yanit', null, $http);
        }
        if ($http >= 400 || ($veri['success'] ?? false) !== true) {
            throw new GymTeknoApiHatasi((string)($veri['error'] ?? 'bilinmeyen_hata'), $veri['errorCode'] ?? null, $http);
        }
        return $veri;
    }
}
