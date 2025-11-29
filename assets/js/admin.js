/**
 * Ekmek Sipariş Sistemi - Admin Panel JS
 */

// DOM yüklendikten sonra çalıştır
document.addEventListener('DOMContentLoaded', function() {
    // Admin dashboard grafikleri
    initDashboardCharts();
    
    // Sipariş durumu güncelleme
    initOrderStatusUpdate();
    
    // Kullanıcı durumu güncelleme
    initUserStatusUpdate();
    
    // Ekmek çeşidi durumu güncelleme
    initBreadStatusUpdate();
    
    // Filtreler
    initFilterDropdowns();
    
    // Arama kutuları
    initSearchBoxes();
    
    // Tarih filtreleri
    initDateRangePicker();
    
    // Stok hareketleri
    initInventoryMovements();
    
    // SMTP ayarları testi
    initSmtpTest();
});

/**
 * Admin Dashboard Grafikleri
 */
function initDashboardCharts() {
    // Satış Grafiği (Son 7 Gün)
    const salesChart = document.getElementById('salesChart');
    if (salesChart) {
        // Canvas içeriğini al
        const ctx = salesChart.getContext('2d');
        
        // Verileri al
        const salesData = JSON.parse(salesChart.getAttribute('data-sales')) || [];
        const salesLabels = JSON.parse(salesChart.getAttribute('data-labels')) || [];
        
        // Grafik oluştur
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: salesLabels,
                datasets: [{
                    label: 'Günlük Satış (TL)',
                    data: salesData,
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    pointRadius: 3,
                    pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointBorderColor: 'rgba(78, 115, 223, 1)',
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    tension: 0.3,
                    borderWidth: 2,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: {
                        left: 10,
                        right: 25,
                        top: 25,
                        bottom: 0
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        }
                    },
                    y: {
                        ticks: {
                            maxTicksLimit: 5,
                            callback: function(value) {
                                return value.toLocaleString('tr-TR') + ' TL';
                            }
                        },
                        grid: {
                            color: "rgb(234, 236, 244)",
                            zeroLineColor: "rgb(234, 236, 244)",
                            drawBorder: false,
                            borderDash: [2],
                            zeroLineBorderDash: [2]
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: "rgb(255, 255, 255)",
                        bodyColor: "#858796",
                        titleColor: "#6e707e",
                        titleFontSize: 14,
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        displayColors: false,
                        caretPadding: 10,
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y.toLocaleString('tr-TR') + ' TL';
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Ekmek Çeşitleri Dağılımı (Pasta Grafik)
    const breadPieChart = document.getElementById('breadDistributionChart');
    if (breadPieChart) {
        // Canvas içeriğini al
        const ctx = breadPieChart.getContext('2d');
        
        // Verileri al
        const breadData = JSON.parse(breadPieChart.getAttribute('data-values')) || [];
        const breadLabels = JSON.parse(breadPieChart.getAttribute('data-labels')) || [];
        
        // Grafik oluştur
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: breadLabels,
                datasets: [{
                    data: breadData,
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                        '#5a5c69', '#6f42c1', '#20c9a6', '#fd7e14', '#6c757d'
                    ],
                    hoverBackgroundColor: [
                        '#2e59d9', '#17a673', '#2c9faf', '#dda20a', '#c53030',
                        '#484a54', '#5d37a8', '#17a689', '#dc6502', '#5a6268'
                    ],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12
                        }
                    },
                    tooltip: {
                        backgroundColor: "rgb(255, 255, 255)",
                        bodyColor: "#858796",
                        titleColor: "#6e707e",
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        displayColors: false,
                        caretPadding: 10
                    }
                },
                cutout: '70%'
            }
        });
    }
}

/**
 * Sipariş Durumu Güncelleme
 */
