# Green PHP SDK
A well featured PHP SDK for calling methods from the Green Payment Processing API and parsing the responses. The eCheck API allows for integration of your application into our real-time check entry system. 

# Server Requirements
To be able to connect with Green Payment Processing, your server must have an SSL certificate installed and be able to make calls via HTTPS. Our service resides on a gatweay with 1024-bit encryption via SSL.

PHP version 5 >= 5.6 or PHP version 7 required as some methods make use of the PHP SoapClient class to make calls.

Our service supports the following protocols: HTTP POST/GET, SOAP 1.1 and SOAP 1.2. This PHP SDK uses POST (via cURL methods) for the majority of service calls, but relies on SoapClient where binary data must be sent securely.

# Installation
Manually copy the Green.php file into your server files structure and then include it anywhere you want to use it!

# Usage
In order to use our library, simply require the Green.php file, instantiate an object of the CheckGateway class, and then call its methods! Note that you must have valid API credentials (Client_ID and ApiPassword). If you do not have these, please contact Green Customer Support to get those for your merchant account

```php
require_once 'path/to/Green.php';

use Green;

$client_id = 'your_id_here';
$api_pass = 'your_password_here';

$gateway = new \Green\CheckGateway($client_id, $api_pass); 
```

A method call to an API will return a mixed result. If the API call failed, the methods return a boolean value of false. If the call succeeds, the method will return data either as an array or as a character delimited string based on how you requested output. The default returns as an associative array if character delimited return is not specified.

```php
//Results as array
$result = $gateway->checkStatus($check_id); 
/* 
$result = array (
 "Result" => "0",
 "ResultDescription" => "Not yet processed",
 "CheckNumber" => "123456",
 "Check_ID" => "234567"
)
*/

//Results as character delimited string
$result = $gateway->checkStatus($check_id, TRUE, ',');
//$result =  "0,Not yet processed,123456,234567";
```

# Examples
This repository contains an examples folder with a few files that can be used as an idea on how to use the main functions of this SDK to do things like create checks and view their status via API calls. These examples may not have been fully tested and as such may not be suitable for a production environment.

# API Documentation
Complete API documentation on all methods can be found at [Green's website](http://www.green.money/api) and you can contact [Customer Support](http://www.green.money/contact) with any questions about integration. When contacting support, please include in that email the name of the merchant you are with to speed up the process.

# License
This code is released under a modified MIT License which can be found in the repository code.
