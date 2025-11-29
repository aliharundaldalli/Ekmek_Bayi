<?php
/**
 * My Account - Create New Support Ticket
 *
 * This script handles the creation of new support tickets by logged-in users.
 * It validates user input, processes file attachments, interacts with the database,
 * and sends email notifications.
 * 
 * @version 2.0
 */

// Define BASE_URL and ROOT_PATH if not already defined
// Example definitions (replace with your actual paths/URLs):
// define('ROOT_PATH', '/path/to/your/project/root');
// define('BASE_URL', 'http://yourdomain.com');

// Assume init.php defines: $pdo (PDO connection), $settings (array), functions like isLoggedIn(),
// isAdmin(), redirect(), sendEmail(), generateCsrfToken(), validateCsrfToken(), BASE_URL, ROOT_PATH.
// Also assume session_start() is called in init.php.
require_once '../../init.php'; // Adjust path as needed

// --- Email Templates ---
/**
 * Generates an HTML email template for ticket creation notifications to user
 * 
 * @param string $ticket_number The ticket reference number
 * @param string $subject The ticket subject
 * @param string $category The ticket category
 * @param string $priority The ticket priority
 * @param array $user_info User information array (first_name, last_name)
 * @param string $ticket_view_url URL to view the ticket
 * @param string $site_title Website title
 * @return string HTML formatted email content
 */
function createTicketUserEmailTemplate($ticket_number, $subject, $category, $priority, $user_info, $ticket_view_url, $site_title) {
    $priority_text = [
        'low' => 'Düşük',
        'medium' => 'Orta',
        'high' => 'Yüksek'
    ][$priority] ?? ucfirst($priority);
    
    $user_full_name = htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']);
    
    $content = '
    <p>Merhaba ' . $user_full_name . ',</p>
    <p><strong>' . htmlspecialchars($ticket_number) . '</strong> numaralı destek talebiniz başarıyla oluşturulmuştur.</p>
    
    <div class="info-box">
        <p style="margin: 5px 0;"><strong>Konu:</strong> ' . htmlspecialchars($subject) . '</p>
        <p style="margin: 5px 0;"><strong>Kategori:</strong> ' . htmlspecialchars($category) . '</p>
        <p style="margin: 5px 0;"><strong>Öncelik:</strong> ' . htmlspecialchars($priority_text) . '</p>
        <p style="margin: 5px 0;"><strong>Durum:</strong> <span style="color: #4e73df; font-weight: bold;">Açık</span></p>
    </div>
    
    <p>Ekibimiz en kısa sürede talebinizi inceleyecek ve size yanıt verecektir.</p>
    <p>Talebinizi görüntülemek ve takip etmek için aşağıdaki butonu kullanabilirsiniz.</p>';
    
    return getStandardEmailTemplate('Destek Talebiniz Oluşturuldu', $content, 'Destek Talebini Görüntüle', $ticket_view_url);
}

function createTicketAdminEmailTemplate($ticket_number, $subject, $category, $priority, $message, $user_info, $admin_ticket_url, $site_title) {
    $priority_text = [
        'low' => 'Düşük',
        'medium' => 'Orta',
        'high' => 'Yüksek'
    ][$priority] ?? ucfirst($priority);
    
    $priority_color = [
        'low' => '#1cc88a',
        'medium' => '#f6c23e',
        'high' => '#e74a3b'
    ][$priority] ?? '#858796';
    
    $user_full_name = htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']);
    
    $content = '
    <p><strong>' . $user_full_name . '</strong> tarafından yeni bir destek talebi oluşturuldu.</p>
    
    <div class="info-box">
        <p style="margin: 5px 0;"><strong>Talep No:</strong> ' . htmlspecialchars($ticket_number) . '</p>
        <p style="margin: 5px 0;"><strong>Konu:</strong> ' . htmlspecialchars($subject) . '</p>
        <p style="margin: 5px 0;"><strong>Kategori:</strong> ' . htmlspecialchars($category) . '</p>
        <p style="margin: 5px 0;"><strong>Öncelik:</strong> <span style="color: ' . $priority_color . '; font-weight: bold;">' . htmlspecialchars($priority_text) . '</span></p>
        <p style="margin: 5px 0;"><strong>Kullanıcı:</strong> ' . $user_full_name . ' (' . htmlspecialchars($user_info['email']) . ')</p>
    </div>
    
    <div style="background-color: #f8f9fc; padding: 15px; border-radius: 5px; border-left: 4px solid #4e73df; margin-bottom: 20px;">
        <p style="margin-top: 0; font-weight: bold; color: #4e73df;">Mesaj İçeriği:</p>
        <p style="margin-bottom: 0;">' . nl2br(htmlspecialchars($message)) . '</p>
    </div>
    
    <p>Destek talebini görüntülemek ve yanıtlamak için aşağıdaki butonu kullanabilirsiniz.</p>';
    
    return getStandardEmailTemplate('Yeni Destek Talebi', $content, 'Destek Talebini Görüntüle', $admin_ticket_url);
}

/**
 * Function to convert HTML email to plain text
 * This function is useful for email clients that prefer or require plain text
 * 
 * @param string $html HTML content
 * @return string Plain text version
 */
function createPlainTextEmail($html) {
    // Remove HTML tags
    $text = strip_tags($html);
    
    // Replace HTML entities
    $text = str_replace('&nbsp;', ' ', $text);
    $text = html_entity_decode($text);
    
    // Clean up whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    $text = preg_replace('/\s*\n\s*/', "\n", $text);
    $text = preg_replace('/\s*\n\n\s*/', "\n\n", $text);
    
    // Additional replacements to improve readability
    $text = str_replace(' ,', ',', $text);
    $text = str_replace(' .', '.', $text);
    
    return trim($text);
}

/**
 * Checks if SMTP is properly configured and available
 * 
 * @param array $settings Site settings array
 * @return bool True if SMTP is available, false otherwise
 */
