<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isCustomer();
    }

    public function rules(): array
    {
        return [
            'country_id' => 'required|exists:countries,id',
            'service_id' => 'required|exists:services,id',
        ];
    }

    public function messages(): array
    {
        return [
            'country_id.required' => 'Please select a country',
            'country_id.exists' => 'Selected country is not available',
            'service_id.required' => 'Please select a service',
            'service_id.exists' => 'Selected service is not available',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            back()->withErrors($validator)->withInput()
        );
    }
}