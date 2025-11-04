<?php
session_start();
require_once "../classes/database.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'employee') {
    header("Location: ../auth/login.php");
    exit();
}

$user = $_SESSION['user'];
$username = $user['username'];
$user_id = $user['id'];

$conn = (new Database())->connect();
$stmt = $conn->prepare("SELECT * FROM petty_cash_requests WHERE user_id = ? ORDER BY date_requested DESC");
$stmt->execute([$user_id]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <div class="logout">
            <a href="add_request.php">+ Add New Request</a> |
            <a href="../auth/login.php">Logout</a>
        </div>
        <h1>Employee Dashboard</h1>
        <p>Welcome, <?php echo htmlspecialchars($username); ?>!</p>

        <h3>Your Requests</h3>
        <table>
        <tr>
            <th>Request ID</th>
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
                <?php if ($r['status'] == 'Approved'): ?>
                    <?php if ($r['liquidation_status'] == 'Not Submitted'): ?>
                        <a href="liquidate.php?id=<?= $r['id'] ?>">Submit Liquidation</a>
                    <?php else: ?>
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
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    N/A
                <?php endif; ?>
            </td>
            <td>
                <?php if ($r['status'] == 'Pending'): ?>
                    <em>Waiting for approval</em>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </table>
    </div>
</body>
</html>