function initOrderStatusUpdate() {
    const orderStatusSelects = document.querySelectorAll('.order-status-select');
    
    orderStatusSelects.forEach(select => {
        select.addEventListener('change', function() {
            const orderId = this.getAttribute('data-order-id');
            const statusValue = this.value;
            const orderRow = document.getElementById('order-row-' + orderId);
            
            // AJAX isteği gönder
            fetch('../admin/orders/update-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'order_id=' + orderId + '&status=' + statusValue
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Başarılı mesajı göster
                    showNotification('Sipariş durumu başarıyla güncellendi.', 'success');
                    
                    // Satır rengini güncelle
                    updateOrderRowStatus(orderRow, statusValue);
                } else {
                    // Hata mesajı göster
                    showNotification('Sipariş durumu güncellenirken bir hata oluştu: ' + data.message, 'danger');
                    
                    // Select'i eski değerine döndür
                    this.value = this.getAttribute('data-original-value');
                }
            })
            .catch(error => {
                // Hata mesajı göster
                showNotification('Bir hata oluştu: ' + error, 'danger');
                
                // Select'i eski değerine döndür
                this.value = this.getAttribute('data-original-value');
            });
        });
    });
}

/**
 * Sipariş satırının durumunu güncelle
 */
function updateOrderRowStatus(row, status) {
    if (!row) return;
    
    // Satırdan tüm durum classlarını kaldır
    row.classList.remove('table-secondary', 'table-primary', 'table-info', 'table-success', 'table-danger');
    
    // Yeni durum classını ekle
    switch(status) {
        case 'pending':
            row.classList.add('table-secondary');
            break;
        case 'confirmed':
            row.classList.add('table-primary');
            break;
        case 'preparing':
            row.classList.add('table-info');
            break;
        case 'ready':
        case 'delivered':
            row.classList.add('table-success');
            break;
        case 'cancelled':
            row.classList.add('table-danger');
            break;
    }
}

/**
 * Kullanıcı Durumu Güncelleme
 */
function initUserStatusUpdate() {
    const userStatusSwitches = document.querySelectorAll('.user-status-switch');
    
    userStatusSwitches.forEach(switch_elem => {
        switch_elem.addEventListener('change', function() {
            const userId = this.getAttribute('data-user-id');
            const statusValue = this.checked ? 1 : 0;
            
            // AJAX isteği gönder
            fetch('../admin/users/update-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'user_id=' + userId + '&status=' + statusValue
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Başarılı mesajı göster
                    showNotification('Kullanıcı durumu başarıyla güncellendi.', 'success');
                } else {
                    // Hata mesajı göster
                    showNotification('Kullanıcı durumu güncellenirken bir hata oluştu: ' + data.message, 'danger');
                    
                    // Switch'i eski haline getir
                    this.checked = !this.checked;
                }
            })
            .catch(error => {
                // Hata mesajı göster
                showNotification('Bir hata oluştu: ' + error, 'danger');
                
                // Switch'i eski haline getir
                this.checked = !this.checked;
            });
        });
    });
}

/**
 * Ekmek Çeşidi Durumu Güncelleme
 */
function initBreadStatusUpdate() {
    const breadStatusSwitches = document.querySelectorAll('.bread-status-switch');
    
    breadStatusSwitches.forEach(switch_elem => {
        switch_elem.addEventListener('change', function() {
            const breadId = this.getAttribute('data-bread-id');
            const statusValue = this.checked ? 1 : 0;
            
            // AJAX isteği gönder
            fetch('../admin/bread/update-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'bread_id=' + breadId + '&status=' + statusValue
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Başarılı mesajı göster
                    showNotification('Ekmek çeşidi durumu başarıyla güncellendi.', 'success');
                } else {
                    // Hata mesajı göster
                    showNotification('Ekmek çeşidi durumu güncellenirken bir hata oluştu: ' + data.message, 'danger');
                    
                    // Switch'i eski haline getir
                    this.checked = !this.checked;
                }
            })
            .catch(error => {
                // Hata mesajı göster
                showNotification('Bir hata oluştu: ' + error, 'danger');
                
                // Switch'i eski haline getir
                this.checked = !this.checked;
            });
        });
    });
}

/**
 * Bildirim Göster
 */
function showNotification(message, type = 'info') {
    const notificationArea = document.getElementById('notificationArea');
    
    if (!notificationArea) return;
    
    // Bildirim HTML'i oluştur
    const notificationId = 'notification-' + Date.now();
    const notificationHtml = `
        <div id="${notificationId}" class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
        </div>
    `;
    
    // Bildirim alanına ekle
    notificationArea.innerHTML += notificationHtml;
    
    // 5 saniye sonra otomatik kapat
    setTimeout(() => {
        const notification = document.getElementById(notificationId);
        if (notification) {
            // Bootstrap 5 dismiss kullan
            const alert = new bootstrap.Alert(notification);
            alert.close();
        }
    }, 5000);
}

