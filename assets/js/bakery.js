/**
 * Ekmek Sipariş Sistemi - Büfe Paneli JS
 */

// DOM yüklendikten sonra çalıştır
document.addEventListener('DOMContentLoaded', function() {
    // Büfe Dashboard Grafikleri
    initBakeryDashboardCharts();
    
    // Sipariş Formu İşlemleri
    initOrderForm();
    
    // Sipariş İptal Onay
    initOrderCancelation();
    
    // Sipariş Teslimat Onay
    initOrderDeliveryConfirmation();
    
    // Sipariş Arama/Filtreleme
    initOrderFilters();
    
    // Sipariş Detayları Modal
    initOrderDetailsModal();
    
    // Fatura İndirme
    initInvoiceDownload();
    
    // Profil Fotoğrafı Önizleme
    initProfileImagePreview();
});

/**
 * Büfe Dashboard Grafikleri
 */
function initBakeryDashboardCharts() {
    // Son 7 Gün Siparişleri
    const ordersChart = document.getElementById('bakeryOrdersChart');
    if (ordersChart) {
        // Canvas içeriğini al
        const ctx = ordersChart.getContext('2d');
        
        // Verileri al
        const ordersData = JSON.parse(ordersChart.getAttribute('data-orders')) || [];
        const ordersLabels = JSON.parse(ordersChart.getAttribute('data-labels')) || [];
        const amountsData = JSON.parse(ordersChart.getAttribute('data-amounts')) || [];
        
        // Grafik oluştur
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ordersLabels,
                datasets: [
                    {
                        label: 'Sipariş Sayısı',
                        data: ordersData,
                        backgroundColor: 'rgba(13, 110, 253, 0.7)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 1,
                        borderRadius: 5,
                        yAxisID: 'y-orders',
                        order: 1
                    },
                    {
                        label: 'Sipariş Tutarı (TL)',
                        data: amountsData,
                        backgroundColor: 'rgba(25, 135, 84, 0)',
                        borderColor: 'rgba(25, 135, 84, 1)',
                        borderWidth: 2,
                        type: 'line',
                        yAxisID: 'y-amounts',
                        tension: 0.3,
                        order: 0
                    }
                ]
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
                    'y-orders': {
                        position: 'left',
                        grid: {
                            color: "rgb(234, 236, 244)",
                            zeroLineColor: "rgb(234, 236, 244)",
                            drawBorder: false,
                            borderDash: [2],
                            zeroLineBorderDash: [2]
                        },
                        ticks: {
                            beginAtZero: true,
                            precision: 0
                        }
                    },
                    'y-amounts': {
                        position: 'right',
                        grid: {
                            display: false
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('tr-TR') + ' TL';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        backgroundColor: "rgb(255, 255, 255)",
                        bodyColor: "#858796",
                        titleColor: "#6e707e",
                        titleFontSize: 14,
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        displayColors: true,
                        caretPadding: 10,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label === 'Sipariş Tutarı (TL)') {
                                    return label + ': ' + context.parsed.y.toLocaleString('tr-TR') + ' TL';
                                }
                                return label + ': ' + context.parsed.y;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Ekmek Çeşitlerine Göre Dağılım (Pasta Grafik)
    const breadPieChart = document.getElementById('bakeryBreadDistributionChart');
    if (breadPieChart) {
        // Canvas içeriğini al
        const ctx = breadPieChart.getContext('2d');
        
        // Verileri al
        const breadData = JSON.parse(breadPieChart.getAttribute('data-values')) || [];
        const breadLabels = JSON.parse(breadPieChart.getAttribute('data-labels')) || [];
        
        // Grafik oluştur
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: breadLabels,
                datasets: [{
                    data: breadData,
                    backgroundColor: [
                        '#0d6efd', '#198754', '#0dcaf0', '#ffc107', '#dc3545',
                        '#6c757d', '#6f42c1', '#20c997', '#fd7e14', '#0dcaf0'
                    ],
                    hoverBackgroundColor: [
                        '#0b5ed7', '#157347', '#0bacbe', '#d39e00', '#bb2d3b',
                        '#5c636a', '#61428f', '#1aa179', '#ca6510', '#0bacbe'
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
                }
            }
        });
    }
}

/**
 * Sipariş Formu İşlemleri
 */
function initOrderForm() {
    const orderForm = document.getElementById('orderForm');
    
    if (orderForm) {
        // Miktar değiştikçe sipariş toplamını güncelle
        const quantityInputs = document.querySelectorAll('.product-quantity');
        
        quantityInputs.forEach(input => {
            input.addEventListener('change', function() {
                updateOrderTotal();
            });
        });
        
        // Ürün seçildiğinde/kaldırıldığında
        const productCheckboxes = document.querySelectorAll('.product-checkbox');
        
        productCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const productId = this.value;
                const quantityInput = document.getElementById('quantity_' + productId);
                
                if (this.checked) {
                    quantityInput.disabled = false;
                    quantityInput.value = 1;
                } else {
                    quantityInput.disabled = true;
                    quantityInput.value = 0;
                }
                
                updateOrderTotal();
            });
        });
        
        // Ürün arama
        const searchInput = document.getElementById('productSearch');
        
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const productCards = document.querySelectorAll('.product-card');
                
                productCards.forEach(card => {
                    const productName = card.querySelector('.product-name').textContent.toLowerCase();
                    
                    if (productName.includes(searchTerm)) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        }
        
        // Form gönderilmeden önce kontrol
        orderForm.addEventListener('submit', function(e) {
            const selectedProducts = document.querySelectorAll('.product-checkbox:checked');
            
            if (selectedProducts.length === 0) {
                e.preventDefault();
                alert('Lütfen en az bir ürün seçiniz.');
                return false;
            }
            
            // En az bir ürün için miktar kontrolü
            let hasQuantity = false;
            
            selectedProducts.forEach(product => {
                const productId = product.value;
                const quantity = parseInt(document.getElementById('quantity_' + productId).value);
                
                if (quantity > 0) {
                    hasQuantity = true;
                }
            });
            
            if (!hasQuantity) {
                e.preventDefault();
                alert('Lütfen seçtiğiniz ürünler için miktar giriniz.');
                return false;
            }
        });
    }
}

