<?php
/**
 * Bu dosyayı config.php adıyla kopyalayıp değerleri doldurun.
 * API anahtarı yalnız bu sunucuda kalır; kiosk ekranındaki tarayıcıya gönderilmez.
 */
return [
    'api_kok' => 'https://open-api.gymteknonetwork.com/api',

    // Tesis kodu — panele girerken kullanılan tesis no.
    'tesis' => 'doremsoft',

    // Panel → Sistem → API Anahtarları. Kapsam: kart-sorgu, antreman-plan.
    // Anahtarın bağlı olduğu personelde "kartSorgula" (Müşteri) ve "profilAntremanProgrami" (Antrenman) yetkisi olmalı.
    'api_anahtari' => getenv('GT_API_KEY') ?: 'gt_live_BURAYA_ANAHTAR',

    // api.php'yi yalnız bu adresler çağırabilir (kiosk bilgisayarı). Kiosk aynı makinedeyse 127.0.0.1 yeterli.
    // Başkası çağırabilseydi kart numarası deneyerek üyelerin programlarını görebilirdi.
    'izinli_ipler' => ['127.0.0.1', '::1'],

    // Ekran bu kadar saniye dokunulmazsa başa döner ve oturum kapanır.
    'bekleme_saniye' => 90,

    // Ekranda en fazla kaç program gösterilsin (en yeniler).
    'en_fazla_plan' => 3,

    // Üst başlık / alt bilgi metinleri.
    'baslik'   => 'Antrenman Programım',
    'alt_yazi' => 'Kartınızı ya da uygulamadaki QR kodunu okutun',
];
