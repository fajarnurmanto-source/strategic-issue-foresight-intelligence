<?php

namespace App\Services\Sifi\Integration;

use App\Models\SifiConnectorProfile;

/**
 * Explain draft-only connector blockers without reading secrets or contacting providers.
 * This is a presentation aid, not a rights/dispatch/production admission service.
 */
final class ConnectorReadiness
{
    public function describe(SifiConnectorProfile $profile): array
    {
        $reasons = [
            ['code' => 'NO_DISPATCH_CONTRACT', 'message' => 'Kontrak izin eksekusi provider belum tersedia.'],
        ];

        if (! $profile->enabled) {
            $reasons[] = ['code' => 'CONFIGURATION_DISABLED', 'message' => 'Konfigurasi masih nonaktif.'];
        }

        if ($profile->rights_status !== 'APPROVED') {
            $reasons[] = ['code' => 'RIGHTS_NOT_APPROVED', 'message' => 'Hak penggunaan sumber belum disetujui untuk pemanggilan.'];
        }

        if ($profile->connection_status !== 'VERIFIED') {
            $reasons[] = ['code' => 'CONNECTION_NOT_VERIFIED', 'message' => 'Koneksi dan identitas endpoint belum diverifikasi.'];
        }

        if ($profile->rate_limit_per_minute < 1) {
            $reasons[] = ['code' => 'RATE_LIMIT_UNSET', 'message' => 'Batas permintaan aman belum ditentukan.'];
        }

        if ($profile->auth_scheme !== 'NONE' && ! $profile->credentialIsStored()) {
            $reasons[] = ['code' => 'CREDENTIAL_REFERENCE_MISSING', 'message' => 'Kredensial yang dibutuhkan belum tersimpan.'];
        }

        if ($profile->billing_authorized || $profile->max_cost_micros > 0) {
            $reasons[] = ['code' => 'POTENTIAL_SPEND_REQUIRES_REVIEW', 'message' => 'Konfigurasi biaya memerlukan pemeriksaan kebijakan zero-spend.'];
        }

        return [
            'state' => 'NOT_ADMITTED',
            'can_dispatch' => false,
            'connection_probe' => 'NOT_RUN',
            'reasons' => $reasons,
        ];
    }
}
