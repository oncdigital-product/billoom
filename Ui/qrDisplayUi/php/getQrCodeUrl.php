<?php


$amount=file_get_contents('../../../data/paymentAmount.txt');
if($amount=="done"){
exit($amount);
}

echo(json_encode(["url"=>'upi://pay?pa=paytm.s1nbstd@pty&pn=Paytm&am='.$amount.'&cu=INR',"amount"=>$amount]));



?>