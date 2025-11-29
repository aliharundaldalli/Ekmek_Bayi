/**
 * Ekmek Sipariş Sistemi - Ana JS Dosyası
 */

// DOM yüklendikten sonra çalıştır
document.addEventListener('DOMContentLoaded', function() {
    // Bootstrap Form Doğrulama
    initFormValidation();
    
    // Şifre göster/gizle düğmesi
    initPasswordToggle();
    
    // Filtreleme işlemleri
    initFilters();
    
    // Sipariş formu işlemleri
    initOrderForm();
    
    // Ürün arama
    initProductSearch();
    
    // DataTables (eğer varsa)
    initDataTables();
    
    // Tooltips
    initTooltips();
    
    // DatePicker
    initDatepicker();
});

/**
 * Bootstrap Form Doğrulama
 */
function initFormValidation() {
    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    var forms = document.querySelectorAll('.needs-validation');
    
    // Loop over them and prevent submission
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
}

/**
 * Şifre göster/gizle düğmesi
 */
function initPasswordToggle() {
    const togglePassword = document.querySelectorAll('.password-toggle');
    
    togglePassword.forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            const input = document.querySelector(this.getAttribute('data-target'));
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
}

/**
 * Filtreleme işlemleri
 */
function initFilters() {
    const filterInputs = document.querySelectorAll('.filter-input');
    
    filterInputs.forEach(function(input) {
        input.addEventListener('change', function() {
            const form = this.closest('form');
            form.submit();
        });
    });
}

/**
 * Sipariş formu işlemleri
 */
function initOrderForm() {
    // Miktar değiştikçe fiyatları güncelle
    const quantityInputs = document.querySelectorAll('.product-quantity');
    
    quantityInputs.forEach(function(input) {
        input.addEventListener('change', function() {
            updateOrderTotals();
        });
    });
    
    // Ürün seçimi
    const productCheckboxes = document.querySelectorAll('.product-checkbox');
    
    productCheckboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const quantityInput = document.querySelector(`#quantity_${this.value}`);
            
            if (this.checked) {
                quantityInput.removeAttribute('disabled');
                quantityInput.value = 1;
            } else {
                quantityInput.setAttribute('disabled', 'disabled');
                quantityInput.value = 0;
            }
            
            updateOrderTotals();
        });
    });
}

/**
 * Sipariş toplamlarını güncelle
 */
function updateOrderTotals() {
    let subtotal = 0;
    const quantityInputs = document.querySelectorAll('.product-quantity:not([disabled])');
    
    quantityInputs.forEach(function(input) {
        const price = parseFloat(input.getAttribute('data-price'));
        const quantity = parseInt(input.value);
        
        if (quantity > 0 && price > 0) {
            const itemTotal = price * quantity;
            subtotal += itemTotal;
            
            // Ürün satır toplamını güncelle (varsa)
            const itemTotalElement = document.querySelector(`#item_total_${input.getAttribute('data-id')}`);
            if (itemTotalElement) {
                itemTotalElement.textContent = formatMoney(itemTotal);
            }
        }
    });
    
    // Alt toplam, KDV ve genel toplamı güncelle
    const subtotalElement = document.querySelector('#subtotal');
    const taxElement = document.querySelector('#tax_amount');
    const totalElement = document.querySelector('#total_amount');
    
    if (subtotalElement) {
        subtotalElement.textContent = formatMoney(subtotal);
    }
    
    if (taxElement) {
        const taxRate = parseFloat(taxElement.getAttribute('data-tax-rate') || 0.18);
        const taxAmount = subtotal * taxRate;
        taxElement.textContent = formatMoney(taxAmount);
    }
    
    if (totalElement) {
        const taxRate = parseFloat(taxElement?.getAttribute('data-tax-rate') || 0.18);
        const total = subtotal * (1 + taxRate);
        totalElement.textContent = formatMoney(total);
        
        // Gizli toplam input değerini güncelle
        const totalInput = document.querySelector('input[name="total_amount"]');
        if (totalInput) {
            totalInput.value = total.toFixed(2);
        }
    }
}

/**
 * Para birimini formatla
 */
function formatMoney(amount) {
    return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(amount);
}

/**
 * Ürün arama
 */
function initProductSearch() {
    const searchInput = document.querySelector('#product-search');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const productRows = document.querySelectorAll('.product-row');
            
            productRows.forEach(function(row) {
                const productName = row.getAttribute('data-product-name').toLowerCase();
                
                if (productName.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
}

/**
 * DataTables
 */
function initDataTables() {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('.datatable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json"
            },
            "responsive": true
        });
    }
}

/**
 * Tooltips
 */
function initTooltips() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
}

/**
 * DatePicker
 */
function initDatepicker() {
    // Eğer flatpickr yüklüyse
    if (typeof flatpickr !== 'undefined') {
        flatpickr(".datepicker", {
            dateFormat: "d.m.Y",
            locale: "tr"
        });
    }
}