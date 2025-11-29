<?php
/**
 * Ekmek Çeşidi Düzenleme Sayfası (Yol Düzeltmeleriyle)
 */

// --- Yol Tanımlamaları ---
// BASE_PATH: Sunucudaki kök dizin dosya yolu
define('BASE_PATH', dirname(dirname(__DIR__))); // Örnek: /home/ahdakade/test.ahdakademi.com

// BASE_URL: Web sitesinin temel URL yolu (tarayıcı tarafından kullanılır)
// define('BASE_URL', '/'); // Sitenin kök URL'si - init.php içinde tanımlanıyor

// --- init.php Dahil Etme ---
require_once BASE_PATH . '/init.php'; // include/require için BASE_PATH

// Kullanıcı girişi ve yetkisi kontrolü
if (!isLoggedIn()) {
    // Yönlendirme için BASE_URL kullanılır
    redirect(BASE_URL . 'login.php');
}

if (!isAdmin()) {
    // Yönlendirme için BASE_URL kullanılır
    redirect(BASE_URL . 'my/index.php');
}

// --- ID Al ve Doğrula ---
$bread_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$bread_id) {
    $_SESSION['error_message'] = 'Geçersiz Ekmek ID.';
    // Yönlendirme için BASE_URL kullanılır
    redirect(BASE_URL . 'admin/bread/index.php');
    exit;
}

// --- Mevcut Veriyi Çek ---
$bread = null; // Başlangıç değeri
try {
    $stmt = $pdo->prepare("SELECT * FROM bread_types WHERE id = ?");
    $stmt->execute([$bread_id]);
    $bread = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bread) {
        $_SESSION['error_message'] = 'Ekmek çeşidi bulunamadı (ID: ' . $bread_id . ').';
        // Yönlendirme için BASE_URL kullanılır
        redirect(BASE_URL . 'admin/bread/index.php');
        exit;
    }
} catch (PDOException $e) {
     error_log("Bread Fetch Error (Edit): " . $e->getMessage());
     $_SESSION['error_message'] = 'Veritabanı hatası oluştu.';
     // Yönlendirme için BASE_URL kullanılır
     redirect(BASE_URL . 'admin/bread/index.php');
     exit;
}

// Sayfa başlığı
$page_title = 'Ekmek Çeşidi Düzenle: ' . htmlspecialchars($bread['name']);

// Form verilerini ve hataları tutacak değişkenler
$form_data = $bread; // Başlangıçta DB'den gelen veri
$form_errors = [];

