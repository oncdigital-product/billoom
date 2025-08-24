<?php
$data=file_get_contents("../../../data/receiptPrintData.txt");
file_put_contents("../../../data/receiptPrintData.txt","");
echo($data);
?>