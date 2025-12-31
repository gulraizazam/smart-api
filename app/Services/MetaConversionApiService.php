<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaConversionApiService
{
    protected $pixelId;
    protected $accessToken;
    protected $apiVersion;
    protected $testEventCode;
    protected $enabled;
    protected $baseUrl;

    public function __construct()
    {
        $this->pixelId = config('meta.pixel_id');
        $this->accessToken = config('meta.access_token');
        $this->apiVersion = config('meta.api_version');
        $this->testEventCode = config('meta.test_event_code');
        $this->enabled = config('meta.enabled');
        $this->baseUrl = "https://graph.facebook.com/{$this->apiVersion}/{$this->pixelId}/events";
    }

    /**
     * Send Lead Status to Meta
     * Supports: booked, arrived, converted, no_show, cancelled
     *
     * @param string $phone - Lead's phone number (primary identifier for matching)
     * @param string $status - Lead status: booked, arrived, converted, no_show, cancelled
     * @param string|null $leadId - Your internal lead ID (optional, for tracking)
     * @param string|null $email - Lead's email (optional, improves matching rate)
     * @return array
     */
    public function sendLeadStatus(
        string $phone,
        string $status,
        ?string $leadId = null,
        ?string $email = null,
        ?string $currency = null,
        $value = null
    ): array {
        Log::info('Meta CAPI: sendLeadStatus called', [
            'phone' => $phone,
            'status' => $status,
            'lead_id' => $leadId,
            'email' => $email,
            'enabled' => $this->enabled,
            'pixel_id_set' => !empty($this->pixelId),
            'access_token_set' => !empty($this->accessToken),
        ]);

        if (!$this->enabled) {
            Log::warning('Meta CAPI: API is disabled');
            return [
                'success' => false,
                'message' => 'Meta Conversion API is disabled'
            ];
        }

        if (empty($this->pixelId) || empty($this->accessToken)) {
            Log::warning('Meta CAPI: Pixel ID or Access Token not configured');
            return [
                'success' => false,
                'message' => 'Meta Pixel ID or Access Token is not configured'
            ];
        }

        // Map your CRM status to Meta's lead event
        $statusMapping = $this->mapStatusToMeta($status);
        Log::info('Meta CAPI: Status mapping result', [
            'status' => $status,
            'mapping' => $statusMapping,
        ]);
        
        // If status doesn't qualify (negative or unknown), don't send to Meta
        if (!$statusMapping) {
            Log::info('Meta CAPI: Status does not qualify for Meta optimization', [
                'status' => $status,
            ]);
            return [
                'success' => true,
                'message' => 'Status does not qualify for Meta optimization (ignored)'
            ];
        }
        // Build user data for matching
        $userData = [];

        if ($phone) {
            $userData['ph'] = $this->hashValue($this->normalizePhone($phone));
        }

        if ($email) {
            $userData['em'] = $this->hashValue(strtolower(trim($email)));
        }

        if ($leadId) {
            $userData['lead_id'] = $leadId;
        }

        // Ensure at least one identifier exists
        if (empty($userData)) {
            Log::warning('Meta CAPI: No valid user identifiers provided');
            return [
                'success' => false,
                'message' => 'No valid user identifiers (phone, email, or lead_id)'
            ];
        }

        // Build custom data with lead quality indicator
        $customData = [
           
            'lead_event_source' => 'CuteraCRM',
             //'event_source' => 'crm',
            'lead_status' => $status,
            'lead_quality' => $statusMapping['quality'], // qualified or disqualified
        ];

        if ($leadId) {
            $customData['lead_id'] = $leadId;
        }

        // Add currency and value for Purchase events (required by Meta)
        if ($statusMapping['event'] === 'Purchase') {
            $customData['currency'] = $currency ?? 'PKR';
            $customData['value'] = floatval($value ?? 0);
        }

        // Build the event data
        $eventData = [
            'event_name' => $statusMapping['event'],
            'event_time' => time(),
            'action_source' => 'system_generated',
            'user_data' => $userData,
            'custom_data' => $customData,
        ];

        // Use lead_id as event_id for deduplication if provided
        if ($leadId) {
            $eventData['event_id'] = $status . '_' . $leadId;
        }

        return $this->sendToMeta($eventData);
    }

    /**
     * Map CRM status to Meta event and quality indicator
     *
     * @param string $status
     * @return array ['event' => string, 'quality' => string]
     */
    protected function mapStatusToMeta(string $status): ?array
    {
        $normalizedStatus = strtolower(str_replace([' ', '-'], '_', $status));

        // Positive statuses - Meta will show ads to similar people
        // Note: Use standard Meta events or custom events that are configured in Events Manager
        $positiveStatuses = [
            'booked' => ['event' => 'Schedule', 'quality' => 'neutral'],
            'arrived' => ['event' => 'Contact', 'quality' => 'qualified'], // Contact = customer showed up/engaged
            'converted' => ['event' => 'Purchase', 'quality' => 'qualified'],  
        ];

        // Negative statuses - don't send to Meta (no value for optimization)
        $negativeStatuses = ['no_show', 'noshow', 'cancelled', 'canceled', 'junk', 'closed_lost'];

        if (isset($positiveStatuses[$normalizedStatus])) {
            return $positiveStatuses[$normalizedStatus];
        }

        // Negative or unknown statuses return null (won't be sent)
        if (in_array($normalizedStatus, $negativeStatuses)) {
            return null;
        }

        // Unknown status - don't send
        return null;
    }

    /**
     * Normalize phone number (remove spaces, dashes, country code variations)
     *
     * @param string $phone
     * @return string
     */
    protected function normalizePhone(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // If starts with 0, replace with country code (Pakistan: 92)
        if (substr($phone, 0, 1) === '0') {
            $phone = '92' . substr($phone, 1);
        }

        // If doesn't start with country code, add it
        if (strlen($phone) === 10) {
            $phone = '92' . $phone;
        }

        return $phone;
    }

    /**
     * Hash value using SHA256 as required by Meta
     *
     * @param string $value
     * @return string
     */
    protected function hashValue(string $value): string
    {
        return hash('sha256', $value);
    }

    /**
     * Send event data to Meta
     *
     * @param array $eventData
     * @return array
     */
    protected function sendToMeta(array $eventData): array
    {
        // Build the request payload
        $payload = [
            'data' => [json_encode($eventData)],
            'access_token' => $this->accessToken,
        ];

        // Add test event code if configured (for testing)
        if (!empty($this->testEventCode)) {
            $payload['test_event_code'] = $this->testEventCode;
        }

        try {
            $response = Http::post($this->baseUrl, $payload);

            if ($response->successful()) {
                Log::info('Meta CAPI: Lead Status Sent', [
                    'event_name' => $eventData['event_name'],
                    'lead_status' => $eventData['custom_data']['lead_status'] ?? null,
                    'response' => $response->json()
                ]);

                return [
                    'success' => true,
                    'message' => 'Lead status sent to Meta successfully',
                    'data' => $response->json()
                ];
            } else {
                Log::error('Meta CAPI: Failed to send lead status', [
                    'event_data' => $eventData,
                    'error' => $response->json()
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to send lead status to Meta',
                    'error' => $response->json()
                ];
            }
        } catch (\Exception $e) {
            Log::error('Meta CAPI Exception', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Exception occurred',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test the connection to Meta CAPI
     *
     * @return array
     */
    public function testConnection(): array
    {
        $eventData = [
            'event_name' => 'Lead',
            'event_time' => time(),
            'action_source' => 'system_generated',
            'user_data' => [
                'ph' => $this->hashValue('923001234567'),
            ],
            'custom_data' => [
                'lead_status' => 'test',
            ],
        ];

        return $this->sendToMeta($eventData);
    }
}
