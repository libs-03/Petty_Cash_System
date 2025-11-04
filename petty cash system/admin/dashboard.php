<?php
session_start();
require_once '../classes/database.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$db = new Database();
$conn = $db->connect();

$message = '';
$section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';

// Handle status update for requests
if (isset($_GET['action']) && isset($_GET['id'])) {
    if ($_GET['action'] == 'approve' || $_GET['action'] == 'reject') {
        $status = ($_GET['action'] == 'approve') ? 'Approved' : 'Rejected';
        $stmt = $conn->prepare("UPDATE petty_cash_requests SET status = ? WHERE id = ?");
        $stmt->execute([$status, $_GET['id']]);
        $message = "Request " . strtolower($status) . " successfully.";
    } elseif ($_GET['action'] == 'approve_liquidation' || $_GET['action'] == 'reject_liquidation') {
        $liquidation_status = ($_GET['action'] == 'approve_liquidation') ? 'Approved' : 'Rejected';
        $stmt = $conn->prepare("UPDATE petty_cash_requests SET liquidation_status = ?, status = 'Liquidated' WHERE id = ?");
        $stmt->execute([$liquidation_status, $_GET['id']]);
        $message = "Liquidation " . strtolower($liquidation_status) . " successfully.";
    }
    header("Location: dashboard.php?section=view_requests");
    exit();
}

// Handle adding new employee
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_employee'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $name = trim($_POST['name']);
    $department = trim($_POST['department']);

    if (empty($username) || empty($password) || empty($name) || empty($department)) {
        $message = "All fields are required.";
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $message = "Username already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, name, department) VALUES (?, ?, 'employee', ?, ?)");
            $stmt->execute([$username, $hashed_password, $name, $department]);
            $message = "Employee added successfully.";
        }
    }
    $section = 'employee_list';
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $user_id = $_POST['user_id'];
    $new_password = $_POST['new_password'];

    if (empty($new_password)) {
        $message = "New password is required.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $user_id]);
        $message = "Password reset successfully.";
    }
    $section = 'employee_list';
}

