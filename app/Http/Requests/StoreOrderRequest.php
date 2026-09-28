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
            // Facebook/Meta orders require the VPN notice acknowledgment.
            'vpn_acknowledged' => 'nullable',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $slug = \App\Models\Service::where('id', $this->service_id)->value('slug');
            if (in_array($slug, ['facebook', 'meta'], true) && !$this->boolean('vpn_acknowledged')) {
                $v->errors()->add('vpn_acknowledged', 'Please confirm that you understand the Facebook VPN requirement before continuing.');
            }
        });
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