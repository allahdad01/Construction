<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole(['super_admin']);
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-credit-card"></i> Subscription Plans
        </h1>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Subscription Plans Management</h6>
                </div>
                <div class="card-body text-center">
                    <i class="fas fa-info-circle fa-4x text-info mb-4"></i>
                    <h4 class="text-gray-800 mb-3">Subscription Plans Feature</h4>
                    <p class="text-gray-600 mb-4">
                        The subscription plans management feature is not implemented in the current schema. 
                        Companies are managed with basic subscription plans (Basic, Professional, Enterprise) 
                        stored directly in the companies table.
                    </p>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card border-left-primary">
                                <div class="card-body">
                                    <h6 class="card-title">Basic Plan</h6>
                                    <p class="card-text">Standard features for small companies</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-left-success">
                                <div class="card-body">
                                    <h6 class="card-title">Professional Plan</h6>
                                    <p class="card-text">Advanced features for growing companies</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-left-warning">
                                <div class="card-body">
                                    <h6 class="card-title">Enterprise Plan</h6>
                                    <p class="card-text">Full features for large companies</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="/constract360/construction/public/super-admin/companies/" class="btn btn-primary">
                            <i class="fas fa-building"></i> Manage Companies
                        </a>
                        <a href="/constract360/construction/public/super-admin/" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>