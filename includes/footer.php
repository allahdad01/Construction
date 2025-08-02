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

    <!-- Bootstrap core JavaScript-->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.4.1/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script>
        // Sidebar toggle functionality
        $(document).ready(function() {
            $("#sidebarToggle, #sidebarToggleTop").on('click', function(e) {
                $("body").toggleClass("sidebar-toggled");
                $(".sidebar").toggleClass("toggled");
                if ($(".sidebar").hasClass("toggled")) {
                    $('.sidebar .collapse').collapse('hide');
                };
            });

            // Prevent the content wrapper from scrolling when the fixed side navigation hovered over
            $('body.fixed-nav .sidebar').on('mousewheel DOMMouseScroll wheel', function(e) {
                if ($(window).width() > 768) {
                    var e0 = e.originalEvent,
                        delta = e0.wheelDelta || -e0.detail;
                    this.scrollTop += (delta < 0 ? 1 : -1) * 30;
                    e.preventDefault();
                }
            });

            // Scroll to top button appear
            $(document).on('scroll', function() {
                var scrollDistance = $(this).scrollTop();

                if (scrollDistance > 100) {
                    $('.scroll-to-top').fadeIn();
                } else {
                    $('.scroll-to-top').fadeOut();
                }
            });

            // Smooth scrolling using jQuery easing
            $(document).on('click', 'a.scroll-to-top', function(e) {
                var $anchor = $(this);
                $('html, body').stop().animate({
                    scrollTop: ($($anchor.attr('href')).offset().top)
                }, 1000, 'easeInOutExpo');
                e.preventDefault();
            });

            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();

            // Initialize popovers
            $('[data-toggle="popover"]').popover();

            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });

        // Form validation
        function validateForm(formId) {
            var form = document.getElementById(formId);
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }

        // Confirm delete
        function confirmDelete(message) {
            return confirm(message || 'Are you sure you want to delete this item?');
        }

        // Format currency
        function formatCurrency(amount) {
            return '$' + parseFloat(amount).toFixed(2);
        }

        // Calculate daily rate
        function calculateDailyRate(monthlyAmount) {
            return parseFloat(monthlyAmount) / 30;
        }

        // Calculate working days
        function calculateWorkingDays(startDate, endDate) {
            var start = new Date(startDate);
            var end = new Date(endDate);
            var diffTime = Math.abs(end - start);
            var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            return diffDays + 1; // Include both start and end dates
        }

        // Calculate salary
        function calculateSalary(monthlySalary, workingDays) {
            var dailyRate = calculateDailyRate(monthlySalary);
            return dailyRate * workingDays;
        }

        // Calculate parking/rental amount
        function calculateRentalAmount(monthlyRate, startDate, endDate) {
            var workingDays = calculateWorkingDays(startDate, endDate);
            var dailyRate = calculateDailyRate(monthlyRate);
            return dailyRate * workingDays;
        }

        // Update calculations on form changes
        $(document).ready(function() {
            // Salary calculation
            $('#monthly_salary, #working_days').on('input', function() {
                var monthlySalary = parseFloat($('#monthly_salary').val()) || 0;
                var workingDays = parseInt($('#working_days').val()) || 0;
                var dailyRate = calculateDailyRate(monthlySalary);
                var totalSalary = calculateSalary(monthlySalary, workingDays);
                
                $('#daily_rate').val(dailyRate.toFixed(2));
                $('#total_salary').val(totalSalary.toFixed(2));
            });

            // Rental calculation
            $('#monthly_rate, #start_date, #end_date').on('input change', function() {
                var monthlyRate = parseFloat($('#monthly_rate').val()) || 0;
                var startDate = $('#start_date').val();
                var endDate = $('#end_date').val();
                
                if (startDate && endDate) {
                    var workingDays = calculateWorkingDays(startDate, endDate);
                    var dailyRate = calculateDailyRate(monthlyRate);
                    var totalAmount = calculateRentalAmount(monthlyRate, startDate, endDate);
                    
                    $('#daily_rate').val(dailyRate.toFixed(2));
                    $('#total_days').val(workingDays);
                    $('#total_amount').val(totalAmount.toFixed(2));
                }
            });

            // Contract calculation
            $('#contract_type, #rate_amount, #working_hours_per_day').on('input', function() {
                var contractType = $('#contract_type').val();
                var rateAmount = parseFloat($('#rate_amount').val()) || 0;
                var workingHoursPerDay = parseInt($('#working_hours_per_day').val()) || 9;
                
                if (contractType === 'hourly') {
                    $('#total_hours_required').prop('readonly', false);
                    $('#total_days_required').prop('readonly', true);
                } else if (contractType === 'daily') {
                    $('#total_hours_required').prop('readonly', true);
                    $('#total_days_required').prop('readonly', false);
                } else if (contractType === 'monthly') {
                    $('#total_hours_required').prop('readonly', true);
                    $('#total_days_required').prop('readonly', true);
                    $('#total_days_required').val(30);
                    $('#total_hours_required').val(30 * workingHoursPerDay);
                }
            });
        });
    </script>
</body>
</html>