# GymTekno API — Entegrasyonlar

Tesisin verisine kendi yazılımınızdan (web sitesi, muhasebe programı, pano, mobil uygulama sunucusu)
erişmek için GymTekno **public API**'si kullanılır. Bu klasör nasıl başlanacağını ve hazır örnekleri içerir.

| Örnek | Ne yapar |
|---|---|
| [php-kayit-formu/](php-kayit-formu/) | Tesisin web sitesine konan üyelik ön kayıt formu (PHP, reCAPTCHA'lı) |

**Canlı doküman (tüm uçlar, alanlar, örnek yanıtlar):**
`https://open-api.gymteknonetwork.com/api/<tesis>/api/docs`
Makine okunur şema (OpenAPI 3.1): `https://open-api.gymteknonetwork.com/api/<tesis>/api/openapi.json`

`<tesis>` = panele girerken kullanılan tesis kodu (ör. `doremsoft`).

---

## 1. API anahtarı oluşturma

### 1.1 Entegrasyon için ayrı bir personel hesabı açın

Anahtar **bağlı olduğu kullanıcının yetkisiyle** çalışır. Admin'e bağlanan anahtar admin'in yapabildiği her şeyi
yapabilir. Bu yüzden:

1. Panel → Personel → yeni personel (ör. "Web Sitesi Entegrasyonu").
2. Rolüne **yalnız entegrasyonun ihtiyacı olan** yetkileri verin (ör. kayıt formu için yalnız *müşteri ekleme*).
3. Anahtarı bu personele bağlayın. Bağlanan kullanıcı, anahtarı oluşturan kişiden daha yetkili olamaz.

### 1.2 Anahtarı üretin

Panel → **Tüm İşlemler → Sistem → API Anahtarları** (yetki: *API Anahtarı Yönet*; admin her zaman görür).

1. **Yeni Anahtar**'a tıklayın.
2. **Entegrasyon adı:** ne için olduğunu yazın ("Web sitesi kayıt formu").
3. **Bağlı kullanıcı:** 1.1'de açtığınız personel.
4. **Kapsam:** anahtarın erişebileceği kategoriler (bkz. bölüm 4). Boş bırakılırsa yayındaki **tüm** uçlara
   erişir; mutlaka gerekenleri seçin.
5. **Oluştur** → ekranda `gt_live_...` ile başlayan anahtar **yalnız bu bir kez** gösterilir. Kopyalayıp güvenli
   bir yere kaydedin; sistem onu bir daha gösteremez (yalnız özeti saklanır). Kaybolursa yeni anahtar üretilir.

`gt_test_` önekli anahtarlar deneme amaçlıdır; yetki farkı yoktur, yalnız ayırt etmek içindir.

### 1.3 Güvenli IP ekleyin

Aynı ekranda **Güvenli IP'ler** bölümüne, API'yi çağıracak sunucunun **internete çıktığı (dış) IP**'sini yazın.

- Tek adres (`203.0.113.14`) ya da aralık (`203.0.113.0/24`); IPv6 desteklenir, en fazla 100 kayıt.
- Sunucu NAT / proxy / bulut ağ geçidi arkasındaysa o çıkış adresi yazılır.
- curl / Postman / Insomnia / Python ile kendi bilgisayarınızdan deneyecekseniz bilgisayarınızın dış IP'si de
  eklenmeli ("Bu bilgisayarın IP'sini ekle" düğmesi).
- **Liste boşsa** sunucu ve geliştirme araçlarından gelen çağrıların hepsi reddedilir (`istemci_engellendi`).
- Değişiklik en geç 1 dakikada geçerli olur.

### 1.4 İptal

Anahtar listesinde **İptal et** → anahtar **anında** geçersiz olur. Sızdığından şüphelenilen anahtar iptal edilip
yenisi üretilir. Listede her anahtarın son kullanım zamanı ve son istek IP'si görünür.

---

## 2. İstek yapma

```
https://open-api.gymteknonetwork.com/api/<tesis>/<uç>
Authorization: Bearer gt_live_xxxxxxxxxxxxxxxxxxxxxxxx
Content-Type: application/json        (gövdeli isteklerde)
```

**curl** (bilgisayarınızın IP'si güvenli listede olmalı):

```bash
curl -s https://open-api.gymteknonetwork.com/api/<tesis>/branch/liste-public \
  -H "Authorization: Bearer gt_live_xxxxxxxx"
```

**PHP:**

```php
$ch = curl_init('https://open-api.gymteknonetwork.com/api/<tesis>/musteri/create-customer');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . getenv('GT_API_KEY'), 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode(['branch' => 'aBcD1234Ef', 'name' => 'Ayşe', 'surname' => 'Yılmaz']),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERAGENT      => 'FirmaAdi-Entegrasyon/1.0',
]);
$yanit = json_decode(curl_exec($ch), true);
```

**JavaScript (Node.js — sunucu tarafı):**

```js
const r = await fetch(`https://open-api.gymteknonetwork.com/api/${tesis}/musteri/tumu?limit=50&skip=0`, {
  headers: { Authorization: `Bearer ${process.env.GT_API_KEY}` },
});
const veri = await r.json();
```

> **Anahtar yalnız sunucuda durur.** Tarayıcıdaki JavaScript'e, mobil uygulamanın içine ya da herkese açık bir
> depoya konmaz — konursa herkes tesisin verisine erişir. Web sitesi formları isteği kendi sunucusu üzerinden yapar
> (bkz. [php-kayit-formu](php-kayit-formu/)).

---

## 3. Yanıt, sayfalama, hatalar

**Yanıt zarfı** her uçta aynıdır:

| Alan | Anlamı |
|---|---|
| `success` | İşlem başarılı mı |
| `result` | Tekil sonuç (tekil uçlar) |
| `results` | Liste sonucu (liste uçları) |
| `api` | `v3` |
| `mstime` | Sunucu işlem süresi (ms) |

**Sayfalama:** liste uçları `limit` (sayfa boyutu) ve `skip` (atlanacak kayıt) alır; 25–100 arası önerilir.
Sayfalı uçlarda `result.totalCount` filtreye uyan **toplam** kayıt sayısıdır, `result.items` o sayfadır.

**Hatalar** HTTP **403** ile `{ "success": false, "error": "...", "errorCode": "..." }` döner:

| `error` | Ne demek | Ne yapılır |
|---|---|---|
| `session_error` | Anahtar yok, yanlış ya da iptal edilmiş | Başlığı ve anahtarı kontrol edin |
| `api_kapali` | Uç var ama public API'ye açık değil | Dokümandaki uçları kullanın |
| `kapsam_disi` | Anahtarın kapsamı bu kategoriyi içermiyor | Anahtara kategoriyi ekleyin / yeni anahtar |
| `istemci_engellendi` (`API_003`) | Çağrının IP'si güvenli listede değil | Sunucunun dış IP'sini ekleyin |
| diğer metinler | İş kuralı hatası (ör. "Ad Zorunlu", "Bu telefon numarası … adlı üyede kayıtlı") | Mesaja göre veriyi düzeltin |

İş kuralı hataları başka üyenin adını içerebilir — **son kullanıcıya olduğu gibi göstermeyin**, kendi
mesajınızı gösterip ayrıntıyı loga yazın.

---

## 4. Kapsam kategorileri

Anahtara verilen kapsam bu adlarla eşleşir (dokümanda soldaki başlıklar).

| Kategori | İçerik | Kişisel veri |
|---|---|---|
| `musteri` | Üye kayıtları: liste, arama, detay, ekleme, güncelleme | **Var** |
| `musteri-referans` | Grup, meslek, satış kanalı, kan grubu listeleri | Yok |
| `sube-referans` | Şubeler (`branch` objectId eşlemesi) | Yok |
| `abonelik` | Üyelerin abonelikleri, hareketleri, paket satışı | **Var** |
| `abonelik-referans` | Satılabilir paketler, kategori, paket grubu, kampanya | Yok |
| `crm-islem` | Lead / web formu oluşturma, durum güncelleme, görüşme kaydı (YAZMA) | **Var** |
| `crm-rapor` | Lead listeleri ve görüşme kayıtları (OKUMA) | **Var** |
| `crm-referans` | Lead kaynağı, kategori, durum sebebi, satış temsilcisi | Yok |
| `finans` | Tahsilat / ödeme hareketleri (kasa + banka) | **Var** |
| `finans-alacak` | Vadeli ve vadesi geçen alacaklar | **Var** |
| `finans-islem` | Tahsilat / ödeme oluşturma (gerçek kayıt üretir) | **Var** |
| `finans-profil` | Üye bazlı hesap hareketleri ve bakiye | **Var** |
| `finans-referans` | Kasa ve banka hesapları | Yok |
| `muhasebe-siparis` | Satış / alış siparişleri | **Var** |
| `gecis-kontrol` | Turnike / RFID giriş-çıkış kayıtları | **Var** |
| `pt-seans` | PT ve grup seansları, katılımcılar | **Var** |
| `profil-olcum` | Üye vücut ölçümleri | **Sağlık verisi** |
| `antreman-plan` | Üye antreman planları | Var |
| `egzersiz-katalog` | Kas grupları ve hareketler | Yok |
| `stok` | Ürün kataloğu ve stok durumu | Yok |
| `stok-referans` | Ürün grubu, birim, depo | Yok |
| `plaka-takip` | Plaka sorgulama, otopark giriş/çıkış | **Var** |

Güncel liste her zaman `api/docs` sayfasındadır.

---

## 5. Bilinmesi gerekenler

- **`balance` TERS anlamlıdır:** `> 0` üye tesise **borçlu**, `< 0` üye **alacaklı**, `0` nötr.
  `credit` normaldir (`> 0` = cüzdan bakiyesi).
- **Yeni müşteri potansiyel olarak açılır** (`isCustomer: false`); `musteri/tumu`'da değil
  `musteri/potansiyel`'de görünür, abonelik tanımlanınca üyeye döner.
- Kimlik alanları `objectId`'dir; şube, meslek, grup gibi bağlantılar referans uçlarındaki `objectId` ile verilir.
- Yazma uçları panelden yapılan işlemle aynı sonucu üretir (ör. yeni üyede tesiste açıksa hoş geldin SMS'i ve
  iş akışları çalışır, tahsilat kasaya işlenir). Önce `gt_test_` anahtarı ve deneme verisiyle sınayın.
- **KVKK:** kişisel veri dönen uçlarda yalnız ihtiyacınız olan alanları saklayın, saklama süresini sınırlayın,
  üçüncü taraflara aktarmayın.
- **Denetim kaydı:** anahtarla yapılan her çağrı (hangi anahtar, hangi uç, sonuç, süre) kaydedilir; tesis yöneticisi
  panelden görür.
