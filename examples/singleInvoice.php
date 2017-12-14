<?php
require_once '../Green.php';


use Green\CheckGateway as Gateway;


$ClientID = "your_client_id"; //Your numeric Client_ID
$ApiPassword = "your_api_password"; //Your system generated ApiPassword


$gateway = new Gateway($ClientID, $ApiPassword); //Create the gatway using the Client_ID and Password combination
$gateway->testMode(); //Put the Gateway into testing mode so calls go to the Sandbox and you won't get charged!



//Create a single check and get results back after verification in array format


$payor_name = 'Testing Smith';
$email = 'test@test.test';
$item_name = 'Testitem';
$item_description = 'test description';
$amount = '10.00';
$date = date("m/d/Y");
$delim  = FALSE;
$delim_char = ',';

$result = $gateway->singleInvoice($payor_name, $email, $item_name, $item_description, $amount, $date, $delim, $delim_char);



if($result) {
  //The call succeeded, let's parse it out
  if($result['Result'] == '0'){
    //A "Result" of 0 typically means success
    echo "Check created with ID: " . $result['Check_ID'] . "<br/>";
  } else {
    //Anything other than 0 specifies some kind of error.
    echo "Check not created.<br/>Error Code: {$result['Result']}<br/>Error: {$result['ResultDescription']}<br/>";
  }

  echo "Full Return Details<br/><pre>".print_r($result, TRUE)."</pre>";
} else {
  //The call failed!
  echo "GATEWAY ERROR: " . $gateway->getLastError();

}



?>
