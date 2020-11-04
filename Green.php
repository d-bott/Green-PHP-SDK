<?php
namespace Green;

/**
 * A basic class to create calls to the Green Payment Processing API
 *
 * Used to easily generate API calls by instead calling pre-made PHP functions of this class with your check data.
 * The class then handles generating the API call automatically
 * TERMS:
 *  Verification Mode - requests to our API can run in either Real Time or Batch Mode.
 *    Batch - Calls made will return immediately with a success message stating the check was received. eVerification (and Phone or other verification as needed by your merchant account settings) will run at a later time requiring a separate call to CheckStatus to tell if a check has been verified/processed/etc.
 *    Real Time - (default) Calls made will insert the check(s) and immediately run eVerification (and other verification if specified by your merchant account) and will return a result stating whether the check passed or failed verification. This is the default mode and most merchants will only use this mode.
 *  EndPoint - the mode in which calls are made. You can make calls to our "test" sandbox or directly to our "live" system.
 */
class CheckGateway
{
  private $client_id = "";
  private $api_pass = "";
  private $endpoint = "";
  private $error = "";

  /** @var bool $live Specifies whether this Gateway should make calls to the live API or to the Sandbox */
  private $live = false;

  const ENDPOINT = array(
    "test" => "https://cpsandbox.com/echeck.asmx",
    "live" => "https://greenbyphone.com/echeck.asmx"
  );

  /***
  Standard constructor
  ***/
  function __construct($client_id, $api_pass, $live = true){
    $this->client_id = $client_id;
    $this->api_pass = $api_pass;
    $this->live = $live;
    $this->setEndpoint();
  }

  public function setClientID($id) {
    $this->client_id = $id;
  }

  public function getClientID(){
    return $this->client_id;
  }

  public function setApiPassword($pass){
    $this->api_pass = $pass;
  }

  public function getApiPassword() {
    return $this->api_pass;
  }

  public function setEndpoint() {
    if($this->live){
      $this->endpoint = self::ENDPOINT['live'];
    } else {
      $this->endpoint = self::ENDPOINT['test'];
    }
  }

  public function getEndpoint(){
    return $this->endpoint;
  }

  public function liveMode(){
    $this->live = true;
    $this->setEndpoint();
  }

  public function testMode(){
    $this->live = false;
    $this->setEndpoint();
  }

  function __toString(){
    $str  = "Gateway Type: POST\n";
    $str .= "Endpoint: ".$this->getEndpoint()."\n";
    $str .= "Client ID: ".$this->getClientID()."\n";
    $str .= "ApiPassword: ".$this->getApiPassword()."\n";

    return $str;
  }

  function toString($html = TRUE){
    if($html){
      return nl2br($this->__toString());
    }

    return $this->__toString();
  }

  private function setLastError($error){
    $this->error = $error;
  }

  public function getLastError(){
    return $this->error;
  }



  /**
   * A default method used to generate API Calls
   *
   * This method is used internally by all other methods to generate API calls easily. This method can be used externally to create a request to any API method available if we haven't created a simple method for it in the class
   *
   * @param string  $method   The name of the API method to call at the endpoint (ex. OneTimeDraftRTV, CheckStatus, etc.)
   * @param array   $options  An array of "APIFieldName" => "Value" pairs. Must include the Client_ID and ApiPassword variables
   *
   * @return mixed            Returns associative array or delimited string on success OR cURL error string on failure
   */
  function request($method, $options, $resultArray = array()) {
    if(!isset($options['Client_ID'])){
      $options["Client_ID"] = $this->getClientID();
    }

    if(!isset($options['ApiPassword'])){
      $options['ApiPassword'] = $this->getApiPassword();
    }

    //Test whether they want the delimited return or not to start with
    $returnDelim = ($options['x_delim_data'] === "TRUE");
    //Now let's actually set delim to TRUE because we always want to get a delimited string back from the API so we can parse it
    $options["x_delim_data"] = "TRUE";

    try {
      $ch = curl_init();

      if($ch === FALSE){
        throw new \Exception('Failed to initialize cURL');
      }

      curl_setopt($ch, CURLOPT_URL, $this->getEndpoint() . '/' . $method);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($options));

      $response = curl_exec($ch);

      if($response === FALSE){
        throw new \Exception(curl_error($ch), curl_errno($ch));
      }

      curl_close($ch);
    } catch(\Exception $e) {
      $this->setLastError(sprintf('Curl failed with error #%d: %s', $e->getCode(), $e->getMessage()));
      return false;
    }

