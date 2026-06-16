<?php

declare(strict_types=1);

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SendWhatsappReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('whatsapp.inbox.reply');
    }

    public function rules(): array
    {
        return [
            // 4096 chars = the Cloud API's own text-body limit
            'message' => 'required|string|min:1|max:4096',
            // Optional: quote (reply to) an earlier message — must be one that
            // actually belongs to this conversation (either direction).
            'reply_to_wamid' => [
                'nullable', 'string',
                Rule::exists('whatsapp_messages', 'wamid')
                    ->where('whatsapp_conversation_id', (int) $this->route('id')),
            ],
        ];
    }
}
