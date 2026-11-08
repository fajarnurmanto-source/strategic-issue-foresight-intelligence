@php($inventory = $this->inventory())

<x-filament-panels::page>
    <x-filament::section heading="Integration Hub â€” katalog baca-saja">
        <div class="space-y-3">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Daftar ini bersumber dari registry SIFI yang sudah ada. Tidak ada koneksi API,
                pembacaan kredensial pengguna, ataupun verifikasi kelayakan paket yang dijalankan.
            </p>
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::badge color="warning">CONNECTION CHECK: NOT RUN</x-filament::badge>
                <x-filament::badge color="gray">READ ONLY</x-filament::badge>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    Snapshot {{ $inventory['as_of'] }}
                </span>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section heading="Katalog AI">
        <div class="grid gap-3 md:grid-cols-2">
            @foreach ($inventory['ai'] as $provider)
                <div class="space-y-2 rounded-xl border border-gray-200 p-4 dark:border-gray-700"
                     wire:key="ai-{{ $provider['id'] }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-semibold">{{ $provider['label'] }}</h3>
                        <x-filament::badge :color="$provider['catalog_allowed'] ? 'info' : 'gray'">
                            {{ $provider['catalog_allowed'] ? 'CATALOG ALLOWED' : 'CATALOG DISABLED' }}
                        </x-filament::badge>
                    </div>
                    <p class="text-sm">Eligibility: {{ $provider['policy_state'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $provider['model_count'] }} model dalam katalog Â· Koneksi {{ $provider['connection_probe'] }}
                    </p>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section heading="Sumber data riset">
        <div class="grid gap-3 md:grid-cols-2">
            @foreach ($inventory['research'] as $source)
                <div class="space-y-2 rounded-xl border border-gray-200 p-4 dark:border-gray-700"
                     wire:key="data-{{ $source['id'] }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-semibold">{{ $source['label'] }}</h3>
                        <x-filament::badge :color="$source['review_state'] === 'REVIEW_EXPIRED' ? 'danger' : 'warning'">
                            {{ $source['review_state'] }}
                        </x-filament::badge>
                    </div>
                    <p class="text-sm">Aturan penggunaan: {{ $source['rights'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Tinjauan berlaku hingga: {{ $source['review_until'] ?: 'belum diketahui' }}
                        Â· Koneksi {{ $source['connection_probe'] }}
                    </p>
                </div>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            REVIEW_EXPIRED berarti tanggal evaluasi izin dan ketentuan sumber harus ditinjau ulang,
            bukan berarti lisensi sumber tersebut otomatis berakhir.
        </p>
    </x-filament::section>

    <x-filament::section heading="Tugas AI yang diizinkan oleh kontrak">
        <ul class="list-disc space-y-1 pl-5 text-sm">
            @foreach ($inventory['ai_tasks'] as $task)
                <li>{{ $task }}</li>
            @endforeach
        </ul>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            AI tidak memiliki kewenangan menetapkan kebenaran klaim, status bukti, atau admission produksi.
        </p>
    </x-filament::section>
</x-filament-panels::page>