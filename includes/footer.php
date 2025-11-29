</main> 
        <footer class="bg-dark text-white py-4 mt-auto"> 
            <div class="container">
                <div class="row">
                    <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                        <h5 class="mb-1"><?php echo htmlspecialchars($settings['site_title'] ?? 'Site Başlığı'); ?></h5>
                        <p class="small text-white-50 mb-0"><?php echo htmlspecialchars($settings['site_description'] ?? 'Site açıklaması.'); ?></p>
                    </div>
                    <div class="col-md-6 text-center text-md-end">
                        <?php if(!empty($settings['contact_email'])): ?>
                            <p class="mb-1 small"><i class="fas fa-envelope fa-fw me-2"></i> <?php echo htmlspecialchars($settings['contact_email']); ?></p>
                        <?php endif; ?>
                        <?php if(!empty($settings['contact_phone'])): ?>
                            <p class="mb-1 small"><i class="fas fa-phone fa-fw me-2"></i> <?php echo htmlspecialchars($settings['contact_phone']); ?></p>
                        <?php endif; ?>
                        <p class="small text-white-50 mt-2 mb-0">&copy; <?php echo date('Y'); ?> - Tüm hakları saklıdır.</p>
                    </div>
                </div>
            </div>
        </footer>
    </div> 
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="<?php echo $base_url_trimmed; ?>/assets/js/main.js"></script>
    <script>
        $(document).ready(function() {
            // Genel (public) sayfalar için gerekli JS kodları buraya eklenebilir.
            // Örneğin, Bootstrap tooltip'lerini etkinleştirme:
            // var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            // var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            //   return new bootstrap.Tooltip(tooltipTriggerEl)
            // })
             // DataTables başlatma kodu burada olmamalı (eğer public sayfalarda tablo yoksa)
             // Eğer public sayfalarda da DataTables kullanılıyorsa, buraya eklenebilir.
             // Auto close alerts (header'dan gelenler için)
             window.setTimeout(function() {
                $(".alert").not('.alert-dismissible-none').fadeTo(500, 0).slideUp(500, function(){
                    $(this).alert('close');
                });
            }, 5000);
        });
    </script>
</body>
</html>