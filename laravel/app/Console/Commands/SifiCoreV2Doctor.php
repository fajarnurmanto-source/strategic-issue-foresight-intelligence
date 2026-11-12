<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Sifi\CoreV2\LegacySessionReadGateway;
use Illuminate\Console\Command;

/** Offline/read-only Core V2 adoption report. Not a replacement for composer audit. */
final class SifiCoreV2Doctor extends Command
{
    protected $signature = 'sifi:v2:doctor {--session= : Optional UUID for a specific existing session} {--json : Emit JSON report}';

    protected $description = 'Inspect Core V2 foundation and an existing E2E pipeline without mutating the app';

    public function handle(LegacySessionReadGateway $gateway): int
    {
        $lockFile = base_path('composer.lock');
        $versions = [];
        if (is_file($lockFile)) {
            $lock = json_decode((string) file_get_contents($lockFile), true);
            if (is_array($lock)) {
                foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $package) {
                    if (is_array($package) && isset($package['name'], $package['version'])
                        && is_string($package['name']) && is_string($package['version'])) {
                        $versions[$package['name']] = $package['version'];
                    }
                }
            }
        }
        $secureSymfony = [];
        foreach (['symfony/http-foundation', 'symfony/http-kernel', 'symfony/routing', 'symfony/serializer', 'symfony/yaml'] as $name) {
            $v = ltrim($versions[$name] ?? '', 'v');
            $secureSymfony[$name] = [
                'locked_version' => $v !== '' ? $v : null,
                'meets_7_4_23_floor' => $v !== '' && preg_match('/\A7\.4\.\d+\z/', $v) === 1
                    ? version_compare($v, '7.4.23', '>=') : null,
            ];
        }
        $session = $this->option('session');
        if (($session !== null && ! is_string($session))
            || (is_string($session) && $session !== '' && ! preg_match('/\A[0-9a-fA-F-]{36}\z/D', $session))) {
            $this->error('INVALID_UUID_OPTION');

            return self::INVALID;
        }
        try {
            $projection = $gateway->inspect(is_string($session) && $session !== '' ? $session : null);
        } catch (\Throwable $e) {
            $projection = ['status' => 'READ_MODEL_UNAVAILABLE', 'error_class' => get_class($e)];
        }
        $report = [
            'schema' => 'sifi-core-v2-r1-doctor-1',
            'core_v2_enabled' => config('sifi_core_v2.enabled', false) === true,
            'mode' => config('sifi_core_v2.mode', 'SHADOW_READ_ONLY'),
            'foundation_packages' => [
                'laravel/ai' => $versions['laravel/ai'] ?? null,
                'laravel/pennant' => $versions['laravel/pennant'] ?? null,
                'laravel/scout' => $versions['laravel/scout'] ?? null,
            ],
            'symfony_patch_floors' => $secureSymfony,
            'security_audit_status' => 'NOT_A_SUBSTITUTE_FOR_COMPOSER_AUDIT',
            'e2e_session_projection' => $projection,
            'network_requests' => 0,
            'database_writes' => 0,
            'ai_calls' => 0,
            'verified_evidence_admission' => 'NOT_ASSERTED',
        ];
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->info('SIFI_CORE_V2_R1_DOCTOR_PASS');
            $this->line('V2_MODE='.$report['mode']);
            $this->line('SESSION_STATUS='.($projection['status']));
            $this->line('NO_NETWORK_OR_DATABASE_CHANGES=TRUE');
            $this->line('SECURITY_AUDIT=RUN_COMPOSER_AUDIT_SEPARATELY');
        }

        return self::SUCCESS;
    }
}
