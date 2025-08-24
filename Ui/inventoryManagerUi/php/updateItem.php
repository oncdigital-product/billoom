<?php
include "../../../data/dbConfig.php";

// --- Get POST data ---
if (!isset($_POST['item'])) {
    exit("No item data received.");
}

// Decode JSON string to PHP array/object
$item =$_POST['item'];
if (!$item) {
    exit("Invalid item data.");
}

// Validate required fields
if (
    !isset($item['uid'], $item['dateTime'], $item['name'], $item['displayPrice'], $item['finalPrice'], $item['itemCount']) ||
    $item['uid'] === '' ||
    $item['dateTime'] === '' ||
    $item['name'] === '' ||
    $item['displayPrice'] === '' ||
    $item['finalPrice'] === '' ||
    $item['itemCount'] === ''
) {
    exit("Some fields are missing.");
}

// Extract variables
$uid          = $item['uid'];
$dateTime     = $item['dateTime'];
$name         = $item['name'];
$displayPrice = (float)$item['displayPrice'];
$finalPrice   = (float)$item['finalPrice'];
$itemCount    = (int)$item['itemCount'];

// --- Connect to MySQL ---
$conn = new mysqli($db, $dbUserName, $dbPassword, $dbName);
if ($conn->connect_error) {
    exit("Connection failed: " . $conn->connect_error);
}

// --- Prepare update statement ---
$stmt = $conn->prepare("
    UPDATE `$inventoryTbName` 
    SET `dateTime` = ?, `name` = ?, `displayPrice` = ?, `finalPrice` = ?, `itemCount` = ?
    WHERE `uid` = ?
");
$stmt->bind_param("ssddii", $dateTime, $name, $displayPrice, $finalPrice, $itemCount, $uid);

// Execute
if ($stmt->execute()) {
    echo "success";
} else {
    echo "Error: " . $stmt->error;
}

// Close connections
$stmt->close();
$conn->close();
?>
