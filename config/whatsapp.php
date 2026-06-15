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

    // HTTP timeouts (seconds) for Graph calls. Bounded so a stalled Meta
    // endpoint can't hang an agent's "Send" request: control-plane calls
    // (send a message, resolve a media URL) use `timeout`; media byte
    // transfers (upload/download, can be a few MB) use `media_timeout`.
    'connect_timeout' => (int) env('WHATSAPP_CONNECT_TIMEOUT', 5),
    'timeout' => (int) env('WHATSAPP_TIMEOUT', 10),
    'media_timeout' => (int) env('WHATSAPP_MEDIA_TIMEOUT', 30),

    // The account that owns the WhatsApp line — inbound wa_ids are matched to
    // patients within this account only (the DB is single-account today; this
    // keeps the match tenant-scoped per the security charter). Default 1.
    'account_id' => (int) env('WHATSAPP_ACCOUNT_ID', 1),

    // Dialing country code (digits, no '+') used to reconcile a wa_id
    // (E.164, e.g. 923001234567) against locally-stored phones (0300…).
    'country_code' => env('WHATSAPP_COUNTRY_CODE', '92'),

    // Free-tier backstop: when true (default), WhatsAppService refuses to send
    // template messages (the only PAID surface) — a permanent guard so paid
    // sends are impossible even if a future endpoint/UI wires sendTemplate().
    // Flip to false (env WHATSAPP_FREE_TIER_ONLY=false) only as a deliberate
    // decision to start using paid templates.
    'free_tier_only' => (bool) env('WHATSAPP_FREE_TIER_ONLY', true),

];
