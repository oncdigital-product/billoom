<?php
include "../../../data/dbConfig.php";
date_default_timezone_set('Asia/Kolkata');

if(!isset($_POST["amount"])){
    exit("withdraw amount is not given");
}

if(!isset($_POST["reason"])){
    exit("withdraw reason is not given");
}

if(!isset($_POST["treasurerId"])){
    exit("treasurer id is not given");
}

if(!isset($_POST["cashOrOnline"])){
    exit("cash or online is not given");
}

$amount = $_POST["amount"];
$reason = $_POST["reason"];
$treasurerId = $_POST["treasurerId"];
$cashOrOnline=$_POST["cashOrOnline"];

if(!checkIfAnyRowExists($db, $dbUserName, $dbPassword, $dbName, $transactionTable)){
    insertFirstBlankRow($db, $dbUserName, $dbPassword, $dbName, $transactionTable, $reason, $treasurerId);
}

if($amount<0){//if its withdraw then check sufficient balance in profit balance
if(!checkIfSufficientProfitBalance($db, $dbUserName, $dbPassword, $dbName, $transactionTable, $amount)){
exit("in sufficient profit balance was found");
}
}

if($amount<0){//if its withdraw then check if sufficient balance in cash balance and online balance
if(checkIfSufficientBalanceInCashOrOnlineBalance($db, $dbUserName, $dbPassword, $dbName, $transactionTable, $amount, $cashOrOnline)){
exit("in sufficient ".strtolower($cashOrOnline)." balance was found in ");
}
}






insertNewProfitTransactionRecord($db, $dbUserName, $dbPassword, $dbName, $transactionTable, $amount, $reason, $treasurerId,$cashOrOnline);

echo("success");


// ======================== FUNCTIONS ========================

function checkIfAnyRowExists($db, $dbUserName, $dbPassword, $dbName, $transactionTable) {
    $conn = new mysqli($db, $dbUserName, $dbPassword, $dbName);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $sql = "SELECT 1 FROM $transactionTable LIMIT 1";
    $result = $conn->query($sql);
    $rowExists = ($result && $result->num_rows > 0);

    $conn->close();
    return $rowExists;
}

function checkIfSufficientProfitBalance($db, $dbUserName, $dbPassword, $dbName, $transactionTable, $amount) {
    $conn = new mysqli($db, $dbUserName, $dbPassword, $dbName);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $sql = "SELECT profit_balance_after_transaction FROM $transactionTable ORDER BY uid DESC LIMIT 1";
    $result = $conn->query($sql);

    $currentProfitBalance = 0;
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $currentProfitBalance = $row["profit_balance_after_transaction"];
    }

    $conn->close();

    if ($currentProfitBalance + $amount < 0) {
        return false;
    }

    return true;
}

function checkIfSufficientBalanceInCashOrOnlineBalance($db, $dbUserName, $dbPassword, $dbName, $transactionTable, $amount, $cashOrOnline) {
    // Connect to DB
    $conn = new mysqli($db, $dbUserName, $dbPassword, $dbName);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get latest row ordered by uid DESC
    $sql = "SELECT balanceInCash, balanceInOnline FROM $transactionTable ORDER BY uid DESC LIMIT 1";
    $result = $conn->query($sql);

    if ($result && $row = $result->fetch_assoc()) {
        // Check based on type
        if ($cashOrOnline === 'CASH') {
            $newBalance = $row['balanceInCash'] + $amount;
            return $newBalance >= 0;
        } elseif ($cashOrOnline === 'ONLINE') {
            $newBalance = $row['balanceInOnline'] + $amount;
            return $newBalance >= 0;
        }
    }

    // If no rows found, assume insufficient
    return false;
}

function insertNewProfitTransactionRecord($db, $dbUserName, $dbPassword, $dbName, $transactionTable, $amount, $reason, $treasurerId, $cashOrOnline) {
    $conn = new mysqli($db, $dbUserName, $dbPassword, $dbName);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get latest row
    $sql = "SELECT * FROM $transactionTable ORDER BY uid DESC LIMIT 1";
    $result = $conn->query($sql);

    $latestInvestedBalance = 0;
    $latestProfitBalance   = 0;
    $latestCashBalance     = 0;
    $latestOnlineBalance   = 0;

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $latestInvestedBalance = $row['invested_balance_after_transaction'];
        $latestProfitBalance   = $row['profit_balance_after_transaction'];
        $latestCashBalance     = isset($row['balanceInCash']) ? $row['balanceInCash'] : 0;
        $latestOnlineBalance   = isset($row['balanceInOnline']) ? $row['balanceInOnline'] : 0;
    }

    // Balances update
    $newInvestedBalance = $latestInvestedBalance; // unchanged
    $newProfitBalance   = $latestProfitBalance + $amount;

    // CASH / ONLINE logic
    if ($cashOrOnline === "CASH") {
        $newCashBalance   = $latestCashBalance + $amount;  // Add to cash
        $newOnlineBalance = $latestOnlineBalance;          // Keep online same
    } elseif ($cashOrOnline === "ONLINE") {
        $newCashBalance   = $latestCashBalance;            // Keep cash same
        $newOnlineBalance = $latestOnlineBalance + $amount; // Add to online
    } else {
        // If neither CASH nor ONLINE, keep both same
        $newCashBalance   = $latestCashBalance;
        $newOnlineBalance = $latestOnlineBalance;
    }

    $currentDateTime = date("m-d-Y h:i:s");

    // Insert new row with updated balances
    $stmt = $conn->prepare("INSERT INTO $transactionTable (
        dateTime, 
        invested_balance_before_transaction, invested_balance_transaction_amount, invested_balance_after_transaction,
        profit_balance_before_transaction, profit_transaction_amount, profit_balance_after_transaction,
        reason, treasurerId, balanceInCash, balanceInOnline
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $zero = 0; // For invested part since we don't touch it

    $stmt->bind_param(
        "sddddddssss",
        $currentDateTime,  
        $latestInvestedBalance, $zero, $newInvestedBalance,
        $latestProfitBalance, $amount, $newProfitBalance,
        $reason, $treasurerId, $newCashBalance, $newOnlineBalance
    );

    if (!$stmt->execute()) {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}



function insertFirstBlankRow($db, $dbUserName, $dbPassword, $dbName, $transactionTable) {
    $conn = new mysqli($db, $dbUserName, $dbPassword, $dbName);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("INSERT INTO $transactionTable (
        dateTime, 
        invested_balance_before_transaction, invested_balance_transaction_amount, invested_balance_after_transaction,
        profit_balance_before_transaction, profit_transaction_amount, profit_balance_after_transaction,
        reason, treasurerId,balanceInCash,balanceInOnline
    ) VALUES (NOW(), 0, 0, 0, 0, 0, 0, 0, 0,0,0)");


    $success = $stmt->execute();

    if (!$success) {
        echo "Error inserting first row: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();

    return $success;
}
?>
