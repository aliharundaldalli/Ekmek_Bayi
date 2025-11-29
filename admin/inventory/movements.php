<?php
/**
 * Admin Paneli - Stok Hareketleri Listesi
 */

// --- init.php Dahil Etme ve Kontroller ---
require_once '../../init.php';
require_once ROOT_PATH . '/admin/includes/admin_check.php';
require_once ROOT_PATH . '/admin/includes/inventory_functions.php';

// --- Sayfa Başlığı ve Aktif Menü ---
$page_title = 'Stok Hareketleri';
$current_page = 'inventory';

// --- Filtreleme Değişkenleri ---
$bread_id = isset($_GET['bread_id']) && is_numeric($_GET['bread_id']) ? (int)$_GET['bread_id'] : 0;
$movement_type = $_GET['movement_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// --- Pagination ---
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 50; // Sayfa başına kayıt sayısı
$offset = ($page - 1) * $per_page;

// Stok hareketlerini getir
$filters = [
    'bread_id' => $bread_id,
    'movement_type' => $movement_type,
    'date_from' => $date_from, 
    'date_to' => $date_to,
    'limit' => $per_page,
    'offset' => $offset
];

// Not: getInventoryMovements fonksiyonu inventory_functions.php içerisinde tanımlanmıştır
$result = getInventoryMovements($filters, $pdo);
$movements = $result['movements'] ?? [];
$total_count = $result['total_count'] ?? 0;
$total_pages = ceil($total_count / $per_page);

// --- Ekmek Tiplerini Getir (Filtre dropdown için) ---
$bread_types = [];
try {
    $stmt_bread = $pdo->query("SELECT id, name FROM bread_types ORDER BY name ASC");
    $bread_types = $stmt_bread->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Bread Types Fetch for Filter Error: " . $e->getMessage());
}

// --- Header'ı Dahil Et ---
include_once ROOT_PATH . '/admin/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-wrap justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary me-3 mb-2 mb-md-0">
            <i class="fas fa-exchange-alt me-2"></i><?php echo htmlspecialchars($page_title); ?>
        </h6>
        <div class="btn-group btn-group-sm" role="group">
            <a href="<?php echo BASE_URL; ?>/admin/inventory/add.php" class="btn btn-primary" title="Stok Ekle">
                <i class="fas fa-plus me-1"></i> Stok Ekle
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/inventory/index.php" class="btn btn-info" title="Stok Listesi">
                <i class="fas fa-warehouse me-1"></i> Stok Listesi
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/inventory/export_movements.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success" title="Filtrelenmiş Veriyi CSV Olarak İndir">
                <i class="fas fa-file-excel"></i> <span class="d-none d-sm-inline-block">CSV İndir</span>
            </a>
            <button type="button" class="btn btn-secondary" id="printMovementsBtn" title="Mevcut Listeyi Yazdır">
                <i class="fas fa-print"></i> <span class="d-none d-sm-inline-block">Yazdır</span>
            </button>
        </div>
    </div>

    <div class="card-body border-bottom bg-light py-2 filter-form">
        <form action="" method="get" id="filterForm">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                    <label for="bread_id" class="form-label small mb-1">Ekmek Türü</label>
                    <select name="bread_id" id="bread_id" class="form-select form-select-sm">
                        <option value="">Tümü</option>
                        <?php foreach ($bread_types as $bread): ?>
                            <option value="<?php echo $bread['id']; ?>" <?php echo ($bread_id == $bread['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($bread['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                    <label for="movement_type" class="form-label small mb-1">Hareket Türü</label>
                    <select name="movement_type" id="movement_type" class="form-select form-select-sm">
                        <option value="">Tümü</option>
                        <option value="in" <?php echo ($movement_type === 'in') ? 'selected' : ''; ?>>Giriş</option>
                        <option value="out" <?php echo ($movement_type === 'out') ? 'selected' : ''; ?>>Çıkış</option>
                    </select>
                </div>
                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                    <label for="date_from" class="form-label small mb-1">Başlangıç</label>
                    <input type="date" name="date_from" id="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                    <label for="date_to" class="form-label small mb-1">Bitiş</label>
                    <input type="date" name="date_to" id="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-12 col-sm-4 col-md-6 col-lg-2">
                    <label for="search" class="form-label small mb-1">Ara</label>
                    <input type="text" name="search" id="search" class="form-control form-control-sm" placeholder="Not, Açıklama..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-12 col-lg-2">
                    <div class="d-grid gap-2 d-lg-flex">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                            <i class="fas fa-filter"></i> Filtrele
                        </button>
                        <a href="<?php echo BASE_URL; ?>/admin/inventory/movements.php" class="btn btn-secondary btn-sm flex-grow-1" title="Filtreleri Temizle">
                            <i class="fas fa-times"></i> Sıfırla
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="card-body">
        <?php // Session mesajları header'da gösteriliyor varsayalım ?>

        <?php if (!empty($movements)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover align-middle" id="movementsTable">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Ekmek</th>
                            <th class="text-center">Hareket</th>
                            <th class="text-center">Adet</th>
                            <th class="text-center">Kasa</th>
                            <th>Sipariş No</th>
                            <th>Not</th>
                            <th>İşlemi Yapan</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movements as $movement): ?>
                            <tr>
                                <td><?php echo $movement['id']; ?></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>/admin/inventory/view.php?id=<?php echo $movement['bread_id']; ?>" title="Ürün Detayı">
                                        <?php echo htmlspecialchars($movement['bread_name'] ?? 'Bilinmeyen Ürün'); ?>
                                    </a>
                                </td>
                                <td class="text-center">
                                    <?php if ($movement['movement_type'] === 'in'): ?>
                                        <span class="badge bg-success"><i class="fas fa-arrow-down"></i> Giriş</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="fas fa-arrow-up"></i> Çıkış</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($movement['piece_quantity'] > 0): ?>
                                        <span class="fw-bold"><?php echo number_format($movement['piece_quantity']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($movement['box_quantity'] > 0): ?>
                                        <span class="fw-bold"><?php echo number_format($movement['box_quantity']); ?></span>
                                        <?php if (!empty($movement['box_capacity'])): ?>
                                            <small class="text-muted">(<?php echo $movement['box_capacity'] * $movement['box_quantity']; ?> adet)</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($movement['order_id']) && !empty($movement['order_number'])): ?>
                                        <a href="<?php echo BASE_URL; ?>/admin/orders/view.php?id=<?php echo $movement['order_id']; ?>" title="Sipariş Detayı">
                                            <?php echo htmlspecialchars($movement['order_number']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo nl2br(htmlspecialchars($movement['note'] ?? '')); ?></td>
                                <td>
                                    <?php 
                                        if (!empty($movement['username'])) {
                                            echo htmlspecialchars($movement['first_name'] . ' ' . $movement['last_name'] . ' (' . $movement['username'] . ')');
                                        } else {
                                            echo '<span class="text-muted">Sistem</span>';
                                        }
                                    ?>
                                </td>
                                <td><?php echo formatDate($movement['created_at'], true); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <nav aria-label="Stok hareketleri sayfaları" class="mt-4 d-flex justify-content-center">
                    <ul class="pagination pagination-sm">
                        <?php
                            // URL parametrelerini al (page hariç)
                            $queryParams = $_GET;
                            unset($queryParams['page']);
                            $queryString = http_build_query($queryParams);

                            // Önceki Sayfa
                            echo '<li class="page-item ' . ($page <= 1 ? 'disabled' : '') . '">';
                            echo '<a class="page-link" href="?' . $queryString . '&page=' . ($page - 1) . '" aria-label="Önceki">';
                            echo '<span aria-hidden="true">&laquo;</span>';
                            echo '</a></li>';

                            // Sayfa Numaraları (Daha dinamik bir aralık)
                            $links_limit = 5; // Gösterilecek maksimum sayfa linki sayısı
                            $start = max(1, $page - floor($links_limit / 2));
                            $end = min($total_pages, $start + $links_limit - 1);
                            // Eğer sona çok yaklaşıldıysa başlangıcı ayarla
                            $start = max(1, $end - $links_limit + 1);

                            if ($start > 1) { // İlk sayfa ve ...
                                echo '<li class="page-item"><a class="page-link" href="?' . $queryString . '&page=1">1</a></li>';
                                if ($start > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }

                            for ($i = $start; $i <= $end; $i++) {
                                $active = ($i == $page) ? 'active' : '';
                                echo '<li class="page-item ' . $active . '"><a class="page-link" href="?' . $queryString . '&page=' . $i . '">' . $i . '</a></li>';
                            }

                            if ($end < $total_pages) { // Son sayfa ve ...
                                if ($end < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?' . $queryString . '&page=' . $total_pages . '">' . $total_pages . '</a></li>';
                            }

                            // Sonraki Sayfa
                            echo '<li class="page-item ' . ($page >= $total_pages ? 'disabled' : '') . '">';
                            echo '<a class="page-link" href="?' . $queryString . '&page=' . ($page + 1) . '" aria-label="Sonraki">';
                            echo '<span aria-hidden="true">&raquo;</span>';
                            echo '</a></li>';
                        ?>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert alert-warning text-center">
                <i class="fas fa-info-circle me-2"></i> Arama kriterlerinize uygun stok hareketi bulunamadı. Filtreleri sıfırlamayı deneyin.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Yazdır Butonu ---
    const printBtn = document.getElementById('printMovementsBtn');
    if (printBtn) {
        printBtn.addEventListener('click', function() {
            // Yazdırmadan önce filtre/başlık gibi gereksiz alanları gizle, sonra geri aç
            const elementsToHide = '.filter-form, .card-header .btn-group, .pagination, #adminSidebar, header.bg-dark, footer.bg-dark';
            document.querySelectorAll(elementsToHide).forEach(el => el.style.display = 'none');
            window.print();
            document.querySelectorAll(elementsToHide).forEach(el => el.style.display = '');
        });
    }

    // --- DataTables Başlatma (Eğer datatable sınıfı kullanılıyorsa) ---
    if (typeof $ !== 'undefined' && $.fn.dataTable && $('#movementsTable').length > 0 && !$.fn.dataTable.isDataTable('#movementsTable')) {
        $('#movementsTable').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/tr.json" },
            "responsive": true,
            "order": [[8, 'desc']], // Tarihe göre sırala (index 8)
            "columnDefs": [
                { "orderable": false, "targets": [6] } // Not sütunu sıralanamaz
            ],
            "paging": false,    // PHP pagination kullanılıyor
            "info": false,      // PHP pagination kullanılıyor
            "searching": false, // PHP filtreleme kullanılıyor
            "autoWidth": false  // Otomatik genişliği devre dışı bırak
        });
    }
});
</script>

<?php
// --- Footer'ı Dahil Et ---
include_once ROOT_PATH . '/admin/footer.php';
?>