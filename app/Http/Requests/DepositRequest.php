<?php

namespace App\Http\Requests;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class DepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $min = (int) Setting::get('min_deposit_amount', 100);
        $max = (int) Setting::get('max_deposit_amount', 1000000);

        return [
            // Whole XAF only — no decimals, no other currencies.
            'amount' => "required|integer|min:{$min}|max:{$max}",
            'payment_method' => 'required|in:mtn_momo,mock',
            // Optional — the Fapshi hosted checkout collects the mobile
            // money number itself. Accepted (and validated) if supplied.
            'phone' => [
                'nullable',
                'regex:/^(237)?6\d{8}$/',
            ],
        ];
    }

    public function messages(): array
    {
        $min = (int) Setting::get('min_deposit_amount', 100);
        $max = (int) Setting::get('max_deposit_amount', 1000000);

        return [
            'amount.required' => 'Please enter an amount',
            'amount.integer' => 'Amount must be a whole number of XAF',
            'amount.min' => "Minimum deposit amount is {$min} XAF.",
            'amount.max' => 'Maximum deposit amount is ' . number_format($max) . ' XAF.',
            'payment_method.required' => 'Please select a payment method',
            'payment_method.in' => 'Invalid payment method selected',
            'phone.required_if' => 'Enter the MTN Mobile Money number to charge',
            'phone.regex' => 'Enter a valid Cameroon mobile money number (e.g. 670000000)',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            back()->withErrors($validator)->withInput()
        );
    }
}
