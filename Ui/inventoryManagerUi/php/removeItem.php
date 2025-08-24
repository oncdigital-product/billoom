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

// --- Prepare DELETE statement ---
$stmt = $conn->prepare("DELETE FROM `$inventoryTbName` WHERE `uid` = ?");
$stmt->bind_param("i", $uid);

// --- Execute and return result ---
if ($stmt->execute()) {
    echo "success";
} else {
    echo "Error: " . $stmt->error;
}

// --- Close connections ---
$stmt->close();
$conn->close();
?>
