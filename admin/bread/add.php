<?php
/**
 * Yeni Ekmek Çeşidi Ekleme Sayfası (Yol Düzeltmeleriyle)
 */

// --- Yol Tanımlamaları ---
// BASE_PATH: Sunucudaki kök dizin dosya yolu
// Bu dosyanın konumu: /home/ahdakade/test.ahdakademi.com/admin/bread/add.php
// Kök dizin (/home/ahdakade/test.ahdakademi.com) 3 seviye yukarıda
define('BASE_PATH', dirname(dirname(__DIR__))); // Örnek: /home/ahdakade/test.ahdakademi.com

// BASE_URL: Web sitesinin temel URL yolu (tarayıcı tarafından kullanılır)
// Genellikle sadece "/" olur, eğer site alt klasörde değilse.
// Veya protokol ve domain ile: 'https://test.ahdakademi.com' (daha az esnek)
// Biz göreceli kök yolu kullanalım:
// define('BASE_URL', '/'); // Sitenin kök URL'si - init.php içinde tanımlanıyor

// --- init.php Dahil Etme ---
// init.php'nin BASE_PATH içinde olduğunu varsayıyoruz
// Eğer init.php bu sabitleri zaten tanımlıyorsa, yukarıdaki define() satırlarını
// init.php'ye taşıyıp buradan silebilirsiniz.
require_once BASE_PATH . '/init.php';

// Kullanıcı girişi ve yetkisi kontrolü
if (!isLoggedIn()) {
    // Yönlendirme için BASE_URL kullanılır
    redirect(BASE_URL . 'login.php'); // Varsayım: login.php kök dizinde
}

if (!isAdmin()) {
    // Yönlendirme için BASE_URL kullanılır
    redirect(BASE_URL . 'my/index.php'); // Varsayım: my/index.php kök dizinde
}

// Sayfa başlığı
$page_title = 'Yeni Ekmek Çeşidi Ekle';

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Kontrolü
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Güvenlik hatası: Geçersiz form gönderimi (CSRF). Lütfen sayfayı yenileyip tekrar deneyin.';
        redirect(BASE_URL . 'admin/bread/add.php');
        exit;
    }

    // Form verilerini al... (önceki kodla aynı)
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
    $sale_type = $_POST['sale_type'] ?? 'piece';
    $box_capacity = ($sale_type === 'box' || $sale_type === 'both') ? filter_var($_POST['box_capacity'] ?? null, FILTER_VALIDATE_INT) : null;
    $is_packaged = isset($_POST['is_packaged']) ? 1 : 0;
    $package_weight = $is_packaged ? filter_var($_POST['package_weight'] ?? null, FILTER_VALIDATE_INT) : null;
    $status = isset($_POST['status']) ? 1 : 0;
    $image_path = null;

    // Basit Doğrulama... (önceki kodla aynı)
    $errors = [];
    if (empty($name)) {
        $errors[] = 'Ekmek adı boş bırakılamaz.';
    }
    if ($price === false || $price < 0) {
        $errors[] = 'Geçerli bir fiyat giriniz.';
    }
    if (($sale_type === 'box' || $sale_type === 'both') && ($box_capacity === false || $box_capacity <= 0)) {
        $errors[] = 'Kasa satışı için geçerli bir kasa kapasitesi giriniz.';
    }
     if ($is_packaged && ($package_weight === false || $package_weight <= 0)) {
        $errors[] = 'Paketli ürün için geçerli bir ağırlık (gram) giriniz.';
    }
    if (!in_array($sale_type, ['piece', 'box', 'both'])) {
         $errors[] = 'Geçersiz satış tipi.';
    }


    // Resim Yükleme İşlemi
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        // Dosya işlemleri için BASE_PATH kullanılır
        $upload_dir = BASE_PATH . '/uploads/'; // Doğru dosya yolu
        if (!is_dir($upload_dir)) {
             // mkdir için BASE_PATH kullanılır
            @mkdir($upload_dir, 0777, true); // @ hata kontrolünü bastırır, daha iyi hata yönetimi yapılabilir
        }

        // ... (dosya tipi, boyut kontrolü - önceki kodla aynı) ...
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['image']['type'];
        $file_size = $_FILES['image']['size'];
        $max_size = 5 * 1024 * 1024; // 5MB limit

        if (!in_array($file_type, $allowed_types)) {
            $errors[] = 'Geçersiz dosya türü. Sadece JPG, PNG, GIF, WEBP izin verilir.';
        } elseif ($file_size > $max_size) {
            $errors[] = 'Dosya boyutu çok büyük (Maksimum 5MB).';
        } else {
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $safe_filename = uniqid('bread_', true) . '.' . $file_extension;
            // move_uploaded_file için BASE_PATH kullanılır
            $destination = $upload_dir . $safe_filename; // Tam dosya yolu

            if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                $image_path = $safe_filename; // Veritabanına sadece dosya adını kaydet
            } else {
                $errors[] = 'Resim yüklenirken bir hata oluştu (taşıma başarısız).';
                 // Daha detaylı hata loglaması eklenebilir: error_log('File upload failed: ' . $_FILES['image']['error']);
            }
        }
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
         $errors[] = 'Resim yüklenirken bir hata oluştu: Hata kodu ' . $_FILES['image']['error'];
    }


    // Hata yoksa veritabanına ekle
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO bread_types (name, description, price, image, status, sale_type, box_capacity, is_packaged, package_weight, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $name, $description, $price, $image_path, $status, $sale_type, $box_capacity, $is_packaged, $package_weight
            ]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['success_message'] = 'Ekmek çeşidi başarıyla eklendi.';
                // Yönlendirme için BASE_URL kullanılır
                redirect(BASE_URL . 'admin/bread/index.php');
            } else {
                $_SESSION['error_message'] = 'Ekmek çeşidi eklenirken bir veritabanı hatası oluştu.';
            }
        } catch (PDOException $e) {
             error_log("Bread Add Error: " . $e->getMessage());
             $_SESSION['error_message'] = 'Ekmek çeşidi eklenirken bir veritabanı hatası oluştu.';
        }

    } else {
        $_SESSION['form_data'] = $_POST;
        $_SESSION['form_errors'] = $errors;
    }

    // Hata varsa veya VT hatası olursa aynı sayfaya yönlendir (POST-Redirect-GET pattern)
    // Yönlendirme için BASE_URL kullanılır
     redirect(BASE_URL . 'admin/bread/add.php');
     exit;

} else {
    // GET isteği... (önceki kodla aynı)
    $form_data = $_SESSION['form_data'] ?? [];
    $form_errors = $_SESSION['form_errors'] ?? [];
    unset($_SESSION['form_data'], $_SESSION['form_errors']);
}


