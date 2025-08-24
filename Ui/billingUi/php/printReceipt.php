<?php
date_default_timezone_set("Asia/Kolkata");

include "../../../data/dbConfig.php";

if(!isset($_POST["json"])){
exit("no print json data given");
}

$json=$_POST["json"];
$json['dateTime']=date('d-m-Y h:i:s A');

file_put_contents("../../../data/receiptPrintData.txt",json_encode($json));


echo("success");



?>