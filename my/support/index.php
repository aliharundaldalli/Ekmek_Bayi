<?php
/**
 * Büfe Paneli - Destek Taleplerim
 * Modern ve profesyonel arayüz
 */

require_once '../../init.php';

// Oturum kontrolü
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    redirect(BASE_URL . 'login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$page_title = 'Destek Taleplerim';
$current_page = 'support';

// --- Veri Çekme ---
try {
    // İstatistikler
    $total_tickets = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE user_id = $user_id")->fetchColumn();
    $open_tickets = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE user_id = $user_id AND status IN ('open', 'in_progress', 'waiting')")->fetchColumn();
    $closed_tickets = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE user_id = $user_id AND status IN ('resolved', 'closed')")->fetchColumn();

    // Talepler
    $query = "
        SELECT id, ticket_number, subject, category, priority, status, created_at, updated_at
        FROM support_tickets
        WHERE user_id = ?
        ORDER BY updated_at DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Support List Error: " . $e->getMessage());
    $tickets = [];
    $total_tickets = $open_tickets = $closed_tickets = 0;
}

// Helper: Durum Badge
function getStatusBadge($status) {
    $badges = [
        'open' => ['class' => 'bg-warning text-dark', 'text' => 'Yeni', 'icon' => 'fa-star'],
        'in_progress' => ['class' => 'bg-info text-white', 'text' => 'İşlemde', 'icon' => 'fa-spinner fa-spin'],
        'waiting' => ['class' => 'bg-primary', 'text' => 'Yanıt Bekleniyor', 'icon' => 'fa-clock'],
        'resolved' => ['class' => 'bg-success', 'text' => 'Çözüldü', 'icon' => 'fa-check-circle'],
        'closed' => ['class' => 'bg-secondary', 'text' => 'Kapatıldı', 'icon' => 'fa-lock'],
    ];
    $b = $badges[$status] ?? ['class' => 'bg-light text-dark', 'text' => $status, 'icon' => 'fa-question'];
    return "<span class='badge {$b['class']}'><i class='fas {$b['icon']} me-1'></i> {$b['text']}</span>";
}

// Helper: Öncelik Badge
function getPriorityBadge($priority) {
    $badges = [
        'low' => ['class' => 'bg-success', 'text' => 'Düşük'],
        'medium' => ['class' => 'bg-warning text-dark', 'text' => 'Orta'],
        'high' => ['class' => 'bg-danger', 'text' => 'Yüksek'],
        'urgent' => ['class' => 'bg-danger', 'text' => 'Acil'],
    ];
    $b = $badges[$priority] ?? ['class' => 'bg-secondary', 'text' => $priority];
    return "<span class='badge {$b['class']}'>{$b['text']}</span>";
}

include_once ROOT_PATH . '/my/header.php';
?>

<div class="container-fluid">

    <!-- Başlık ve Buton -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Destek Taleplerim</h1>
        <a href="create.php" class="btn btn-primary shadow-sm">
            <i class="fas fa-plus-circle fa-sm text-white-50 me-2"></i> Yeni Talep Oluştur
        </a>
    </div>

    <!-- İstatistik Kartları -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Toplam Talep</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_tickets; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-ticket-alt fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Açık / İşlemde</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $open_tickets; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-folder-open fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Çözüldü / Kapalı</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $closed_tickets; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-check-circle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Talep Listesi -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Taleplerim</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Talep No</th>
                            <th>Konu</th>
                            <th>Kategori</th>
                            <th>Öncelik</th>
                            <th>Durum</th>
                            <th>Son Güncelleme</th>
                            <th class="text-end pe-4">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tickets)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">Henüz bir destek talebiniz bulunmuyor.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary">#<?php echo htmlspecialchars($ticket['ticket_number']); ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($ticket['subject']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($ticket['category']); ?></td>
                                <td><?php echo getPriorityBadge($ticket['priority']); ?></td>
                                <td><?php echo getStatusBadge($ticket['status']); ?></td>
                                <td class="small text-muted">
                                    <i class="far fa-clock me-1"></i>
                                    <?php echo date('d.m.Y H:i', strtotime($ticket['updated_at'])); ?>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="view.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-info text-white" title="Görüntüle">
                                        <i class="fas fa-eye"></i> Detay
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

<?php include_once ROOT_PATH . '/my/footer.php'; ?>
