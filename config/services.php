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

    'telnyx' => [
        // v2 REST API key (KEY01…). Used as Bearer on every Telnyx REST call.
        'api_key' => env('TELNYX_API_KEY'),
        // Base64-encoded Ed25519 public key from Portal → Developers → Public
        // Key. Fed into sodium_crypto_sign_verify_detached() to prove each
        // webhook body actually came from Telnyx (replay-protected via timestamp).
        'public_key' => env('TELNYX_PUBLIC_KEY'),
        // Credential Connection ID — used to mint telephony_credentials so
        // the browser SDK can register via WebRTC. `connection_id` is the
        // legacy name (kept for back-compat with early demo .env files).
        'credential_connection_id' => env('TELNYX_CREDENTIAL_CONNECTION_ID', env('TELNYX_CONNECTION_ID')),
        // Call Control Application ID — used by the BACKEND to originate
        // outbound legs. Split from credential_connection_id because:
        //   • Telephony credentials REQUIRE a Credential Connection.
        //   • POST /v2/calls REQUIRES a Call Control App.
        // The bridge-based flow originates both the customer's PSTN leg
        // and the agent's SIP leg via this connection so both are Call
        // Control legs bridgeable via /v2/calls/{ccid}/actions/bridge.
        'call_control_app_id' => env('TELNYX_CALL_CONTROL_APP_ID'),
        // E.164 caller-id number. Bought in the Portal and bound to the
        // Call Control App above. Demo phase 1: US +14015982433 (PK number
        // KYC in-flight; swap here + config:clear when it arrives).
        'caller_id' => env('TELNYX_CALLER_ID'),
        // Webhook replay-attack window. Any request whose
        // Telnyx-Signature-Ed25519-Timestamp is older than this many seconds
        // is rejected before signature verification even runs.
        'signature_max_age_seconds' => (int) env('TELNYX_SIGNATURE_MAX_AGE_SECONDS', 300),
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

    // Telnyx — click-to-call + auto-recording on the leads screen.
    // See app/Services/Voice/TelnyxVoiceService.php. All values sourced
    // from .env; NEVER commit real credentials.

];
