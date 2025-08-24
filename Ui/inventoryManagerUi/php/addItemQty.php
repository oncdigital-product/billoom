<?php
include "../../../data/dbConfig.php";

// --- Validate UID ---
if (!isset($_POST['uid']) || $_POST['uid'] === '') {
    exit("UID missing.");
}

$uid = $_POST['uid'];

// --- Connect to MySQL ---
$conn = new mysqli($db, $dbUserName, $dbPassword, $dbName);
if ($conn->connect_error) {
    exit("Connection failed: " . $conn->connect_error);
}

// --- Prepare UPDATE statement ---
$stmt = $conn->prepare("UPDATE `$inventoryTbName` SET `itemCount` = `itemCount` + 1 WHERE `uid` = ?");
$stmt->bind_param("i", $uid);

// --- Execute and return result ---
if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo "success";
    } else {
        echo "No row found with UID: $uid";
    }
} else {
    echo "Error: " . $stmt->error;
}

// --- Close connections ---
$stmt->close();
$conn->close();
?>
