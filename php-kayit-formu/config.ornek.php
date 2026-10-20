<?php
/**
 * Bu dosyayı config.php adıyla kopyalayıp değerleri doldurun.
 * config.php içindeki API anahtarı SUNUCUDA kalır; tarayıcıya hiçbir zaman gönderilmez.
 */
return [
    // API kökü (sonunda / yok). Tesis kodu ayrıca aşağıda.
    'api_kok'   => 'https://open-api.gymteknonetwork.com/api',

    // Tesis kodu — panele girerken kullanılan tesis no.
    'tesis'     => 'doremsoft',

    // Panel → Sistem → API Anahtarları'ndan üretilen anahtar (gt_live_... / gt_test_...).
    // Önerilen kapsam: musteri, musteri-referans, sube-referans.
    // Ortam değişkeni varsa o kullanılır (anahtarı dosyaya yazmamak için).
    'api_anahtari' => getenv('GT_API_KEY') ?: 'gt_live_BURAYA_ANAHTAR',

    // Formda şube sorulmasın, hep bu şubeye yazılsın istenirse şube objectId'si.
    // Boş bırakılırsa ziyaretçi şubeyi listeden seçer.
    'sabit_sube' => '',

    // İsteğe bağlı: panelde tanımlı "satış kanalı" objectId'si (ör. "Web Sitesi").
    // Böylece kayıtların web sitesinden geldiği panelde görünür.
    'satis_kanali' => '',

    // Kaydın açıklamasına düşülecek not.
    'aciklama_notu' => 'Web sitesi kayıt formundan geldi.',

    // Tesisin KVKK aydınlatma metninin web sitesindeki adresi (formda link olarak gösterilir).
    'kvkk_url' => 'https://www.ornektesis.com/kvkk-aydinlatma-metni',

    // Şube listesi ve alan şeması bu kadar saniye önbellekte tutulur (her ziyarette API çağrılmasın).
    'onbellek_saniye' => 600,

    // Google reCAPTCHA v2 ("Ben robot değilim" kutusu) — https://www.google.com/recaptcha/admin
    // Site anahtarı sayfaya basılır, gizli anahtar yalnız sunucuda kalır.
    // İkisi de boşsa reCAPTCHA kapalıdır (yalnız deneme için; canlıda doldurun).
    'recaptcha_site_key' => '',
    'recaptcha_secret'   => getenv('RECAPTCHA_SECRET') ?: '',

    // Aynı IP'den bu süre (sn) içinde en fazla kaç kayıt denemesi kabul edilsin.
    'deneme_penceresi' => 600,
    'deneme_siniri'    => 5,
];
