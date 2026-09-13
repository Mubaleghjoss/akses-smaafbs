<?php

namespace App\Services\Exam;

use App\Models\Exam\AiSetting;
use RuntimeException;

class AiQuestionService
{
    public function configuration(): AiSetting
    {
        $settings = AiSetting::query()->latest('id')->first();
        if (! $settings?->enabled || blank($settings->base_url) || blank($settings->model) || blank($settings->api_key)) {
            throw new RuntimeException('AI Soal belum siap. Admin perlu mengaktifkan provider serta mengisi Base URL, model, dan API key.');
        }
        return $settings;
    }

    public function testConnection(): array
    {
        $settings = $this->configuration();
        return ['ready' => true, 'message' => "Konfigurasi {$settings->provider}/{$settings->model} siap. Koneksi jaringan tidak dilakukan oleh MVP."];
    }
}
