<?php

declare(strict_types=1);

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

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
        ];
    }
}
