<?php
include "../../../data/dbConfig.php";

// --- Validation ---
if (
    !isset($_POST['dateTime'], $_POST['uid'], $_POST['name'],$_POST['purchasedPrice'], $_POST['displayPrice'], $_POST['finalPrice'], $_POST['itemCount']) ||
    $_POST['dateTime'] === '' ||
    $_POST['uid'] === '' ||
    $_POST['name'] === '' ||
    $_POST["purchasedPrice"]==="" ||
    $_POST['displayPrice'] === '' ||
    $_POST['finalPrice'] === '' ||
    $_POST['itemCount'] === ''
) {
    exit("Some fields are missing.");
}

$dateTime     = $_POST['dateTime'];
$uid          = $_POST['uid'];
$name         = $_POST['name'];
$purchasedPrice=$_POST["purchasedPrice"];
$displayPrice = (float) $_POST['displayPrice'];
$finalPrice   = (float) $_POST['finalPrice'];
$itemCount    = (int) $_POST['itemCount'];

// --- Connect to MySQL ---
$conn = new mysqli($db, $dbUserName, $dbPassword, $dbName);
if ($conn->connect_error) {
    exit("Connection failed: " . $conn->connect_error);
}

// --- Check if name already exists ---
$checkStmt = $conn->prepare("SELECT COUNT(*) FROM `$inventoryTbName` WHERE `name` = ?");
$checkStmt->bind_param("s", $name);
$checkStmt->execute();
$checkStmt->bind_result($count);
$checkStmt->fetch();
$checkStmt->close();

if ($count > 0) {
    exit("Item name already exists.");
}

// --- Insert new item ---
$stmt = $conn->prepare("INSERT INTO `$inventoryTbName` (`dateTime`, `uid`, `name`,`purchasedPrice`, `displayPrice`, `finalPrice`, `itemCount`) VALUES (?, ?, ?, ?,?, ?, ?)");
$stmt->bind_param("sisdddi", $dateTime, $uid, $name,$purchasedPrice, $displayPrice, $finalPrice, $itemCount);

if ($stmt->execute()) {
    echo "success";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
