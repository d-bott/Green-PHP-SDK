<?php
require_once '../Green.php';


use Green\CheckGateway as Gateway;


$ClientID = "your_client_id"; //Your numeric Client_ID
$ApiPassword = "your_api_password"; //Your system generated ApiPassword


$gateway = new Gateway($ClientID, $ApiPassword); //Create the gatway using the Client_ID and Password combination



//Shows a check status and get results back after verification in array format

$check_id = '12322';
$delim  = FALSE;
$delim_char = ',';
$result = $gateway->checkStatus($check_id, $delim, $delim_char);



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