function isSmtpAvailable($settings) {
    // First try with standard settings
    if (!empty($settings['smtp_status']) && $settings['smtp_status'] == 1 && 
        !empty($settings['smtp_host']) && !empty($settings['smtp_username'])) {
        return true;
    }
    
    // If smtp_settings table exists in database, check it too
    global $pdo;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'smtp_settings'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("SELECT * FROM smtp_settings WHERE status = 1 LIMIT 1");
            if ($stmt->rowCount() > 0) {
                return true;
            }
        }
    } catch (PDOException $e) {
        error_log("SMTP settings check error: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Gets SMTP settings from either site_settings or smtp_settings table
 * 
 * @return array|null SMTP settings or null if not found
 */
function getSmtpSettingsForEmail() {
    global $pdo, $settings;
    
    // First check if we have a dedicated smtp_settings table
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'smtp_settings'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("SELECT * FROM smtp_settings WHERE status = 1 LIMIT 1");
            $smtp_settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($smtp_settings) {
                // Convert to the format expected by sendEmail
                return [
                    'smtp_status' => $smtp_settings['status'],
                    'smtp_host' => $smtp_settings['host'],
                    'smtp_port' => $smtp_settings['port'],
                    'smtp_username' => $smtp_settings['username'],
                    'smtp_password' => $smtp_settings['password'],
                    'smtp_encryption' => $smtp_settings['encryption'],
                    'smtp_from_email' => $smtp_settings['from_email'],
                    'smtp_from_name' => $smtp_settings['from_name']
                ];
            }
        }
    } catch (PDOException $e) {
        error_log("SMTP settings fetch error: " . $e->getMessage());
    }
    
    // Fallback to regular settings
    return $settings;
}

// --- Authentication & Authorization ---
if (!isLoggedIn()) {
    // Redirect non-logged-in users to login page
    redirect(BASE_URL . '/login.php');
    exit; // Stop script execution after redirect
}

if (isAdmin()) {
    // Redirect administrators away, they should use the admin panel
    $_SESSION['warning'] = "Yöneticiler bu sayfadan destek talebi oluşturamaz.";
    redirect(BASE_URL . '/admin/support/index.php');
    exit; // Stop script execution after redirect
}

// Set default timezone (Important for NOW() in SQL and date functions)
date_default_timezone_set($settings['timezone'] ?? 'Europe/Istanbul'); // Use setting or default

// --- Page Setup ---
$page_title = 'Yeni Destek Talebi';
$current_page = 'support'; // For navigation highlighting
$user_id = $_SESSION['user_id']; // Assumes user ID is stored in session

// --- Initialize Variables ---
$user_info = null;
$categories = [];
$form_data = $_POST; // Persist form data on error
$errors = []; // Store validation errors
$uploaded_files_info = []; // Store info about successfully validated uploads

// Priority options definition (can be moved to settings/config if needed)
$priority_options = [
    'low' => ['text' => 'Düşük', 'desc' => 'Genel sorular, öneriler', 'class' => 'btn-outline-success', 'icon' => 'fa-arrow-down'],
    'medium' => ['text' => 'Orta', 'desc' => 'Önemli sorunlar, işinizi kısmen etkileyen durumlar', 'class' => 'btn-outline-warning', 'icon' => 'fa-minus'],
    'high' => ['text' => 'Yüksek', 'desc' => 'Acil sorunlar, işinizi ciddi şekilde etkileyen durumlar', 'class' => 'btn-outline-danger', 'icon' => 'fa-exclamation']
];

// Attachment settings (can be moved to settings/config)
$max_total_files = 5;
$max_file_size = 5 * 1024 * 1024; // 5MB in bytes
$allowed_mime_types = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/pdf',
    'application/msword', // .doc
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
    'text/plain' // .txt
];
// Define the upload directory (relative to ROOT_PATH, ensure it's writable)
$upload_dir = ROOT_PATH . '/uploads/support_attachments/';
if (!is_dir($upload_dir)) {
    // Attempt to create directory if it doesn't exist
    if (!mkdir($upload_dir, 0755, true)) {
        error_log("Failed to create attachment upload directory: " . $upload_dir);
        // Set a warning, but allow ticket creation without attachments
        $_SESSION['warning'] = ($_SESSION['warning'] ?? '') . " Dosya yükleme klasörü erişilebilir değil. Ekler kaydedilemeyebilir.";
    }
}


// --- Fetch User Details ---
try {
    $stmt_user = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = :user_id");
    $stmt_user->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_user->execute();
    $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        // This should ideally not happen for a logged-in user
        throw new Exception("Giriş yapmış kullanıcı detayı bulunamadı (ID: {$user_id}).");
    }
} catch (PDOException | Exception $e) {
    error_log("Kullanıcı detayı alınamadı (ID: {$user_id}): " . $e->getMessage());
    $_SESSION['error'] = "Hesap bilgileriniz yüklenirken bir sorun oluştu. Lütfen daha sonra tekrar deneyin.";
    redirect(BASE_URL . '/my/support/index.php'); // Redirect back to support list
    exit;
}

// --- Fetch Active Support Categories ---
try {
    // Fetch only active categories
    $stmt_cat = $pdo->query("SELECT id, name FROM support_categories WHERE is_active = 1 ORDER BY name ASC");
    $categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
    if (empty($categories)) {
         $_SESSION['warning'] = ($_SESSION['warning'] ?? '') . " Aktif destek kategorisi bulunamadı.";
    }
} catch (PDOException $e) {
    error_log("Destek kategorileri sorgu hatası (Create Ticket): " . $e->getMessage());
    // Display a warning but allow the form to load (user can't select category)
    $_SESSION['warning'] = ($_SESSION['warning'] ?? '') . " Destek kategorileri yüklenemedi. Lütfen yönetici ile iletişime geçin.";
}


// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Basic CSRF Check ---
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['form'] = "Güvenlik hatası: Geçersiz form gönderimi (CSRF). Lütfen sayfayı yenileyip tekrar deneyin.";
    }

    // Proceed only if CSRF is valid (or check is disabled)
    if (empty($errors['form'])) {

        // --- Get and Trim Inputs ---
        $subject = trim($_POST['subject'] ?? '');
        $category_name = trim($_POST['category'] ?? ''); // Store category name directly
        $priority = trim($_POST['priority'] ?? ''); // Default will be handled later if empty
        $message = trim($_POST['message'] ?? '');

        // --- Input Validations ---
        if (empty($subject)) {
            $errors['subject'] = "Lütfen bir konu belirtin.";
        } elseif (mb_strlen($subject) > 100) { // Use mb_strlen for multi-byte characters
            $errors['subject'] = "Konu en fazla 100 karakter olabilir.";
        }

        if (empty($category_name)) {
            $errors['category'] = "Lütfen bir kategori seçin.";
        } else {
            // Validate if the selected category exists and is active (optional but good practice)
            $category_valid = false;
            foreach ($categories as $cat) {
                if ($cat['name'] === $category_name) {
                    $category_valid = true;
                    break;
                }
            }
            if (!$category_valid) {
                $errors['category'] = "Geçersiz veya aktif olmayan bir kategori seçildi.";
                // Maybe unset $category_name to prevent insertion? Or handle in DB logic.
            }
        }

        if (empty($priority)) {
             $errors['priority'] = "Lütfen bir öncelik seviyesi seçin.";
        } elseif (!array_key_exists($priority, $priority_options)) {
            $errors['priority'] = "Geçersiz öncelik seviyesi seçildi.";
            $priority = 'medium'; // Reset to default on invalid input
            $form_data['priority'] = 'medium'; // Update form data for persistence
        }

        if (empty($message)) {
            $errors['message'] = "Lütfen mesajınızı yazın.";
        } // Add length validation if needed: elseif (mb_strlen($message) > 5000) { $errors['message'] = "..."; }


        // --- Attachment Validation ---
        $total_upload_size = 0;
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            $file_count = count($_FILES['attachments']['name']);

            if ($file_count > $max_total_files) {
                $errors['attachments'] = "En fazla {$max_total_files} dosya yükleyebilirsiniz. ({$file_count} dosya seçildi)";
            } else {
                for ($i = 0; $i < $file_count; $i++) {
                    $file_error = $_FILES['attachments']['error'][$i];
                    $file_name = $_FILES['attachments']['name'][$i];
                    $file_size = $_FILES['attachments']['size'][$i];
                    $file_tmp_name = $_FILES['attachments']['tmp_name'][$i];
                    $file_type = ''; // Will be determined using finfo

                    // Check for upload errors first
                    if ($file_error !== UPLOAD_ERR_OK) {
                        $upload_errors = [
                            UPLOAD_ERR_INI_SIZE => 'Dosya boyutu sunucu limitini aşıyor.',
                            UPLOAD_ERR_FORM_SIZE => 'Dosya boyutu form limitini aşıyor.',
                            UPLOAD_ERR_PARTIAL => 'Dosya kısmen yüklendi.',
                            UPLOAD_ERR_NO_FILE => 'Dosya yüklenmedi.',
                            UPLOAD_ERR_NO_TMP_DIR => 'Geçici klasör bulunamadı.',
                            UPLOAD_ERR_CANT_WRITE => 'Dosya diske yazılamadı.',
                            UPLOAD_ERR_EXTENSION => 'PHP eklentisi dosya yüklemesini durdurdu.',
                        ];
                        $errors['attachments'] = "Dosya yükleme hatası ({$file_name}): " . ($upload_errors[$file_error] ?? 'Bilinmeyen bir hata oluştu.');
                        break; // Stop validation on first file error
                    }

                    // Check file size
                    if ($file_size > $max_file_size) {
                        $errors['attachments'] = "Dosya boyutu çok büyük ({$file_name}): Maksimum " . ($max_file_size / 1024 / 1024) . "MB.";
                        break;
                    }
                    $total_upload_size += $file_size;

                    // Check MIME type using finfo for better security
                    if (function_exists('finfo_open')) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $file_type = finfo_file($finfo, $file_tmp_name);
                        finfo_close($finfo);
                    } else {
                        // Fallback to $_FILES['type'] - less reliable
                        $file_type = $_FILES['attachments']['type'][$i];
                         error_log("finfo_open function not available. Falling back to less reliable MIME type detection for file: " . $file_name);
                    }


                    if (!in_array($file_type, $allowed_mime_types, true)) {
                        $errors['attachments'] = "İzin verilmeyen dosya türü ({$file_name}): {$file_type}. İzin verilenler: JPG, PNG, GIF, PDF, DOC, DOCX, TXT.";
                        break;
                    }

                    // If file is valid so far, store its info for later processing
                    $uploaded_files_info[] = [
                        'original_name' => $file_name,
                        'tmp_name' => $file_tmp_name,
                        'size' => $file_size,
                        'mime_type' => $file_type
                    ];
                } // end for loop
            } // end else (file count check)
        } // end attachment check

        // --- Process if no validation errors ---
        if (empty($errors)) {
            $ticket_id = null;
            $message_id = null;
            $ticket_number = '';

            try {
                $pdo->beginTransaction();

                // --- Generate Unique Ticket Number ---
                $ticket_prefix = 'SUP-' . date('ymd') . '-'; // Shorter prefix
                $max_retries = 5; // Prevent infinite loop
                $ticket_number_generated = false;
                for ($i = 0; $i < $max_retries; $i++) {
                    $random_suffix = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 5); // 5 char random suffix
                    $ticket_number_candidate = $ticket_prefix . $random_suffix;

                    // Check if ticket number already exists
                    $stmt_check = $pdo->prepare("SELECT id FROM support_tickets WHERE ticket_number = :ticket_number");
                    $stmt_check->bindParam(':ticket_number', $ticket_number_candidate);
                    $stmt_check->execute();

                    if ($stmt_check->fetchColumn() === false) {
                        // Unique number found
                        $ticket_number = $ticket_number_candidate;
                        $ticket_number_generated = true;
                        break;
                    }
                }
                // Fallback if unique generation failed after retries
                if (!$ticket_number_generated) {
                    $ticket_number = $ticket_prefix . uniqid(); // Less ideal but ensures uniqueness
                     error_log("Failed to generate unique ticket number after {$max_retries} retries. Using uniqid fallback.");
                }


                // --- Create Ticket Record ---
                $stmt_ticket = $pdo->prepare(
                    "INSERT INTO support_tickets (ticket_number, user_id, subject, category, priority, status, created_at, updated_at)
                     VALUES (:ticket_number, :user_id, :subject, :category, :priority, 'open', NOW(), NOW())"
                );
                $stmt_ticket->bindParam(':ticket_number', $ticket_number);
                $stmt_ticket->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt_ticket->bindParam(':subject', $subject);
                $stmt_ticket->bindParam(':category', $category_name);
                $stmt_ticket->bindParam(':priority', $priority);
                $stmt_ticket->execute();
                $ticket_id = $pdo->lastInsertId();

                if (!$ticket_id) {
                    throw new Exception("Failed to insert ticket record into database.");
                }

                // --- Add First Message Record ---
                $stmt_message = $pdo->prepare(
                    "INSERT INTO support_messages (ticket_id, user_id, message, is_internal, created_at)
                     VALUES (:ticket_id, :user_id, :message, 0, NOW())"
                );
                $stmt_message->bindParam(':ticket_id', $ticket_id, PDO::PARAM_INT);
                $stmt_message->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt_message->bindParam(':message', $message);
                $stmt_message->execute();
                $message_id = $pdo->lastInsertId();

                if (!$message_id) {
                    throw new Exception("Failed to insert initial message record into database.");
                }

                // --- Add History Record ---
                $stmt_history = $pdo->prepare(
                    "INSERT INTO support_ticket_history (ticket_id, user_id, action, created_at)
                     VALUES (:ticket_id, :user_id, 'created', NOW())"
                );
                $stmt_history->bindParam(':ticket_id', $ticket_id, PDO::PARAM_INT);
                $stmt_history->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt_history->execute();


                // --- Process Valid Attachments ---
                $attachment_insert_count = 0;
                if (!empty($uploaded_files_info) && $message_id) {
                     // Assume support_attachments table structure: id, message_id, original_filename, stored_filename, file_path, mime_type, file_size, uploaded_at
                    $stmt_attach = $pdo->prepare(
                        "INSERT INTO support_attachments (message_id, file_name, file_path, file_type, file_size, created_at)
                         VALUES (:message_id, :file_name, :file_path, :file_type, :file_size, NOW())"
                    );

                    foreach ($uploaded_files_info as $file_info) {
                        // Generate a unique stored filename
                        $file_extension = pathinfo($file_info['original_name'], PATHINFO_EXTENSION);
                        $stored_filename = uniqid('attach_' . $message_id . '_', true) . '.' . $file_extension;
                        $destination_path = $upload_dir . $stored_filename;
                        // Store a relative path usable from the web root, adjust if needed
                        $relative_path = str_replace(ROOT_PATH, '', $upload_dir) . $stored_filename;
                         // Ensure leading slash if ROOT_PATH was the web root
                        if (strpos($relative_path, '/') !== 0) {
                            $relative_path = '/' . $relative_path;
                        }


                        // Move the uploaded file
                        if (move_uploaded_file($file_info['tmp_name'], $destination_path)) {
                            // Insert attachment record into database
                            $stmt_attach->bindParam(':message_id', $message_id, PDO::PARAM_INT);
                            $stmt_attach->bindParam(':file_name', $file_info['original_name']); // Store original name
                            $stmt_attach->bindParam(':file_path', $relative_path); // Store relative path
                            $stmt_attach->bindParam(':file_type', $file_info['mime_type']);
                            $stmt_attach->bindParam(':file_size', $file_info['size'], PDO::PARAM_INT);

                            if ($stmt_attach->execute()) {
                                $attachment_insert_count++;
                            } else {
                                // Log error, maybe delete the moved file?
                                $attach_errorInfo = $stmt_attach->errorInfo();
                                error_log("Failed to insert attachment record for file: {$file_info['original_name']} (Ticket: {$ticket_id}, Message: {$message_id}). DB Error: " . ($attach_errorInfo[2] ?? 'Unknown error'));
                                unlink($destination_path); // Clean up moved file if DB insert fails
                                // Optionally add a warning to the user
                                $_SESSION['warning'] = ($_SESSION['warning'] ?? '') . " Dosya '{$file_info['original_name']}' veritabanına kaydedilemedi.";
                            }
                        } else {
                            // Log error if move failed
                            error_log("Failed to move uploaded file: {$file_info['original_name']} to {$destination_path}");
                            $_SESSION['warning'] = ($_SESSION['warning'] ?? '') . " Dosya '{$file_info['original_name']}' sunucuya taşınamadı.";
                        }
                    } // end foreach attachment
                     error_log("[Ticket Create - ID:{$ticket_id}] Processed {$attachment_insert_count} attachments.");
                } // end if attachments exist


                // --- Everything OK - Commit Transaction ---
                $pdo->commit();
                error_log("[Ticket Create - ID:{$ticket_id}] Database transaction committed successfully."); // LOG: Commit OK

                // --- Send Email Notifications (After successful commit) ---
                // Find these lines in your create.php file (around line 590-600)

    // --- Send Email Notifications (After successful commit) ---
    // First check if email system is available
        $smtp_available = isSmtpAvailable($settings);
        error_log("[Ticket Create - ID:{$ticket_id}] SMTP Available: " . ($smtp_available ? 'Yes' : 'No'));
        
        if ($smtp_available && function_exists('sendEmail')) {
            $user_full_name = htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']);
            $user_email = $user_info['email'];
            $ticket_view_url = BASE_URL . '/my/support/view.php?id=' . $ticket_id;
            $admin_ticket_url = BASE_URL . '/admin/support/ticket.php?id=' . $ticket_id; // Admin view URL
            
            // DEFINE ADMIN EMAIL VARIABLE HERE - This was missing
            $admin_notify_email = $settings['support_notification_email'] ?? null;
            
            $site_title = htmlspecialchars($settings['site_title'] ?? 'Destek Sistemi');

        error_log("[Ticket Create - ID:{$ticket_id}] Preparing emails. User: {$user_email}, Admin Notify: {$admin_notify_email}"); // LOG: Email addresses
                    error_log("[Ticket Create - ID:{$ticket_id}] Preparing emails. User: {$user_email}, Admin Notify: {$admin_notify_email}"); // LOG: Email addresses

                    // --- Get email settings from dedicated table if exists ---
                    $email_settings = getSmtpSettingsForEmail();
                    
                    // --- Email to User ---
                    $subject_user = "Destek Talebiniz Oluşturuldu - Talep No: " . htmlspecialchars($ticket_number);
                    
                    // Use enhanced template for better user experience
                    $body_user_html = createTicketUserEmailTemplate(
                        $ticket_number, 
                        $subject, 
                        $category_name, 
                        $priority, 
                        $user_info, 
                        $ticket_view_url, 
                        $site_title
                    );
                    
                    // Create plain text version for email clients that prefer it
                    $plain_text = createPlainTextEmail($body_user_html);
                    
                    // Send email with either settings format
                    $send_user_status = sendEmail($user_email, $subject_user, $body_user_html, $plain_text, $email_settings);
                    error_log("[Ticket Create - ID:{$ticket_id}] User email send status: " . ($send_user_status ? 'Success' : 'FAILED'));

                    if (!$send_user_status && !isset($_SESSION['warning'])) { // Add warning only if no other warning exists
                        $_SESSION['warning'] = "Destek talebiniz oluşturuldu ancak onay e-postası gönderilemedi. Lütfen e-posta ayarlarını kontrol edin.";
                    }

                    // --- Email to Admin Users (FIXED VERSION) ---
                    // First try to get admin email from settings
                    $admin_notify_email = $settings['support_notification_email'] ?? null;

                    // If admin email is empty, get it from the first admin in the database
                    if (empty($admin_notify_email) || !filter_var($admin_notify_email, FILTER_VALIDATE_EMAIL)) {
                        try {
                            $stmt_admin = $pdo->query("SELECT email FROM users WHERE role = 'admin' AND status = 1 AND email IS NOT NULL LIMIT 1");
                            if ($stmt_admin && $admin_row = $stmt_admin->fetch(PDO::FETCH_ASSOC)) {
                                $admin_notify_email = $admin_row['email'];
                                error_log("[Ticket Create - ID:{$ticket_id}] Using admin email from database: {$admin_notify_email}");
                            } else {
                                // Hardcode a default if needed - replace with your admin email if this is still empty
                                $admin_notify_email = "matematikmku@gmail.com"; 
                                error_log("[Ticket Create - ID:{$ticket_id}] Using hardcoded admin email: {$admin_notify_email}");
                            }
                        } catch (PDOException $e) {
                            // If database query fails, use hardcoded admin email
                            $admin_notify_email = "matematikmku@gmail.com";
                            error_log("[Ticket Create - ID:{$ticket_id}] Database error, using hardcoded admin email: {$admin_notify_email}");
                        }
                    }

                    // Now we should have a valid admin email
                    if (!empty($admin_notify_email) && filter_var($admin_notify_email, FILTER_VALIDATE_EMAIL)) {
                        // Process admin notifications
                        try {
                            // Get all active admin users from the database
                            $stmt_admins = $pdo->query("
                                SELECT id, first_name, last_name, email 
                                FROM users 
                                WHERE role = 'admin' AND status = 1 AND email IS NOT NULL
                                ORDER BY id ASC
                            ");
                            $admin_users = $stmt_admins->fetchAll(PDO::FETCH_ASSOC);
                            
                            $admin_notification_count = 0;
                            
                            if (!empty($admin_users)) {
                                $subject_admin = "Yeni Destek Talebi: #" . htmlspecialchars($ticket_number) . " (" . htmlspecialchars($subject) . ")";
                                
                                // Use enhanced template for admin notifications
                                $body_admin_html = createTicketAdminEmailTemplate(
                                    $ticket_number,
                                    $subject,
                                    $category_name,
                                    $priority,
                                    $message,
                                    $user_info,
                                    $admin_ticket_url,
                                    $site_title
                                );
                                
                                // Create plain text version
                                $admin_plain_text = createPlainTextEmail($body_admin_html);
                                
                                // Send to each admin user
                                foreach ($admin_users as $admin) {
                                    if (!empty($admin['email']) && filter_var($admin['email'], FILTER_VALIDATE_EMAIL)) {
                                        $admin_name = $admin['first_name'] . ' ' . $admin['last_name'];
                                        $send_admin_status = sendEmail($admin['email'], $subject_admin, $body_admin_html, $admin_plain_text, $email_settings);
                                        
                                        if ($send_admin_status) {
                                            $admin_notification_count++;
                                            error_log("[Ticket Create - ID:{$ticket_id}] Admin notification sent to: {$admin_name} ({$admin['email']})");
                                        } else {
                                            error_log("[Ticket Create - ID:{$ticket_id}] Failed to send admin notification to: {$admin_name} ({$admin['email']})");
                                        }
                                    }
                                }
                                
                                if ($admin_notification_count > 0) {
                                    error_log("[Ticket Create - ID:{$ticket_id}] Admin notifications sent successfully to {$admin_notification_count} admin users.");
                                } else {
                                    error_log("[Ticket Create - ID:{$ticket_id}] No admin notifications were sent successfully. Trying fallback.");
                                    
                                    // Fallback to the single admin email
                                    $send_admin_status = sendEmail($admin_notify_email, $subject_admin, $body_admin_html, $admin_plain_text, $email_settings);
                                    error_log("[Ticket Create - ID:{$ticket_id}] Fallback admin notification to {$admin_notify_email}: " . ($send_admin_status ? 'Success' : 'FAILED'));
                                }
                            } else {
                                error_log("[Ticket Create - ID:{$ticket_id}] No active admin users found for notifications. Using fallback.");
                                
                                // Fallback to the single admin email if no admin users found
                                $subject_admin = "Yeni Destek Talebi: #" . htmlspecialchars($ticket_number) . " (" . htmlspecialchars($subject) . ")";
                                $body_admin_html = createTicketAdminEmailTemplate(
                                    $ticket_number,
                                    $subject,
                                    $category_name,
                                    $priority,
                                    $message,
                                    $user_info,
                                    $admin_ticket_url,
                                    $site_title
                                );
                                $admin_plain_text = createPlainTextEmail($body_admin_html);
                                
                                $send_admin_status = sendEmail($admin_notify_email, $subject_admin, $body_admin_html, $admin_plain_text, $email_settings);
                                error_log("[Ticket Create - ID:{$ticket_id}] Direct admin notification to {$admin_notify_email}: " . ($send_admin_status ? 'Success' : 'FAILED'));
                            }
                        } catch (Exception $e) {
                            error_log("[Ticket Create - ID:{$ticket_id}] Error sending admin notifications: " . $e->getMessage());
                        }
                    } else {
                        error_log("[Ticket Create - ID:{$ticket_id}] No valid admin email found for notifications. Admin notification skipped.");
                    }
                } else {
                    error_log("[Ticket Create - ID:{$ticket_id}] Email notifications skipped: SMTP disabled, settings missing, or sendEmail function unavailable.");
                    if (!isset($_SESSION['warning'])) {
                        $_SESSION['warning'] = "Destek talebiniz oluşturuldu ancak e-posta bildirim sistemi aktif değil veya yapılandırılmamış.";
                    }
                }

                // --- Redirect to View Ticket Page on Success ---
                $_SESSION['success'] = "Destek talebiniz başarıyla oluşturuldu. Talep numaranız: " . htmlspecialchars($ticket_number);
                redirect(BASE_URL . '/my/support/view.php?id=' . $ticket_id);
                exit; // Stop script execution

            } catch (PDOException $e) {
                // Rollback transaction on database error
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                // *** ENHANCED LOGGING FOR PDOException ***
                error_log("Ticket creation PDO error: [Code: " . $e->getCode() . "] " . $e->getMessage() . " - Trace: " . $e->getTraceAsString() . " - Data: " . print_r($form_data, true));
                $errors['database'] = "Destek talebi oluşturulurken bir veritabanı hatası oluştu. Lütfen tekrar deneyin veya yönetici ile iletişime geçin.";
            } catch (Exception $e) {
                // Rollback transaction on general error during DB operations
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                 // *** ENHANCED LOGGING FOR Exception ***
                error_log("Ticket creation general error: [Code: " . $e->getCode() . "] " . $e->getMessage() . " - Trace: " . $e->getTraceAsString() . " - Data: " . print_r($form_data, true));
                $errors['general'] = "Destek talebi oluşturulurken beklenmedik bir sistem hatası oluştu."; // Keep user message generic
            }
        } // End if empty($errors) validation check

    } // End CSRF Check else block

} // End POST request handling

