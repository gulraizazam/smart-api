<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API (Meta) Configuration
    |--------------------------------------------------------------------------
    |
    | Credentials for the WhatsApp Business Cloud API. All values come from
    | Meta Business Manager / the Meta app dashboard:
    | https://developers.facebook.com/apps → WhatsApp → API Setup
    |
    */

    // Permanent system-user access token (NOT the 24h temporary token)
    'token' => env('WHATSAPP_TOKEN', ''),

    // The business phone number's id (not the phone number itself)
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),

    // WhatsApp Business Account (WABA) id
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID', ''),

    // Arbitrary secret we choose; Meta echoes it in the GET webhook
    // verification handshake (hub.verify_token)
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN', ''),

    // Meta app secret — used to verify the X-Hub-Signature-256 HMAC on
    // every webhook POST
    'app_secret' => env('WHATSAPP_APP_SECRET', ''),

    // Graph API version for outbound sends (no env key — bump here)
    'api_version' => 'v25.0',

];
