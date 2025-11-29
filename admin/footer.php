<?php
/**
 * Admin Panel Footer (Dinamik Veri Kullanımı - $site_settings ile)
 */

// Header dosyasının bu dosyadan önce include edildiğini ve
// $site_settings dizisini (DB'den gelen verilerle) tanımladığını varsayıyoruz.

// Eğer $site_settings tanımlı değilse, boş bir dizi oluşturarak hataları önleyelim
if (!isset($site_settings) || !is_array($site_settings)) {
    $site_settings = [];
    // Opsiyonel: Hata loglama
    // error_log("Footer Warning: \$site_settings array is not available.");
}

// BASE_URL'nin tanımlı olduğunu varsayıyoruz
$base_url_footer = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

?>
                </div> <?php // col-lg-9 col-md-8 kapanışı ?>
            </div> <?php // row kapanışı ?>
        </div> <?php // container mt-4 flex-grow-1 kapanışı ?>

        <footer class="bg-dark text-white py-4 mt-auto"> <?php // mt-auto footer'ı aşağı iter ?>
            <div class="container">
                <div class="row">
                    <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                        <?php // Site başlığını $site_settings'den al ?>
                        <h5 class="mb-1"><?php echo htmlspecialchars($site_settings['site_title'] ?? 'Site Başlığı Yok'); ?></h5>
                        <?php // Site açıklamasını $site_settings'den al ?>
                        <p class="small text-white-50 mb-0"><?php echo htmlspecialchars($site_settings['site_description'] ?? 'Site açıklaması yok.'); ?></p>
                    </div>
                    <div class="col-md-6 text-center text-md-end">
                        <?php // İletişim e-postasını $site_settings'den al (varsa) ?>
                        <?php if(!empty($site_settings['contact_email'])): ?>
                            <p class="mb-1 small"><i class="fas fa-envelope fa-fw me-2"></i> <?php echo htmlspecialchars($site_settings['contact_email']); ?></p>
                        <?php endif; ?>
                        <?php // İletişim telefonunu $site_settings'den al (varsa) ?>
                        <?php if(!empty($site_settings['contact_phone'])): ?>
                            <p class="mb-1 small"><i class="fas fa-phone fa-fw me-2"></i> <?php echo htmlspecialchars($site_settings['contact_phone']); ?></p>
                        <?php endif; ?>
                        <p class="small text-white-50 mt-2 mb-0">&copy; <?php echo date('Y'); ?> - Tüm hakları saklıdır.</p>
                    </div>
                </div>
            </div>
        </footer>

    </div> <?php // d-flex flex-column min-vh-100 kapanışı ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <?php // Chart.js dahil edilmiş ?>
    <script src="<?php echo $base_url_footer; ?>/assets/js/main.js"></script>

    <script>
        // DataTables ve Alert Kapatma Scriptleri
        $(document).ready(function() {
            // DataTables başlatma (class='datatable' olanlar için)
            if (!$.fn.dataTable.isDataTable('.datatable')) {
                $('.datatable').DataTable({
                    "language": {
                         "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/tr.json"
                       // "url": "<?php echo $base_url_footer; ?>/assets/vendor/datatables/tr.json" // Yerel dil dosyası
                    },
                    "responsive": true,
                    "order": [] // Varsayılan sıralama yok
                });
            }

            // Alert'leri otomatik kapatma
             window.setTimeout(function() {
                 $(".alert").not('.alert-dismissible-none').fadeTo(500, 0).slideUp(500, function(){
                     $(this).alert('close');
                 });
             }, 5000); // 5 saniye
        });
    </script>
</body>
</html>