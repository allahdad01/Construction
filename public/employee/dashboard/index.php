<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

requireAuth();
requireAnyRole(['driver','driver_assistant']);

$db = new Database();
$conn = $db->getConnection();
$user = getCurrentUser();
$company_id = getCurrentCompanyId();

$page_title = __('employee_dashboard');

// Resolve employee and pay settings
$emp = null; $cols = [];
try { $rs = $conn->query("SHOW COLUMNS FROM employees"); foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $c) { $cols[] = $c['Field']; } } catch (Exception $e) {}
$empStmt = $conn->prepare('SELECT * FROM employees WHERE company_id = ? AND user_id = ? LIMIT 1');
$empStmt->execute([$company_id, $user['id']]);
$emp = $empStmt->fetch(PDO::FETCH_ASSOC);

$today = new DateTime();
$monthStart = new DateTime(date('Y-m-01'));
// Start from hire date if available and later than month start
if ($emp && in_array('hire_date', $cols, true) && !empty($emp['hire_date'])) {
    try { $hd = new DateTime($emp['hire_date']); if ($hd > $monthStart) { $monthStart = $hd; } } catch (Exception $e) {}
}
$startStr = $monthStart->format('Y-m-d');
$endStr = $today->format('Y-m-d');

$daysInRange = (int)$monthStart->diff($today)->days + 1;
// Leave days in range
$leaveDays = 0;
$suspensionDays = 0;
if ($emp) {
    // Count leave days
    $st = $conn->prepare("SELECT COUNT(*) FROM employee_attendance WHERE company_id = ? AND employee_id = ? AND status='leave' AND date BETWEEN ? AND ?");
    $st->execute([$company_id, $emp['id'], $startStr, $endStr]);
    $leaveDays = (int)$st->fetchColumn();

    // Count suspension days
    $suspensionStmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM employee_work_suspensions 
        WHERE employee_id = ? AND company_id = ? 
        AND (
            (suspension_start_date BETWEEN ? AND ?) 
            OR 
            (suspension_end_date BETWEEN ? AND ?) 
            OR 
            (suspension_start_date <= ? AND suspension_end_date >= ?)
        )
        AND status != 'ended'
    ");
    $suspensionStmt->execute([
        $emp['id'], 
        $company_id, 
        $startStr, $endStr,  // Suspension start within range
        $startStr, $endStr,  // Suspension end within range
        $startStr, $endStr   // Suspension spans the entire range
    ]);
    $suspensionDays = (int)$suspensionStmt->fetchColumn();
}
$workedDays = max(0, $daysInRange - $leaveDays - $suspensionDays);

// Calculate comprehensive work days excluding leave and suspension
$overallWorkDays = 0;
$overallLeaveDays = 0;
$overallSuspensionDays = 0;
$hireDate = null;

