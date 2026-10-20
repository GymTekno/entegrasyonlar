# Web sitesi kayıt formu — PHP örneği

Tesisin kendi web sitesine konan "üyelik ön kayıt" formu. Ziyaretçinin bilgileri GymTekno public API ile
tesisin paneline **potansiyel müşteri** olarak yazılır (panel → Müşteriler → Potansiyel müşteriler).

API dokümanı: `https://api.gymteknonetwork.com/api/<tesis>/api/docs`

| Dosya | Ne yapar |
|---|---|
| `index.php` | Form + sunucu tarafı doğrulama + kayıt |
| `GymTeknoApi.php` | Küçük API istemcisi (PHP 7.4+ ve `curl` eklentisi) |
| `config.ornek.php` | Ayar şablonu — `config.php` adıyla kopyalanır |

## Kullanılan uçlar

| Uç | Neden |
|---|---|
| `GET branch/liste-public` | Şube listesi (kapsam `sube-referans`) |
| `GET musteri/init-create-customer` | Tesisin zorunlu alanları, tesise özel alanlar, meslek listesi (kapsam `musteri`) |
| `POST musteri/create-customer` | Kaydı açar (kapsam `musteri`) |

Şube listesi ve alan şeması 10 dk önbelleğe alınır; her ziyarette API çağrılmaz.

## Kurulum

1. **Panel → Personel**: yalnız bu iş için bir personel hesabı açın, rolünde yalnız *müşteri ekleme*
   yetkisi olsun. API anahtarı bağlı olduğu kullanıcının yetkisiyle çalışır — anahtarı admin'e bağlamayın.
2. **Panel → Sistem → API Anahtarları → Yeni anahtar**: o personele bağlayın, kapsam `musteri`,
   `musteri-referans`, `sube-referans`. Ham anahtar yalnız bir kez gösterilir.
3. **Güvenli IP'ler**: web sitesini barındıran sunucunun internete çıktığı IP'yi ekleyin.
4. `config.ornek.php` → `config.php` kopyalayın, `tesis`, `kvkk_url` ve anahtarı doldurun
   (anahtarı dosyaya yazmak yerine `GT_API_KEY` ortam değişkeni de verilebilir).
5. Klasörü sitenize yükleyin. `config.php` web'den indirilemesin diye mümkünse web kökünün dışında tutun.

## Bilinmesi gerekenler

- **Anahtar yalnız sunucuda.** Formu JavaScript'ten doğrudan API'ye göndermeyin; anahtar herkese açılır.
- **API hata metni ziyaretçiye gösterilmez.** Telefon / T.C. / e-posta başka üyede kayıtlıysa API
  "… *Ad Soyad* adlı üyede kayıtlı" der — gösterilirse başka üyenin adı açığa çıkar. Form genel bir mesaj
  gösterir, ayrıntıyı sunucu loguna yazar.
- **Zorunlu alanlar** tesisin "Müşteri alanları → Zorunlu alanlar" ayarından okunur; yalnız formda sorulan
  alanlar (telefon, e-posta, doğum tarihi, cinsiyet, T.C., adres, meslek) dikkate alınır. Panelin iç alanları
  (sözleşme no, referans, müşteri grubu…) web ziyaretçisine sorulmaz.
- **KVKK:** form, sitedeki aydınlatma metnine link verir ve "okudum" kutusu ister. Bu beyan API ile
  panele yazılmaz — kaydını sitenin kendisi tutmalıdır. Panelde KVKK onayı ayrıca istenecekse üye tesiste
  ya da e-posta doğrulama linkiyle onaylar.
- **Yeni kayıt panelde kayıt açılmasıyla aynıdır:** tesiste açıksa hoş geldin SMS'i ve "müşteri oluşturuldu"
  iş akışları çalışır.
- **reCAPTCHA v2:** `config.php`'de `recaptcha_site_key` + `recaptcha_secret` doldurulunca (Google reCAPTCHA
  yönetim ekranından, "Onay kutusu" türü, sitenin alan adıyla) form "Ben robot değilim" kutusu ister; cevap
  sunucuda Google'a doğrulatılır ve alan adı da eşleşmelidir. Google'a ulaşılamazsa kayıt alınmaz.
  İkisi boşsa kutu kapalıdır — canlıda boş bırakmayın.
- Ek koruma: CSRF belirteci, gizli tuzak alanı (bot), IP başına 10 dakikada 5 deneme, gönderim sonrası
  yönlendirme (sayfa yenilenince ikinci kayıt açılmaz).
- `satis_kanali` ayarına panelde "Web Sitesi" adlı bir satış kanalının objectId'si yazılırsa kayıtların
  kaynağı panelde görünür (kanal listesi `init-create-customer` yanıtındaki `kanallar`).
