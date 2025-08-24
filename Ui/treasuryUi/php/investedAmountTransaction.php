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

if(!checkIfAnyRowExists($db, $dbUserName, $dbPassword, $dbName, $transactionTable, $amount, $reason, $treasurerId)){
    insertFirstBlankRow($db, $dbUserName, $dbPassword, $dbName, $transactionTable, $amount, $reason, $treasurerId);
}


if($amount<0){//if its withdraw then check sufficient balance
if(!checkIfSufficientBalance($db, $dbUserName, $dbPassword, $dbName, $transactionTable, $amount)){
    exit("in sufficient balance to withdraw in invested balance");
}
}

if($amount<0){//if its withdraw then check if cash blanace and online balance is sufficient
 if(!checkIfSufficientBalanceInCashOrOnlineBalance($db, $dbUserName, $dbPassword, $dbName, $transactionTable, $amount, $cashOrOnline)){
   exit("in sufficient ".strtolower($cashOrOnline)." balance was found in invested balance");
 }
}

insertNewInvestedTransactionRecord($db, $dbUserName, $dbPassword, $dbName, $transactionTable, $amount, $reason, $treasurerId,$cashOrOnline);

echo("success");


// ======================== FUNCTIONS ========================

function checkIfAnyRowExists($db, $dbUserName, $dbPassword, $dbName, $transactionTable, $amount, $reason, $treasurerId) {
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

function checkIfSufficientBalance($db, $dbUserName, $dbPassword, $dbName, $transactionTable, $amount) {
    $conn = new mysqli($db, $dbUserName, $dbPassword, $dbName);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Fetch the latest row by uid instead of dateTime
    $sql = "SELECT invested_balance_after_transaction FROM $transactionTable ORDER BY uid DESC LIMIT 1";
    $result = $conn->query($sql);

    $currentBalance = 0;
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $currentBalance = $row["invested_balance_after_transaction"];
    }

    $conn->close();

    if ($currentBalance + $amount < 0) {
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


function insertNewInvestedTransactionRecord($db, $dbUserName, $dbPassword, $dbName, $transactionTable, $amount, $reason, $treasurerId, $cashOrOnline) {
    $conn = new mysqli($db, $dbUserName, $dbPassword, $dbName);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Fetch latest row by uid
    $sql = "SELECT * FROM $transactionTable ORDER BY uid DESC LIMIT 1";
    $result = $conn->query($sql);

    $latestInvestedBalance = 0;
    $latestProfitBalance   = 0;
    $latestbalanceInCash    = 0;
    $latestbalanceInOnline  = 0;

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $latestInvestedBalance = $row['invested_balance_after_transaction'];
        $latestProfitBalance   = $row['profit_balance_after_transaction'];
        $latestbalanceInCash    = $row['balanceInCash'];
        $latestbalanceInOnline  = $row['balanceInOnline'];
    }

    // CASH / ONLINE logic
    if ($cashOrOnline === "CASH") {
        $newbalanceInCash   = $latestbalanceInCash + $amount;  
        $newbalanceInOnline = $latestbalanceInOnline;          
    } elseif ($cashOrOnline === "ONLINE") {
        $newbalanceInCash   = $latestbalanceInCash;            
        $newbalanceInOnline = $latestbalanceInOnline + $amount; 
    } else {
        $newbalanceInCash   = $latestbalanceInCash;
        $newbalanceInOnline = $latestbalanceInOnline;
    }

    // Existing balance calculations
    $newInvestedBalance = $latestInvestedBalance + $amount;
    $newProfitBalance   = $latestProfitBalance; // unchanged

    $currentDateTime = date("m-d-Y h:i:s");
    $profitWithdrawAmount = 0;

    // Insert new row with correct order (treasurerId first, then balances)
    $stmt = $conn->prepare("INSERT INTO $transactionTable (
        dateTime, 
        invested_balance_before_transaction, invested_balance_transaction_amount, invested_balance_after_transaction,
        profit_balance_before_transaction, profit_transaction_amount, profit_balance_after_transaction,
        reason, treasurerId, balanceInCash, balanceInOnline
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "sdddddddsss",
        $currentDateTime,  
        $latestInvestedBalance, $amount, $newInvestedBalance,
        $latestProfitBalance, $profitWithdrawAmount, $newProfitBalance,
        $reason, $treasurerId, $newbalanceInCash, $newbalanceInOnline
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
