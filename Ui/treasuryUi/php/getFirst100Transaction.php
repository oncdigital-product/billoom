<?php
include "../../../data/dbConfig.php";
date_default_timezone_set('Asia/Kolkata');




echo(json_encode(getFirst100Transaction($db, $dbUserName, $dbPassword, $dbName, $transactionTable)));






function getFirst100Transaction($db, $dbUserName, $dbPassword, $dbName, $transactionTable) {
    $conn = new mysqli($db, $dbUserName, $dbPassword, $dbName);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get the latest 100 transactions by uid descending
    $sql = "SELECT * FROM $transactionTable ORDER BY uid DESC LIMIT 100";
    $result = $conn->query($sql);

    $transactions = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
    }

    $conn->close();
    return $transactions;
}




?>