if ($emp) {
    // Get hire date
    $hireDate = !empty($emp['hire_date']) ? new DateTime($emp['hire_date']) : null;
    
    if ($hireDate) {
        $today = new DateTime();
        
        // Prepare query to count work days, excluding leave and suspension
        $workDaysQuery = $conn->prepare("
            WITH date_range AS (
                SELECT 
                    DATE_ADD(?, INTERVAL (t.n - 1) DAY) AS date
                FROM (
                    SELECT a.N + b.N * 10 + 1 AS n
                    FROM 
                        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
                        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
                ) t
                WHERE DATE_ADD(?, INTERVAL (t.n - 1) DAY) <= ?
            ),
            leave_days AS (
                SELECT date 
                FROM employee_attendance 
                WHERE employee_id = ? 
                AND company_id = ? 
                AND status = 'leave'
            ),
            suspension_days AS (
                SELECT 
                    dr.date
                FROM date_range dr
                JOIN employee_work_suspensions ews ON 
                    dr.date BETWEEN ews.suspension_start_date AND COALESCE(ews.suspension_end_date, CURRENT_DATE)
                WHERE ews.employee_id = ? 
                AND ews.company_id = ?
                AND ews.status != 'ended'
            )
            SELECT 
                COUNT(DISTINCT dr.date) as total_days,
                COUNT(DISTINCT ld.date) as total_leave_days,
                COUNT(DISTINCT sd.date) as total_suspension_days
            FROM date_range dr
            LEFT JOIN leave_days ld ON dr.date = ld.date
            LEFT JOIN suspension_days sd ON dr.date = sd.date
            WHERE ld.date IS NULL AND sd.date IS NULL
        ");

        $workDaysQuery->execute([
            $hireDate->format('Y-m-d'),  // Start date for generating range
            $hireDate->format('Y-m-d'),  // Start date for generating range
            $today->format('Y-m-d'),     // End date
            $emp['id'],                  // Employee ID for leave days
            $company_id,                 // Company ID for leave days
            $emp['id'],                  // Employee ID for suspension days
            $company_id                  // Company ID for suspension days
        ]);
        $workDaysResult = $workDaysQuery->fetch(PDO::FETCH_ASSOC);

        // Set calculated values
        $overallWorkDays = (int)($workDaysResult['total_days'] ?? 0);
        $overallLeaveDays = (int)($workDaysResult['total_leave_days'] ?? 0);
        $overallSuspensionDays = (int)($workDaysResult['total_suspension_days'] ?? 0);
    }
}

// Calculate total days since hire
$totalDaysSinceHire = $hireDate ? (int)$hireDate->diff(new DateTime())->days + 1 : 0;

// Determine daily rate and currency
$currency = 'USD';
$dailyRate = 0.0;
if ($emp) {
    if (in_array('salary_currency', $cols, true) && !empty($emp['salary_currency'])) { $currency = $emp['salary_currency']; }
    if (in_array('daily_rate', $cols, true) && isset($emp['daily_rate']) && $emp['daily_rate'] > 0) {
        $dailyRate = (float)$emp['daily_rate'];
    } elseif (in_array('monthly_salary', $cols, true) && isset($emp['monthly_salary']) && $emp['monthly_salary'] > 0) {
        $dailyRate = (float)$emp['monthly_salary'] / 30.0;
    } elseif (in_array('salary_amount', $cols, true) && isset($emp['salary_amount']) && $emp['salary_amount'] > 0) {
        $dailyRate = (float)$emp['salary_amount'] / 30.0;
    }
}
$expectedPay = $workedDays * $dailyRate;

// Paid this month in same currency
$paidThisMonth = 0.0;
if ($emp) {
    $payStmt = $conn->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM salary_payments WHERE company_id = ? AND employee_id = ? AND currency = ? AND payment_date BETWEEN ? AND ?");
    $payStmt->execute([$company_id, $emp['id'], $currency, $startStr, $endStr]);
    $paidThisMonth = (float)$payStmt->fetchColumn();
}
$remaining = max(0.0, $expectedPay - $paidThisMonth);

// Function to convert days to human-readable months and days
function formatWorkDays($totalDays) {
    $months = floor($totalDays / 30);
    $remainingDays = $totalDays % 30;
    
    if ($months > 0 && $remainingDays > 0) {
        return sprintf("%d %s %d %s", 
            $months, 
            $months == 1 ? 'month' : 'months', 
            $remainingDays, 
            $remainingDays == 1 ? 'day' : 'days'
        );
    } elseif ($months > 0) {
        return sprintf("%d %s", 
            $months, 
            $months == 1 ? 'month' : 'months'
        );
    } else {
        return sprintf("%d %s", 
            $remainingDays, 
            $remainingDays == 1 ? 'day' : 'days'
        );
    }
}

// Fetch payment details
$paymentDetailsStmt = $conn->prepare("
    SELECT 
        payment_date, 
        amount_paid, 
        currency,
        MONTH(payment_date) as payment_month,
        YEAR(payment_date) as payment_year
    FROM salary_payments 
    WHERE employee_id = ? AND company_id = ?
    ORDER BY payment_date DESC
    LIMIT 12
");
$paymentDetails = [];
if ($emp) {
    $paymentDetailsStmt->execute([$emp['id'], $company_id]);
    $paymentDetails = $paymentDetailsStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Prepare payment details with formatted months
$formattedPaymentDetails = [];
foreach ($paymentDetails as $payment) {
    $formattedPaymentDetails[] = [
        'month' => date('F Y', strtotime($payment['payment_date'])),
        'amount' => formatCurrencyAmount($payment['amount_paid'], $payment['currency']),
        'currency' => $payment['currency']
    ];
}

// Calculate comprehensive financial details
$financialDetails = [
    'total_earnings' => 0.0,
    'total_paid' => 0.0,
    'remaining_balance' => 0.0
];

if ($emp) {
    // Calculate total earnings since hire date
    $earningsQuery = $conn->prepare("
        SELECT 
            COALESCE(SUM(
                CASE 
                    WHEN daily_rate IS NOT NULL THEN daily_rate * worked_days 
                    ELSE (monthly_salary / 30.0) * worked_days 
                END
            ), 0) as total_earnings,
            COALESCE(SUM(worked_days), 0) as total_worked_days
        FROM (
            SELECT 
                e.daily_rate,
                e.monthly_salary,
                COUNT(DISTINCT ea.date) as worked_days
            FROM employees e
            JOIN employee_attendance ea ON e.id = ea.employee_id
            LEFT JOIN employee_work_suspensions ews ON 
                ea.employee_id = ews.employee_id AND 
                ea.date BETWEEN ews.suspension_start_date AND COALESCE(ews.suspension_end_date, CURRENT_DATE)
            WHERE e.id = ? AND e.company_id = ?
            AND ea.status = 'present'
            AND (ews.id IS NULL OR ews.status = 'ended')
        ) as earnings_calculation
    ");
    $earningsQuery->execute([$emp['id'], $company_id]);
    $earningsResult = $earningsQuery->fetch(PDO::FETCH_ASSOC);

    // Calculate total payments
    $paymentsQuery = $conn->prepare("
        SELECT 
            COALESCE(SUM(amount_paid), 0) as total_paid,
            COUNT(*) as payment_count
        FROM salary_payments
        WHERE employee_id = ? AND company_id = ?
    ");
    $paymentsQuery->execute([$emp['id'], $company_id]);
    $paymentsResult = $paymentsQuery->fetch(PDO::FETCH_ASSOC);

    // Determine daily rate
    $dailyRate = 0.0;
    if (!empty($emp['daily_rate']) && $emp['daily_rate'] > 0) {
        $dailyRate = (float)$emp['daily_rate'];
    } elseif (!empty($emp['monthly_salary']) && $emp['monthly_salary'] > 0) {
        $dailyRate = (float)$emp['monthly_salary'] / 30.0;
    }

    // Populate financial details
    $financialDetails = [
        'total_earnings' => (float)$earningsResult['total_earnings'],
        'total_paid' => (float)$paymentsResult['total_paid'],
        'remaining_balance' => (float)$earningsResult['total_earnings'] - (float)$paymentsResult['total_paid'],
        'total_worked_days' => (int)$earningsResult['total_worked_days'],
        'payment_count' => (int)$paymentsResult['payment_count'],
        'daily_rate' => $dailyRate
    ];
}

// Prepare detailed payment history
$detailedPaymentHistoryStmt = $conn->prepare("
    SELECT 
        payment_date, 
        amount_paid, 
        currency,
        MONTH(payment_date) as payment_month,
        YEAR(payment_date) as payment_year,
        payment_method,
        notes
    FROM salary_payments 
    WHERE employee_id = ? AND company_id = ?
    ORDER BY payment_date DESC
    LIMIT 12
");
$detailedPaymentHistory = [];
if ($emp) {
    $detailedPaymentHistoryStmt->execute([$emp['id'], $company_id]);
    $detailedPaymentHistory = $detailedPaymentHistoryStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch suspension history
$suspensionHistoryStmt = $conn->prepare("
    SELECT 
        id,
        suspension_start_date,
        suspension_end_date,
        reason,
        status
    FROM employee_work_suspensions
    WHERE employee_id = ? AND company_id = ?
    ORDER BY suspension_start_date DESC
");
$suspensionHistory = [];
if ($emp) {
    $suspensionHistoryStmt->execute([$emp['id'], $company_id]);
    $suspensionHistory = $suspensionHistoryStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <h1 class="h3 mb-4"><?php echo __('welcome'); ?>, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
    </div>
  </div>

  <div class="row g-4 mb-3">
    <div class="col-md-4">
      <div class="card">
        <div class="card-header"><strong><?php echo __('worked_days'); ?> (<?php echo __('this_month'); ?>)</strong></div>
        <div class="card-body">
          <div class="h4 mb-0"><?php echo number_format($workedDays); ?></div>
          <div class="text-muted small">
            <?php echo number_format($daysInRange); ?> <?php echo __('days_in_period'); ?> • 
            <?php echo number_format($leaveDays); ?> <?php echo __('leave'); ?> • 
            <?php echo number_format($suspensionDays); ?> <?php echo __('suspension'); ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-header"><strong><?php echo __('overall_work_days'); ?></strong></div>
        <div class="card-body">
          <div class="h4 mb-0"><?php echo formatWorkDays($overallWorkDays); ?></div>
          <div class="text-muted small">
            <?php echo number_format($totalDaysSinceHire); ?> <?php echo __('total_days'); ?> • 
            <?php echo number_format($overallLeaveDays); ?> <?php echo __('leave'); ?> • 
            <?php echo number_format($overallSuspensionDays); ?> <?php echo __('suspension'); ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-header"><strong><?php echo __('payment_history'); ?></strong></div>
        <div class="card-body">
          <?php if (!empty($detailedPaymentHistory)): ?>
            <div class="list-group list-group-flush">
              <?php foreach ($detailedPaymentHistory as $payment): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                  <span><?php echo htmlspecialchars($payment['payment_date']); ?></span>
                  <span class="badge bg-primary"><?php echo formatCurrencyAmount($payment['amount_paid'], $payment['currency']); ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-muted text-center"><?php echo __('no_payment_history'); ?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-3">
    <div class="col-md-4">
      <div class="card">
        <div class="card-header"><strong><?php echo __('earnings'); ?></strong></div>
        <div class="card-body">
          <div class="h4 mb-0"><?php echo formatCurrencyAmount($financialDetails['total_earnings'], $currency); ?></div>
          <div class="text-muted small">
            <?php echo number_format($financialDetails['total_worked_days']); ?> <?php echo __('worked_days'); ?> • 
            <?php echo formatCurrencyAmount($financialDetails['daily_rate'], $currency); ?> <?php echo __('daily_rate'); ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-header"><strong><?php echo __('payments'); ?></strong></div>
        <div class="card-body">
          <div class="h4 mb-0"><?php echo formatCurrencyAmount($financialDetails['total_paid'], $currency); ?></div>
          <div class="text-muted small">
            <?php echo $financialDetails['payment_count']; ?> <?php echo __('payments_made'); ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-header">
          <strong>
            <?php 
            if ($financialDetails['remaining_balance'] > 0) {
              echo __('remaining_balance');
            } elseif ($financialDetails['remaining_balance'] < 0) {
              echo __('overpaid');
            } else {
              echo __('balance');
            }
            ?>
          </strong>
        </div>
        <div class="card-body">
          <div class="h4 mb-0 <?php 
            echo $financialDetails['remaining_balance'] > 0 ? 'text-success' : 
                 ($financialDetails['remaining_balance'] < 0 ? 'text-danger' : 'text-muted');
          ?>">
            <?php echo formatCurrencyAmount(abs($financialDetails['remaining_balance']), $currency); ?>
          </div>
          <div class="text-muted small">
            <?php 
            if ($financialDetails['remaining_balance'] > 0) {
              echo __('amount_to_be_paid');
            } elseif ($financialDetails['remaining_balance'] < 0) {
              echo __('amount_overpaid');
            } else {
              echo __('fully_paid');
            }
            ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-md-4">
      <div class="card">
        <div class="card-header"><strong><?php echo __('quick_actions'); ?></strong></div>
        <div class="card-body">
          <a href="<?php echo $base_url; ?>employee/attendance/" class="btn btn-primary w-100 mb-2"><i class="fas fa-clock"></i> <?php echo __('view_attendance'); ?></a>
          <a href="<?php echo $base_url; ?>employee/salary/" class="btn btn-success w-100 mb-2"><i class="fas fa-money-bill-wave"></i> <?php echo __('view_salary'); ?></a>
          <a href="<?php echo $base_url; ?>employee/contracts/" class="btn btn-info w-100"><i class="fas fa-file-contract"></i> <?php echo __('assigned_contracts'); ?></a>
        </div>
      </div>
    </div>
    <div class="col-md-8">
      <div class="card">
        <div class="card-header"><strong><?php echo __('suspension_history'); ?></strong></div>
        <div class="card-body">
          <?php if (empty($suspensionHistory)): ?>
            <div class="text-center text-muted py-4">
              <i class="fas fa-pause-circle fa-3x mb-3"></i>
              <p><?php echo __('no_suspension_history'); ?></p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th><?php echo __('start_date'); ?></th>
                    <th><?php echo __('end_date'); ?></th>
                    <th><?php echo __('reason'); ?></th>
                    <th><?php echo __('status'); ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($suspensionHistory as $suspension): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($suspension['suspension_start_date']); ?></td>
                      <td><?php echo htmlspecialchars($suspension['suspension_end_date'] ?? 'Not Set'); ?></td>
                      <td><?php echo htmlspecialchars($suspension['reason'] ?? 'No reason provided'); ?></td>
                      <td>
                        <span class="badge bg-<?php 
                          echo $suspension['status'] === 'active' ? 'warning' : 
                               ($suspension['status'] === 'ended' ? 'success' : 'secondary'); 
                        ?>">
                          <?php echo ucfirst($suspension['status']); ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once '../../../includes/footer.php'; ?>