// --- Form Gönderildi mi? (POST Metodu) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Güvenlik: Formun doğru ID için gönderildiğini doğrula
    if (!isset($_POST['bread_id']) || (int)$_POST['bread_id'] !== $bread_id) {
        $_SESSION['error_message'] = 'Form gönderim hatası!';
        // Yönlendirme için BASE_URL kullanılır
        redirect(BASE_URL . 'admin/bread/index.php');
        exit;
    }

    // Gönderilen verileri al (ve $form_data'yı güncelle)
    $form_data['name'] = trim($_POST['name'] ?? $bread['name']);
    $form_data['description'] = trim($_POST['description'] ?? $bread['description']);
    $form_data['price'] = filter_var($_POST['price'] ?? $bread['price'], FILTER_VALIDATE_FLOAT);
    $form_data['sale_type'] = $_POST['sale_type'] ?? $bread['sale_type'];
    $form_data['box_capacity'] = ($form_data['sale_type'] === 'box' || $form_data['sale_type'] === 'both') ? filter_var($_POST['box_capacity'] ?? $bread['box_capacity'], FILTER_VALIDATE_INT) : null;
    $form_data['is_packaged'] = isset($_POST['is_packaged']) ? 1 : 0;
    $form_data['package_weight'] = $form_data['is_packaged'] ? filter_var($_POST['package_weight'] ?? $bread['package_weight'], FILTER_VALIDATE_INT) : null;
    $form_data['status'] = isset($_POST['status']) ? 1 : 0;

    $current_image = $bread['image']; // Mevcut resim dosya adı
    $image_path_to_save = $current_image; // Kaydedilecek resim adı (başlangıçta mevcut olan)
    $new_image_uploaded = false;

    // --- Doğrulama ---
    if (empty($form_data['name'])) {
        $form_errors[] = 'Ekmek adı boş bırakılamaz.';
    }
    if ($form_data['price'] === false || $form_data['price'] < 0) {
        $form_errors[] = 'Geçerli bir fiyat giriniz.';
    }
    if (($form_data['sale_type'] === 'box' || $form_data['sale_type'] === 'both') && ($form_data['box_capacity'] === false || $form_data['box_capacity'] <= 0)) {
        $form_errors[] = 'Kasa satışı için geçerli bir kasa kapasitesi giriniz.';
    }
    if ($form_data['is_packaged'] && ($form_data['package_weight'] === false || $form_data['package_weight'] <= 0)) {
        $form_errors[] = 'Paketli ürün için geçerli bir ağırlık (gram) giriniz.';
    }
    if (!in_array($form_data['sale_type'], ['piece', 'box', 'both'])) {
         $form_errors[] = 'Geçersiz satış tipi.';
    }

    // --- Yeni Resim Yükleme İşlemi ---
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        // Dosya işlemleri için BASE_PATH kullanılır
        $upload_dir = BASE_PATH . '/uploads/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0777, true);
        }

        // ... (dosya tipi, boyut kontrolü) ...
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['image']['type'];
        $file_size = $_FILES['image']['size'];
        $max_size = 5 * 1024 * 1024; // 5MB limit

        if (!in_array($file_type, $allowed_types)) {
            $form_errors[] = 'Geçersiz dosya türü. Sadece JPG, PNG, GIF, WEBP izin verilir.';
        } elseif ($file_size > $max_size) {
            $form_errors[] = 'Dosya boyutu çok büyük (Maksimum 5MB).';
        } else {
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $safe_filename = uniqid('bread_', true) . '.' . $file_extension;
            // move_uploaded_file için BASE_PATH kullanılır
            $destination = $upload_dir . $safe_filename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                $image_path_to_save = $safe_filename; // Veritabanına kaydedilecek yeni dosya adı
                $new_image_uploaded = true;
            } else {
                $form_errors[] = 'Yeni resim yüklenirken bir hata oluştu (taşıma başarısız).';
            }
        }
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
         $form_errors[] = 'Resim yüklenirken bir hata oluştu: Hata kodu ' . $_FILES['image']['error'];
    }

    // --- Hata yoksa Veritabanını Güncelle ---
    if (empty($form_errors)) {
        try {
            // Yeni resim başarıyla yüklendiyse ve eski bir resim varsa, eskisini sil
            if ($new_image_uploaded && !empty($current_image)) {
                $old_image_file = BASE_PATH . '/uploads/' . $current_image;
                if (file_exists($old_image_file)) {
                    @unlink($old_image_file); // Eski dosyayı sil (unlink için BASE_PATH)
                }
            }

            // Güncelleme sorgusu
            $sql = "UPDATE bread_types SET
                        name = ?,
                        description = ?,
                        price = ?,
                        image = ?,
                        status = ?,
                        sale_type = ?,
                        box_capacity = ?,
                        is_packaged = ?,
                        package_weight = ?,
                        updated_at = NOW()
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $form_data['name'],
                $form_data['description'],
                $form_data['price'],
                $image_path_to_save, // Yeni veya eski resim dosya adı
                $form_data['status'],
                $form_data['sale_type'],
                $form_data['box_capacity'],
                $form_data['is_packaged'],
                $form_data['package_weight'],
                $bread_id // WHERE koşulu
            ]);

            $_SESSION['success_message'] = 'Ekmek çeşidi başarıyla güncellendi.';
            // Yönlendirme için BASE_URL kullanılır
            redirect(BASE_URL . 'admin/bread/index.php');
            exit;

        } catch (PDOException $e) {
             error_log("Bread Update Error: " . $e->getMessage());
             // Hata mesajını form hatalarına ekle
             $form_errors[] = 'Ekmek çeşidi güncellenirken bir veritabanı hatası oluştu.';
             // Eğer yeni resim yüklendiyse ama VT hatası olduysa, yüklenen yeni resmi silmek iyi olabilir
             if ($new_image_uploaded && isset($destination) && file_exists($destination)) {
                 @unlink($destination);
             }
        }
    }
    // Hata varsa, sayfa aşağıda form_data ve form_errors ile tekrar render edilecek.
    // Session kullanmaya gerek yok çünkü aynı istek içinde kalıyoruz (POST sonrası render).
}

