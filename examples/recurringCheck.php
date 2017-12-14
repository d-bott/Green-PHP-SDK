<?php
require_once '../Green.php';


use Green\CheckGateway as Gateway;


$ClientID = "your_client_id"; //Your numeric Client_ID
$ApiPassword = "your_api_password"; //Your system generated ApiPassword


$gateway = new Gateway($ClientID, $ApiPassword); //Create the gatway using the Client_ID and Password combination
$gateway->testMode(); //Put the Gateway into testing mode so calls go to the Sandbox and you won't get charged!



//Create a recurring check and get results back after verification in array format

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
$memo = 'Testing!';
$amount = '10.00';
$date = date("m/d/Y");
$check_number = '';
$recur_type ='M';
$recur_offset = '1';
$recur_payments = '-1';
$delim  = FALSE;
$delim_char = ',';


$result = $gateway->recurringCheck($name, $email, $phone, $phone_ext, $address1, $address2, $city, $state, $zip, $country, $routing, $account, $bank_name, $memo, $amount, $date, $recur_type, $recur_offset, $recur_payments, $check_number, $delim, $delim_char);



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
