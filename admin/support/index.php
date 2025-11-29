<?php
/**
 * Admin - Destek Talepleri Listesi
 */

require_once '../../init.php';
require_once ROOT_PATH . '/admin/includes/admin_check.php';

$page_title = 'Destek Talepleri';
$current_page = 'support';

// --- Filtreleme Parametreleri ---
$filter_status = $_GET['status'] ?? 'active';
$filter_search = trim($_GET['search'] ?? '');
$filter_priority = $_GET['priority'] ?? '';

// --- Veri Çekme ---
try {
    // İstatistikler
    $stats = $pdo->query("
        SELECT 
            SUM(CASE WHEN status IN ('open', 'in_progress', 'waiting') THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count,
            SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as closed_count,
            COUNT(*) as total_count
        FROM support_tickets
    ")->fetch(PDO::FETCH_ASSOC);

    // Talepler
    $where = ["1=1"];
    $params = [];

    if ($filter_status === 'active') {
        $where[] = "t.status IN ('open', 'in_progress', 'waiting')";
    } elseif ($filter_status === 'closed') {
        $where[] = "t.status IN ('resolved', 'closed')";
    } elseif ($filter_status !== 'all') {
        $where[] = "t.status = ?";
        $params[] = $filter_status;
    }

    if (!empty($filter_priority)) {
        $where[] = "t.priority = ?";
        $params[] = $filter_priority;
    }

    if (!empty($filter_search)) {
        $where[] = "(t.ticket_number LIKE ? OR t.subject LIKE ? OR u.first_name LIKE ? OR u.bakery_name LIKE ?)";
        $search_term = "%$filter_search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    $sql = "
        SELECT t.*, u.first_name, u.last_name, u.bakery_name,
               (SELECT COUNT(*) FROM support_messages WHERE ticket_id = t.id) as message_count,
               (SELECT created_at FROM support_messages WHERE ticket_id = t.id ORDER BY created_at DESC LIMIT 1) as last_reply_at
        FROM support_tickets t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY 
            CASE t.status 
                WHEN 'open' THEN 1 
                WHEN 'in_progress' THEN 2 
                WHEN 'waiting' THEN 3 
                ELSE 4 
            END ASC, 
            t.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Support List Error: " . $e->getMessage());
    $tickets = [];
}

include_once ROOT_PATH . '/admin/header.php';
?>

<div class="container-fluid">
    <!-- Başlık ve Aksiyonlar -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Destek Talepleri</h1>
        <a href="<?php echo BASE_URL; ?>admin/support/categories.php" class="btn btn-sm btn-outline-secondary shadow-sm">
            <i class="fas fa-tags me-1"></i> Kategorileri Yönet
        </a>
    </div>

    <!-- İstatistik Kartları -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Toplam Talep</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_count'] ?? 0; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-ticket-alt fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Açık (Yeni)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['open_count'] ?? 0; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-envelope-open fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Aktif (İşlemde)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['active_count'] ?? 0; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-spinner fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Çözülen</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['closed_count'] ?? 0; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-check-circle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtreler ve Liste -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Aktif Talepler</option>
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Tüm Talepler</option>
                        <option value="open" <?php echo $filter_status === 'open' ? 'selected' : ''; ?>>Sadece Açık</option>
                        <option value="closed" <?php echo $filter_status === 'closed' ? 'selected' : ''; ?>>Kapalı/Çözülen</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="priority" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Tüm Öncelikler</option>
                        <option value="high" <?php echo $filter_priority === 'high' ? 'selected' : ''; ?>>Yüksek</option>
                        <option value="medium" <?php echo $filter_priority === 'medium' ? 'selected' : ''; ?>>Orta</option>
                        <option value="low" <?php echo $filter_priority === 'low' ? 'selected' : ''; ?>>Düşük</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" class="form-control" placeholder="Talep No, Konu veya Müşteri ara..." value="<?php echo htmlspecialchars($filter_search); ?>">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </div>
                <?php if($filter_status !== 'active' || !empty($filter_search) || !empty($filter_priority)): ?>
                <div class="col-md-2 text-end">
                    <a href="index.php" class="btn btn-sm btn-link text-danger text-decoration-none"><i class="fas fa-times me-1"></i>Temizle</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Talep No</th>
                            <th>Konu</th>
                            <th>Müşteri</th>
                            <th>Durum</th>
                            <th>Öncelik</th>
                            <th>Son İşlem</th>
                            <th class="text-end pe-4">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tickets)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">Kriterlere uygun destek talebi bulunamadı.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary">
                                    <a href="ticket.php?id=<?php echo $ticket['id']; ?>" class="text-decoration-none">
                                        #<?php echo htmlspecialchars($ticket['ticket_number']); ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars(mb_strimwidth($ticket['subject'], 0, 50, '...')); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($ticket['category']); ?></small>
                                </td>
                                <td>
                                    <div class="text-dark fw-bold"><?php echo htmlspecialchars($ticket['bakery_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></small>
                                </td>
                                <td>
                                    <?php
                                        $status_badges = [
                                            'open' => ['bg-danger', 'Açık'],
                                            'in_progress' => ['bg-primary', 'İşlemde'],
                                            'waiting' => ['bg-warning text-dark', 'Yanıt Bekliyor'],
                                            'resolved' => ['bg-success', 'Çözüldü'],
                                            'closed' => ['bg-secondary', 'Kapatıldı']
                                        ];
                                        $badge = $status_badges[$ticket['status']] ?? ['bg-secondary', $ticket['status']];
                                    ?>
                                    <span class="badge <?php echo $badge[0]; ?>"><?php echo $badge[1]; ?></span>
                                </td>
                                <td>
                                    <?php if ($ticket['priority'] === 'high'): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger">Yüksek</span>
                                    <?php elseif ($ticket['priority'] === 'medium'): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning">Orta</span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success border border-success">Düşük</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small text-dark"><?php echo date('d.m.Y H:i', strtotime($ticket['last_reply_at'] ?? $ticket['created_at'])); ?></div>
                                    <small class="text-muted"><?php echo $ticket['message_count']; ?> mesaj</small>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-primary shadow-sm">
                                        <i class="fas fa-eye me-1"></i> Detay
                                    </a>
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

<?php include_once ROOT_PATH . '/admin/footer.php'; ?>