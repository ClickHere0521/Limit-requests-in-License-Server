<?php

// get values from request
mb_internal_encoding("UTF-8");

$data = file_get_contents('php://input');

$api_key = "12345678901234561234567890123456";

$session = $_GET["session"];
$encrypted_license = $_GET["license"];
$product = intval( $_GET["product"]);
$hmac = $_GET["hmac"];


//---******-----     From now, I added some codes.              ---****---//
// set the variables that define the limits:
//--------------     I limit max 10 requests in a minute.       ----------//
$min_time = 60; 
$max_requests = 10;

// Make sure we have a session scope
session_start();

// Create our requests array in session scope if it does not yet exist
if (!isset($_SESSION['requests'])) {
    $_SESSION['requests'] = [];
}

// Create a shortcut variable for this array (just for shorter & faster code)
$requests = &$_SESSION['requests'];

$countRecent = 0;

foreach($requests as $request) {

    // Count (only) new requests made in last minute
    if ($request["time"] >= time() - $min_time) {
        $countRecent++;
		if ($countRecent >= $max_requests) {
			session_unset();
			session_destroy();
        	die("Too many new ID requests in a short time");
		}   
		// Add current request to the log.
		$countRecent++;
		$requests[] = ["time" => time(), "productId" => $product];
    }
}

$content = $session . $encrypted_license . $owner . $product;

$result = "fail";
$current_license = "nope";

$license = openssl_decrypt(base64_decode($encrypted_license), "AES-256-ECB", $api_key, OPENSSL_ZERO_PADDING|				OPENSSL_RAW_DATA);

if ( $hmac == hash_hmac("sha256", $encrypted_license, $api_key, false)) {
	$result = "hmac_match";

	
	$curl = curl_init();
	
	curl_setopt_array($curl, array(
	  CURLOPT_URL => "https://test.bankersfx.com/wp-json/lmfwc/v2/licenses/",
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => "",
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 0,
	  CURLOPT_FOLLOWLOCATION => false,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => "GET",
	  CURLOPT_USERPWD => "ck_a98b5b12509f877b7907bab954b174977f5b7d8d:cs_52fe7615d278649d3fbfa624bd1bd46cce34f443",
	));

	$response = curl_exec($curl);
	$err = curl_error($curl);

	curl_close($curl);

	if ($err) {
	  $result = "cURL error: " . $err;
	} else {
		  $obj = json_decode( $response, true);
		  $result = "cURL_ok_" . count( $obj["data"]);
		  
		  foreach( $obj["data"] as $row) {
			if( $row[ "status"] == 2) 
            if( $row[ "expiresAt"] >= date("Y-m-d H:i:s"))
			if( $row["productId"] == $product) {
				$current_license = $row[ "licenseKey"];
				$current_license = str_replace( "%", "A", $current_license);
				$current_license = str_replace( "-", "A", $current_license);
				$current_license = str_replace( "!", "A", $current_license);
				$current_license = substr( $current_license, 0, 23);			
				if( substr( $license, 0, 23) == $current_license) 	
					$result = "confirmed";
				//$result = $row[ "licenseKey"];
				//$result = $license;
				}
			}
		}
	}
?>