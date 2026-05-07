<?php

namespace App\Http\Requests;

use App\Models\PricingConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQuoteRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $activeCfg = Cache::remember('active_pricing_config', 3600, fn () => PricingConfig::getActive());
        $validAddonKeys = array_keys($activeCfg->config['addons'] ?? []);
        $validPackageKeys = array_keys($activeCfg->config['base_packages'] ?? []);

        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:200'],
            'phone' => ['required', 'string', 'max:30'],
            'company' => ['nullable', 'string', 'max:200'],
            'service_category_id' => ['nullable', 'integer'],
            'service_package_id' => ['nullable', 'integer'],
            'package_key' => ['required', 'string', Rule::in($validPackageKeys)],
            'modifiers' => ['nullable', 'array'],
            'addon_keys' => ['nullable', 'array'],
            'addon_keys.*' => ['string', Rule::in($validAddonKeys)],
            'rush' => ['boolean'],
            'form_payload' => ['required', 'array'],
            'form_payload.source' => ['nullable', 'string', 'max:100'],
            'form_payload.notes' => ['nullable', 'string', 'max:2000'],
            'website_url' => ['nullable', 'max:0'], // honeypot — must be empty
            'cf_turnstile_response' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            if (!$this->verifyTurnstile($this->input('cf_turnstile_response'))) {
                $v->errors()->add('cf_turnstile_response', 'Bot verification failed. Please refresh and try again.');
            }
        });
    }

    private function verifyTurnstile(?string $token): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        $secret = config('services.turnstile.secret');

        // Allow bypass when not configured (local dev without Turnstile keys)
        if (!$secret || $secret === '') {
            return true;
        }

        $response = Http::asForm()->post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            ['secret' => $secret, 'response' => $token]
        );

        return $response->json('success') === true;
    }
}