// Fetch data based on section
if ($section == 'dashboard') {
    // Summary statistics
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'employee'");
    $stmt->execute();
    $total_employees = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM petty_cash_requests");
    $stmt->execute();
    $total_requests = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM petty_cash_requests WHERE status = 'Pending'");
    $stmt->execute();
    $pending_requests = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM petty_cash_requests WHERE liquidation_status = 'Pending'");
    $stmt->execute();
    $pending_liquidations = $stmt->fetchColumn();
} elseif ($section == 'employee_list') {
    $stmt = $conn->prepare("SELECT id, username, name, department, role FROM users WHERE role = 'employee' ORDER BY name");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($section == 'view_requests') {
    $stmt = $conn->prepare("SELECT * FROM petty_cash_requests ORDER BY date_requested DESC");
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <div class="logout">
            <a href="../auth/login.php">Logout</a>
        </div>
        <h1>Admin Dashboard</h1>

        <!-- Navigation -->
        <nav>
            <a href="?section=dashboard" class="<?= $section == 'dashboard' ? 'active' : '' ?>">Dashboard</a>
            <a href="?section=employee_list" class="<?= $section == 'employee_list' ? 'active' : '' ?>">Employee List</a>
            <a href="?section=view_requests" class="<?= $section == 'view_requests' ? 'active' : '' ?>">View Requests</a>
        </nav>

        <?php if (!empty($message)): ?>
            <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($section == 'dashboard'): ?>
            <h2>Overview</h2>
            <div class="stats">
                <div class="stat">
                    <h3>Total Employees</h3>
                    <p><?= $total_employees ?></p>
                </div>
                <div class="stat">
                    <h3>Total Requests</h3>
                    <p><?= $total_requests ?></p>
                </div>
                <div class="stat">
                    <h3>Pending Requests</h3>
                    <p><?= $pending_requests ?></p>
                </div>
                <div class="stat">
                    <h3>Pending Liquidations</h3>
                    <p><?= $pending_liquidations ?></p>
                </div>
            </div>

        <?php elseif ($section == 'employee_list'): ?>
            <h2>Employee List</h2>

            <!-- Add New Employee Form -->
            <h3>Add New Employee</h3>
            <form method="POST">
                <label>Username:</label>
                <input type="text" name="username" required>

                <label>Password:</label>
                <input type="password" name="password" required>

                <label>Name:</label>
                <input type="text" name="name" required>

                <label>Department:</label>
                <input type="text" name="department" required>

                <button type="submit" name="add_employee">Add Employee</button>
            </form>

            <h3>All Employees</h3>
            <table>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Department</th>
                    <th>Role</th>
                    <th>Password</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($employees as $emp): ?>
                <tr>
                    <td><?= htmlspecialchars($emp['name']) ?></td>
                    <td><?= htmlspecialchars($emp['username']) ?></td>
                    <td><?= htmlspecialchars($emp['department']) ?></td>
                    <td><?= htmlspecialchars($emp['role']) ?></td>
                    <td>Hashed (Reset Available)</td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="user_id" value="<?= $emp['id'] ?>">
                            <input type="password" name="new_password" placeholder="New Password" required>
                            <button type="submit" name="reset_password">Reset Password</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>

        <?php elseif ($section == 'view_requests'): ?>
            <h2>All Requests</h2>
            <table>
                <tr>
                    <th>Request ID</th>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Amount</th>
                    <th>Category</th>
                    <th>Purpose</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Liquidation</th>
                    <th>Action</th>
                </tr>
                <?php foreach ($requests as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['request_id']) ?></td>
                    <td><?= htmlspecialchars($r['employee_name']) ?></td>
                    <td><?= htmlspecialchars($r['department']) ?></td>
                    <td>₱<?= number_format($r['amount_requested'], 2) ?></td>
                    <td><?= htmlspecialchars($r['expense_category']) ?></td>
                    <td>
                        <?= htmlspecialchars($r['purpose']) ?>
                        <?php if ($r['justification']): ?>
                            <br><small><strong>Justification:</strong> <?= htmlspecialchars(substr($r['justification'], 0, 50)) ?>...</small>
                        <?php endif; ?>
                        <?php if ($r['breakdown']): ?>
                            <br><small><strong>Breakdown:</strong> <?= htmlspecialchars(substr($r['breakdown'], 0, 50)) ?>...</small>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($r['date_requested']) ?></td>
                    <td><?= htmlspecialchars($r['status']) ?></td>
                    <td>
                        <?php if ($r['liquidation_status'] != 'Not Submitted'): ?>
                            <?= htmlspecialchars($r['liquidation_status']) ?>
                            <?php if ($r['total_spent']): ?>
                                <br>Total Spent: ₱<?= number_format($r['total_spent'], 2) ?>
                                <?php
                                // Fetch expense items
                                $stmt_expenses = $conn->prepare("SELECT * FROM liquidation_expenses WHERE request_id = ?");
                                $stmt_expenses->execute([$r['request_id']]);
                                $expenses = $stmt_expenses->fetchAll(PDO::FETCH_ASSOC);
                                if ($expenses): ?>
                                    <br><small>Expenses:</small>
                                    <?php foreach ($expenses as $exp): ?>
                                        <br><small>- <?= htmlspecialchars($exp['description']) ?>: ₱<?= number_format($exp['amount'], 2) ?></small>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if ($r['refund_needed'] > 0): ?>
                                    <br>Refund Needed: ₱<?= number_format($r['refund_needed'], 2) ?>
                                <?php elseif ($r['reimbursement'] > 0): ?>
                                    <br>Reimbursement: ₱<?= number_format($r['reimbursement'], 2) ?>
                                <?php endif; ?>
                                <?php if ($r['receipts']): ?>
                                    <br><small>Receipts: <?= htmlspecialchars(substr($r['receipts'], 0, 50)) ?>...</small>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['status'] == 'Pending'): ?>
                            <a href="?section=view_requests&action=approve&id=<?= $r['id'] ?>">Approve</a> |
                            <a href="?section=view_requests&action=reject&id=<?= $r['id'] ?>">Reject</a>
                        <?php elseif ($r['liquidation_status'] == 'Pending'): ?>
                            <a href="?section=view_requests&action=approve_liquidation&id=<?= $r['id'] ?>">Approve Liquidation</a> |
                            <a href="?section=view_requests&action=reject_liquidation&id=<?= $r['id'] ?>">Reject Liquidation</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
