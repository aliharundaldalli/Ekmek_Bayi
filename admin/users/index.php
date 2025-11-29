<?php
/**
 * Kullanıcı Yönetimi Sayfası
 * (Tablo yapısı yeniden düzenlendi)
 */

// --- init.php Dahil Etme ---
require_once '../../init.php'; // init.php'nin ROOT_PATH ve BASE_URL tanımladığını varsayıyoruz

// --- Auth Checks ---
if (!isLoggedIn()) { redirect(rtrim(BASE_URL, '/') . '/login.php'); exit; }
if (!isAdmin()) { redirect(rtrim(BASE_URL, '/') . '/my/index.php'); exit; }

// --- Page Title ---
$page_title = 'Kullanıcı Yönetimi';

// --- Kullanıcı Silme İşlemi (Soft Delete) ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $user_id_to_delete = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$user_id_to_delete) {
         $_SESSION['error_message'] = 'Geçersiz Kullanıcı ID.';
    } else if ($_SESSION['user_id'] == $user_id_to_delete) { // Kendini silme kontrolü
         $_SESSION['error_message'] = 'Kendinizi silemezsiniz!';
    }
    else {
        try {
            // Silinmeye çalışılan kullanıcının rolünü kontrol et
            $stmt_check = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt_check->execute([$user_id_to_delete]);
            $user_to_delete = $stmt_check->fetch();

            if ($user_to_delete && $user_to_delete['role'] === 'admin') {
                $_SESSION['error_message'] = 'Admin rolündeki kullanıcılar silinemez!';
            } else if ($user_to_delete) {
                // Admin değilse veya bulunamadıysa (bulunamama durumu pek olası değil ama kontrol edelim)
                // Soft delete yap (status = 0)
                $stmt_update = $pdo->prepare("UPDATE users SET status = 0 WHERE id = ? AND status != 0");
                $stmt_update->execute([$user_id_to_delete]);

                if ($stmt_update->rowCount() > 0) {
                    $_SESSION['success_message'] = 'Kullanıcı (ID: ' . $user_id_to_delete . ') başarıyla pasif hale getirildi.';
                } else {
                    // Neden güncelleme olmadı?
                     $stmt_status = $pdo->prepare("SELECT status FROM users WHERE id = ?");
                     $stmt_status->execute([$user_id_to_delete]);
                     $current_status = $stmt_status->fetchColumn();
                     if ($current_status === 0) {
                         $_SESSION['error_message'] = 'Kullanıcı (ID: ' . $user_id_to_delete . ') zaten pasif durumda.';
                     } elseif ($current_status === false) {
                         $_SESSION['error_message'] = 'Kullanıcı (ID: ' . $user_id_to_delete . ') bulunamadı.';
                     } else {
                          $_SESSION['error_message'] = 'Kullanıcı (ID: ' . $user_id_to_delete . ') pasif hale getirilirken bilinmeyen bir sorun oluştu.';
                     }
                }
            } else {
                 $_SESSION['error_message'] = 'Silinmek istenen kullanıcı (ID: ' . $user_id_to_delete . ') bulunamadı.';
            }
        } catch (PDOException $e) {
             error_log("User Delete (Set Inactive) Error for ID " . $user_id_to_delete . ": " . $e->getMessage());
             $_SESSION['error_message'] = 'Kullanıcı silinirken bir veritabanı hatası oluştu.';
        }
    }
    // Her durumda index'e geri dön
    redirect(rtrim(BASE_URL, '/') . '/admin/users/index.php');
    exit;
}

// --- Kullanıcı Durum Değiştirme İşlemi (Eklendi) ---
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $user_id_to_toggle = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$user_id_to_toggle) {
        $_SESSION['error_message'] = 'Geçersiz Kullanıcı ID.';
    } else if ($_SESSION['user_id'] == $user_id_to_toggle) { // Kendi durumunu değiştirme kontrolü
         $_SESSION['error_message'] = 'Kendi durumunuzu buradan değiştiremezsiniz!';
    } else {
        try {
            $stmt_fetch = $pdo->prepare("SELECT status, role FROM users WHERE id = ?");
            $stmt_fetch->execute([$user_id_to_toggle]);
            $user_to_toggle = $stmt_fetch->fetch();

            if ($user_to_toggle) {
                 if ($user_to_toggle['role'] === 'admin') {
                     $_SESSION['error_message'] = 'Admin kullanıcısının durumu değiştirilemez!';
                 } else {
                    $new_status = ($user_to_toggle['status'] == 1) ? 0 : 1;
                    $stmt_update = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                    $stmt_update->execute([$new_status, $user_id_to_toggle]);

                    if ($stmt_update->rowCount() > 0) {
                        $_SESSION['success_message'] = 'Kullanıcı (ID: ' . $user_id_to_toggle . ') durumu başarıyla güncellendi (' . ($new_status ? 'Aktif' : 'Pasif') . ').';
                    } else {
                        $_SESSION['error_message'] = 'Kullanıcı (ID: ' . $user_id_to_toggle . ') durumu güncellenirken bir hata oluştu (rowCount=0).';
                    }
                 }
            } else {
                $_SESSION['error_message'] = 'Kullanıcı bulunamadı (ID: ' . $user_id_to_toggle . ').';
            }
        } catch (PDOException $e) {
            error_log("User Toggle Status Error for ID " . $user_id_to_toggle . ": " . $e->getMessage());
            $_SESSION['error_message'] = 'Kullanıcı durumu güncellenirken bir veritabanı hatası oluştu.';
        }
    }
    redirect(rtrim(BASE_URL, '/') . '/admin/users/index.php');
    exit;
}