// --- Include Header ---
// Include header AFTER processing POST and setting all necessary variables for the view
include_once ROOT_PATH . '/my/header.php'; // Adjust path as needed
?>

<div class="container py-4">
    <!-- Page Header -->
    <div class="bg-gradient-primary-to-secondary text-white p-4 mb-4 rounded-3 shadow-sm">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-0 fw-bold">Yeni Destek Talebi</h1>
                <p class="mb-0">Sorularınızı, sorunlarınızı veya taleplerinizi detaylı bir şekilde belirtin.</p>
            </div>
            <form action="<?php echo BASE_URL; ?>/my/support/create.php" method="post" enctype="multipart/form-data" id="createTicketForm">
                <?php $csrf_token = generateCSRFToken(); ?>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <a href="<?php echo BASE_URL; ?>/my/support/index.php" class="btn btn-light btn-sm px-3">
                    <i class="fas fa-arrow-left me-1"></i> Taleplerim
                </a>
            </form>
        </div>
    </div>
    
    <!-- Main Content Card -->
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-header bg-white py-3 border-bottom-0">
            <div class="d-flex align-items-center">
                <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3">
                    <i class="fas fa-headset text-primary fs-4"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 fw-bold">Destek Talebi Formu</h5>
                    <p class="text-muted small mb-0">Tüm alanları eksiksiz doldurun</p>
                </div>
            </div>
        </div>

        <div class="card-body p-4">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="fas fa-exclamation-circle fs-3"></i>
                        </div>
                        <div>
                            <h6 class="alert-heading fw-bold">Lütfen aşağıdaki hataları düzeltin:</h6>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $field => $errorMsg): ?>
                                    <li><?php echo htmlspecialchars($errorMsg); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="fas fa-times-circle fs-3"></i>
                        </div>
                        <div>
                            <strong>Hata!</strong> <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['warning'])): ?>
                <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="fas fa-exclamation-triangle fs-3"></i>
                        </div>
                        <div>
                            <strong>Uyarı!</strong> <?php echo $_SESSION['warning']; unset($_SESSION['warning']); /* Allow potential HTML in warning */ ?>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): /* Usually redirected, but just in case */ ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="fas fa-check-circle fs-3"></i>
                        </div>
                        <div>
                            <strong>Başarılı!</strong> <?php echo $_SESSION['success']; unset($_SESSION['success']); /* Allow potential HTML in success */ ?>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- User Information Card -->
            <div class="card bg-light border-0 mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <div class="avatar-circle bg-primary text-white">
                                <?php 
                                    $initials = mb_substr($user_info['first_name'], 0, 1) . mb_substr($user_info['last_name'], 0, 1);
                                    echo strtoupper($initials); 
                                ?>
                            </div>
                        </div>
                        <div>
                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']); ?></h6>
                            <p class="mb-0 text-muted small">
                                <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($user_info['email']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ticket Form -->
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data" class="needs-validation" novalidate>
                <?php $csrf_token = generateCSRFToken(); ?>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <label for="subject" class="form-label fw-semibold">
                            <i class="fas fa-tag text-primary me-1"></i> Konu <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               class="form-control form-control-lg <?php echo isset($errors['subject']) ? 'is-invalid' : ''; ?>"
                               id="subject"
                               name="subject"
                               value="<?php echo htmlspecialchars($form_data['subject'] ?? ''); ?>"
                               placeholder="Sorununuzu veya talebinizi kısaca tanımlayın"
                               required
                               maxlength="100">
                        <div class="form-text"><i class="fas fa-info-circle me-1"></i> Sorununuzu veya talebinizi kısaca özetleyin (En fazla 100 karakter).</div>
                        <?php if (isset($errors['subject'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['subject']; ?></div>
                        <?php else: ?>
                            <div class="invalid-feedback">Lütfen bir konu girin.</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label for="category" class="form-label fw-semibold">
                            <i class="fas fa-folder text-primary me-1"></i> Kategori <span class="text-danger">*</span>
                        </label>
                        <select class="form-select form-select-lg <?php echo isset($errors['category']) ? 'is-invalid' : ''; ?>"
                                id="category"
                                name="category"
                                required>
                            <option value="" disabled <?php echo empty($form_data['category']) ? 'selected' : ''; ?>>-- Kategori Seçin --</option>
                            <?php if (empty($categories)): ?>
                                <option value="" disabled>Kategori yüklenemedi</option>
                            <?php else: ?>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['name']); ?>"
                                            <?php echo (isset($form_data['category']) && $form_data['category'] == $category['name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php if (isset($errors['category'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['category']; ?></div>
                        <?php else: ?>
                             <div class="invalid-feedback">Lütfen bir kategori seçin.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold d-block">
                        <i class="fas fa-flag text-primary me-1"></i> Öncelik <span class="text-danger">*</span>
                    </label>
                    <div class="priority-options">
                        <?php
                        // Get selected priority from form data, default to 'medium' if not set or invalid
                        $selected_priority = $form_data['priority'] ?? 'medium';
                        if (!array_key_exists($selected_priority, $priority_options)) {
                            $selected_priority = 'medium'; // Ensure default if invalid value was somehow submitted
                        }

                        foreach ($priority_options as $value => $option):
                            $is_checked = ($selected_priority === $value);
                        ?>
                            <div class="priority-option">
                                <input type="radio"
                                       class="btn-check"
                                       name="priority"
                                       id="priority_<?php echo $value; ?>"
                                       value="<?php echo $value; ?>"
                                       autocomplete="off"
                                       <?php echo $is_checked ? 'checked' : ''; ?>
                                       required>
                                <label class="priority-card <?php echo $is_checked ? 'active' : ''; ?>" 
                                      for="priority_<?php echo $value; ?>"
                                      data-bs-toggle="tooltip"
                                      data-bs-placement="top"
                                      title="<?php echo htmlspecialchars($option['desc']); ?>">
                                    <div class="icon <?php echo str_replace('btn-outline-', 'text-', $option['class']); ?>">
                                        <i class="fas <?php echo $option['icon']; ?>"></i>
                                    </div>
                                    <div class="priority-text">
                                        <strong><?php echo htmlspecialchars($option['text']); ?></strong>
                                        <span class="d-block text-muted small"><?php echo htmlspecialchars($option['desc']); ?></span>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (isset($errors['priority'])): ?>
                        <div class="d-block invalid-feedback mt-2"><?php echo $errors['priority']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-4">
                    <label for="message" class="form-label fw-semibold">
                        <i class="fas fa-comment-alt text-primary me-1"></i> Mesajınız <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control <?php echo isset($errors['message']) ? 'is-invalid' : ''; ?>"
                              id="message"
                              name="message"
                              rows="10"
                              placeholder="Lütfen sorununuzu veya talebinizi detaylı bir şekilde açıklayın..."
                              required><?php echo htmlspecialchars($form_data['message'] ?? ''); ?></textarea>
                    <div class="form-text">
                        <i class="fas fa-lightbulb me-1"></i> İpucu: Sorununuza dair ne kadar çok detay verirseniz, size o kadar hızlı ve etkili yardım sunabiliriz.
                    </div>
                     <?php if (isset($errors['message'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['message']; ?></div>
                     <?php else: ?>
                        <div class="invalid-feedback">Lütfen mesajınızı girin.</div>
                     <?php endif; ?>
                </div>

                <div class="mb-4">
                    <label for="attachments" class="form-label fw-semibold">
                        <i class="fas fa-paperclip text-primary me-1"></i> Dosya Ekle (Opsiyonel)
                    </label>
                    <div class="custom-file-upload">
                        <input type="file"
                               class="form-control <?php echo isset($errors['attachments']) ? 'is-invalid' : ''; ?>"
                               id="attachments"
                               name="attachments[]"
                               multiple
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt">
                        <div class="dragndrop-area text-center p-4">
                            <i class="fas fa-cloud-upload-alt fs-3 mb-2 text-primary"></i>
                            <p class="mb-1">Dosyaları sürükleyip bırakın veya seçmek için tıklayın</p>
                            <p class="small text-muted mb-0">
                                Maksimum <?php echo $max_total_files; ?> dosya, her biri en fazla <?php echo ($max_file_size / 1024 / 1024); ?>MB olabilir.
                            </p>
                        </div>
                    </div>
                    <div class="form-text">
                        <i class="fas fa-info-circle me-1"></i> İzin verilen dosya türleri: Resim (JPG, PNG, GIF), PDF, Word (DOC, DOCX), Metin (TXT).
                    </div>
                    <div id="file-list" class="mt-2"></div>
                    <?php if (isset($errors['attachments'])): ?>
                        <div class="d-block invalid-feedback"><?php echo $errors['attachments']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-between border-top pt-4 mt-4">
                    <a href="<?php echo BASE_URL; ?>/my/support/index.php" class="btn btn-light px-4">
                        <i class="fas fa-times me-1"></i> İptal
                    </a>
                    <button type="submit" class="btn btn-primary px-5">
                        <i class="fas fa-paper-plane me-1"></i> Destek Talebi Gönder
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Support Tips Card -->
    <div class="card border-0 bg-light rounded-3 shadow-sm">
        <div class="card-body p-4">
            <div class="d-flex mb-3">
                <div class="me-3">
                    <div class="bg-info bg-opacity-10 p-2 rounded-circle">
                        <i class="fas fa-lightbulb text-info"></i>
                    </div>
                </div>
                <div>
                    <h5 class="card-title fw-bold">Etkili Destek Talebi İpuçları</h5>
                    <p class="card-text text-muted small">Daha hızlı çözüm için bu önerileri dikkate alın</p>
                </div>
            </div>
            
           <div class="row g-4">
  <div class="col-md-6">
    <div class="card h-100 shadow-sm border-light">
      <div class="card-body d-flex align-items-start">
        <div class="me-3 flex-shrink-0">
          <i class="fas fa-info-circle fa-2x text-success"></i>
          </div>
        <div>
          <h6 class="fw-bold mb-1">Detaylı Bilgi Verin</h6>
          <p class="small text-muted mb-0">Sorununuzu adım adım açıklayın. Hangi işlemi yaparken, ne zaman ve nasıl oluştuğunu net bir şekilde belirtin.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card h-100 shadow-sm border-light">
      <div class="card-body d-flex align-items-start">
        <div class="me-3 flex-shrink-0">
          <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
          </div>
        <div>
          <h6 class="fw-bold mb-1">Hata Mesajlarını Paylaşın</h6>
          <p class="small text-muted mb-0">Karşılaştığınız hata mesajlarını tam olarak kopyalayıp yapıştırın veya ilgili ekran görüntülerini ekleyin.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card h-100 shadow-sm border-light">
      <div class="card-body d-flex align-items-start">
        <div class="me-3 flex-shrink-0">
          <i class="fas fa-redo-alt fa-2x text-primary"></i>
          </div>
        <div>
          <h6 class="fw-bold mb-1">Sorunu Tekrarlama Adımları</h6>
          <p class="small text-muted mb-0">Hatayı yeniden oluşturmak için izlediğiniz adımları madde madde ve anlaşılır bir şekilde sıralayın.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card h-100 shadow-sm border-light">
      <div class="card-body d-flex align-items-start">
        <div class="me-3 flex-shrink-0">
          <i class="fas fa-exclamation-circle fa-2x text-warning"></i>
          </div>
        <div>
          <h6 class="fw-bold mb-1">Öncelik Seviyesini Belirtin</h6>
          <p class="small text-muted mb-0">Sorunun aciliyetini belirtin. İş akışınızı tamamen engelliyorsa yüksek, değilse uygun seviyeyi seçin.</p>
        </div>
      </div>
    </div>
  </div>
</div>
        </div>
    </div>
</div>

<style>
/* Custom Styles for Enhanced Form UI */
.bg-gradient-primary-to-secondary {
    background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%);
}

.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.priority-options {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 10px;
}

.priority-option {
    flex: 1;
    min-width: 180px;
}

.priority-card {
    display: flex;
    align-items: center;
    padding: 15px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    transition: all 0.2s ease;
    cursor: pointer;
    background-color: white;
    height: 100%;
}

.priority-card:hover {
    border-color: #0d6efd;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.priority-card.active {
    border-color: #0d6efd;
    background-color: #f0f7ff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.priority-card .icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: rgba(13, 110, 253, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.priority-card .text-success {
    background-color: rgba(25, 135, 84, 0.1);
}

.priority-card .text-warning {
    background-color: rgba(255, 193, 7, 0.1);
}

.priority-card .text-danger {
    background-color: rgba(220, 53, 69, 0.1);
}

.priority-text {
    flex: 1;
}

.custom-file-upload {
    position: relative;
}

.custom-file-upload input[type="file"] {
    position: absolute;
    height: 100%;
    width: 100%;
    opacity: 0;
    cursor: pointer;
    z-index: 2;
}

.dragndrop-area {
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    background-color: #f8f9fa;
    transition: all 0.2s ease;
}

.custom-file-upload:hover .dragndrop-area {
    border-color: #0d6efd;
    background-color: #f0f7ff;
}

.form-control:focus, .form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.required:after {
    content: " *";
    color: #dc3545;
}

/* Custom styles for file list display */
#file-list ul {
    background-color: #f8f9fa;
    border-radius: 6px;
    padding: 10px 15px;
}

#file-list li {
    padding: 6px 0;
    border-bottom: 1px solid #eee;
}

#file-list li:last-child {
    border-bottom: none;
}
</style>

<?php
// --- Include Footer ---
include_once ROOT_PATH . '/my/footer.php'; // Adjust path as needed
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Initialize Bootstrap Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // 2. Bootstrap Form Validation (Standard Script)
    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    var forms = document.querySelectorAll('.needs-validation');
    // Loop over them and prevent submission
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });

    // 3. Custom File Input Enhancement
    const fileInput = document.getElementById('attachments');
    const fileListDiv = document.getElementById('file-list');
    const dragndropArea = document.querySelector('.dragndrop-area');

    if (fileInput && fileListDiv && dragndropArea) {
        // Handle drag and drop visuals
        ['dragenter', 'dragover'].forEach(eventName => {
            dragndropArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dragndropArea.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            e.preventDefault();
            dragndropArea.classList.add('bg-light');
            dragndropArea.style.borderColor = '#0d6efd';
        }

        function unhighlight(e) {
            e.preventDefault();
            dragndropArea.classList.remove('bg-light');
            dragndropArea.style.borderColor = '#dee2e6';
        }

        // Handle file selection
        fileInput.addEventListener('change', function(event) {
            fileListDiv.innerHTML = ''; // Clear previous list
            const files = event.target.files;

            if (files.length > 0) {
                const list = document.createElement('ul');
                list.classList.add('list-unstyled', 'mb-0', 'mt-3', 'file-preview-list');

                // Display max files message if needed
                const maxFiles = <?php echo $max_total_files; ?>;
                if (files.length > maxFiles) {
                    const errorItem = document.createElement('div');
                    errorItem.classList.add('alert', 'alert-danger', 'py-2', 'small', 'mt-2');
                    errorItem.innerHTML = `<i class="fas fa-exclamation-triangle me-1"></i> En fazla ${maxFiles} dosya seçebilirsiniz (${files.length} seçildi).`;
                    fileListDiv.appendChild(errorItem);
                }

                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    const listItem = document.createElement('li');
                    listItem.classList.add('text-truncate', 'd-flex', 'align-items-center'); 
                    
                    // Get appropriate icon based on file type
                    let fileIcon = 'fa-file';
                    if (file.type.includes('image')) fileIcon = 'fa-file-image';
                    else if (file.type.includes('pdf')) fileIcon = 'fa-file-pdf';
                    else if (file.type.includes('word')) fileIcon = 'fa-file-word';
                    else if (file.type.includes('text')) fileIcon = 'fa-file-alt';
                    
                    listItem.innerHTML = `
                        <i class="fas ${fileIcon} me-2 text-primary"></i>
                        <span class="text-truncate">${escapeHtml(file.name)}</span>
                        <span class="ms-2 badge bg-light text-dark">${formatFileSize(file.size)}</span>
                    `;
                    list.appendChild(listItem);
                    
                    if (i >= maxFiles - 1 && files.length > maxFiles) {
                        // Stop listing files if limit exceeded, already shown error
                        break;
                    }
                }
                fileListDiv.appendChild(list);
            }
        });
    }

    // 4. Priority Selection Enhancement
    const priorityOptions = document.querySelectorAll('.priority-card');
    priorityOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Update active state visually
            priorityOptions.forEach(opt => opt.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Helper functions
    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        else if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        else return (bytes / 1048576).toFixed(2) + ' MB';
    }
});
</script>