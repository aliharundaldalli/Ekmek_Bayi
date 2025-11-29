<?php
/**
 * Support Ticket Delete Script
 * 
 * This script handles the deletion of support tickets with proper permission checks,
 * database integrity, and security measures.
 * 
 * @version 1.0
 */

require_once '../../../init.php';

// --- Authentication & Authorization Checks ---
if (!isLoggedIn()) {
    $_SESSION['error'] = "Bu işlemi gerçekleştirmek için giriş yapmalısınız.";
    redirect(BASE_URL . '/login.php');
    exit;
}

// Get current user ID and role
$current_user_id = $_SESSION['user_id'];
$is_admin = isAdmin(); // Assuming isAdmin() function exists in your system

// Check for required parameters
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Geçersiz veya eksik talep ID'si.";
    
    // Redirect to appropriate page based on role
    if ($is_admin) {
        redirect(BASE_URL . '/admin/support/index.php');
    } else {
        redirect(BASE_URL . '/my/support/index.php');
    }
    exit;
}

$ticket_id = intval($_GET['id']);

// --- Get Ticket Details and Check Permissions ---
try {
    $stmt = $pdo->prepare("
        SELECT t.*, u.first_name, u.last_name, u.email
        FROM support_tickets t 
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        $_SESSION['error'] = "Belirtilen destek talebi bulunamadı.";
        if ($is_admin) {
            redirect(BASE_URL . '/admin/support/index.php');
        } else {
            redirect(BASE_URL . '/my/support/index.php');
        }
        exit;
    }

    // Check if user has permission to delete this ticket
    if (!$is_admin && $ticket['user_id'] != $current_user_id) {
        $_SESSION['error'] = "Bu destek talebini silme yetkiniz bulunmamaktadır.";
        redirect(BASE_URL . '/my/support/index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Ticket fetch error in delete.php: " . $e->getMessage());
    $_SESSION['error'] = "Destek talebi bilgileri alınırken bir hata oluştu.";
    if ($is_admin) {
        redirect(BASE_URL . '/admin/support/index.php');
    } else {
        redirect(BASE_URL . '/my/support/index.php');
    }
    exit;
}

// --- Handle deletion confirmation ---
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

// If not confirmed and not POST, show confirmation page
if (!$confirmed && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Include header and display confirmation page
    $page_title = 'Destek Talebi Sil';
    $current_page = 'support';
    include_once ROOT_PATH . '/my/header.php';
    ?>
    <div class="container py-4">
        <div class="bg-gradient-danger-to-warning text-white p-4 mb-4 rounded-3 shadow-sm">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0 fw-bold">Destek Talebi Sil</h1>
                    <p class="mb-0 opacity-75">Bu işlem geri alınamaz!</p>
                </div>
                <div>
                    <?php if ($is_admin): ?>
                    <a href="<?php echo BASE_URL; ?>/admin/support/index.php" class="btn btn-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Talep Listesine Dön
                    </a>
                    <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/my/support/index.php" class="btn btn-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Talep Listesine Dön
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="card border-danger shadow-sm">
                    <div class="card-header bg-danger text-white py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-exclamation-triangle me-2"></i> Silme İşlemini Onayla
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>Uyarı:</strong> Bu işlem geri alınamaz. Destek talebi ve ilişkili tüm mesajlar, ekler ve geçmiş kalıcı olarak silinecektir.
                        </div>

                        <div class="mt-4 mb-4">
                            <h6 class="fw-bold">Silinecek Destek Talebi Bilgileri:</h6>
                            <div class="table-responsive mt-3">
                                <table class="table table-bordered">
                                    <tr>
                                        <th class="bg-light" style="width: 30%;">Talep No</th>
                                        <td><?php echo htmlspecialchars($ticket['ticket_number']); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Konu</th>
                                        <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
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
                                                case 'open': $status_class = 'primary'; break;
                                                case 'in_progress': $status_class = 'info'; break;
                                                case 'waiting': $status_class = 'warning'; break;
                                                case 'resolved': $status_class = 'success'; break;
                                                case 'closed': $status_class = 'secondary'; break;
                                                default: $status_class = 'light';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $status_class; ?>">
                                                <?php echo htmlspecialchars(ucfirst($ticket['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Oluşturan</th>
                                        <td>
                                            <?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?>
                                            (<?php echo htmlspecialchars($ticket['email']); ?>)
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Oluşturulma Tarihi</th>
                                        <td><?php echo date('d.m.Y H:i', strtotime($ticket['created_at'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <form method="post" action="<?php echo BASE_URL; ?>/my/support/delete_ticket.php?id=<?php echo $ticket_id; ?>&confirm=yes">
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" name="confirm_deletion" id="confirm_deletion" required>
                                <label class="form-check-label" for="confirm_deletion">
                                    Bu destek talebini ve ilişkili tüm verileri kalıcı olarak silmek istediğimi onaylıyorum.
                                </label>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <?php if ($is_admin): ?>
                                <a href="<?php echo BASE_URL; ?>/admin/support/index.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i> İptal
                                </a>
                                <?php else: ?>
                                <a href="<?php echo BASE_URL; ?>/my/support/index.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i> İptal
                                </a>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-trash-alt me-1"></i> Kalıcı Olarak Sil
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    .bg-gradient-danger-to-warning {
        background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
    }
    </style>

    <?php
    include_once ROOT_PATH . '/my/footer.php';
    exit;
}

// Process deletion (either confirmed via GET or submitted via POST)
if ($confirmed || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_deletion']))) {
    try {
        $pdo->beginTransaction();

        // 1. Get all message IDs for this ticket first (needed for attachments)
        $stmt = $pdo->prepare("SELECT id FROM support_messages WHERE ticket_id = ?");
        $stmt->execute([$ticket_id]);
        $message_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 2. Delete attachments if any messages exists
        if (!empty($message_ids)) {
            // First, get the file paths to physically delete them
            $placeholders = str_repeat('?,', count($message_ids) - 1) . '?';
            $stmt = $pdo->prepare("SELECT file_path FROM support_attachments WHERE message_id IN ($placeholders)");
            $stmt->execute($message_ids);
            $attachments = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Delete physical files
            foreach ($attachments as $file_path) {
                $full_path = ROOT_PATH . '/' . ltrim($file_path, '/');
                if (file_exists($full_path)) {
                    @unlink($full_path); // Try to delete the file
                }
            }
            
            // Delete attachments records
            $stmt = $pdo->prepare("DELETE FROM support_attachments WHERE message_id IN ($placeholders)");
            $stmt->execute($message_ids);
        }

        // 3. Delete messages
        $stmt = $pdo->prepare("DELETE FROM support_messages WHERE ticket_id = ?");
        $stmt->execute([$ticket_id]);
        
        // 4. Delete history records
        $stmt = $pdo->prepare("DELETE FROM support_ticket_history WHERE ticket_id = ?");
        $stmt->execute([$ticket_id]);
        
        // 5. Finally, delete the ticket itself
        $stmt = $pdo->prepare("DELETE FROM support_tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);
        
        $pdo->commit();
        
        // Set success message
        $_SESSION['success'] = "Destek talebi (#" . htmlspecialchars($ticket['ticket_number']) . ") başarıyla silindi.";
        
        // Redirect based on user role
        if ($is_admin) {
            redirect(BASE_URL . '/admin/support/index.php');
        } else {
            redirect(BASE_URL . '/my/support/index.php');
        }
        exit;
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Ticket deletion error: " . $e->getMessage());
        $_SESSION['error'] = "Destek talebi silinirken bir hata oluştu: " . $e->getMessage();
        
        // Redirect based on user role
        if ($is_admin) {
            redirect(BASE_URL . '/admin/support/index.php');
        } else {
            redirect(BASE_URL . '/my/support/index.php');
        }
        exit;
    }
}

// If we reach here, something's wrong with the request
$_SESSION['error'] = "Geçersiz işlem.";
if ($is_admin) {
    redirect(BASE_URL . '/admin/support/index.php');
} else {
    redirect(BASE_URL . '/my/support/index.php');
}
exit;