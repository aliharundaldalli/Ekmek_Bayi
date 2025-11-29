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

### � Bildirim ve Destek
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

| Dashboard & Özet | Sipariş Yönetimi |
|:---:|:---:|
| ![Admin Dashboard](screenshots/admin_index.png) | ![Siparişler](screenshots/admin_order_index.png) |

| Ekmek Çeşitleri | Bayi (Kullanıcı) Yönetimi |
|:---:|:---:|
| ![Ekmek Yönetimi](screenshots/admin_bread_index.png) | ![Kullanıcılar](screenshots/admin_user_index.png) |

| Raporlar | Sistem Ayarları |
|:---:|:---:|
| ![Raporlar](screenshots/admin_reports_index.png) | ![Ayarlar](screenshots/admin_system_index.png) |

---

### 2. Bayi Paneli (Kullanıcı)
Bayilerin sipariş verdiği ve hesaplarını yönettiği arayüz.

| Bayi Özeti | Yeni Sipariş Oluşturma |
|:---:|:---:|
| ![Bayi Dashboard](screenshots/my_index.png) | ![Sipariş Ver](screenshots/my_order_create.png) |

| Siparişlerim | Faturalarım |
|:---:|:---:|
| ![Sipariş Geçmişi](screenshots/my_order_index.png) | ![Faturalar](screenshots/my_invoices_index.png) |

---

### 3. Destek ve İletişim
Sorun bildirimi ve çözüm süreçleri.

| Destek Talepleri | Talep Detayı & Mesajlaşma |
|:---:|:---:|
| ![Destek Listesi](screenshots/my_support_index.png) | ![Talep Detayı](screenshots/my_support_view.png) |

| Talep Kapatma & Değerlendirme | Yeni Talep Oluşturma |
|:---:|:---:|
| ![Talep Kapat](screenshots/my_support_close.png) | ![Yeni Talep](screenshots/my_support_create.png) |

---

### 4. Güvenlik ve Giriş İşlemleri
Modern ve güvenli kimlik doğrulama ekranları.

| Giriş Ekranı | Şifremi Unuttum |
|:---:|:---:|
| ![Login](screenshots/login.png) | ![Şifremi Unuttum](screenshots/sifremi_unuttum.png) |

| Şifre Sıfırlama | E-Posta Doğrulama |
|:---:|:---:|
| ![Şifre Sıfırlama](screenshots/sifre_sifirlama.png) | ![Email Doğrulama](screenshots/email_dogrulama.png) |

---

### 5. E-Posta Bildirimleri
Sistem tarafından gönderilen otomatik bilgilendirme mailleri.

| Sipariş Bildirimi (Admin) | Fatura Bildirimi |
|:---:|:---:|
| ![Sipariş Maili](screenshots/siparis_maili_admin.png) | ![Fatura Maili](screenshots/fatura_maili.png) |

| Şifre Sıfırlama Maili | Destek Talebi Yanıtı |
|:---:|:---:|
| ![Şifre Maili](screenshots/sifre_sifirlama_maili.png) | ![Destek Yanıtı](screenshots/destek_talebi_yaniti.png) |

---

## 🛠️ Kurulum

Projeyi yerel sunucunuzda (Localhost) çalıştırmak için aşağıdaki adımları izleyin.

### Adım 1: Dosyaları Hazırlayın
Proje dosyalarını sunucunuzun kök dizinine (örn: `htdocs` veya `www`) kopyalayın.

### Adım 2: Veritabanı Kurulumu
1.  MySQL veritabanı yönetim panelinizde (phpMyAdmin vb.) `ekmek_bayi` adında bir veritabanı oluşturun.
2.  Ana dizindeki `database.sql` dosyasını bu veritabanına içe aktarın.

### Adım 3: Konfigürasyon
1.  `config/` klasörü içindeki (veya ana dizindeki) `config.php` dosyasını açın (yoksa oluşturun).
2.  Veritabanı bilgilerinizi düzenleyin:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ekmek_bayi');
define('DB_USER', 'root');
define('DB_PASS', 'root'); // MAMP için 'root', XAMPP için boş bırakın
define('DB_CHARSET', 'utf8mb4');
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

## 👨‍💻 Geliştirici

**Ali Harun DALDALLI**