// Header'ı dahil et
// include/require için BASE_PATH kullanılır
include_once BASE_PATH . '/admin/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-plus me-2"></i>Yeni Ekmek Çeşidi Ekle
        </h6>
        <a href="<?php echo BASE_URL; ?>admin/bread/index.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Listeye Dön
        </a>
    </div>
    <div class="card-body">
        <?php if (!empty($form_errors)): ?>
            <div class="alert alert-danger">
                <strong>Hata!</strong> Lütfen aşağıdaki sorunları düzeltin:
                <ul>
                    <?php foreach ($form_errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

         <form action="<?php echo BASE_URL; ?>admin/bread/add.php" method="POST" enctype="multipart/form-data">
            <?php $csrf_token = generateCSRFToken(); ?>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="mb-3">
                <label for="name" class="form-label">Ekmek Adı <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>" required>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Açıklama</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                     <label for="price" class="form-label">Fiyat (₺) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($form_data['price'] ?? ''); ?>" required>
                </div>
                 <div class="col-md-6 mb-3">
                    <label for="image" class="form-label">Resim</label>
                    <input class="form-control" type="file" id="image" name="image" accept="image/jpeg, image/png, image/gif, image/webp">
                    <small class="form-text text-muted">İzin verilen türler: JPG, PNG, GIF, WEBP. Maksimum boyut: 5MB.</small>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="sale_type" class="form-label">Satış Tipi <span class="text-danger">*</span></label>
                    <select class="form-select" id="sale_type" name="sale_type" required>
                        <option value="piece" <?php echo (($form_data['sale_type'] ?? 'piece') === 'piece') ? 'selected' : ''; ?>>Adet</option>
                        <option value="box" <?php echo (($form_data['sale_type'] ?? '') === 'box') ? 'selected' : ''; ?>>Kasa</option>
                        <option value="both" <?php echo (($form_data['sale_type'] ?? '') === 'both') ? 'selected' : ''; ?>>Adet ve Kasa</option>
                    </select>
                </div>
                 <div class="col-md-6 mb-3" id="box_capacity_container" style="display: <?php echo (($form_data['sale_type'] ?? 'piece') === 'box' || ($form_data['sale_type'] ?? '') === 'both') ? 'block' : 'none'; ?>;">
                    <label for="box_capacity" class="form-label">Kasa Kapasitesi (adet) <span class="text-danger">*</span></label>
                    <input type="number" min="1" class="form-control" id="box_capacity" name="box_capacity" value="<?php echo htmlspecialchars($form_data['box_capacity'] ?? ''); ?>">
                </div>
            </div>

             <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="form-check form-switch mt-4 pt-2">
                      <input class="form-check-input" type="checkbox" role="switch" id="is_packaged" name="is_packaged" value="1" <?php echo isset($form_data['is_packaged']) ? 'checked' : ''; ?>>
                      <label class="form-check-label" for="is_packaged">Paketli Ürün mü?</label>
                    </div>
                </div>
                 <div class="col-md-6 mb-3" id="package_weight_container" style="display: <?php echo isset($form_data['is_packaged']) ? 'block' : 'none'; ?>;">
                    <label for="package_weight" class="form-label">Paket Ağırlığı (gram) <span class="text-danger">*</span></label>
                    <input type="number" min="1" class="form-control" id="package_weight" name="package_weight" value="<?php echo htmlspecialchars($form_data['package_weight'] ?? ''); ?>">
                </div>
            </div>

            <div class="mb-3">
                 <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" role="switch" id="status" name="status" value="1" <?php echo (isset($form_data['status']) && $form_data['status'] == 1) || !isset($form_data['status']) ? 'checked' : ''; ?>> <label class="form-check-label" for="status">Durum (Aktif/Pasif)</label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i>Kaydet
            </button>
        </form>
    </div>
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
