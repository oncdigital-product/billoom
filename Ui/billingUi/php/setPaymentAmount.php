<?php

if(!isset($_POST["amount"])){
exit("amount is not given");
}

$amount=$_POST["amount"];

file_put_contents('../../../data/paymentAmount.txt',$amount);

echo("success");

?>