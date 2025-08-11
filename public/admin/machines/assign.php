<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

requireAuth();
requireAnyRole(['company_admin','super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$error = '';
$success = '';

$machine_id = (int)($_GET['machine_id'] ?? 0);
if (!$machine_id) { header('Location: index.php'); exit; }

// Ensure assignment table exists
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS machine_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        machine_id INT NOT NULL,
        driver_employee_id INT NOT NULL,
        assistant_employee_id INT NULL,
        start_date DATE NOT NULL,
        end_date DATE NULL,
        status VARCHAR(20) DEFAULT 'active',
        notes TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_company_machine (company_id, machine_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// Load machine
$stmt = $conn->prepare("SELECT id, machine_code, name FROM machines WHERE id = ? AND company_id = ?");
$stmt->execute([$machine_id, $company_id]);
$machine = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$machine) { header('Location: index.php'); exit; }

// Load drivers and assistants
$drivers = [];$assistants = [];
$stmt = $conn->prepare("SELECT id, name, employee_code FROM employees WHERE company_id = ? AND position = 'driver' AND status='active' ORDER BY name");
$stmt->execute([$company_id]);
$drivers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$stmt = $conn->prepare("SELECT id, name, employee_code FROM employees WHERE company_id = ? AND position = 'driver_assistant' AND status='active' ORDER BY name");
$stmt->execute([$company_id]);
$assistants = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $driver_id = (int)($_POST['driver_employee_id'] ?? 0);
        $assistant_id = (int)($_POST['assistant_employee_id'] ?? 0) ?: null;
        $start_date = $_POST['start_date'] ?? date('Y-m-d');
        $notes = $_POST['notes'] ?? null;
        if ($driver_id <= 0) { throw new Exception('Please select a driver.'); }
        // Close any active assignments for this machine
        $conn->prepare("UPDATE machine_assignments SET status='ended', end_date = ? WHERE company_id = ? AND machine_id = ? AND status = 'active'")
             ->execute([date('Y-m-d'), $company_id, $machine_id]);
        // Insert new assignment
        $ins = $conn->prepare("INSERT INTO machine_assignments (company_id, machine_id, driver_employee_id, assistant_employee_id, start_date, status, notes) VALUES (?,?,?,?,?,'active',?)");
        $ins->execute([$company_id, $machine_id, $driver_id, $assistant_id, $start_date, $notes]);
        $success = 'Assignment saved successfully.';
        echo "<script>setTimeout(function(){ window.location.href='view.php?id=" . (int)$machine_id . "'; }, 1500);</script>";
    } catch (Exception $e) { $error = $e->getMessage(); }
}
?>
<div class="container-fluid">
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><?php echo __('assign_operators'); ?></h1>
    <a href="view.php?id=<?php echo $machine_id; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> <?php echo __('back'); ?></a>
  </div>
  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

  <div class="card">
    <div class="card-header"><?php echo __('machine'); ?>: <?php echo htmlspecialchars($machine['machine_code'] . ' - ' . $machine['name']); ?></div>
    <div class="card-body">
      <form method="POST">
        <div class="row">
          <div class="col-md-6">
            <label class="form-label"><?php echo __('driver'); ?> *</label>
            <select class="form-control" name="driver_employee_id" required>
              <option value=""><?php echo __('select_driver'); ?></option>
              <?php foreach ($drivers as $d): ?>
                <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name'] . ' (' . $d['employee_code'] . ')'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label"><?php echo __('assistant'); ?> (<?php echo __('optional'); ?>)</label>
            <select class="form-control" name="assistant_employee_id">
              <option value=""><?php echo __('none'); ?></option>
              <?php foreach ($assistants as $a): ?>
                <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['name'] . ' (' . $a['employee_code'] . ')'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="row mt-3">
          <div class="col-md-4">
            <label class="form-label"><?php echo __('start_date'); ?> *</label>
            <input type="date" class="form-control" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="col-md-8">
            <label class="form-label"><?php echo __('notes'); ?></label>
            <input type="text" class="form-control" name="notes" placeholder="<?php echo __('optional'); ?>">
          </div>
        </div>
        <div class="text-end mt-3">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo __('assign'); ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once '../../../includes/footer.php'; ?>