<?php
/**
 * Büfe Paneli - Faturalarım
 * Modern ve profesyonel arayüz
 */

require_once '../../init.php';

if (!isLoggedIn()) { redirect(BASE_URL . 'login.php'); exit; }

$page_title = 'Faturalarım';
$current_page = 'invoices';
$user_id = $_SESSION['user_id'];

// Filtreleme Parametreleri
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_sent = isset($_GET['filter_sent']) ? $_GET['filter_sent'] : '';
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) && $_GET['order'] == 'asc' ? 'ASC' : 'DESC';

// Geçerli sıralama sütunları
$valid_columns = ['invoice_number', 'invoice_date', 'total_amount', 'created_at'];
if (!in_array($sort_column, $valid_columns)) { $sort_column = 'created_at'; }

// Sorgu Hazırlama
$query = "
    SELECT i.*, o.order_number, o.total_amount 
    FROM invoices i
    JOIN orders o ON i.order_id = o.id
    WHERE o.user_id = :user_id
";

if (!empty($search)) {
    $query .= " AND (i.invoice_number LIKE :search OR o.order_number LIKE :search)";
}

if ($filter_sent !== '') {
    $query .= " AND i.is_sent = :is_sent";
}

$query .= " ORDER BY i.$sort_column $sort_order";

try {
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    
    if (!empty($search)) {
        $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    }
    
    if ($filter_sent !== '') {
        $stmt->bindValue(':is_sent', $filter_sent, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Invoices Error: " . $e->getMessage());
    $invoices = [];
}

include_once ROOT_PATH . '/my/header.php';
?>

<div class="container-fluid">

    <!-- Başlık -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Faturalarım</h1>
    </div>

    <!-- Filtre Kartı -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filtreleme</h6>
        </div>
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-uppercase text-muted">Arama</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-gray-400"></i></span>
                        <input type="text" class="form-control border-start-0" name="search" placeholder="Fatura veya Sipariş No..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-uppercase text-muted">Durum</label>
                    <select name="filter_sent" class="form-select">
                        <option value="">Tümü</option>
                        <option value="1" <?php echo $filter_sent === '1' ? 'selected' : ''; ?>>Gönderildi</option>
                        <option value="0" <?php echo $filter_sent === '0' ? 'selected' : ''; ?>>Gönderilmedi</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-uppercase text-muted">Sıralama</label>
                    <select name="sort" class="form-select">
                        <option value="created_at" <?php echo $sort_column == 'created_at' ? 'selected' : ''; ?>>Oluşturulma Tarihi</option>
                        <option value="invoice_date" <?php echo $sort_column == 'invoice_date' ? 'selected' : ''; ?>>Fatura Tarihi</option>
                        <option value="total_amount" <?php echo $sort_column == 'total_amount' ? 'selected' : ''; ?>>Tutar</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filtrele
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste Kartı -->
    <div class="card shadow mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Fatura No</th>
                            <th>Sipariş No</th>
                            <th>Fatura Tarihi</th>
                            <th>Tutar</th>
                            <th>Durum</th>
                            <th class="text-end pe-4">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoices)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">Kayıtlı fatura bulunamadı.</td></tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary">
                                    <i class="fas fa-file-invoice me-2 text-gray-400"></i>
                                    <?php echo htmlspecialchars($inv['invoice_number']); ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?php echo htmlspecialchars($inv['order_number']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d.m.Y', strtotime($inv['invoice_date'])); ?></td>
                                <td class="fw-bold"><?php echo formatMoney($inv['total_amount']); ?></td>
                                <td>
                                    <?php if ($inv['is_sent']): ?>
                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i> Gönderildi</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> Bekliyor</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <a href="view.php?id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-info text-white" title="Görüntüle" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="download.php?id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-primary" title="İndir">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php include_once ROOT_PATH . '/my/footer.php'; ?>