<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Meta (Facebook) Conversion API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for Meta's Conversion API (CAPI).
    | You can obtain these values from your Meta Business Manager:
    | https://business.facebook.com/events_manager
    |
    */

    // Your Meta Pixel ID (found in Events Manager)
    'pixel_id' => env('META_PIXEL_ID', ''),

    // Your Conversion API Access Token (generated in Events Manager > Settings)
    'access_token' => env('META_ACCESS_TOKEN', ''),

    // API Version (use latest stable version)
    'api_version' => env('META_API_VERSION', 'v18.0'),

    // Test Event Code (for testing in Events Manager - remove in production)
    // You can find this in Events Manager > Test Events
    'test_event_code' => env('META_TEST_EVENT_CODE', ''),

    // Enable/Disable the Conversion API
    'enabled' => env('META_CAPI_ENABLED', true),

];