/**
 * Sipariş toplam tutarını güncelle
 */
function updateOrderTotal() {
    let total = 0;
    const quantityInputs = document.querySelectorAll('.product-quantity:not(:disabled)');
    
    quantityInputs.forEach(input => {
        const price = parseFloat(input.getAttribute('data-price'));
        const quantity = parseInt(input.value);
        
        if (quantity > 0) {
            total += price * quantity;
        }
    });
    
    // Toplam tutarı güncelle
    const totalElement = document.getElementById('orderTotal');
    if (totalElement) {
        totalElement.textContent = total.toLocaleString('tr-TR') + ' TL';
    }
    
    // Gizli input alanını güncelle
    const totalInput = document.getElementById('total_amount');
    if (totalInput) {
        totalInput.value = total.toFixed(2);
    }
}

/**
 * Sipariş İptal Onay
 */
function initOrderCancelation() {
    const cancelButtons = document.querySelectorAll('.cancel-order-btn');
    
    cancelButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Bu siparişi iptal etmek istediğinize emin misiniz?')) {
                e.preventDefault();
            }
        });
    });
}

/**
 * Sipariş Teslimat Onay
 */
function initOrderDeliveryConfirmation() {
    const confirmButtons = document.querySelectorAll('.confirm-delivery-btn');
    
    confirmButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Siparişi teslim aldığınızı onaylıyor musunuz?')) {
                e.preventDefault();
            }
        });
    });
}

/**
 * Sipariş Arama/Filtreleme
 */
function initOrderFilters() {
    const filterForm = document.getElementById('orderFilterForm');
    
    if (filterForm) {
        const filterInputs = filterForm.querySelectorAll('.filter-control');
        
        filterInputs.forEach(input => {
            input.addEventListener('change', function() {
                filterForm.submit();
            });
        });
        
        // Tarih filtresi
        const dateRangeInput = document.getElementById('dateRange');
        if (dateRangeInput && typeof flatpickr !== 'undefined') {
            flatpickr(dateRangeInput, {
                mode: "range",
                dateFormat: "Y-m-d",
                locale: "tr",
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        const startDate = document.getElementById('start_date');
                        const endDate = document.getElementById('end_date');
                        
                        startDate.value = selectedDates[0].toISOString().split('T')[0];
                        endDate.value = selectedDates[1].toISOString().split('T')[0];
                        
                        filterForm.submit();
                    }
                }
            });
        }
    }
}

/**
 * Sipariş Detayları Modal
 */
function initOrderDetailsModal() {
    const orderDetailsButtons = document.querySelectorAll('.order-details-btn');
    
    orderDetailsButtons.forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.getAttribute('data-order-id');
            const modal = document.getElementById('orderDetailsModal');
            const modalContent = document.getElementById('orderDetailsContent');
            
            // Loading göster
            modalContent.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Yükleniyor...</span></div><p class="mt-3">Sipariş detayları yükleniyor...</p></div>';
            
            // Modal'ı göster
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            // AJAX ile sipariş detaylarını getir
            fetch('../my/orders/get-details.php?id=' + orderId)
                .then(response => response.text())
                .then(data => {
                    modalContent.innerHTML = data;
                })
                .catch(error => {
                    modalContent.innerHTML = '<div class="alert alert-danger">Sipariş detayları yüklenirken bir hata oluştu: ' + error + '</div>';
                });
        });
    });
}

/**
 * Fatura İndirme
 */
function initInvoiceDownload() {
    const downloadButtons = document.querySelectorAll('.download-invoice-btn');
    
    downloadButtons.forEach(button => {
        button.addEventListener('click', function() {
            const invoiceId = this.getAttribute('data-invoice-id');
            window.location.href = '../my/invoices/download.php?id=' + invoiceId;
        });
    });
}

/**
 * Profil Fotoğrafı Önizleme
 */
function initProfileImagePreview() {
    const profileImageInput = document.getElementById('profile_image');
    const profileImagePreview = document.getElementById('profile_image_preview');
    
    if (profileImageInput && profileImagePreview) {
        profileImageInput.addEventListener('change', function() {
            const file = this.files[0];
            
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    profileImagePreview.src = e.target.result;
                    profileImagePreview.style.display = 'block';
                };
                
                reader.readAsDataURL(file);
            } else {
                profileImagePreview.src = '#';
                profileImagePreview.style.display = 'none';
            }
        });
    }
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