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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $employee_name = $user['name'];
    $department = $user['department'];
    $amount_requested = $_POST['amount_requested'];
    $purpose = $_POST['purpose'];
    $expense_category = $_POST['expense_category'];
    $justification = $_POST['justification'];
    $breakdown = $_POST['breakdown'];
    $date_requested = date("Y-m-d");
    $status = "Pending";

    $stmt = $conn->prepare("INSERT INTO petty_cash_requests (user_id, employee_name, department, amount_requested, purpose, expense_category, justification, breakdown, date_requested, status)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user['id'], $employee_name, $department, $amount_requested, $purpose, $expense_category, $justification, $breakdown, $date_requested, $status]);

    // Auto-generate request_id
    $last_id = $conn->lastInsertId();
    $request_id = 'PCR-' . str_pad($last_id, 3, '0', STR_PAD_LEFT);
    $stmt = $conn->prepare("UPDATE petty_cash_requests SET request_id = ? WHERE id = ?");
    $stmt->execute([$request_id, $last_id]);

    echo "<script>alert('Request submitted successfully! Request ID: $request_id'); window.location.href='dashboard.php';</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Petty Cash Request</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <h2>Add Petty Cash Request</h2>
        <p><strong>Employee:</strong> <?php echo htmlspecialchars($user['name']); ?> | <strong>Department:</strong> <?php echo htmlspecialchars($user['department']); ?></p>
        <form method="POST">
            <label>Amount Requested (₱):</label>
            <input type="number" step="0.01" name="amount_requested" required>

            <label>Purpose/Description:</label>
            <textarea name="purpose" required></textarea>

            <label>Expense Category:</label>
            <select name="expense_category" required>
                <option value="">Select Category</option>
                <option value="Office Supplies">Office Supplies</option>
                <option value="Travel">Travel</option>
                <option value="Meals">Meals</option>
                <option value="Transportation">Transportation</option>
                <option value="Miscellaneous">Miscellaneous</option>
            </select>

            <label>Justification:</label>
            <textarea name="justification" placeholder="Explain why this expense is necessary..." required></textarea>

            <label>Detailed Breakdown:</label>
            <textarea name="breakdown" placeholder="Provide a detailed breakdown of the expenses..." required></textarea>

            <button type="submit">Submit Request</button>
        </form>

        <a href="dashboard.php">Back to Dashboard</a>
    </div>
</body>
</html>
