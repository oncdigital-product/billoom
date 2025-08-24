<?php
$item=file_get_contents("../../../data/barCodePrintItemData.txt");
file_put_contents("../../../data/barCodePrintItemData.txt","");
echo($item);
?>