    try {
      if($returnDelim){
        return $response;
      } else {
        return $this->resultToArray($response, $options['x_delim_char'], $resultArray);
      }
    } catch(\Exception $e){
      $this->setLastError("An error occurred while attempting to parse the API result: ". $e->getMessage());
      return false;
    }
  }

  function requestSOAP($method, $options){
    if(!isset($options['Client_ID'])){
      $options["Client_ID"] = $this->getClientID();
    }

    if(!isset($options['ApiPassword'])){
      $options['ApiPassword'] = $this->getApiPassword();
    }

    //Test whether they want the delimited return or not to start with
    $returnDelim = ($options['x_delim_data'] === "TRUE");
    //Now let's actually set delim to FALSE because calling by SOAP requires we get a response in XML
    $options["x_delim_data"] = "";

    $client = new \SoapClient($this->getEndpoint() . "?wsdl", array("trace" => 1));
    try {
      $result = $client->__soapCall($method, array($options));

      $resultArray = (array) $result;
      $resultInnerArray = (array) reset($resultArray); //cheat to return the first element in the array without needing the key for it

      if($returnDelim){
        //We need to take it's arguments and turn them into a delimited string
        return implode($options['x_delim_char'], array_values($resultInnerArray));
      } else {
        //Return it as an array
        return $resultInnerArray;
      }
    } catch(\Exception $e){
      $this->setLastError(sprintf('SOAP Request failed with error #%d: %s <br/> %s <br/> %s', $e->getCode(), $e->getMessage(), $client->__getLastRequest(), $client->__getLastResponse()));
      return false;
    }
  }

  /**
   * Function takes result string from API and parses into PHP associative Array
   *
   * If a return is specified to be returned as delimited, it will return the string. Otherwise, this function will be called to
   * return the result as an associative array in the format specified by the API documentation.
   *
   * @param string  $result       The result string as returned by cURL
   * @param string  $delim_char   The character used to delimit the string in cURL
   * @param array   $keys         An array containing the key names for the result variable as specified by the API docs
   *
   * @return array                Associative array of key=>values pair described by the API docs as the return for the called method
   */
  private function resultToArray($result, $delim_char, $keys){
    $split = explode($delim_char, $result);
    $resultArray = array();
    foreach ($keys as $key => $keyName) {
      $resultArray[$keyName] = $split[$key];
    }

    return $resultArray;
  }



  /**
   * Inserts a single check
   *
   * Inserts a single draft from your customer's bank account to the default US bank account on file with your merchant account for the specified amount/date.
   *
   * @param string  $name           Customer's Full Name on their checking account
   * @param string  $email          Customer's email address. If provided, will be notified with receipt of payment. If not provided, customer will be notified via US Mail at additional cost to your Green Account
   * @param string  $phone          Customer's 10-digit US phone number in the format ###-###-####
   * @param string  $phone_ext      Customer's phone extension
   * @param string  $address1       Customer's street number and street name
   * @param string  $address2       Customer's additional address information (Suite #, Floor #, etc.)
   * @param string  $city           Customer's city name
   * @param string  $state          Customer's 2-character state abbreviation (ex. NY, CA, GA, etc.)
   * @param string  $zip            Customer's 5-digit or 9-digit zip code in the format ##### or #####-####
   * @param string  $country        Customer's 2-character country code, ex. "US"
   * @param string  $routing        Customer's 9-digit bank routing number
   * @param string  $account        Customer's bank account number
   * @param string  $bank_name      The customer's Bank Name (ex. Wachovia, BB&T, etc.)
   * @param string  $memo           Memo to appear on the check
   * @param string  $amount         Check amount in the format ##.##. Do not include monetary symbols
   * @param string  $date           The check date
   * @param string  $check_number   Optional check number you want to use to identify this check. Defaults to system generated number
   * @param bool    $delim          True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char     Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   * @param bool    $realtime       Specifies whether to verify in real time or in batch mode. See class comments on "Verification Mode" for more details
   *
   * @return mixed                  Returns associative array or delimited string on success OR cURL error string on failure
   */
  public function singleCheck($name, $email, $phone, $phone_ext, $address1, $address2, $city, $state, $zip, $country, $routing, $account, $bank_name, $memo, $amount, $date, $check_number = '', $delim = FALSE, $delim_char = ",", $realtime = TRUE){
    $method = "OneTimeDraftBV";
    if($realtime){
      $method = "OneTimeDraftRTV";
    }
    return $this->request($method, array(
      'Name'=> $name,
      'EmailAddress'=> $email,
      'Phone'=> $phone,
      'PhoneExtension'=> $phone_ext,
      'Address1'=> $address1,
      'Address2'=> $address2,
      'City'=> $city,
      'State'=> $state,
      'Zip'=> $zip,
      'Country'=> $country,
      'RoutingNumber'=> $routing,
      'AccountNumber'=> $account,
      'BankName' => $bank_name,
      'CheckMemo'=> $memo,
      'CheckAmount'=> $amount,
      'CheckDate'=> $date,
      'CheckNumber' => $check_number,
      'x_delim_data' => ($delim) ? "TRUE" : "",
      'x_delim_char' => $delim_char
    ), array(
      "Result",
      "ResultDescription",
      "VerifyResult",
      "VerifyResultDescription",
      "CheckNumber",
      "Check_ID"
    ));
  }

  /**
   * Inserts a recurring check
   *
   * Inserts the first check in the series and then each time this series is processed, inserts a new check
   * for the specified ReccuringType, Offset, and until it hits the number of RecurringPayments.
   * Ex. Once a month for 12 payments would be: $recur_type = "M", $recur_offset = "1", $recur_payments = "12"
   * Every other day for 10 payments would be: $recur_type = "D", $recur_offset = "2", $recur_payments = "10"
   *
   * @param string  $name           Customer's Full Name on their checking account
   * @param string  $email          Customer's email address. If provided, will be notified with receipt of payment. If not provided, customer will be notified via US Mail at additional cost to your Green Account
   * @param string  $phone          Customer's 10-digit US phone number in the format ###-###-####
   * @param string  $phone_ext      Customer's phone extension
   * @param string  $address1       Customer's street number and street name
   * @param string  $address2       Customer's additional address information (Suite #, Floor #, etc.)
   * @param string  $city           Customer's city name
   * @param string  $state          Customer's 2-character state abbreviation (ex. NY, CA, GA, etc.)
   * @param string  $zip            Customer's 5-digit or 9-digit zip code in the format ##### or #####-####
   * @param string  $country        Customer's 2-character country code, ex. "US"
   * @param string  $routing        Customer's 9-digit bank routing number
   * @param string  $account        Customer's bank account number
   * @param string  $bank_name      The customer's Bank Name (ex. Wachovia, BB&T, etc.)
   * @param string  $memo           Memo to appear on the check
   * @param string  $amount         Check amount in the format ##.##. Do not include monetary symbols
   * @param string  $date           The date for the first check in the format mm/dd/yyyy. Valid values range from 2 months prior to 1 year forward from current date
   * @param string  $recur_type     valid values are "M" for month, "W" for week, and "D" for day
   * @param string  $recur_offset   The number of units of $recur_type between each check in the series
   * @param string  $recur_payments Total number of checks to be processed over time. Valid values are integers from 2-99 or the special value -1 which recurs until payments are stopped by you or your client
   * @param string  $check_number   Optional check number you want to use to identify this check. Defaults to system generated number
   * @param bool    $delim          True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char     Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   * @param bool    $realtime       Specifies whether to verify in real time or in batch mode. See class comments on "Verification Mode" for more details
   *
   * @return mixed                  Returns associative array or delimited string on success OR cURL error string on failure
   */
  public function recurringCheck($name, $email, $phone, $phone_ext, $address1, $address2, $city, $state, $zip, $country, $routing, $account, $bank_name, $memo, $amount, $date, $recur_type, $recur_offset, $recur_payments, $check_number = '', $delim = FALSE, $delim_char = ",", $realtime = TRUE){
    $method = "RecurringDraftBV";
    if($realtime){
      $method = "RecurringDraftRTV";
    }
    return $this->request($method, array(
      'Name'=> $name,
      'EmailAddress'=> $email,
      'Phone'=> $phone,
      'PhoneExtension'=> $phone_ext,
      'Address1'=> $address1,
      'Address2'=> $address2,
      'City'=> $city,
      'State'=> $state,
      'Zip'=> $zip,
      'Country'=> $country,
      'RoutingNumber'=> $routing,
      'AccountNumber'=> $account,
      'BankName' => $bank_name,
      'CheckMemo'=> $memo,
      'CheckAmount'=> $amount,
      'CheckDate'=> $date,
      'CheckNumber' => $check_number,
      'RecurringType' => $recur_type,
      'RecurringOffset' => $recur_offset,
      'RecurringPayments' => $recur_payments,
      'x_delim_data' => ($delim) ? "TRUE" : "",
      'x_delim_char' => $delim_char
    ), array(
      "Result",
      "ResultDescription",
      "VerifyResult",
      "VerifyResultDescription",
      "CheckNumber",
      "Check_ID"
    ));
  }

  /**
   * Enters a single check with check signature.
   *
   * Method enters checks only in Real Time Verification mode. Image data must be passed in
   * jpeg/jpg format through a base64 encoded string
   *
   * @param string  $name           Customer's Full Name on their checking account
   * @param string  $email          Customer's email address. If provided, will be notified with receipt of payment. If not provided, customer will be notified via US Mail at additional cost to your Green Account
   * @param string  $phone          Customer's 10-digit US phone number in the format ###-###-####
   * @param string  $phone_ext      Customer's phone extension
   * @param string  $address1       Customer's street number and street name
   * @param string  $address2       Customer's additional address information (Suite #, Floor #, etc.)
   * @param string  $city           Customer's city name
   * @param string  $state          Customer's 2-character state abbreviation (ex. NY, CA, GA, etc.)
   * @param string  $zip            Customer's 5-digit or 9-digit zip code in the format ##### or #####-####
   * @param string  $country        Customer's 2-character country code, ex. "US"
   * @param string  $routing        Customer's 9-digit bank routing number
   * @param string  $account        Customer's bank account number
   * @param string  $bank_name      The customer's Bank Name (ex. Wachovia, BB&T, etc.)
   * @param string  $memo           Memo to appear on the check
   * @param string  $amount         Check amount in the format ##.##. Do not include monetary symbols
   * @param string  $date      		  CheckDate - Check date in the format mm/dd/yyyy.  Check date can be from 2 months prior to 1 year in
   * @param string  $image		      The jpeg data for a document with the client’s signature in base64Binary format
   * @param string  $check_number   Optional check number you want to use to identify this check. Defaults to system generated number
   * @param bool    $delim          True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char     Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   *
   * @return mixed                  Returns associative array or delimited string on success OR cURL error string on failure
   */
  public function singleCheckWithSignature($name, $email, $phone, $phone_ext, $address1, $address2, $city, $state, $zip, $country, $routing, $account, $bank_name, $memo, $amount, $date, $image, $check_number = '', $delim = FALSE, $delim_char = ","){
    return $this->requestSOAP("OneTimeDraftWithSignatureImage", array(
      'Name'=> $name,
      'EmailAddress'=>$email,
      'Phone'=>$phone,
      'PhoneExtension'=>$phone_ext,
      'Address1'=> $address1,
      'Address2'=> $address2,
      'City'=> $city,
      'State'=> $state,
      'Zip'=> $zip,
      'Country'=> $country,
      'RoutingNumber'=> $routing,
      'AccountNumber'=> $account,
      'BankName' => $bank_name,
      'CheckMemo'=> $memo,
      'CheckAmount'=> $amount,
      'CheckDate'=> $date,
      'CheckNumber' => $check_number,
      'ImageData'=> $image,
      'x_delim_data' => ($delim) ? "TRUE" : '',
      'x_delim_char' => $delim_char
    ));
  }

  /**
   * Enters a single check with check signature.
   *
   * Method enters checks only in Real Time Verification mode.
   * NOTE: Because this method requires a base64Binary type to be sent, we cannot use the POST request
   * function. This method must use a SOAP client to generate the request
   *
   * @param string  $name           Customer's Full Name on their checking account
   * @param string  $email          Customer's email address. If provided, will be notified with receipt of payment. If not provided, customer will be notified via US Mail at additional cost to your Green Account
   * @param string  $phone          Customer's 10-digit US phone number in the format ###-###-####
   * @param string  $phone_ext      Customer's phone extension
   * @param string  $address1       Customer's street number and street name
   * @param string  $address2       Customer's additional address information (Suite #, Floor #, etc.)
   * @param string  $city           Customer's city name
   * @param string  $state          Customer's 2-character state abbreviation (ex. NY, CA, GA, etc.)
   * @param string  $zip            Customer's 5-digit or 9-digit zip code in the format ##### or #####-####
   * @param string  $country        Customer's 2-character country code, ex. "US"
   * @param string  $routing        Customer's 9-digit bank routing number
   * @param string  $account        Customer's bank account number
   * @param string  $bank_name      The customer's Bank Name (ex. Wachovia, BB&T, etc.)
   * @param string  $memo           Memo to appear on the check
   * @param string  $amount         Check amount in the format ##.##. Do not include monetary symbols
   * @param string  $date      		  CheckDate - Check date in the format mm/dd/yyyy.  Check date can be from 2 months prior to 1 year in
   * @param string  $image		      The jpeg data for a document with the client’s signature in base64Binary format
   * @param string  $recur_type     valid values are "M" for month, "W" for week, and "D" for day
   * @param string  $recur_offset   The number of units of $recur_type between each check in the series
   * @param string  $recur_payments The total number of payments to be made in this series
   * @param string  $check_number   Optional check number you want to use to identify this check. Defaults to system generated number
   * @param bool    $delim          True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char     Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   *
   * @return mixed                  Returns associative array or delimited string on success OR cURL error string on failure
   */
  public function recurringCheckWithSignature($name, $email, $phone, $phone_ext, $address1, $address2, $city, $state, $zip, $country, $routing, $account, $bank_name, $memo, $amount, $date, $image, $recur_type, $recur_offset, $recur_payments, $check_number = '', $delim = FALSE, $delim_char = ","){
    return $this->requestSOAP("RecurringDraftWithSignatureImage", array(
      'Name'=> $name,
      'EmailAddress'=> $email,
      'Phone'=> $phone,
      'PhoneExtension'=> $phone_ext,
      'Address1'=> $address1,
      'Address2'=> $address2,
      'City'=> $city,
      'State'=> $state,
      'Zip'=> $zip,
      'Country'=> $country,
      'RoutingNumber'=> $routing,
      'AccountNumber'=> $account,
      'BankName' => $bank_name,
      'CheckMemo'=> $memo,
      'CheckAmount'=> $amount,
      'CheckDate'=> $date,
      'CheckNumber' => $check_number,
      'ImageData'=> $image,
      'RecurringType' => $recur_type,
      'RecurringOffset' => $recur_offset,
      'RecurringPayments' => $recur_payments,
      'x_delim_data' => ($delim) ? "TRUE" : "",
      'x_delim_char' => $delim_char
    ));
  }

  /**
   * Return the status results for a check that was previously input
   *
   * Will return a status string that contains the results of eVerification, processing status, deletion/rejection status and dates, and other relevant information
   *
   * @param string  $check_id       The numeric Check_ID of the previously entered check you want the status for
   * @param bool    $delim          True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char     Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   *
   * @return mixed                  Returns associative array or delimited string on success OR cURL error string on failure
   */
  public function checkStatus($check_id, $delim = FALSE, $delim_char = ","){
    return $this->request("CheckStatus", array(
      "Check_ID" => $check_id,
      "x_delim_data" => ($delim) ? "TRUE" : "",
      "x_delim_char" => $delim_char
    ), array(
      "Result",
      "ResultDescription",
      "VerifyResult",
      "VerifyResultDescription",
      "VerifyOverridden",
      "Deleted",
      "DeletedDate",
      "Processed",
      "ProcessedDate",
      "Rejected",
      "RejectedDate",
      "CheckNumber",
      "Check_ID"
    ));
  }

  /**
   * Cancels a previously entered check
   *
   * This function allows you to cancel any previously entered check as long as it has NOT already been processed.
   * NOTE: For recurring checks, this function cancels the entire series of payments.
   *
   * @param string  $check_id
   * @param bool    $delim          True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char     Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   *
   * @return mixed                  Returns associative array or delimited string on success OR cURL error string on failure
   */
  public function cancelCheck($check_id, $delim = FALSE, $delim_char = ","){
    return $this->request("CancelCheck", array(
      "Check_ID" => $check_id,
      "x_delim_data" => ($delim) ? "TRUE" : "",
      "x_delim_char" => $delim_char
    ), array(
      "Result",
      "ResultDescription",
    ));
  }

  /**
   * Issue a refund for a single check previously entered
   *
   * Allows you to start the process of entereing a refund. On a successful result, the refund will be processed at the next batch and sent to the customer.
   *
   * @param string  $check_id		    The numeric Check_ID of the previously entered check you want the refund for
   * @param string  $RefundMemo     Memo to appear on the refund
   * @param string  $RefundAmount	  Refund amount in the format ##.##. Do not include monetary symbols
   * @param bool    $delim          True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char     Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   *
   * @return mixed                  Returns associative array or delimited string on success OR cURL error string on failure
   */
  public function refundCheck($check_id, $memo, $amount, $delim = FALSE, $delim_char = ","){
    return $this->request("RefundCheck", array(
      "Check_ID" => $check_id,
  	  "RefundMemo" => $memo,
  	  "RefundAmount" => $amount,
      "x_delim_data" => ($delim) ? "TRUE" : "",
      "x_delim_char" => $delim_char
    ), array(
      "Result",
      "ResultDescription",
      "RefundCheckNumber",
      "RefundCheck_ID"
	 ));
  }

  /**
   * Insert a note for a previously entered check
   *
   * Creates a check note for the check which can be viewed using the Check System Tracking pages in your Green Portal.
   *
   * @param string  $check_id       The numeric Check_ID of the previously entered check
   * @param string  $note           The actual note to enter, limit of 2000 characters
   * @param bool    $delim          True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char     Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   *
   * @return mixed                  Returns associative array or delimited string on success OR cURL error string on failure
   */
  public function checkNote($check_id, $note, $delim = FALSE, $delim_char = ','){
    if(strlen($note) > 2000){
      $note = substr($note, 0, 2000);
    }

    return $this->request("CheckNote", array(
      "Check_ID" => $check_id,
      "Note" => $note,
      "x_delim_char" => ($delim) ? "TRUE" : "",
      "x_delim_char" => $delim_char
    ), array(
      "Result",
      "ResultDescription"
    ));
  }

  /**
   * Upload a signature image for a previously entered check.
   *
   * Image data must be provided to the API as a jpeg/jpg file in the form of a base64 encoded string.
   * NOTE: Because this method requires a base64Binary type to be sent, we cannot use the POST request
   * function. This method must use a SOAP client to generate the request
   *
   * @param string  $check_id		 The Check_ID for the previously entered check
   * @param string  $image  	   The jpeg data for a document with the client’s signature in base64Binary format
   * @param bool    $delim       True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char  Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   *
   * @return mixed               Returns associative array or delimited string on success OR cURL error string on failure
   */
  public function uploadCheckSignature($check_id, $image, $delim = FALSE, $delim_char = ","){
    return $this->requestSOAP("UploadSignatureImage", array(
      "Check_ID" => $check_id,
      "ImageData" => $image,
      "x_delim_data" => ($delim) ? "TRUE" : "",
      "x_delim_char" => $delim_char
    ));
  }



  /**
   * Return the verification status of a check that was previously input
   *
   * Similar to @see self::checkStatus but returns only the result of verification
   *
   * @param string  $check_id       The numeric Check_ID of the previously entered check you want the status for
   * @param bool    $delim          True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char     Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   *
   * @return mixed                  Returns associative array or delimited string on success OR cURL error string on failure
   */
  public function verificationResult($check_id, $delim = FALSE, $delim_char = ","){
    return $this->request("VerificationResult", array(
      "Check_ID" => $check_id,
      "x_delim_data" => ($delim) ? "TRUE" : "",
      "x_delim_char" => $delim_char
    ), array(
      "Result",
      "ResultDescription",
      "VerifyResult",
      "VerifyResultDescription",
      "CheckNumber",
      "Check_ID"
    ));
  }

  /**
   * Override the verification code of a check previously entered
   *
   * If a check gets returned by eVerification as Risky/Bad and has an overridable response code, this function allows you
   * to override the code and process the check at the next awaiting batch.
   *
   * @param string  $check_id
   * @param bool    $delim          True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char     Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   *
   * @return mixed                  Returns associative array or delimited string on success OR cURL error string on failure
   */
  public function overrideVerification($check_id, $delim = FALSE, $delim_char = ","){
    return $this->request("VerificationOverride", array(
      "Check_ID" => $check_id,
      "x_delim_data" => ($delim) ? "TRUE" : "",
      "x_delim_char" => $delim_char
    ), array(
      "Result",
      "ResultDescription",
      "VerifyResult",
      "VerifyResultDescription",
      "CheckNumber",
      "Check_ID"
    ));
  }



  /**
   * Send a single payment from your bank account to another person or company.
   *
   * Most banks offer this feature already, however, if you'd like to integrate this into your system to handles
   * rebates, incentives, et. al this is the feature you need!
   *
   * @param string  $name           Customer's Full Name on their checking account
   * @param string  $address1       Customer's street number and street name
   * @param string  $address2       Customer's additional address information (Suite #, Floor #, etc.)
   * @param string  $city           Customer's city name
   * @param string  $state          Customer's 2-character state abbreviation (ex. NY, CA, GA, etc.)
   * @param string  $zip            Customer's 5-digit or 9-digit zip code in the format ##### or #####-####
   * @param string  $country        Customer's 2-character country code, ex. "US"
   * @param string  $routing        Customer's 9-digit bank routing number
   * @param string  $account        Customer's bank account number
   * @param string  $bank_name      The customer's Bank Name (ex. Wachovia, BB&T, etc.)
   * @param string  $memo           Memo to appear on the check
   * @param string  $amount         Check amount in the format ##.##. Do not include monetary symbols
   * @param string  $date           The date for the first check in the format mm/dd/yyyy. Valid values range from 2 months prior to 1 year forward from current date
   * @param string  $check_number   Optional check number you want to use to identify this check. Defaults to system generated number
   * @param bool    $delim          True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char     Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   *
   * @return mixed                  Returns associative array or delimited string on success OR cURL error string on failure
   */
  public function singleBillpay($name, $address1, $address2, $city, $state, $zip, $country, $routing, $account, $bank_name, $memo, $amount, $date, $check_number = '', $delim = FALSE, $delim_char = ","){
    return $this->request("BillPayCheck", array(
      "Name" => $name,
      "Address1" => $address1,
      "Address2" => $address2,
      "City" => $city,
      "State" => $state,
      "Zip" => $zip,
      "Country" => $country,
      "RoutingNumber" => $routing,
      "AccountNumber" => $account,
      "BankName" => $bank_name,
      "CheckMemo" => $memo,
      "CheckAmount" => $amount,
      "CheckDate" => $date,
      'CheckNumber' => $check_number,
      "x_delim_data" => ($delim) ? "TRUE" : "",
      "x_delim_char" => $delim_char
    ), array(
      "Result",
      "ResultDescription",
      "CheckNumber",
      "Check_ID"
	 ));
  }

  /**
   * Allows you to enter a single payment from your bank account TO another person or company.
   *
   * Like @see CheckGateway::singleBillpay but requires no bank information.
   * Since we don't have the bank info, we cannot deposit these checks directly
   *
   * @param string  $name           Customer's Full Name on their checking account
   * @param string  $address1       Customer's street number and street name
   * @param string  $address2       Customer's additional address information (Suite #, Floor #, etc.)
   * @param string  $city           Customer's city name
   * @param string  $state          Customer's 2-character state abbreviation (ex. NY, CA, GA, etc.)
   * @param string  $zip            Customer's 5-digit or 9-digit zip code in the format ##### or #####-####
   * @param string  $country        Customer's 2-character country code, ex. "US"
   * @param string  $memo           Memo to appear on the check
   * @param string  $amount         Check amount in the format ##.##. Do not include monetary symbols
   * @param string  $date           The date for the first check in the format mm/dd/yyyy. Valid values range from 2 months prior to 1 year forward from current date
   * @param string  $check_number   Optional check number you want to use to identify this check. Defaults to system generated number
   * @param bool    $delim          True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char     Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   *
   * @return mixed                  Returns associative array or delimited string on success OR cURL error string on failure
   */
  public function singleBillpayWithoutBank($name, $address1, $address2, $city, $state, $zip, $country, $memo, $amount, $date, $check_number = '', $delim = FALSE, $delim_char = ","){
    return $this->request("BillPayCheckNoBankInfo", array(
      "Name" => $name,
      "Address1" => $address1,
      "Address2" => $address2,
      "City" => $city,
      "State" => $state,
      "Zip" => $zip,
      "Country" => $country,
      "CheckMemo" => $memo,
      "CheckAmount" => $amount,
      "CheckDate" => $date,
      'CheckNumber' => $check_number,
      "x_delim_data" => ($delim) ? "TRUE" : "",
      "x_delim_char" => $delim_char
    ), array(
      "Result",
      "ResultDescription",
      "CheckNumber",
      "Check_ID"
	 ));
  }

  /**
   * Enter a recurring payment from your bank account TO another person or companyName
   *
   * Enters a recurring billpay check using similar methods to @see CheckGateway::singleBillpay combined with @see CheckGateway::recurringCheck
   *
   * @param string  $name           Customer's Full Name on their checking account
   * @param string  $address1       Customer's street number and street name
   * @param string  $address2       Customer's additional address information (Suite #, Floor #, etc.)
   * @param string  $city           Customer's city name
   * @param string  $state          Customer's 2-character state abbreviation (ex. NY, CA, GA, etc.)
   * @param string  $zip            Customer's 5-digit or 9-digit zip code in the format ##### or #####-####
   * @param string  $country        Customer's 2-character country code, ex. "US"
   * @param string  $routing        Customer's routing number for their bank account. Optional.
   * @param string  $account        Customer's account number for their bank account. Optional.
   * @param string  $bank           The name of the customer's bank. Optional but should be supplied if routing and account are supplied.
   * @param string  $memo           Memo to appear on the check
   * @param string  $amount         Check amount in the format ##.##. Do not include monetary symbols
   * @param string  $date           The date for the first check in the format mm/dd/yyyy. Valid values range from 2 months prior to 1 year forward from current date
   * @param string  $recur_type     valid values are "M" for month, "W" for week, and "D" for day
   * @param string  $recur_offset   The number of units of $recur_type between each check in the series
   * @param string  $recur_payments Total number of checks to be processed over time. Valid values are integers from 2-99 or the special value -1 which recurs until payments are stopped by you or your client
   * @param string  $check_number   Optional check number you want to use to identify this check. Defaults to system generated number
   * @param bool    $delim          True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char     Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   *
   * @return mixed                  Returns associative array or delimited string on success OR cURL error string on failure
   */
  public function recurringBillpay($name, $address1, $address2, $city, $state, $zip, $country, $routing, $account, $bank, $memo, $amount, $date, $recur_type, $recur_offset, $recur_payments, $check_number = '', $delim = FALSE, $delim_char = ","){
    return $this->request("RecurringBillPayCheck", array(
      "Name" => $name,
      "Address1" => $address1,
      "Address2" => $address2,
      "City" => $city,
      "State" => $state,
      "Zip" => $zip,
      "Country" => $country,
      "RoutingNumber" => $routing,
      "AccountNumber" => $account,
      "BankName" => $bank,
      "CheckMemo" => $memo,
      "CheckAmount" => $amount,
      "CheckDate" => $date,
      'CheckNumber' => $check_number,
      "RecurringType" => $recur_type,
      "RecurringOffset" => $recur_offset,
      "RecurringPayments" => $recur_payments,
      "x_delim_data" => ($delim) ? "TRUE" : "",
      "x_delim_char" => $delim_char
    ), array(
      "Result",
      "ResultDescription",
      "CheckNumber",
      "Check_ID"
	 ));
  }

  /**
    * Enters a single invoice that sends the customer an invoice via email.
    *
    * @param string  $payor_name        Name of person paying
    * @param string  $email      	      Email to be sent the invoice
    * @param string  $item_name      	  Name of the Item
    * @param string  $item_description  Description of the Item
    * @param string  $amount	          Initial Amount
    * @param string  $date       		    Payment date
    *
    * @param bool    $delim             True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
    * @param string  $delim_char        Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
    *
    * @return mixed                     Returns associative array or delimited string on success OR cURL error string on failure
    */
  public function singleInvoice($payor_name, $email, $item_name, $item_description, $amount, $date, $delim = FALSE, $delim_char = ","){
    return $this->request("OneTimeInvoice", array(
      "CustomerName" => $payor_name,
      "EmailAddress" => $email,
      "ItemName" => $item_name,
      "ItemDescription" => $item_description,
      "Amount" => $amount,
      "PaymentDate" => $date,
      "x_delim_data" => ($delim) ? "TRUE" : "",
      "x_delim_char" => $delim_char
    ), array(
      "Result",
      "ResultDescription",
      "PaymentResult",
      "PaymentResultDescription",
      "Invoice_ID",
      "Check_ID"
	 ));
  }

  /**
    * RecurringInvoice allows you to enter a single invoice that sends your customer an invoice via email for a recurring draft.
    *
    * RecurringInvoice shares similar inputs and the same outputs as OneTimeInvoice.
    *
    * @param string  $payor_name        Name of person paying
    * @param string  $email      	      Email to be sent the invoice
    * @param string  $item_name      	  Name of the Item
    * @param string  $item_description  Description of the Item
    * @param string  $amount	          Dollar amount on the invoice
    * @param string  $date       		    Payment date
    * @param string  $recur_type        valid values are "M" for month, "W" for week, and "D" for day
    * @param string  $recur_offset      The number of units of $recur_type between each check in the series
    * @param string  $recur_payments    The total number of payments to be made in this series

    * @param bool    $delim             True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
    * @param string  $delim_char        Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
    *
    * @return mixed                     Returns associative array or delimited string on success OR cURL error string on failure
    */
  public function recurringInvoice($payor_name, $email, $item_name, $item_description, $amount, $date, $recur_type, $recur_offset, $recur_payments, $delim = FALSE, $delim_char = ","){
    return $this->request("RecurringInvoice", array(
      "CustomerName" => $payor_name,
      "EmailAddress" => $email,
      "ItemName" => $item_name,
      "ItemDescription" => $item_description,
      "Amount" => $amount,
      "PaymentDate" => $date,
      "RecurringType" => $recur_type,
      "RecurringOffset" => $recur_offset,
      "RecurringPayments" => $recur_payments,
      "x_delim_data" => ($delim) ? "TRUE" : "",
      "x_delim_char" => $delim_char
    ), array(
      "Result",
      "ResultDescription",
      "PaymentResult",
      "PaymentResultDescription",
      "Invoice_ID",
      "Check_ID"
	 ));
  }

  /**
   * Inserts a combination invoice
   *
   * Shares inputs with @see CheckGateway::recurringInvoice(). Function enters an invoice that sends your
   * customer an invoice via email for a down payment and a recurring draft.
   *
   * @param string  $payor_name       Customer's Full Name on their checking account
   * @param string  $email            Customer's email address. If provided, will be notified with receipt of payment. If not provided, customer will be notified via US Mail at additional cost to your Green Account
   * @param string  $item_name        The name of the item on the invoice
   * @param string  $item_description A full description of the item on the invoice
   * @param string  $init_amount      The initial payment amount
   * @param string  $init_date        The initial payment date of the invoice check
   * @param string  $recur_amount     The amount of each check in the recurring series
   * @param string  $recur_init_date  The initial date of the first recurring check in the series
   * @param string  $recur_type       valid values are "M" for month, "W" for week, and "D" for day
   * @param string  $recur_offset     The number of units of $recur_type between each check in the series
   * @param string  $recur_payments   Total number of checks to be processed over time. Valid values are integers from 2-99 or the special value -1 which recurs until payments are stopped by you or your client
   * @param bool    $delim            True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char       Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   *
   * @return mixed                    Returns associative array or delimited string on success OR cURL error string on failure
   */
  public function combinationInvoice($payor_name, $email, $item_name, $item_description, $init_amount, $init_date, $recur_amount, $recur_init_date, $recur_type, $recur_offset, $recur_payments, $delim = FALSE, $delim_char = ","){
    return $this->request("CombinationInvoice", array(
      "CustomerName" => $payor_name,
      "EmailAddress" => $email,
      "ItemName" => $item_name,
      "ItemDescription" => $item_description,
      "InitialAmount" => $init_amount,
      "InitialPaymentDate" => $init_date,
      "RecurringAmount" => $recur_amount,
      "RecurringPaymentDate" => $recur_init_date,
      "RecurringType" => $recur_type,
      "RecurringOffset" => $recur_offset,
      "RecurringPayments" => $recur_payments,
      "x_delim_data" => ($delim) ? "TRUE" : "",
      "x_delim_char" => $delim_char
    ), array(
      "Result",
      "ResultDescription",
      "PaymentResult",
      "PaymentResultDescription",
      "Invoice_ID",
      "Check_ID"
	 ));
  }

  /**
   * InvoiceStatus allows you to retrieve payment status on a previously entered invoice.
   *
   * @param string  $invoice_id     Number that identifies the invoice
   * @param bool    $delim          True if you want the result returned character delimited. Defaults to false, which returns results in an associative array format
   * @param string  $delim_char     Defaults to "," but can be set to any character you wish to delimit. Examples being "|" or "."
   *
   * @return mixed                  Returns associative array or delimited string on success OR cURL error string on failure
   */
  public function invoiceStatus($invoice_id, $delim = FALSE, $delim_char = ","){
    return $this->request("InvoiceStatus", array(
      "Invoice_ID" => $invoice_id,
      "x_delim_data" => ($delim) ? "TRUE" : "",
      "x_delim_char" => $delim_char
    ), array(
      "Result",
      "ResultDescription",
      "PaymentResult",
      "PaymentResultDescription",
      "Invoice_ID",
      "Check_ID"
    ));
  }

}
