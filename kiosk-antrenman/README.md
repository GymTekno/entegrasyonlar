# Antrenman kiosku — PHP örneği

Salondaki dokunmatik ekranda üye **kartını ya da üye uygulamasındaki QR'ı** okutur, kendi antrenman
programı açılır: programlar → gün → hareketler (set × tekrar / süre, ağırlık, mola, antrenör notu, görsel).
Ekran 90 sn dokunulmazsa başa döner.

| Dosya | Ne yapar |
|---|---|
| `index.php` | Kiosk ekranı (tam ekran tarayıcıda açılır) |
| `api.php` | Ekranın çağırdığı sunucu ucu — API anahtarı yalnız burada |
| `GymTeknoApi.php` | API istemcisi (PHP 7.4+, `curl`) |
| `config.ornek.php` | Ayar şablonu — `config.php` adıyla kopyalanır |

## Kullanılan uçlar

| Uç | Kapsam | Neden |
|---|---|---|
| `POST musteri/kart-sorgula` | `kart-sorgu` | Okutulan kart / QR → üye (`userId`, "Ahmet Y.") |
| `GET antreman/profil/antreman-planlari-public/{userId}` | `antreman-plan` | Üyenin programları |
| `GET antreman/profil/plan-gruplari-public/{planId}` | `antreman-plan` | Programın günleri |
| `GET antreman/profil/plan-grup-hareketleri-public/{planId}/{grupId}` | `antreman-plan` | Günün hareketleri |

Ekranda yalnız **üyeye görünür** (`active`), başlamış ve bitiş tarihi geçmemiş programlar, en yeni 3 tanesi gösterilir.

## Kurulum

1. **Panel → Personel**: "Kiosk" adlı bir personel açın. Rolünde yalnız şu iki yetki olsun:
   - Müşteri → `kartSorgula`
   - Antrenman → `profilAntremanProgrami`
2. **Panel → Tüm İşlemler → Sistem → API Anahtarları → Yeni Anahtar**: bu personele bağlayın,
   kapsam **`kart-sorgu`** + **`antreman-plan`**. Anahtar üye listesine, telefon/T.C.'ye erişemez.
3. **Güvenli IP'ler**: kiosk'un (ya da `api.php`'nin çalıştığı sunucunun) internete çıktığı IP'yi ekleyin.
4. `config.ornek.php` → `config.php`; `tesis` ve anahtarı doldurun (ya da `GT_API_KEY` ortam değişkeni).
5. Kiosk bilgisayarında PHP ile çalıştırın, tarayıcıyı tam ekran açın:
   ```bash
   php -S 127.0.0.1:8080 -t /yol/kiosk-antrenman
   ```
   ```bash
   google-chrome --kiosk --incognito http://127.0.0.1:8080/
   ```
   (Windows: `chrome.exe --kiosk http://127.0.0.1:8080/`)

## Donanım

- **Kart okuyucu:** USB, "klavye taklidi" (HID keyboard wedge) çalışan herhangi bir RFID okuyucu — kartı okutunca
  numarayı yazıp Enter'a basar. Turnikede tanınan kart burada da tanınır (baştaki sıfırlar ve ters numara biçimleri
  sunucuda eşlenir).
- **QR:** klavye modunda çalışan 2D barkod okuyucu. Üye, uygulamadaki **Kod Oluştur** QR'ını gösterir; kod
  **10 saniye** geçerlidir. Çoğu 2D okuyucu kartları da okuyamaz — ikisi isteniyorsa iki okuyucu takılabilir,
  ikisi de aynı şekilde çalışır.
- Ekranın odakta olması gerekmez; sayfa tuş vuruşlarını kendisi dinler.

## Güvenlik

- **API anahtarı tarayıcıya gitmez**; ekran yalnız `api.php`'yi çağırır.
- `api.php` yalnız `izinli_ipler`'deki adreslerden (varsayılan bu bilgisayar) yanıt verir — başka biri kart numarası
  deneyerek üyelerin programlarını göremez. `api.php` internete açık bir sunucudaysa bu listeyi kiosk IP'siyle sınırlayın.
- Kart okutulunca üye ve ona ait plan/gün kimlikleri PHP oturumunda tutulur; ekran başka bir üyenin gününü isteyemez.
  Oturum hareketsizlikte (90 sn) düşer.
- Ekranda ad kısaltılmış görünür ("Ahmet Y."); telefon, bakiye, abonelik gösterilmez.
- Kartla yapılan her sorgu GymTekno'da erişim kaydına yazılır (kart numarası yazılmaz).
