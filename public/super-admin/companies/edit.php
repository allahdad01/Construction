<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$company_id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

if (!$company_id) {
    header('Location: /constract360/construction/public/super-admin/companies/');
    exit;
}

// Get company details
$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$company) {
    header('Location: /constract360/construction/public/super-admin/companies/');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $subscription_plan = $_POST['subscription_plan'] ?? 'basic';
    $subscription_status = $_POST['subscription_status'] ?? 'trial';
    $trial_ends_at = $_POST['trial_ends_at'] ?? null;
    $max_employees = (int)($_POST['max_employees'] ?? 25);
    $max_machines = (int)($_POST['max_machines'] ?? 50);
    $max_projects = (int)($_POST['max_projects'] ?? 25);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (empty($company_name)) {
        $error = 'Company name is required.';
    } elseif (empty($contact_email)) {
        $error = 'Contact email is required.';
    } elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Check if email is already used by another company
            $stmt = $conn->prepare("SELECT id FROM companies WHERE contact_email = ? AND id != ?");
            $stmt->execute([$contact_email, $company_id]);
            if ($stmt->fetch()) {
                $error = 'This email address is already in use by another company.';
            } else {
                // Update company
                $stmt = $conn->prepare("
                    UPDATE companies SET 
                        company_name = ?, 
                        contact_person = ?, 
                        contact_email = ?, 
                        contact_phone = ?, 
                        address = ?, 
                        city = ?, 
                        state = ?, 
                        country = ?, 
                        subscription_plan = ?, 
                        subscription_status = ?, 
                        trial_ends_at = ?, 
                        max_employees = ?, 
                        max_machines = ?, 
                        max_projects = ?, 
                        is_active = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $company_name, $contact_person, $contact_email, $contact_phone,
                    $address, $city, $state, $country, $subscription_plan,
                    $subscription_status, $trial_ends_at, $max_employees,
                    $max_machines, $max_projects, $is_active, $company_id
                ]);

                $success = 'Company updated successfully!';
                
                // Refresh company data
                $stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
                $stmt->execute([$company_id]);
                $company = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $error = 'Error updating company: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit"></i> Edit Company
        </h1>
        <div>
            <a href="/constract360/construction/public/super-admin/companies/" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Companies
            </a>
            <a href="/constract360/construction/public/super-admin/companies/view.php?id=<?php echo $company['id']; ?>" class="btn btn-info btn-sm">
                <i class="fas fa-eye"></i> View Company
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Edit Company Information</h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="mb-3">Basic Information</h5>
                        
                        <div class="mb-3">
                            <label for="company_name" class="form-label">Company Name *</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" 
                                   value="<?php echo htmlspecialchars($company['company_name']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="contact_person" class="form-label">Contact Person</label>
                            <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                   value="<?php echo htmlspecialchars($company['contact_person'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="contact_email" class="form-label">Contact Email *</label>
                            <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                   value="<?php echo htmlspecialchars($company['contact_email']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="contact_phone" class="form-label">Contact Phone</label>
                            <input type="text" class="form-control" id="contact_phone" name="contact_phone" 
                                   value="<?php echo htmlspecialchars($company['contact_phone'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($company['address'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <h5 class="mb-3">Location & Subscription</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city" 
                                           value="<?php echo htmlspecialchars($company['city'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="state" class="form-label">State</label>
                                    <input type="text" class="form-control" id="state" name="state" 
                                           value="<?php echo htmlspecialchars($company['state'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="country" class="form-label">Country</label>
                            <input type="text" class="form-control" id="country" name="country" 
                                   value="<?php echo htmlspecialchars($company['country'] ?? ''); ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="subscription_plan" class="form-label">Subscription Plan</label>
                                    <select class="form-control" id="subscription_plan" name="subscription_plan">
                                        <option value="basic" <?php echo $company['subscription_plan'] === 'basic' ? 'selected' : ''; ?>>Basic</option>
                                        <option value="professional" <?php echo $company['subscription_plan'] === 'professional' ? 'selected' : ''; ?>>Professional</option>
                                        <option value="enterprise" <?php echo $company['subscription_plan'] === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="subscription_status" class="form-label">Status</label>
                                    <select class="form-control" id="subscription_status" name="subscription_status">
                                        <option value="trial" <?php echo $company['subscription_status'] === 'trial' ? 'selected' : ''; ?>>Trial</option>
                                        <option value="active" <?php echo $company['subscription_status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="suspended" <?php echo $company['subscription_status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                        <option value="cancelled" <?php echo $company['subscription_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="trial_ends_at" class="form-label">Trial Ends At</label>
                            <input type="date" class="form-control" id="trial_ends_at" name="trial_ends_at" 
                                   value="<?php echo $company['trial_ends_at'] ?? ''; ?>">
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                       <?php echo $company['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">
                                    Company is active
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <h5 class="mb-3">Limits</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="max_employees" class="form-label">Max Employees</label>
                                    <input type="number" class="form-control" id="max_employees" name="max_employees" 
                                           value="<?php echo $company['max_employees']; ?>" min="1" max="1000">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="max_machines" class="form-label">Max Machines</label>
                                    <input type="number" class="form-control" id="max_machines" name="max_machines" 
                                           value="<?php echo $company['max_machines']; ?>" min="1" max="1000">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="max_projects" class="form-label">Max Projects</label>
                                    <input type="number" class="form-control" id="max_projects" name="max_projects" 
                                           value="<?php echo $company['max_projects']; ?>" min="1" max="1000">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Company
                        </button>
                        <a href="/constract360/construction/public/super-admin/companies/" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        alert.style.display = 'none';
    });
}, 5000);
</script>

<?php require_once '../../../includes/footer.php'; ?>