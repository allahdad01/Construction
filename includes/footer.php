            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; <?php echo APP_NAME; ?> <?php echo date('Y'); ?></span>
                    </div>
                </div>
            </footer>
            <!-- End of Footer -->
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                    <a class="btn btn-primary" href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- DataTables -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Modern JavaScript with enhanced functionality
        document.addEventListener('DOMContentLoaded', function() {
            
            // Sidebar Toggle Functionality
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('expanded');
                    
                    // Store sidebar state in localStorage
                    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                });
            }
            
            // Restore sidebar state on page load
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (sidebarCollapsed) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            }
            
            // Mobile sidebar toggle
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            }
            
            // Responsive sidebar behavior
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('show');
                }
            });
            
            // Enhanced Navigation
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    // Remove active class from all links
                    navLinks.forEach(l => l.classList.remove('active'));
                    // Add active class to clicked link
                    this.classList.add('active');
                });
            });
            
            // Enhanced Dropdowns
            const dropdowns = document.querySelectorAll('.dropdown-toggle');
            dropdowns.forEach(dropdown => {
                dropdown.addEventListener('click', function(e) {
                    e.preventDefault();
                    const menu = this.nextElementSibling;
                    const isOpen = menu.classList.contains('show');
                    
                    // Close all other dropdowns
                    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                        menu.classList.remove('show');
                    });
                    
                    // Toggle current dropdown
                    if (!isOpen) {
                        menu.classList.add('show');
                    }
                });
            });
            
            // Close dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                        menu.classList.remove('show');
                    });
                }
            });
            
            // Enhanced Form Validation
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const requiredFields = form.querySelectorAll('[required]');
                    let isValid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            isValid = false;
                            field.classList.add('is-invalid');
                            
                            // Add error message if not exists
                            if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('invalid-feedback')) {
                                const errorDiv = document.createElement('div');
                                errorDiv.className = 'invalid-feedback';
                                errorDiv.textContent = 'This field is required.';
                                field.parentNode.appendChild(errorDiv);
                            }
                        } else {
                            field.classList.remove('is-invalid');
                            const errorDiv = field.parentNode.querySelector('.invalid-feedback');
                            if (errorDiv) {
                                errorDiv.remove();
                            }
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        showNotification('Please fill in all required fields.', 'error');
                    }
                });
            });
            
            // Enhanced Table Functionality
            const tables = document.querySelectorAll('.table');
            tables.forEach(table => {
                // Add hover effects
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    row.addEventListener('mouseenter', function() {
                        this.style.transform = 'scale(1.01)';
                        this.style.transition = 'transform 0.2s ease';
                    });
                    
                    row.addEventListener('mouseleave', function() {
                        this.style.transform = 'scale(1)';
                    });
                });
                
                // Add click handlers for action buttons
                const actionButtons = table.querySelectorAll('.btn-action');
                actionButtons.forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        const action = this.dataset.action;
                        const id = this.dataset.id;
                        
                        if (action === 'delete') {
                            if (confirm('Are you sure you want to delete this item?')) {
                                // Handle delete action
                                handleDelete(id);
                            }
                        } else if (action === 'edit') {
                            // Handle edit action
                            handleEdit(id);
                        }
                    });
                });
            });
            
            // Enhanced Card Functionality
            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 10px 25px rgba(0,0,0,0.15)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '0 0.125rem 0.25rem rgba(0, 0, 0, 0.075)';
                });
            });
            
            // Enhanced Button Functionality
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    // Add loading state
                    if (!this.classList.contains('btn-loading')) {
                        this.classList.add('btn-loading');
                        const originalText = this.innerHTML;
                        this.innerHTML = '<span class="loading"></span> Loading...';
                        
                        // Simulate loading (remove in production)
                        setTimeout(() => {
                            this.classList.remove('btn-loading');
                            this.innerHTML = originalText;
                        }, 2000);
                    }
                });
            });
            
            // Enhanced Modal Functionality
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.addEventListener('show.bs.modal', function() {
                    this.classList.add('fade-in');
                });
                
                modal.addEventListener('hidden.bs.modal', function() {
                    this.classList.remove('fade-in');
                });
            });
            
            // Enhanced Alert Functionality
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                // Auto-dismiss alerts after 5 seconds
                setTimeout(() => {
                    if (alert.classList.contains('alert-dismissible')) {
                        const closeButton = alert.querySelector('.btn-close');
                        if (closeButton) {
                            closeButton.click();
                        }
                    }
                }, 5000);
            });
            
            // Enhanced Search Functionality
            const searchInputs = document.querySelectorAll('.search-input');
            searchInputs.forEach(input => {
                let searchTimeout;
                input.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        performSearch(this.value);
                    }, 300);
                });
            });
            
            // Enhanced Pagination
            const paginationLinks = document.querySelectorAll('.pagination .page-link');
            paginationLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const page = this.dataset.page;
                    loadPage(page);
                });
            });
            
            // Enhanced Filter Functionality
            const filterSelects = document.querySelectorAll('.filter-select');
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    applyFilters();
                });
            });
            
            // Enhanced Sort Functionality
            const sortHeaders = document.querySelectorAll('.sort-header');
            sortHeaders.forEach(header => {
                header.addEventListener('click', function() {
                    const column = this.dataset.column;
                    const currentOrder = this.dataset.order || 'asc';
                    const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
                    
                    // Update all headers
                    sortHeaders.forEach(h => {
                        h.dataset.order = '';
                        h.classList.remove('sort-asc', 'sort-desc');
                    });
                    
                    // Update current header
                    this.dataset.order = newOrder;
                    this.classList.add(newOrder === 'asc' ? 'sort-asc' : 'sort-desc');
                    
                    // Perform sort
                    performSort(column, newOrder);
                });
            });
            
            // Enhanced Chart Functionality
            const chartContainers = document.querySelectorAll('.chart-container');
            chartContainers.forEach(container => {
                const canvas = container.querySelector('canvas');
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    const chartData = JSON.parse(container.dataset.chart || '{}');
                    
                    if (chartData.labels && chartData.data) {
                        new Chart(ctx, {
                            type: chartData.type || 'line',
                            data: {
                                labels: chartData.labels,
                                datasets: [{
                                    label: chartData.label || 'Data',
                                    data: chartData.data,
                                    backgroundColor: chartData.backgroundColor || 'rgba(78, 115, 223, 0.2)',
                                    borderColor: chartData.borderColor || 'rgba(78, 115, 223, 1)',
                                    borderWidth: 2,
                                    tension: 0.4
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        display: false
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        grid: {
                                            color: 'rgba(0, 0, 0, 0.1)'
                                        }
                                    },
                                    x: {
                                        grid: {
                                            color: 'rgba(0, 0, 0, 0.1)'
                                        }
                                    }
                                }
                            }
                        });
                    }
                }
            });
            
            // Enhanced DataTable Initialization
            const dataTables = document.querySelectorAll('.datatable');
            dataTables.forEach(table => {
                if ($.fn.DataTable) {
                    $(table).DataTable({
                        responsive: true,
                        language: {
                            search: "Search:",
                            lengthMenu: "Show _MENU_ entries",
                            info: "Showing _START_ to _END_ of _TOTAL_ entries",
                            infoEmpty: "Showing 0 to 0 of 0 entries",
                            infoFiltered: "(filtered from _MAX_ total entries)",
                            paginate: {
                                first: "First",
                                last: "Last",
                                next: "Next",
                                previous: "Previous"
                            }
                        },
                        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                             '<"row"<"col-sm-12"tr>>' +
                             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                        pageLength: 10,
                        order: [[0, 'asc']],
                        columnDefs: [
                            {
                                targets: -1,
                                orderable: false,
                                searchable: false
                            }
                        ]
                    });
                }
            });
            
            // Enhanced Export Functionality
            const exportButtons = document.querySelectorAll('.export-btn');
            exportButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const format = this.dataset.format;
                    const tableId = this.dataset.table;
                    
                    if (format === 'pdf') {
                        exportToPDF(tableId);
                    } else if (format === 'excel') {
                        exportToExcel(tableId);
                    } else if (format === 'csv') {
                        exportToCSV(tableId);
                    }
                });
            });
            
            // Enhanced Print Functionality
            const printButtons = document.querySelectorAll('.print-btn');
            printButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = this.dataset.target;
                    printElement(target);
                });
            });
            
            // Enhanced Theme Toggle
            const themeToggle = document.querySelector('.theme-toggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    const currentTheme = document.body.dataset.theme;
                    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                    
                    document.body.dataset.theme = newTheme;
                    localStorage.setItem('theme', newTheme);
                    
                    // Update theme toggle icon
                    const icon = this.querySelector('i');
                    icon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
                });
            }
            
            // Enhanced Notification System
            window.showNotification = function(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
                notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                notification.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                document.body.appendChild(notification);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 5000);
            };
            
            // Enhanced Loading States
            window.showLoading = function(element) {
                const loading = document.createElement('div');
                loading.className = 'loading-overlay';
                loading.innerHTML = '<div class="loading-spinner"></div>';
                loading.style.cssText = `
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(255, 255, 255, 0.8);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 1000;
                `;
                
                element.style.position = 'relative';
                element.appendChild(loading);
            };
            
            window.hideLoading = function(element) {
                const loading = element.querySelector('.loading-overlay');
                if (loading) {
                    loading.remove();
                }
            };
            
            // Enhanced AJAX Functionality
            window.ajaxRequest = function(url, options = {}) {
                const defaultOptions = {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                };
                
                const finalOptions = { ...defaultOptions, ...options };
                
                return fetch(url, finalOptions)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .catch(error => {
                        console.error('AJAX Error:', error);
                        showNotification('An error occurred while processing your request.', 'error');
                        throw error;
                    });
            };
            
            // Enhanced Form Submission
            window.submitForm = function(formElement, options = {}) {
                const formData = new FormData(formElement);
                const url = formElement.action || window.location.href;
                
                showLoading(formElement);
                
                return fetch(url, {
                    method: formElement.method || 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading(formElement);
                    
                    if (data.success) {
                        showNotification(data.message || 'Operation completed successfully!', 'success');
                        if (options.redirect) {
                            window.location.href = options.redirect;
                        }
                    } else {
                        showNotification(data.message || 'Operation failed!', 'error');
                    }
                })
                .catch(error => {
                    hideLoading(formElement);
                    showNotification('An error occurred while processing your request.', 'error');
                    console.error('Form submission error:', error);
                });
            };
            
            // Enhanced Delete Confirmation
            window.confirmDelete = function(message = 'Are you sure you want to delete this item?') {
                return new Promise((resolve) => {
                    const modal = document.createElement('div');
                    modal.className = 'modal fade';
                    modal.innerHTML = `
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Confirm Delete</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>${message}</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-danger confirm-delete">Delete</button>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    document.body.appendChild(modal);
                    
                    const bootstrapModal = new bootstrap.Modal(modal);
                    bootstrapModal.show();
                    
                    modal.querySelector('.confirm-delete').addEventListener('click', () => {
                        bootstrapModal.hide();
                        modal.addEventListener('hidden.bs.modal', () => {
                            modal.remove();
                        });
                        resolve(true);
                    });
                    
                    modal.addEventListener('hidden.bs.modal', () => {
                        modal.remove();
                        resolve(false);
                    });
                });
            };
            
            // Enhanced File Upload
            const fileInputs = document.querySelectorAll('.file-upload');
            fileInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const preview = this.parentNode.querySelector('.file-preview');
                        if (preview) {
                            if (file.type.startsWith('image/')) {
                                const reader = new FileReader();
                                reader.onload = function(e) {
                                    preview.innerHTML = `<img src="${e.target.result}" class="img-thumbnail" style="max-height: 100px;">`;
                                };
                                reader.readAsDataURL(file);
                            } else {
                                preview.innerHTML = `<div class="alert alert-info">${file.name} (${(file.size / 1024).toFixed(2)} KB)</div>`;
                            }
                        }
                    }
                });
            });
            
            // Enhanced Date Pickers
            const dateInputs = document.querySelectorAll('.date-picker');
            dateInputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.type = 'date';
                });
            });
            
            // Enhanced Time Pickers
            const timeInputs = document.querySelectorAll('.time-picker');
            timeInputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.type = 'time';
                });
            });
            
            // Enhanced Color Pickers
            const colorInputs = document.querySelectorAll('.color-picker');
            colorInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const preview = this.parentNode.querySelector('.color-preview');
                    if (preview) {
                        preview.style.backgroundColor = this.value;
                    }
                });
            });
            
            // Enhanced Search and Filter
            window.performSearch = function(query) {
                const searchableElements = document.querySelectorAll('.searchable');
                searchableElements.forEach(element => {
                    const text = element.textContent.toLowerCase();
                    const matches = text.includes(query.toLowerCase());
                    element.style.display = matches ? '' : 'none';
                });
            };
            
            window.applyFilters = function() {
                const filters = {};
                document.querySelectorAll('.filter-select').forEach(select => {
                    if (select.value) {
                        filters[select.name] = select.value;
                    }
                });
                
                // Apply filters to table rows
                const tableRows = document.querySelectorAll('.filterable-row');
                tableRows.forEach(row => {
                    let show = true;
                    Object.keys(filters).forEach(key => {
                        const cell = row.querySelector(`[data-${key}]`);
                        if (cell && cell.dataset[key] !== filters[key]) {
                            show = false;
                        }
                    });
                    row.style.display = show ? '' : 'none';
                });
            };
            
            // Enhanced Sort Function
            window.performSort = function(column, order) {
                const table = document.querySelector('.sortable-table');
                if (!table) return;
                
                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));
                
                rows.sort((a, b) => {
                    const aValue = a.querySelector(`[data-${column}]`)?.dataset[column] || '';
                    const bValue = b.querySelector(`[data-${column}]`)?.dataset[column] || '';
                    
                    if (order === 'asc') {
                        return aValue.localeCompare(bValue);
                    } else {
                        return bValue.localeCompare(aValue);
                    }
                });
                
                rows.forEach(row => tbody.appendChild(row));
            };
            
            // Enhanced Page Loading
            window.loadPage = function(page) {
                const url = new URL(window.location);
                url.searchParams.set('page', page);
                window.location.href = url.toString();
            };
            
            // Enhanced Delete Handler
            window.handleDelete = function(id) {
                confirmDelete().then(confirmed => {
                    if (confirmed) {
                        // Perform delete action
                        ajaxRequest(`/api/delete/${id}`, { method: 'DELETE' })
                            .then(data => {
                                if (data.success) {
                                    showNotification('Item deleted successfully!', 'success');
                                    // Reload page or remove element
                                    location.reload();
                                }
                            });
                    }
                });
            };
            
            // Enhanced Edit Handler
            window.handleEdit = function(id) {
                // Load edit form or redirect to edit page
                window.location.href = `/edit/${id}`;
            };
            
            // Enhanced Export Functions
            window.exportToPDF = function(tableId) {
                // Implement PDF export
                showNotification('PDF export feature coming soon!', 'info');
            };
            
            window.exportToExcel = function(tableId) {
                // Implement Excel export
                showNotification('Excel export feature coming soon!', 'info');
            };
            
            window.exportToCSV = function(tableId) {
                const table = document.getElementById(tableId);
                if (!table) return;
                
                const rows = table.querySelectorAll('tr');
                let csv = [];
                
                rows.forEach(row => {
                    const cols = row.querySelectorAll('td, th');
                    const rowData = Array.from(cols).map(col => `"${col.textContent.trim()}"`);
                    csv.push(rowData.join(','));
                });
                
                const csvContent = csv.join('\n');
                const blob = new Blob([csvContent], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'export.csv';
                a.click();
                window.URL.revokeObjectURL(url);
            };
            
            // Enhanced Print Function
            window.printElement = function(elementId) {
                const element = document.getElementById(elementId);
                if (!element) return;
                
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>Print</title>
                            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                        </head>
                        <body>
                            ${element.outerHTML}
                        </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.print();
            };
            
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Initialize popovers
            const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
            
            // Enhanced Keyboard Navigation
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + K for search
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    const searchInput = document.querySelector('.search-input');
                    if (searchInput) {
                        searchInput.focus();
                    }
                }
                
                // Escape key to close modals/dropdowns
                if (e.key === 'Escape') {
                    const openModals = document.querySelectorAll('.modal.show');
                    openModals.forEach(modal => {
                        const bootstrapModal = bootstrap.Modal.getInstance(modal);
                        if (bootstrapModal) {
                            bootstrapModal.hide();
                        }
                    });
                    
                    const openDropdowns = document.querySelectorAll('.dropdown-menu.show');
                    openDropdowns.forEach(dropdown => {
                        dropdown.classList.remove('show');
                    });
                }
            });
            
            // Enhanced Accessibility
            const focusableElements = document.querySelectorAll('a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
            focusableElements.forEach(element => {
                element.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.click();
                    }
                });
            });
            
            // Enhanced Performance Monitoring
            if ('performance' in window) {
                window.addEventListener('load', function() {
                    const perfData = performance.getEntriesByType('navigation')[0];
                    if (perfData.loadEventEnd - perfData.loadEventStart > 3000) {
                        console.warn('Page load time is slow:', perfData.loadEventEnd - perfData.loadEventStart, 'ms');
                    }
                });
            }
            
            // Enhanced Error Handling
            window.addEventListener('error', function(e) {
                console.error('JavaScript Error:', e.error);
                showNotification('An error occurred. Please refresh the page.', 'error');
            });
            
            // Enhanced Unhandled Promise Rejection
            window.addEventListener('unhandledrejection', function(e) {
                console.error('Unhandled Promise Rejection:', e.reason);
                showNotification('An error occurred. Please try again.', 'error');
            });
            
            console.log('Enhanced JavaScript loaded successfully!');
        });
    </script>
</body>
</html>