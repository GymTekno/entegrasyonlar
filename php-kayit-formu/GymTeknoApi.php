<?php
/**
 * GymTekno public API için küçük istemci (yalnız PHP curl eklentisi gerekir).
 * Doküman: https://open-api.gymteknonetwork.com/api/<tesis>/api/docs
 */
class GymTeknoApiHatasi extends RuntimeException
{
    /** @var string API'nin döndürdüğü hata anahtarı (session_error, kapsam_disi, ...) ya da ham mesaj */
    public $hata;
    /** @var string|null API_003 gibi hata kodu */
    public $hataKodu;
    /** @var int HTTP durum kodu (0 = bağlantı kurulamadı) */
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

    /** Tesisin şubeleri: [{objectId, branchName, ...}] */
    public function subeler(): array
    {
        return $this->istek('GET', '/branch/liste-public')['results'] ?? [];
    }

    /** Müşteri kartının alan şeması: requiredFields, customFields, meslekler, kanallar ... */
    public function alanSemasi(): array
    {
        return $this->istek('GET', '/musteri/init-create-customer')['result'] ?? [];
    }

    /** Yeni müşteri (potansiyel) kaydı açar; üyenin kaydını döndürür. */
    public function musteriEkle(array $govde): array
    {
        return $this->istek('POST', '/musteri/create-customer', $govde)['result'] ?? [];
    }

    /** Tek üyenin künyesi (kayıt sonrası kontrol için). */
    public function musteri(string $userId): array
    {
        return $this->istek('GET', '/musteri/user/' . rawurlencode($userId))['result'] ?? [];
    }

    private function istek(string $yontem, string $yol, ?array $govde = null): array
    {
        $ch = curl_init($this->kok . $yol);
        $basliklar = [
            'Authorization: Bearer ' . $this->anahtar,
            'Accept: application/json',
        ];
        if ($govde !== null) {
            $basliklar[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($govde, JSON_UNESCAPED_UNICODE));
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $yontem,
            CURLOPT_HTTPHEADER     => $basliklar,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 20,
            // Kendi istemci adınız; "curl/..." gibi araç adı göndermeyin.
            CURLOPT_USERAGENT      => 'TesisWebSitesi-KayitFormu/1.0',
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
