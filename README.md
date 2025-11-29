# 🍞 AHD Ekmek Bayi Yönetim ve Sipariş Sistemi

![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)
![Status](https://img.shields.io/badge/Durum-Tamamland%C4%B1-success?style=for-the-badge)

Bu proje, ekmek fırınları ve bayileri arasındaki sipariş süreçlerini dijitalleştirmek, stok takibini kolaylaştırmak ve finansal raporlamayı otomatize etmek amacıyla geliştirilmiş **web tabanlı bir B2B yönetim sistemidir.**

---

## 🚀 Özellikler

### 👤 Kullanıcı Rolleri ve Yetkiler
* **Admin (Fırın Merkezi):** Tüm sistemi yönetir, ekmek çeşitlerini tanımlar, bayileri ekler, siparişleri onaylar ve raporları inceler.
* **Bayi (Kullanıcı):** Kendi işletmesi için sipariş verir, geçmiş siparişlerini takip eder, faturalarını görüntüler ve destek talebi oluşturur.

### 🍞 Ürün ve Sipariş Yönetimi
* **Ekmek Yönetimi:** Fiyat, stok, görsel ve kategori bazlı ekmek tanımlama.
* **Sipariş Süreci:** Bayiler için kolay sipariş ekranı, sepet mantığı ve anlık stok kontrolü.
* **Sipariş Durumları:** "Bekliyor", "Hazırlanıyor", "Yolda", "Teslim Edildi" gibi aşamalarla sipariş takibi.
* **Fatura Sistemi:** Tamamlanan siparişler için otomatik fatura oluşturma ve görüntüleme.

### 📊 Raporlama ve Analiz
* **Satış Raporları:** Günlük, haftalık ve aylık satış grafikleri.
* **Ürün Raporları:** En çok satılan ürünler ve ciro analizleri.
* **Müşteri Raporları:** En aktif bayiler ve alım istatistikleri.
* **Excel & PDF:** Tüm raporların dışa aktarılabilmesi.

### 🔔 Bildirim ve Destek
* **E-Posta Bildirimleri:** Sipariş durumu değişikliklerinde, yeni kayıtlarda ve destek taleplerinde otomatik e-posta gönderimi.
* **Destek Sistemi (Ticket):** Bayilerin sorunlarını iletebileceği, dosya yüklemeli ve öncelik seviyeli destek modülü.
* **Değerlendirme:** Çözülen destek talepleri için memnuniyet anketi.

### 🛡️ Güvenlik ve Altyapı
* **Güvenli Kimlik Doğrulama:** Şifreli giriş, "Beni Hatırla" özelliği ve güvenli oturum yönetimi.
* **Şifre Sıfırlama:** Token tabanlı güvenli şifre yenileme mekanizması.
* **SMTP Entegrasyonu:** Admin panelinden yapılandırılabilir e-posta sunucusu ayarları.

---

## 📸 Ekran Görüntüleri

### 1. Yönetici Paneli (Admin)
Fırın merkezinin tüm operasyonu yönettiği ana ekranlar.

#### Dashboard & Sipariş Yönetimi
<div style="display: flex; gap: 10px; flex-wrap: wrap;">
  <img src="screenshots/admin_index.png" alt="Admin Dashboard" width="48%">
  <img src="screenshots/admin_order_index.png" alt="Siparişler" width="48%">
</div>

#### Ekmek & Bayi Yönetimi
<div style="display: flex; gap: 10px; flex-wrap: wrap;">
  <img src="screenshots/admin_bread_index.png" alt="Ekmek Yönetimi" width="48%">
  <img src="screenshots/admin_user_index.png" alt="Kullanıcılar" width="48%">
</div>

#### Raporlar & Ayarlar
<div style="display: flex; gap: 10px; flex-wrap: wrap;">
  <img src="screenshots/admin_reports_index.png" alt="Raporlar" width="48%">
  <img src="screenshots/admin_system_index.png" alt="Ayarlar" width="48%">
</div>

---

### 2. Bayi Paneli (Kullanıcı)
Bayilerin sipariş verdiği ve hesaplarını yönettiği arayüz.

#### Özet & Sipariş Oluşturma
<div style="display: flex; gap: 10px; flex-wrap: wrap;">
  <img src="screenshots/my_index.png" alt="Bayi Dashboard" width="48%">
  <img src="screenshots/my_order_create.png" alt="Sipariş Ver" width="48%">
</div>

#### Sipariş Geçmişi & Faturalar
<div style="display: flex; gap: 10px; flex-wrap: wrap;">
  <img src="screenshots/my_order_index.png" alt="Sipariş Geçmişi" width="48%">
  <img src="screenshots/my_invoices_index.png" alt="Faturalar" width="48%">
</div>

---

### 3. Destek ve İletişim
Sorun bildirimi ve çözüm süreçleri.

#### Destek Talepleri & Detay
<div style="display: flex; gap: 10px; flex-wrap: wrap;">
  <img src="screenshots/my_support_index.png" alt="Destek Listesi" width="48%">
  <img src="screenshots/my_support_view.png" alt="Talep Detayı" width="48%">
</div>

#### Talep Kapatma & Yeni Talep
<div style="display: flex; gap: 10px; flex-wrap: wrap;">
  <img src="screenshots/my_support_close.png" alt="Talep Kapat" width="48%">
  <img src="screenshots/my_support_create.png" alt="Yeni Talep" width="48%">
</div>

---

### 4. Güvenlik ve Giriş İşlemleri
Modern ve güvenli kimlik doğrulama ekranları.

#### Giriş & Şifre İşlemleri
<div style="display: flex; gap: 10px; flex-wrap: wrap;">
  <img src="screenshots/login.png" alt="Login" width="48%">
  <img src="screenshots/sifremi_unuttum.png" alt="Şifremi Unuttum" width="48%">
</div>

#### Sıfırlama & Doğrulama
<div style="display: flex; gap: 10px; flex-wrap: wrap;">
  <img src="screenshots/sifre_sifirlama.png" alt="Şifre Sıfırlama" width="48%">
  <img src="screenshots/email_dogrulama.png" alt="Email Doğrulama" width="48%">
</div>

---

### 5. E-Posta Bildirimleri
Sistem tarafından gönderilen otomatik bilgilendirme mailleri.

#### Sipariş & Fatura Bildirimleri
<div style="display: flex; gap: 10px; flex-wrap: wrap;">
  <img src="screenshots/siparis_maili_admin.png" alt="Sipariş Maili" width="48%">
  <img src="screenshots/fatura_maili.png" alt="Fatura Maili" width="48%">
</div>

#### Güvenlik & Destek Bildirimleri
<div style="display: flex; gap: 10px; flex-wrap: wrap;">
  <img src="screenshots/sifre_sifirlama_maili.png" alt="Şifre Maili" width="48%">
  <img src="screenshots/destek_talebi_yaniti.png" alt="Destek Yanıtı" width="48%">
</div>

---

## 🛠️ Kurulum

Projeyi yerel sunucunuzda (Localhost) çalıştırmak için aşağıdaki adımları izleyin.

### Adım 1: Dosyaları Hazırlayın
Proje dosyalarını sunucunuzun kök dizinine (örn: `htdocs` veya `www`) kopyalayın.

### Adım 2: Veritabanı Kurulumu
1.  MySQL veritabanı yönetim panelinizde (phpMyAdmin vb.) `ekmek_bayi` adında bir veritabanı oluşturun.
2.  Ana dizindeki `database.sql` dosyasını bu veritabanına içe aktarın.

### Adım 3: Konfigürasyon
1.  `config/config.sample.php` dosyasının adını `config/config.php` olarak değiştirin.
2.  `config/db.example.php` dosyasının adını `config/db.php` olarak değiştirin.
3.  Her iki dosyayı da açarak veritabanı ve site ayarlarınızı düzenleyin.

**Örnek `config/db.php`:**
```php
<?php
$db_host = 'localhost';
$db_name = 'ekmek_bayi';
$db_user = 'root';
$db_pass = 'root'; // MAMP için 'root', XAMPP için boş bırakın
$db_charset = 'utf8mb4';
// ...
?>
```

### Adım 4: Bağımlılıklar
Terminalde proje dizinine giderek gerekli kütüphaneleri yükleyin:

```bash
composer install
```

---

## 📂 Proje Dizin Yapısı

```text
admin/       → Yönetici paneli ve modülleri
assets/      → CSS, JS, Resimler ve Fontlar
config/      → Veritabanı ve sistem ayarları
includes/    → Yardımcı fonksiyonlar ve sınıflar
my/          → Bayi (Kullanıcı) paneli
screenshots/ → Proje ekran görüntüleri
uploads/     → Ürün resimleri ve destek dosyaları
vendor/      → Composer bağımlılıkları (PHPMailer vb.)
```

---

## 🤝 Katkıda Bulunma

1.  Bu repoyu forklayın.
2.  Yeni bir özellik dalı oluşturun (`git checkout -b yeni-ozellik`).
3.  Değişikliklerinizi commit edin (`git commit -m 'Yeni özellik eklendi'`).
4.  Dalınızı pushlayın (`git push origin yeni-ozellik`).
5.  Bir Pull Request oluşturun.

---

## 📄 Lisans

Bu proje [MIT Lisansı](LICENSE) altında lisanslanmıştır.

---

## 👨‍💻 Geliştirici

**Ali Harun DALDALLI**
