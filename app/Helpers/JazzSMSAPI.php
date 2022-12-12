<?php

namespace App\Helpers ;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class JazzSMSAPI {

    // Username for SMS APIs
    private static $_username = null;
    // Password for SMS APIs
    private static $_password = null;
    // Receiver List which will get SMS
    private static $_tos = null;
    // Text for Receiver List which will get SMS
    private static $_Message = null;
    // SMS Mast (optional)
    private static $_from = null;
    // Unicode for other than English (optional)
    private static $_unicode = null;

    // Test Mode enable/disable
    private static $_test_mode = 0;

    // Url where after authentication SMS will be sent.
    private static $_sendsms_url = 'https://connect.jazzcmt.com/sendsms_xml.html';

    /*
     * Send SMS based on provided Data
     * @param: array|mixed
     * @return: array|mixed
     */
    public static function SendSMS($SMSData) {
        // Error handling variables
        $status = 1;
        $error_msg = array();

        if(!isset($SMSData['username']) || !$SMSData['username']) {
            $status = false;
            $error_msg[] = 'Username is required';
        } else {
            self::$_username = $SMSData['username'];
        }

        if(!isset($SMSData['password']) || !$SMSData['password']) {
            $status = false;
            $error_msg[] = 'Password is required';
        } else {
            self::$_password = $SMSData['password'];
        }

        if(!isset($SMSData['to']) || !$SMSData['to']) {
            $status = false;
            $error_msg[] = 'To is required';
        } else {
            self::$_tos = is_array($SMSData['to']) ? implode(',', $SMSData['to']) : $SMSData['to'];
        }

        if(!isset($SMSData['text']) || !$SMSData['text']) {
            $status = false;
            $error_msg[] = 'Text is required';
        } else {
            self::$_Message = htmlentities($SMSData['text']);
        }

        if(!isset($SMSData['test_mode'])) {
            $status = false;
            $error_msg[] = 'Test Mode value is required';
        } else {
            self::$_test_mode = $SMSData['test_mode'];
        }

        if(isset($SMSData['from']) && $SMSData['from']) {
            self::$_from = $SMSData['from'];
        }

        // Verify Test Mode, If enable then send response immedately
        if(self::$_test_mode) {
            return array(
                'status' => true,
                'sms_data' => 'Test Mode is enabled',
                'error_msg' => '',
            );
        }

        if(!$status) {
            // One or more information is needed
            return array(
                'status' => $status,
                'sms_data' => '',
                'error_msg' => implode(', ', $error_msg),
            );
        } else {
            // All vaidation is complete now send SMS
            return self::sendQuickSMS( $SMSData );
        }
    }


    /*
     * Send Quick SMS based on provided Data
     * @param: void
     * @return: array|mixed
     */

    private static function sendQuickSMS( $SMSData ) {

        $client = new \GuzzleHttp\Client();

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><request></request>');
        $xml->addChild('Username', $SMSData['username']);
        $xml->addChild('Password', $SMSData['password']);
        $xml->addChild('From', $SMSData['from']);
        $xml->addChild('To', self::$_tos);
        $xml->addChild('Message', self::$_Message);
        $xml = $xml->asXML();
        // SMS Response Data
        $sms_data = '';
        try {
            $response = $client->request('POST', self::$_sendsms_url, [
                'headers' => [
                    'Content-Type' => 'text/xml; charset=UTF8',
                ],
                'body' => $xml
            ]);
            $status = true;
            $error_msg = '';
            if($response->getStatusCode() == 200) {
                $responseBody = $response->getBody();
                if($responseBody) {
                    try {
                        $responseJSON = \GuzzleHttp\json_decode(\GuzzleHttp\json_encode(simplexml_load_string($responseBody, "SimpleXMLElement", LIBXML_NOCDATA)), true);
                        if(!array_key_exists('messageid',$responseJSON)) {
                            $status = false;
                            $error_msg = 'Problem belongs to response code 11';
                        }
                    } catch (\Exception $e) {
                        $status = false;
                        $error_msg = 'Service is temporarily unavailable. Your patience is requested.';
                    }
                } else {
                    $status = false;
                    $error_msg = 'Unable to connect with server.';
                }
            } else {
                $status = false;
                $error_msg = $response->getReasonPhrase();
            }
        } catch (ConnectException $e) {
            $status = false;
            $error_msg = $e->getMessage();
        }
        return array(
            'status' => $status,
            'sms_data' => $sms_data,
            'error_msg' => $error_msg
        );
    }

    /*
    * Error Translation for SMS Gateway
    *
    * @param: string
    * @return: string
    */
    private static function tranlateError($ErrorCode) {
        $errorCodes = array(
            'Error 200' => 'Failed login. Username and password do not match.',
            'Error 201' => 'Unknown MSISDN, Please Check Format i.e. 92345xxxxxxx',
            'Error 100' => 'Out of credit.',
            'Error 101' => 'Field or input parameter missing',
            'Error 102' => 'Invalid session ID or the session has expired. Login again.',
            'Error 103' => 'Invalid Mask',
            'Error 104' => 'Invalid operator ID',
            'Error 211' => 'Unknown Message ID.',
            'Error 300' => 'Account has been blocked/suspended',
            'Error 400' => 'Duplicate list name.',
            'Error 401' => 'List name is missing.',
            'Error 411' => 'Invalid MSISDN in the list.',
            'Error 412' => 'List ID is missing.',
            'Error 413' => 'No MSISDNs in the list.',
            'Error 414' => 'List could not be updated. Unknown error.',
            'Error 415' => 'Invalid List ID.',
            'Error 500' => 'Duplicate campaign name.',
            'Error 501' => 'Campaign name is missing.',
            'Error 502' => 'SMS text is missing.',
            'Error 503' => 'No list selected or one of the list IDs is invalid.',
            'Error 504' => 'Invalid schedule time for campaign.',
            'Error 506' => 'Cannot send message at the specified time. Please specify a different time.',
            'Error 507' => 'Campaign could not be saved. Unknown Error',
            'Error 600' => 'Campaign ID is missing',
            'Error 700' => 'File ID is missing',
            'Error 701' => 'File not available or not ready',
            'Error 702' => 'Invalid value for max retries',
            'Error 703' => 'Invalid value for Call ID',
            'Error 704' => 'Invalid Mask for IVR',
            'Error 301' => 'Incoming SMS feature is not available for current user',
            'Error 302' => 'In valid action attribute value',
            'Error 303' => 'User has entered date and is not valid date',
            'Error 304' => 'API throughput limit reached for TPS Control mode',
            'Error 305' => 'User SMS/recipients exceeds than allowed throughput',
        );

        if($ErrorCode && array_key_exists($ErrorCode, $errorCodes)) {
            return $errorCodes[$ErrorCode];
        } else {
            return 'No error code match.';
        }
    }

}