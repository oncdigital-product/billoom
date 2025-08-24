<?php
date_default_timezone_set("Asia/Kolkata");

include "../../../data/dbConfig.php";



if(!isset($_POST["json"])){
exit("no print json data given");
}

$json=$_POST["json"];


//deduct the inventory stock count
$collectedAmount=0;
$investedAmount=0;


foreach($json["billingItemList"] as $item){
reductQtyOnInventory($db, $dbUserName, $dbPassword, $dbName, $inventoryTbName, $item["uid"], $item["qty"]);
$collectedAmount+=(float)$item["finalPrice"]*(int)$item["qty"];
$investedAmount+=(float)$item["purchasedPrice"]*(int)$item["qty"];
}


recordSale($db, $dbUserName, $dbPassword, $dbName,$saleTable,$json);


//REMOVE QR URL
if($json["paymentMode"]=="ONLINE"){
file_put_contents("../../../data/paymentAmount.txt","done");
}

//add to store balance
$profitAmount=$collectedAmount-$investedAmount;

if(!checkIfAnyRowExists($db, $dbUserName, $dbPassword, $dbName, $transactionTable)){
insertFirstBlankRow($db, $dbUserName, $dbPassword, $dbName, $transactionTable, $amount, $reason, $treasurerId);
}

insertNewInvestedTransactionRecord($db, $dbUserName, $dbPassword, $dbName, $transactionTable, $investedAmount, $profitAmount, "billing", $json["billingUid"],$json['paymentMode']);



echo("success");











//:::::::::::::::::::::::FUNCTIONLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLL

function reductQtyOnInventory($db, $dbUserName, $dbPassword, $dbName, $inventorytb, $uid, $qty) {
    // --- Connect to MySQL ---
    $conn = new mysqli($db, $dbUserName, $dbPassword, $dbName);
    if ($conn->connect_error) {
        return "Connection failed: " . $conn->connect_error;
    }

    // --- Find current quantity ---
    $stmt = $conn->prepare("SELECT `itemCount` FROM `$inventorytb` WHERE `uid` = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $currentQty = (int)$row['itemCount'];

        // If already zero, no deduction
        if ($currentQty <= 0) {
            $stmt->close();
            $conn->close();
            return "No deduction — stock is already zero.";
        }

        // Calculate new quantity, minimum 0
        $newQty = max(0, $currentQty - $qty);

        // --- Update the table ---
        $updateStmt = $conn->prepare("UPDATE `$inventorytb` SET `itemCount` = ? WHERE `uid` = ?");
        $updateStmt->bind_param("ii", $newQty, $uid);

        if ($updateStmt->execute()) {
            $msg = "success";
        } else {
            $msg = "Error updating quantity: " . $updateStmt->error;
        }

        $updateStmt->close();
    } else {
        $msg = "No item found with UID: $uid";
    }

    $stmt->close();
    $conn->close();

    return $msg;
}

function recordSale($db, $dbUserName, $dbPassword, $dbName, $saleTable, $json) {
    // Connect to MySQL
    $conn = new mysqli($db, $dbUserName, $dbPassword, $dbName);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Prepare data
    date_default_timezone_set("Asia/Kolkata"); // Ensure correct timezone
    $dateTime = date('Y-m-d H:i:s'); // MySQL datetime format

    $billingUid = $conn->real_escape_string($json["billingUid"]);
    $billerName = $conn->real_escape_string($json["billerName"]);
    $billerId = $conn->real_escape_string($json["billerId"]);
    $paymentMode = $conn->real_escape_string($json["paymentMode"]);
    $billingItemList = $conn->real_escape_string(json_encode($json["billingItemList"], JSON_UNESCAPED_UNICODE));

    // Insert query
    $sql = "INSERT INTO `$saleTable` 
            (`dateTime`, `billingUid`, `billerName`, `billerId`, `paymentMode`, `billingItemList`) 
            VALUES 
            ('$dateTime', '$billingUid', '$billerName', '$billerId', '$paymentMode', '$billingItemList')";

    if ($conn->query($sql) === TRUE) {
        return true; // Success
    } else {
        return "Error: " . $conn->error;
    }

    $conn->close();
}




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


function insertFirstBlankRow($db, $dbUserName, $dbPassword, $dbName, $transactionTable, $amount, $reason, $treasurerId) {
    $conn = new mysqli($db, $dbUserName, $dbPassword, $dbName);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("INSERT INTO $transactionTable (
        dateTime, 
        invested_balance_before_transaction, invested_balance_transaction_amount, invested_balance_after_transaction,
        profit_balance_before_transaction, profit_transaction_amount, profit_balance_after_transaction,
        reason, treasurerId
    ) VALUES (NOW(), 0, 0, 0, 0, 0, 0, ?, ?)");

    $stmt->bind_param("ss", $reason, $treasurerId);

    $success = $stmt->execute();

    if (!$success) {
        echo "Error inserting first row: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();

    return $success;
}


function insertNewInvestedTransactionRecord(
    $db, $dbUserName, $dbPassword, $dbName, 
    $transactionTable, $investedAmount, $profitAmount, 
    $reason, $treasurerId, $paymentMode
) {
    // Connect to DB
    $conn = new mysqli($db, $dbUserName, $dbPassword, $dbName);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get the latest row to fetch current balances
    $sql = "SELECT * FROM $transactionTable ORDER BY uid DESC LIMIT 1";
    $result = $conn->query($sql);

    $latestInvestedBalance = 0;
    $latestProfitBalance   = 0;
    $latestBalanceInCash   = 0;
    $latestBalanceInOnline = 0;

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $latestInvestedBalance = $row['invested_balance_after_transaction'];
        $latestProfitBalance   = $row['profit_balance_after_transaction'];
        $latestBalanceInCash   = isset($row['balanceInCash']) ? $row['balanceInCash'] : 0;
        $latestBalanceInOnline = isset($row['balanceInOnline']) ? $row['balanceInOnline'] : 0;
    }

    // Calculate new balances
    $newInvestedBalance = $latestInvestedBalance + $investedAmount;
    $newProfitBalance   = $latestProfitBalance + $profitAmount;

    // CASH / ONLINE logic for total balance
    $totalAmount = $investedAmount + $profitAmount;
    if ($paymentMode === "CASH") {
        $newBalanceInCash   = $latestBalanceInCash + $totalAmount;
        $newBalanceInOnline = $latestBalanceInOnline;
    } elseif ($paymentMode === "ONLINE") {
        $newBalanceInCash   = $latestBalanceInCash;
        $newBalanceInOnline = $latestBalanceInOnline + $totalAmount;
    } else {
        $newBalanceInCash   = $latestBalanceInCash;
        $newBalanceInOnline = $latestBalanceInOnline;
    }

    // Prepare insert with correct order
    $stmt = $conn->prepare("INSERT INTO $transactionTable (
        dateTime, 
        invested_balance_before_transaction, invested_balance_transaction_amount, invested_balance_after_transaction,
        profit_balance_before_transaction, profit_transaction_amount, profit_balance_after_transaction,
        reason, treasurerId, balanceInCash, balanceInOnline
    ) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    // 6 doubles, 4 strings/numbers → total 10 placeholders
    $stmt->bind_param(
        "ddddddssss",
        $latestInvestedBalance,  
        $investedAmount,         
        $newInvestedBalance,     
        $latestProfitBalance,    
        $profitAmount,           
        $newProfitBalance,       
        $reason,                 
        $treasurerId,            
        $newBalanceInCash,       
        $newBalanceInOnline
    );

    // Execute query
    if (!$stmt->execute()) {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
