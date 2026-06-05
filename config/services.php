<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'passport' => [
        'password_client_id' => env('PASSPORT_PASSWORD_CLIENT_ID'),
        'password_client_secret' => env('PASSPORT_PASSWORD_CLIENT_SECRET'),
    ],

    // Round 4 Auth-C1 — Cloudflare Turnstile keys for the progressive
    // login CAPTCHA gate. The gate is a no-op when either value is empty,
    // so leaving these unset in local dev means no captcha prompts.
    // Provision a site at https://dash.cloudflare.com/?to=/:account/turnstile
    // and paste both keys into .env to enable.
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    // SMS gateway credentials, seeded into global_operator_settings by
    // GlobalOperatorSettingsSeed. Held here (not read via env() in the seeder)
    // so config:cache stays safe — the seeder reads config('services.sms.*').
    'sms' => [
        'telenor' => [
            'username' => env('SMS_TELENOR_USERNAME', ''),
            'password' => env('SMS_TELENOR_PASSWORD', ''),
        ],
        'jazz' => [
            'username' => env('SMS_JAZZ_USERNAME', ''),
            'password' => env('SMS_JAZZ_PASSWORD', ''),
        ],
    ],

];