/**
 * Filtre Dropdown'ları
 */
function initFilterDropdowns() {
    const filterInputs = document.querySelectorAll('.filter-dropdown');
    
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            const form = this.closest('form');
            if (form) {
                form.submit();
            }
        });
    });
}

/**
 * Arama Kutuları
 */
function initSearchBoxes() {
    const searchForms = document.querySelectorAll('.search-form');
    
    searchForms.forEach(form => {
        const input = form.querySelector('.search-input');
        const clearButton = form.querySelector('.search-clear');
        
        if (clearButton) {
            clearButton.addEventListener('click', function(e) {
                e.preventDefault();
                input.value = '';
                form.submit();
            });
        }
    });
}

/**
 * Tarih Aralığı Seçici
 */
function initDateRangePicker() {
    // Flatpickr yüklüyse
    if (typeof flatpickr !== 'undefined') {
        const dateRangePickers = document.querySelectorAll('.date-range-picker');
        
        dateRangePickers.forEach(picker => {
            flatpickr(picker, {
                mode: "range",
                dateFormat: "Y-m-d",
                locale: "tr",
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        // Tarihleri form alanlarına set et
                        const startDate = selectedDates[0].toISOString().split('T')[0];
                        const endDate = selectedDates[1].toISOString().split('T')[0];
                        
                        document.getElementById('start_date').value = startDate;
                        document.getElementById('end_date').value = endDate;
                    }
                }
            });
        });
    }
}

/**
 * Stok Hareketleri
 */
function initInventoryMovements() {
    const inventoryForm = document.getElementById('inventoryMovementForm');
    
    if (inventoryForm) {
        const movementType = document.getElementById('movement_type');
        const sourceFields = document.getElementById('source_fields');
        const destinationFields = document.getElementById('destination_fields');
        
        movementType.addEventListener('change', function() {
            const value = this.value;
            
            if (value === 'in') {
                sourceFields.style.display = 'block';
                destinationFields.style.display = 'none';
            } else if (value === 'out') {
                sourceFields.style.display = 'none';
                destinationFields.style.display = 'block';
            } else if (value === 'transfer') {
                sourceFields.style.display = 'block';
                destinationFields.style.display = 'block';
            } else {
                sourceFields.style.display = 'none';
                destinationFields.style.display = 'none';
            }
        });
        
        // Başlangıçta tetikle
        if (movementType) {
            movementType.dispatchEvent(new Event('change'));
        }
    }
}

/**
 * SMTP Ayarları Testi
 */
function initSmtpTest() {
    const testSmtpButton = document.getElementById('testSmtpButton');
    
    if (testSmtpButton) {
        testSmtpButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Test edilecek e-posta
            const testEmail = document.getElementById('test_email').value;
            
            if (!testEmail) {
                showNotification('Lütfen test için bir e-posta adresi girin.', 'warning');
                return;
            }
            
            // Form verilerini al
            const host = document.getElementById('host').value;
            const port = document.getElementById('port').value;
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const encryption = document.getElementById('encryption').value;
            const fromEmail = document.getElementById('from_email').value;
            const fromName = document.getElementById('from_name').value;
            
            // Test butonu değişikliği
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Test Yapılıyor...';
            
            // AJAX isteği gönder
            fetch('../admin/system/test-smtp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'host=' + encodeURIComponent(host) + 
                      '&port=' + encodeURIComponent(port) + 
                      '&username=' + encodeURIComponent(username) + 
                      '&password=' + encodeURIComponent(password) + 
                      '&encryption=' + encodeURIComponent(encryption) + 
                      '&from_email=' + encodeURIComponent(fromEmail) + 
                      '&from_name=' + encodeURIComponent(fromName) + 
                      '&test_email=' + encodeURIComponent(testEmail)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Başarılı mesajı göster
                    showNotification('E-posta başarıyla gönderildi! SMTP ayarları çalışıyor.', 'success');
                } else {
                    // Hata mesajı göster
                    showNotification('E-posta gönderilirken bir hata oluştu: ' + data.message, 'danger');
                }
                
                // Butonu normal haline getir
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Test E-postası Gönder';
            })
            .catch(error => {
                // Hata mesajı göster
                showNotification('Bir hata oluştu: ' + error, 'danger');
                
                // Butonu normal haline getir
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Test E-postası Gönder';
            });
        });
    }
}