// --- Veri Çekme ---
$users = [];
$list_error = null;
try {
    // Admin olmayanları ve kendi ID'si dışındakileri listelemek daha güvenli olabilir
    // $current_user_id = $_SESSION['user_id'];
    // $stmt = $pdo->prepare("SELECT * FROM users WHERE id != ? ORDER BY role ASC, id DESC");
    // $stmt->execute([$current_user_id]);
    // $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Şimdilik tüm kullanıcıları listeleyelim (Admin kontrolü silme/toggle içinde yapılıyor)
    $stmt = $pdo->query("SELECT * FROM users ORDER BY role ASC, id DESC"); // Role göre sıralama eklendi
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
     error_log("User Fetch Error (Index): " . $e->getMessage());
     $list_error = "Kullanıcılar listelenirken bir veritabanı hatası oluştu.";
}


// --- Header'ı Dahil Et ---
include_once ROOT_PATH . '/admin/header.php';
?>

<div class="container-fluid">

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Kullanıcı Yönetimi</h1>
        <a href="add.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-user-plus fa-sm text-white-50 me-1"></i> Yeni Kullanıcı Ekle
        </a>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($list_error)): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle me-1"></i> <?php echo $list_error; ?>
        </div>
    <?php endif; ?>

    <!-- Users Table Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Kullanıcı Listesi</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle datatable" id="usersTable" width="100%" cellspacing="0">
                    <thead class="table-light">
                        <tr>

                            <th>Kullanıcı</th>
                            <th>İletişim</th>
                            <th>Rol</th>
                            <th>Durum</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): 
                            $initials = mb_strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1));
                            $bg_color = 'bg-primary';
                            if ($user['role'] === 'admin') $bg_color = 'bg-danger';
                            elseif ($user['role'] === 'approver') $bg_color = 'bg-warning text-dark';
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle <?php echo $bg_color; ?> text-white d-flex align-items-center justify-content-center rounded-circle me-3 flex-shrink-0" style="width: 40px; height: 40px; font-size: 0.9rem; overflow: hidden;">
                                        <?php if (!empty($user['profile_image'])): ?>
                                            <img src="<?php echo BASE_URL . '/' . $user['profile_image']; ?>" class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <?php echo $initials; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($user['bakery_name']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="small"><i class="fas fa-envelope text-muted me-1"></i> <?php echo htmlspecialchars($user['email']); ?></div>
                                <div class="small"><i class="fas fa-phone text-muted me-1"></i> <?php echo htmlspecialchars($user['phone']); ?></div>
                            </td>
                            <td>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <span class="badge bg-danger"><i class="fas fa-user-shield me-1"></i> Admin</span>
                                <?php elseif ($user['role'] === 'approver'): ?>
                                    <span class="badge bg-warning text-dark"><i class="fas fa-user-check me-1"></i> Onaylayıcı</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark"><i class="fas fa-store me-1"></i> Bayi</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['role'] !== 'admin' && $user['id'] != $_SESSION['user_id']): ?>
                                    <a href="index.php?action=toggle&id=<?php echo $user['id']; ?>" 
                                       class="text-decoration-none"
                                       onclick="return confirm('Kullanıcı durumunu <?php echo $user['status'] == 1 ? 'pasif' : 'aktif'; ?> yapmak istediğinize emin misiniz?');">
                                        <?php if ($user['status'] == 1): ?>
                                            <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="fas fa-ban me-1"></i> Pasif</span>
                                        <?php endif; ?>
                                    </a>
                                <?php else: ?>
                                    <?php if ($user['status'] == 1): ?>
                                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="fas fa-ban me-1"></i> Pasif</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                           
                            <td class="text-end">
                                <div class="btn-group">
                                    <!-- Görüntüle (Ayrı Buton) -->
                                    <a href="view.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-info" title="Görüntüle" data-bs-toggle="tooltip">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <!-- Diğer İşlemler (Dropdown) -->
                                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                        <span class="visually-hidden">İşlemler</span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><h6 class="dropdown-header">İşlemler</h6></li>
                                        
                                        <!-- Düzenle -->
                                        <li>
                                            <a class="dropdown-item" href="edit.php?id=<?php echo $user['id']; ?>">
                                                <i class="fas fa-edit text-primary me-2"></i>Düzenle
                                            </a>
                                        </li>

                                        <!-- Durum Değiştir (Dropdown içinde de olsun) -->
                                        <?php if ($user['role'] !== 'admin' && $user['id'] != $_SESSION['user_id']): ?>
                                            <li>
                                                <a class="dropdown-item" href="index.php?action=toggle&id=<?php echo $user['id']; ?>">
                                                    <i class="fas <?php echo $user['status'] == 1 ? 'fa-toggle-on text-success' : 'fa-toggle-off text-secondary'; ?> me-2"></i>
                                                    <?php echo $user['status'] == 1 ? 'Pasife Al' : 'Aktifleştir'; ?>
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <!-- Sil -->
                                            <li>
                                                <a class="dropdown-item text-danger" href="index.php?action=delete&id=<?php echo $user['id']; ?>" onclick="return confirm('Bu kullanıcıyı silmek istediğinize emin misiniz?');">
                                                    <i class="fas fa-trash me-2"></i>Sil
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php
// --- Footer'ı Dahil Et ---
// *** GÖRÜNÜM SORUNU İÇİN KONTROL: footer.php dosyasının jQuery, Bootstrap JS ve DataTables JS dosyalarını doğru şekilde içerdiğinden ve DataTables'ı başlattığından (örn. $('#usersTable').DataTable();) emin olun. ***
include_once ROOT_PATH . '/admin/footer.php';
?>
