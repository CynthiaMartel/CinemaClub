<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AzureTranslatorService
{
    private string $key;
    private string $region;
    private string $endpoint = 'https://api.cognitive.microsofttranslator.com/translate';

    public function __construct()
    {
        $this->key    = config('services.azure_translator.key');
        $this->region = config('services.azure_translator.region');
    }

    public function translate(string $text, string $from = 'en', string $to = 'es'): ?string
    {
        if (empty(trim($text))) return null;

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Ocp-Apim-Subscription-Key'    => $this->key,
                    'Ocp-Apim-Subscription-Region' => $this->region,
                    'Content-Type'                 => 'application/json',
                ])
                ->post("{$this->endpoint}?api-version=3.0&from={$from}&to={$to}", [
                    ['Text' => $text],
                ]);

            if (!$response->successful()) {
                Log::warning('Azure Translator error', ['status' => $response->status(), 'body' => $response->body()]);
                return null;
            }

            return $response->json()[0]['translations'][0]['text'] ?? null;

        } catch (\Throwable $e) {
            Log::error('Azure Translator exception: ' . $e->getMessage());
            return null;
        }
    }
}
