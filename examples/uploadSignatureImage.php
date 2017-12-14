<?php
require_once '../Green.php';


use Green\CheckGateway as Gateway;

$ClientID = "your_client_id"; //Your numeric Client_ID
$ApiPassword = "your_api_password"; //Your system generated ApiPassword

$gateway = new \Green\CheckGateway($ClientID, $ApiPassword); //Create the gatway using the Client_ID and Password combination
$gateway->testMode(); //Put the Gateway into testing mode so calls go to the Sandbox and you won't get charged!

//Create a single check and get results back after verification in array format

$check_number = '12322';
$delim  = FALSE;
$delim_char = ',';

$path = 'signatureImage.jpg';
$type = pathinfo($path, PATHINFO_EXTENSION);
$handle = fopen($path, "rb");
$contents = fread($handle, filesize($path));

$result = $gateway->uploadCheckSignature($check_number, $delim, $delim_char);

if($result) {
  //The call succeeded, let's parse it out
  if($result['Result'] == '0'){
    //A "Result" of 0 typically means success
    echo "Check created with ID: " . $result['Check_ID'] . "<br/>";
  } else {
    //Anything other than 0 specifies some kind of error.
    echo "Check not created.<br/>Error Code: {$result['Result']}<br/>Error: {$result['ResultDescription']}<br/>";
  }

  echo "Full Return Details<br/><pre>".print_r($result, TRUE)."</pre><br/>";
} else {
  //The call failed!
  echo "GATEWAY ERROR: " . $gateway->getLastError();
}

?>
