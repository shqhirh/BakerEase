// BakerEase Client Javascript Interactions

document.addEventListener('DOMContentLoaded', function() {
    // 1. Live Product Search & Category Filters
    const searchInput = document.getElementById('productSearch');
    const filterButtons = document.querySelectorAll('.filter-btn');
    const productCards = document.querySelectorAll('.product-card-col');
    
    let currentSearch = '';
    let currentCategory = 'All';

    function filterProducts() {
        let visibleCount = 0;
        const noProductsMsg = document.getElementById('noProductsMessage');

        productCards.forEach(card => {
            const productName = card.dataset.name.toLowerCase();
            const productCategory = card.dataset.category;
            
            const matchesSearch = productName.includes(currentSearch);
            const matchesCategory = (currentCategory === 'All' || productCategory === currentCategory);

            if (matchesSearch && matchesCategory) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (noProductsMsg) {
            if (visibleCount === 0) {
                noProductsMsg.classList.remove('d-none');
            } else {
                noProductsMsg.classList.add('d-none');
            }
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            currentSearch = e.target.value.toLowerCase().trim();
            filterProducts();
        });
    }

    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Update active state
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');

            currentCategory = this.dataset.category;
            filterProducts();
        });
    });

    // 2. Quantity Limit & Stock Validation (Customer Order Form)
    const quantityInput = document.getElementById('orderQuantity');
    const stockQuantitySpan = document.getElementById('stockQuantityVal');
    const orderFormSubmit = document.getElementById('orderForm');

    if (quantityInput && stockQuantitySpan) {
        const stockLimit = parseInt(stockQuantitySpan.innerText, 10);
        
        quantityInput.addEventListener('input', function() {
            let val = parseInt(this.value, 10);
            if (isNaN(val) || val < 1) {
                this.value = 1;
            } else if (val > stockLimit) {
                this.value = stockLimit;
                alert('We only have ' + stockLimit + ' units available in stock.');
            }
        });
    }

    // Auto-dismiss standard Bootstrap alerts after 4 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (typeof bootstrap !== 'undefined') {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            } else {
                alert.style.display = 'none';
            }
        }, 4000);
    });

    // 3. Sidebar toggle control for desktop
    const sidebarToggleBtn = document.getElementById('sidebarToggle');
    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', function() {
            document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', document.body.classList.contains('sidebar-collapsed'));
        });
    }
});
