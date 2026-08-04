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

    // Plivo — click-to-call + auto-recording on the leads screen.
    // See app/Services/Voice/PlivoVoiceService.php and the plan file
    // ~/.claude/plans/yes-its-working-fine-merry-breeze.md.
    // All values sourced from .env; NEVER commit real credentials.
    'plivo' => [
        'auth_id'           => env('PLIVO_AUTH_ID'),
        'auth_token'        => env('PLIVO_AUTH_TOKEN'),
        // A Plivo "Application" object holds the answer/hangup/recording URLs
        // — its UUID is bound to every outbound call so Plivo posts callbacks
        // to the right endpoints.
        'app_id'            => env('PLIVO_APP_ID'),
        // The real PK number bought in the Plivo console, e.g. "+922135XXXXXX".
        // Used as caller-ID on outbound calls and the inbound routing number.
        'caller_id'         => env('PLIVO_CALLER_ID'),
        // Optional URL to a pre-recorded bilingual "call is being recorded"
        // clip served from apidemo.smartaesthetics.pk/audio/recording-notice.mp3.
        // If unset, PlivoVoiceService falls back to <Speak> TTS.
        'record_prompt_url' => env('PLIVO_RECORD_PROMPT_URL'),
    ],

];
