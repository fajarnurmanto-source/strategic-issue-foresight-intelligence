<?php

namespace App\Services\Sifi\Ai;

final class FreeAiCatalogue
{
    public const SYNTHETIC = ['probe' => true, 'text' => 'SYNTHETIC CONNECTION TEST ONLY'];

    /** @return list<array<string, mixed>> */
    public static function entries(): array
    {
        return [
            ['protocol' => 'gemini', 'label' => 'Gemini API Free Tier', 'endpoint' => 'https://generativelanguage.googleapis.com/v1beta', 'models' => ['gemini-3.5-flash-lite'], 'status' => 'FREE ELIGIBILITY UNKNOWN', 'available' => true, 'quota' => 'RPM/TPM/RPD: periksa proyek dan model di AI Studio. Sisa kuota belum diketahui.', 'privacy' => 'Free tier dapat menggunakan input/output untuk peningkatan produk dan peninjauan manusia. Hanya teks sintetis publik.', 'docs' => 'https://ai.google.dev/gemini-api/docs/pricing'],
            ['protocol' => 'groq', 'label' => 'GroqCloud Free Plan', 'endpoint' => 'https://api.groq.com/openai/v1', 'models' => ['openai/gpt-oss-20b', 'openai/gpt-oss-120b'], 'status' => 'FREE ELIGIBILITY UNKNOWN', 'available' => true, 'quota' => 'Referensi free plan: 30 RPM / 1.000 RPD / 8.000 TPM / 200.000 TPD. Batas akun dapat berbeda; sisa kuota belum diketahui.', 'privacy' => 'Metadata penggunaan disimpan; konten dapat disimpan untuk reliabilitas/abuse hingga 30 hari. Hanya teks sintetis publik.', 'docs' => 'https://console.groq.com/docs/rate-limits'],
            ['protocol' => 'openrouter', 'label' => 'OpenRouter free variants', 'endpoint' => 'https://openrouter.ai/api/v1', 'models' => ['meta-llama/llama-3.3-70b-instruct:free'], 'status' => 'FREE ELIGIBILITY UNKNOWN', 'available' => false, 'quota' => 'Batas harian bergantung akun. Route dan dukungan schema endpoint belum diverifikasi. Integrasi belum diaktifkan.', 'privacy' => 'Kebijakan bergantung provider di belakang route; fallback berbayar dilarang.', 'docs' => 'https://openrouter.ai/docs/guides/routing/model-variants/free'],
            ['protocol' => 'ollama', 'label' => 'Ollama lokal saja', 'endpoint' => 'http://127.0.0.1:11434', 'models' => ['llama3.2:3b'], 'status' => 'FREE ELIGIBILITY UNKNOWN', 'available' => true, 'quota' => 'Tidak ada kuota API cloud untuk runtime lokal. Memakai RAM/CPU/GPU/listrik sendiri; kapasitas belum diperiksa.', 'privacy' => 'Wajib bukti cloud disabled dan model lokal terpasang. Tidak mengunduh model atau menyalakan runtime otomatis.', 'docs' => 'https://docs.ollama.com/faq'],
            ['protocol' => 'chat', 'label' => 'API berbayar (dinonaktifkan)', 'endpoint' => 'https://api.openai.com/v1', 'models' => [], 'status' => 'PAID', 'available' => false, 'quota' => 'Tidak diizinkan dalam kebijakan Rp0.', 'privacy' => 'Tidak ada koneksi atau fallback ke penyedia berbayar.', 'docs' => ''],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function provider(string $protocol): ?array
    {
        foreach (self::entries() as $entry) {
            if ($entry['protocol'] === $protocol) {
                return $entry;
            }
        }

        return null;
    }
}
