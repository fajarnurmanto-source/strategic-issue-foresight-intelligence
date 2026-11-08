<x-filament-panels::page>
    <x-filament::section heading="Profil koneksi provider - tahap draft">
        <div class="space-y-3">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Registrasi sumber AI, media sosial, pencarian, berita, dan data terbuka.
                Semua koneksi tetap nonaktif, tanpa pemanggilan API, dan tanpa persetujuan biaya.
            </p>
            <div class="flex flex-wrap gap-2">
                <x-filament::badge color="warning">SHADOW / DRAFT ONLY</x-filament::badge>
                <x-filament::badge color="gray">BILLING NOT AUTHORIZED</x-filament::badge>
                <x-filament::badge color="gray">CONNECTION NOT TESTED</x-filament::badge>
            </div>
            @if (! $this->databaseReady())
                <p class="text-sm font-semibold text-amber-700 dark:text-amber-400">
                    Tabel konfigurasi belum tersedia pada database ini. Halaman aman dibaca,
                    tetapi formulir pembuatan draft tidak diaktifkan. Tidak ada migrasi otomatis.
                </p>
            @else
                <div>{{ $this->createDraftAction }}</div>
            @endif
        </div>
    </x-filament::section>

    @if ($this->databaseReady())
        @php($profiles = $this->profiles())
        <x-filament::section heading="Draft tersimpan">
            <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                {{ count($profiles) }} draft terdaftar. Diagnostik di bawah dihitung secara lokal;
                tidak ada pemeriksaan koneksi maupun izin eksekusi yang dijalankan.
            </p>
            <div class="grid gap-3 md:grid-cols-2">
                @forelse ($profiles as $profile)
                    <div class="space-y-2 rounded-xl border border-gray-200 p-4 dark:border-gray-700"
                         wire:key="draft-{{ $profile['id'] }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="font-semibold">{{ $profile['label'] }}</h3>
                            <x-filament::badge color="danger">{{ $profile['readiness']['state'] }}</x-filament::badge>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            {{ $profile['kind'] }} / {{ $profile['provider_key'] }}
                        </p>
                        <p class="text-sm">Endpoint host: {{ $profile['endpoint_host'] }}</p>
                        <p class="text-sm">
                            Credential: {{ $profile['credential_saved'] ? 'tersimpan terenkripsi' : 'tidak tersimpan' }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Batas konfigurasi: {{ $profile['rate_limit_per_minute'] }}/menit /
                            Biaya diizinkan: {{ $profile['billing_authorized'] ? 'YA' : 'TIDAK' }} /
                            Status: {{ $profile['rights_status'] }} / {{ $profile['connection_status'] }}
                        </p>
                        <div class="rounded-lg bg-amber-500/10 p-3 text-xs">
                            <p class="font-semibold">Alasan pemanggilan provider belum diizinkan:</p>
                            <ul class="mt-2 list-disc space-y-1 pl-4">
                                @foreach ($profile['readiness']['reasons'] as $reason)
                                    <li>{{ $reason['message'] }} <span class="text-gray-500">({{ $reason['code'] }})</span></li>
                                @endforeach
                            </ul>
                            <p class="mt-2">DISPATCH: {{ $profile['readiness']['can_dispatch'] ? 'ALLOWED' : 'DENIED' }}
                                / PROBE: {{ $profile['readiness']['connection_probe'] }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Belum ada draft provider. Gunakan formulir untuk mendaftarkan konfigurasi pertama.
                    </p>
                @endforelse
            </div>
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>