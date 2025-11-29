<?php
/**
 * Admin - Support Ticket Deletion Confirmation Page
 * 
 * This page provides a confirmation interface before permanently deleting a ticket
 * 
 * @version 1.0
 */

// --- Include init.php ---
require_once '../../init.php';

// --- Admin Check ---
require_once ROOT_PATH . '/admin/includes/admin_check.php'; // Ensure admin is logged in

// --- Page Setup ---
$page_title = 'Destek Talebi Sil - Onay';
$current_page = 'support';

// --- Check for Ticket ID ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Geçersiz destek talebi ID'si.";
    redirect(BASE_URL . '/admin/support/index.php');
    exit;
}

$ticket_id = intval($_GET['id']);
$ref_page = isset($_GET['ref']) && $_GET['ref'] === 'ticket' ? 'ticket' : 'index';

// --- Get Ticket Details ---
try {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               u.first_name, u.last_name, u.email, u.bakery_name, 
               (SELECT COUNT(*) FROM support_messages WHERE ticket_id = t.id) as message_count,
               (SELECT COUNT(*) FROM support_attachments sa 
                JOIN support_messages sm ON sa.message_id = sm.id 
                WHERE sm.ticket_id = t.id) as attachment_count
        FROM support_tickets t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        $_SESSION['error_message'] = "Belirtilen destek talebi bulunamadı.";
        redirect(BASE_URL . '/admin/support/index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Ticket fetch error for deletion: " . $e->getMessage());
    $_SESSION['error_message'] = "Destek talebi bilgileri alınırken bir hata oluştu.";
    redirect(BASE_URL . '/admin/support/index.php');
    exit;
}

// --- Include Header ---
include_once ROOT_PATH . '/admin/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Destek Talebi Silme Onayı</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <?php if ($ref_page === 'ticket'): ?>
            <a href="<?php echo BASE_URL; ?>/admin/support/ticket.php?id=<?php echo $ticket_id; ?>" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Talebe Dön
            </a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/admin/support/index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Listeye Dön
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-danger mb-4">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i> Dikkat: Destek Talebi Silinecek
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-warning">
                    <h6 class="alert-heading fw-bold"><i class="fas fa-exclamation-circle me-1"></i> Uyarı</h6>
                    <p>Bu işlem geri alınamaz. Silme işlemi gerçekleştikten sonra:</p>
                    <ul class="mb-0">
                        <li>Talep ve içerdiği tüm veriler kalıcı olarak silinecektir</li>
                        <li>Tüm mesajlar ve ekler kaybolacaktır</li>
                        <li>Talep geçmişi ve notlar tamamen silinecektir</li>
                        <li>Bu veriler geri getirilemez</li>
                    </ul>
                </div>
                
                <h6 class="mt-4 mb-3 fw-bold">Silinecek Destek Talebi Bilgileri:</h6>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tr>
                            <th style="width: 30%;" class="bg-light">Talep No</th>
                            <td class="fw-bold"><?php echo htmlspecialchars($ticket['ticket_number']); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Konu</th>
                            <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Müşteri</th>
                            <td>
                                <?php if (!empty($ticket['bakery_name'])): ?>
                                    <strong><?php echo htmlspecialchars($ticket['bakery_name']); ?></strong><br>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($ticket['email']); ?></small>
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light">Kategori</th>
                            <td><?php echo htmlspecialchars($ticket['category']); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Durum</th>
                            <td>
                                <?php 
                                $status_class = '';
                                switch($ticket['status']) {
                                    case 'open': $status_class = 'warning'; $status_text = 'Açık'; break;
                                    case 'in_progress': $status_class = 'primary'; $status_text = 'İşlemde'; break;
                                    case 'waiting': $status_class = 'info'; $status_text = 'Yanıt Bekliyor'; break;
                                    case 'resolved': $status_class = 'success'; $status_text = 'Çözüldü'; break;
                                    case 'closed': $status_class = 'secondary'; $status_text = 'Kapatıldı'; break;
                                    default: $status_class = 'light'; $status_text = ucfirst($ticket['status']);
                                }
                                ?>
                                <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light">İçerik</th>
                            <td>
                                <span class="badge bg-secondary me-2"><?php echo $ticket['message_count']; ?> Mesaj</span>
                                <span class="badge bg-info"><?php echo $ticket['attachment_count']; ?> Ek Dosya</span>
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light">Oluşturulma Tarihi</th>
                            <td><?php echo date('d.m.Y H:i', strtotime($ticket['created_at'])); ?></td>
                        </tr>
                    </table>
                </div>
                
                <div class="mt-4">
                    <div class="d-flex justify-content-between align-items-center border-top pt-4">
                        <?php if ($ref_page === 'ticket'): ?>
                            <a href="<?php echo BASE_URL; ?>/admin/support/ticket.php?id=<?php echo $ticket_id; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> İptal
                            </a>
                        <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>/admin/support/index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> İptal
                            </a>
                        <?php endif; ?>
                        
                        <a href="<?php echo BASE_URL; ?>/admin/support/actions/delete_ticket.php?id=<?php echo $ticket_id; ?>&confirm=yes&ref=<?php echo $ref_page; ?>" 
                           class="btn btn-danger" 
                           onclick="return confirm('Son onay: Bu destek talebini (#<?php echo htmlspecialchars($ticket['ticket_number']); ?>) ve içindeki tüm verileri KALICI OLARAK silmek istediğinize emin misiniz?');">
                            <i class="fas fa-trash-alt me-1"></i> Kalıcı Olarak Sil
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// --- Include Footer ---
include_once ROOT_PATH . '/admin/footer.php';
?>