<?php

// Require the library
require_once dirname(__FILE__) . '/../lib/TopcheckApiClient.php';

// Create new library client
$client = new \Topcheck\ApiClient\ApiClient('login', 'password', [

    'host'    => 'https://topcheck-test-server',
    'htlogin' => 'httplogin',
    'htpass'  => 'httppassword'
]);

/*
// Test updating of product status
$result = $client->setSingleProductStatus('577638D868907', [

     'isBvnSuccessful'         => true,
     'isCbSuccessful'          => false,
     'isBsSuccessful'          => false,
     'isAddressCorrect'        => true,
     'isDocumentationComplete' => false
 ]);
print_r($result);
*/

/*
// Test setting product rejected
$result = $client->setSingleProductRejected('577638D868907');
print_r($result);
*/

// Test setting product converted
$result = $client->setSingleProductConverted('577638D868907', [

    'loanAmountGranted' => 1000000,
    'tenure'            => 5,
    'conversionDate'    => '2016-04-25'
]);
print_r($result);
