<?php
require_once '../Green.php';

use Green;

$ClientID = "your_client_id"; //Your numeric Client_ID
$ApiPassword = "your_api_password"; //Your system generated ApiPassword

$gateway = new \Green\CheckGateway($ClientID, $ApiPassword); //Create the gatway using the Client_ID and Password combination
$gateway->testMode(); //Put the Gateway into testing mode so calls go to the Sandbox and you won't get charged!

//Create a single check and get results back after verification in array format
$name = 'Testing Smith';
$email = 'test@test.test';
$phone = '323-232-3232';
$phone_ext = '';
$address1 = '123 Testing Lane';
$address2 = '';
$city = 'Testville';
$state = 'GA';
$zip = '12345';
$country = 'US';
$routing = '000000000';
$account = '10000001';
$bank_name = 'Test Bank';
$memo = 'Testing Batch';
$amount = '10.00';
$date = date("m/d/Y");
$result = $gateway->singleCheck($name, $email, $phone, $phone_ext, $address1, $address2, $city, $state, $zip, $country, $routing, $account, $bank_name, $memo, $amount, $date, '', FALSE, ',', FALSE);

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

  //Since the check was entered in batch mode, it will always have a VerificationResult of 1, meaning it will be verified in the background at some later point.
  //We can then, at a later time grab the check status to see if it has been verified yet
  sleep(60);
  $check_id = $result['Check_ID'];
  $result = $gateway->checkStatus($check_id);
  if($result){
    if($result['Result'] == '0'){
      //A "Result" of 0 typically means success
      echo "Check created with ID: " . $result['Check_ID'] . "<br/>";
    } else {
      //Anything other than 0 specifies some kind of error.
      echo "Check not created.<br/>Error Code: {$result['Result']}<br/>Error: {$result['ResultDescription']}<br/>";
    }

    echo "Full Return Details<br/><pre>".print_r($result, TRUE)."</pre>";
  } else {
    echo "GATEWAY ERROR: " . $gateway->getLastError();
  }
} else {
  //The call failed!
  echo "GATEWAY ERROR: " . $gateway->getLastError();
}

?>