// --- Header'ı Dahil Et ---
// include/require için BASE_PATH kullanılır
include_once BASE_PATH . '/admin/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">
             <i class="fas fa-edit me-2"></i>Ekmek Çeşidi Düzenle: <?php echo htmlspecialchars($bread['name']); // Orijinal adı gösterelim ?>
        </h6>
        <a href="<?php echo BASE_URL; ?>admin/bread/index.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Listeye Dön
        </a>
    </div>
    <div class="card-body">
    <!-- Alert Messages -->
    <?php if (!empty($form_errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0 ps-3">
                <?php foreach ($form_errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo BASE_URL; ?>admin/bread/edit.php?id=<?php echo $bread_id; ?>" enctype="multipart/form-data">
        <input type="hidden" name="bread_id" value="<?php echo $bread_id; ?>">
        
        <div class="row">
            <!-- Sol Kolon: Temel Bilgiler & Görsel -->
            <div class="col-lg-8">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Ürün Bilgileri</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Ekmek Adı</label>
                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($form_data['name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Açıklama</label>
                            <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($form_data['description']); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Birim Fiyat (₺)</label>
                                <div class="input-group">
                                    <span class="input-group-text">₺</span>
                                    <input type="number" step="0.01" class="form-control" name="price" value="<?php echo htmlspecialchars($form_data['price']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Satış Tipi</label>
                                <select class="form-select" name="sale_type" id="sale_type">
                                    <option value="piece" <?php echo $form_data['sale_type'] === 'piece' ? 'selected' : ''; ?>>Sadece Adet</option>
                                    <option value="box" <?php echo $form_data['sale_type'] === 'box' ? 'selected' : ''; ?>>Sadece Kasa</option>
                                    <option value="both" <?php echo $form_data['sale_type'] === 'both' ? 'selected' : ''; ?>>Her İkisi (Adet ve Kasa)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Koşullu Alanlar -->
                        <div class="row">
                            <div class="col-md-6 mb-3" id="box_capacity_div" style="<?php echo ($form_data['sale_type'] === 'piece') ? 'display:none;' : ''; ?>">
                                <label class="form-label">Kasa Kapasitesi (Adet)</label>
                                <input type="number" class="form-control" name="box_capacity" value="<?php echo htmlspecialchars($form_data['box_capacity']); ?>">
                                <div class="form-text">Bir kasada kaç adet ekmek olduğunu giriniz.</div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="is_packaged" name="is_packaged" value="1" <?php echo $form_data['is_packaged'] ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-bold" for="is_packaged">Paketli Ürün</label>
                        </div>

                        <div class="mb-3" id="package_weight_div" style="<?php echo !$form_data['is_packaged'] ? 'display:none;' : ''; ?>">
                            <label class="form-label">Paket Ağırlığı (Gram)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="package_weight" value="<?php echo htmlspecialchars($form_data['package_weight']); ?>">
                                <span class="input-group-text">gr</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sağ Kolon: Görsel & Durum -->
            <div class="col-lg-4">
                <!-- Görsel Yükleme -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Ürün Görseli</h6>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <?php if (!empty($form_data['image'])): ?>
                                <img src="<?php echo BASE_URL . 'uploads/' . htmlspecialchars($form_data['image']); ?>" 
                                     alt="Mevcut Resim" 
                                     class="img-fluid rounded mb-2 shadow-sm" 
                                     style="max-height: 200px;" id="image_preview">
                            <?php else: ?>
                                <div class="bg-light d-flex align-items-center justify-content-center rounded border mb-2 mx-auto" style="width: 150px; height: 150px;">
                                    <i class="fas fa-image text-muted fa-3x"></i>
                                </div>
                                <img src="" alt="Önizleme" class="img-fluid rounded mb-2 shadow-sm d-none" style="max-height: 200px;" id="image_preview_new">
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="image" class="form-label small text-muted">Görseli Değiştir/Ekle</label>
                            <input type="file" class="form-control form-control-sm" name="image" id="image" accept="image/*" onchange="previewImage(this)">
                        </div>
                    </div>
                </div>

                <!-- Yayın Durumu -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Yayın Durumu</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="status" name="status" value="1" <?php echo $form_data['status'] == 1 ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="status">Aktif (Satışta)</label>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Kaydet
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const saleTypeSelect = document.getElementById('sale_type');
    const boxCapacityContainer = document.getElementById('box_capacity_container');
    const boxCapacityInput = document.getElementById('box_capacity');

    const isPackagedSwitch = document.getElementById('is_packaged');
    const packageWeightContainer = document.getElementById('package_weight_container');
    const packageWeightInput = document.getElementById('package_weight');

    function toggleBoxCapacity() {
        const selectedType = saleTypeSelect.value;
        if (selectedType === 'box' || selectedType === 'both') {
            boxCapacityContainer.style.display = 'block';
            boxCapacityInput.required = true;
        } else {
            boxCapacityContainer.style.display = 'none';
            boxCapacityInput.required = false;
        }
    }

     function togglePackageWeight() {
        if (isPackagedSwitch.checked) {
            packageWeightContainer.style.display = 'block';
            packageWeightInput.required = true;
        } else {
            packageWeightContainer.style.display = 'none';
            packageWeightInput.required = false;
        }
    }

    saleTypeSelect.addEventListener('change', toggleBoxCapacity);
    isPackagedSwitch.addEventListener('change', togglePackageWeight);

    // Initial check on page load
    toggleBoxCapacity();
    togglePackageWeight();
});
</script>


<?php
// Footer'ı dahil et
// include/require için BASE_PATH kullanılır
include_once BASE_PATH . '/admin/footer.php';
?>
