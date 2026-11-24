<?php

namespace Modules\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'exists:subscription_plans,id'],
            'provider' => ['nullable', 'string', 'in:paydunya,wave,orange_money,cinetpay,mock'],
            'payment_method' => ['nullable', 'string', 'in:wave,orange_money,card'],
        ];
    }
}
