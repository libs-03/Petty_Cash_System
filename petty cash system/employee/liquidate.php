<?php
session_start();
require_once '../classes/database.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'employee') {
    header("Location: ../auth/login.php");
    exit();
}

$user = $_SESSION['user'];
$db = new Database();
$conn = $db->connect();

$request_id = $_GET['id'] ?? null;
if (!$request_id) {
    header("Location: dashboard.php");
    exit();
}

// Get request details
$stmt = $conn->prepare("SELECT * FROM petty_cash_requests WHERE id = ? AND user_id = ? AND status = 'Approved'");
$stmt->execute([$request_id, $user['id']]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $descriptions = $_POST['description'];
    $amounts = $_POST['amount'];
    $receipts = $_POST['receipts'];
    $date_liquidated = date("Y-m-d");

    $total_spent = 0;
    foreach ($amounts as $amount) {
        $total_spent += floatval($amount);
    }

    $refund_needed = $request['amount_requested'] - $total_spent;
    $reimbursement = $total_spent - $request['amount_requested'];

    // Insert expense items into liquidation_expenses table
    foreach ($descriptions as $key => $description) {
        if (!empty($description) && !empty($amounts[$key])) {
            $stmt = $conn->prepare("INSERT INTO liquidation_expenses (request_id, description, amount) VALUES (?, ?, ?)");
            $stmt->execute([$request['request_id'], $description, $amounts[$key]]);
        }
    }

    $stmt = $conn->prepare("UPDATE petty_cash_requests SET
                            actual_expenses = ?,
                            total_spent = ?,
                            receipts = ?,
                            refund_needed = ?,
                            reimbursement = ?,
                            liquidation_status = 'Pending',
                            date_liquidated = ?
                            WHERE id = ?");
    $stmt->execute([$total_spent, $total_spent, $receipts, $refund_needed > 0 ? $refund_needed : 0, $reimbursement > 0 ? $reimbursement : 0, $date_liquidated, $request_id]);

    echo "<script>alert('Liquidation submitted successfully!'); window.location.href='dashboard.php';</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liquidate Petty Cash Request</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <h2>Liquidate Petty Cash Request</h2>

        <p><strong>Request ID:</strong> <?php echo htmlspecialchars($request['request_id']); ?></p>
        <p><strong>Amount Requested:</strong> ₱<?php echo number_format($request['amount_requested'], 2); ?></p>
        <p><strong>Purpose:</strong> <?php echo htmlspecialchars($request['purpose']); ?></p>

        <form method="POST" id="liquidationForm">
            <h3>Expense Items</h3>
            <div id="expenseItems">
                <div class="expense-row">
                    <input type="text" name="description[]" placeholder="Description" required>
                    <input type="number" step="0.01" name="amount[]" placeholder="Amount (₱)" required>
                    <button type="button" class="remove-expense" onclick="removeExpense(this)">Remove</button>
                </div>
            </div>
            <button type="button" onclick="addExpense()">Add Another Expense</button>

            <label>Attach Receipt:</label>
            <textarea name="receipts" placeholder="Describe receipts or attach details..." required></textarea>

            <button type="submit">Submit Liquidation</button>
        </form>

        <a href="dashboard.php">Back to Dashboard</a>
    </div>

    <script>
        function addExpense() {
            const expenseItems = document.getElementById('expenseItems');
            const newRow = document.createElement('div');
            newRow.className = 'expense-row';
            newRow.innerHTML = `
                <input type="text" name="description[]" placeholder="Description" required>
                <input type="number" step="0.01" name="amount[]" placeholder="Amount (₱)" required>
                <button type="button" class="remove-expense" onclick="removeExpense(this)">Remove</button>
            `;
            expenseItems.appendChild(newRow);
        }

        function removeExpense(button) {
            const expenseItems = document.getElementById('expenseItems');
            if (expenseItems.children.length > 1) {
                button.parentElement.remove();
            }
        }
    </script>
</body>
</html>
