<?php
include "../../../data/dbConfig.php";

// --- Connect to MySQL ---
$conn = new mysqli($db, $dbUserName, $dbPassword, $dbName);
if ($conn->connect_error) {
    exit(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

// --- Get search keyword ---
$keyword = isset($_GET["keyword"]) ? trim($_GET["keyword"]) : "";

// --- Prepare query ---
if ($keyword === "") {
    // Return all items, newest first by UID
    $stmt = $conn->prepare("
        SELECT `dateTime`, `uid`, `name`,`purchasedPrice`, `displayPrice`, `finalPrice`, `itemCount`
        FROM `$inventoryTbName`
        ORDER BY `uid` DESC
    ");
} else {
    // Search by name, newest first by UID
    $searchTerm = "%" . $keyword . "%";
    $stmt = $conn->prepare("
        SELECT `dateTime`, `uid`, `name`,`purchasedPrice`, `displayPrice`, `finalPrice`, `itemCount`
        FROM `$inventoryTbName`
        WHERE `name` LIKE ?
        ORDER BY `uid` DESC
    ");
    $stmt->bind_param("s", $searchTerm);
}

$stmt->execute();
$result = $stmt->get_result();

// --- Collect results ---
$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

// --- Output JSON ---
header("Content-Type: application/json");
echo json_encode($items);

// --- Close ---
$stmt->close();
$conn->close();
