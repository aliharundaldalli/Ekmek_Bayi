</div>
                </div>
            </div>

            <!-- Footer başlangıç -->
            <footer class="bg-primary text-white py-4 mt-4">
                <div class="container">
                    <div class="row">
                        <div class="col-md-6 text-center text-md-start">
                            <h5><?php echo htmlspecialchars($settings['site_title'] ?? 'Ekmek Sipariş Sistemi'); ?></h5>
                            <p class="small"><?php echo htmlspecialchars($settings['site_description'] ?? ''); ?></p>
                        </div>
                        <div class="col-md-6 text-center text-md-end">
                            <p class="mb-1"><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?></p>
                            <p class="mb-1"><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?></p>
                            <p class="small mt-3">&copy; <?php echo date('Y'); ?> - Tüm hakları saklıdır.</p>
                        </div>
                    </div>
                </div>
            </footer>
            <!-- Footer bitiş -->
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo rtrim(BASE_URL, '/'); ?>/assets/js/main.js"></script>
</body>
</html>