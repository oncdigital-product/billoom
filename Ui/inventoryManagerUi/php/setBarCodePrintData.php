<?php

if(!isset($_POST["item"])){
exit("no item is given for bar code printing");
}

$item=$_POST["item"];


file_put_contents("../../../data/barCodePrintItemData.txt",json_encode($item));


echo("success");
?>