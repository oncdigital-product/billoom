<?php
include "../../../data/dbConfig.php";


$row=getLatestRow($db, $dbUserName, $dbPassword, $dbName, $transactionTable);

echo(json_encode(array("investedBalance"=>$row["invested_balance_after_transaction"],"profitBalance"=>$row["profit_balance_after_transaction"],"balanceInCash"=>$row["balanceInCash"],"balanceInOnline"=>$row["balanceInOnline"])));



function getLatestRow($db, $dbUserName, $dbPassword, $dbName, $transactionTable) {
    // Connect to database
    $conn = new mysqli($db, $dbUserName, $dbPassword, $dbName);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Fetch the latest row by uid descending
    $sql = "SELECT * FROM $transactionTable ORDER BY uid DESC LIMIT 1";
    $result = $conn->query($sql);

    $latestRow = null;
    if ($result && $result->num_rows > 0) {
        $latestRow = $result->fetch_assoc();
    }

    $conn->close();
    return $latestRow;
}



?>