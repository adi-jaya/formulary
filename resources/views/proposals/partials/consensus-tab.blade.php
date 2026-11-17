<div class="space-y-6">
    <!-- Header Analitik Konsensus -->
    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs">
        <h3 class="text-lg font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <span class="w-2.5 h-5 bg-emerald-600 rounded-full inline-block"></span>
            Analitik Konsensus Usulan Obat
        </h3>
        <p class="text-xs text-slate-500 mt-1">
            Peringkat usulan obat berdasarkan jumlah dokter spesialis (DPJP) yang mengusulkan secara independen.
        </p>
    </div>

    <!-- 3 KPI Cards Konsensus -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- KPI 1: Total Obat Unik Diusulkan -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Obat Unik Diusulkan</p>
                    <p class="text-2xl font-bold text-slate-900 mt-1">
                        {{ number_format($consensusMetrics['total_medicines'] ?? 0, 0, ',', '.') }}
                    </p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">Seluruh obat berbeda yang diajukan para DPJP</p>
        </div>

        <!-- KPI 2: Obat Konsensus Multidokter -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Obat Konsensus Multidokter (≥ 2 DPJP)</p>
                    <p class="text-2xl font-bold text-emerald-700 mt-1">
                        {{ number_format($consensusMetrics['consensus_medicines'] ?? 0, 0, ',', '.') }}
                    </p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">Diusulkan oleh 2 dokter spesialis atau lebih</p>
        </div>

        <!-- KPI 3: Dukungan Tertinggi -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Dukungan Tertinggi</p>
                    <p class="text-2xl font-bold text-slate-900 mt-1">
                        {{ $consensusMetrics['top_supported_count'] ?? 0 }} Dokter
                    </p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">Dukungan dokter terbanyak untuk satu jenis obat</p>
        </div>
    </div>

    <!-- Filter Bar Card Konsensus -->
    <div class="relative z-20 bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 space-y-4">
        <!-- Quick Year Filter Chips -->
        <div class="flex items-center flex-wrap gap-1.5 pb-3 border-b border-slate-100 text-xs">
            <span class="font-semibold text-slate-500 mr-1.5">Tahun Periode:</span>
            <a href="{{ route('proposals.index', array_merge(request()->except('period_year', 'page'), ['period_year' => '', 'tab' => 'consensus'])) }}"
               class="px-3 py-1 rounded-full font-medium transition-colors {{ empty($filters['period_year']) ? 'bg-emerald-700 text-white shadow-2xs font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                Semua Tahun
            </a>
            @foreach ($availableYears as $year)
                <a href="{{ route('proposals.index', array_merge(request()->except('period_year', 'page'), ['period_year' => $year, 'tab' => 'consensus'])) }}"
                   class="px-3 py-1 rounded-full font-medium transition-colors {{ ($filters['period_year'] ?? null) == $year ? 'bg-emerald-700 text-white shadow-2xs font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    {{ $year }}
                </a>
            @endforeach
        </div>

        <!-- Quick Filter Chips: Min Doctors (Konsensus) -->
        <div class="flex items-center flex-wrap gap-2 text-xs">
            <span class="font-semibold text-slate-500 mr-1">Tingkat Konsensus:</span>
            <a href="{{ route('proposals.index', array_merge(request()->except('min_doctors', 'page'), ['min_doctors' => '', 'tab' => 'consensus'])) }}"
               class="px-3 py-1.5 rounded-xl font-medium transition-colors {{ empty($filters['min_doctors']) ? 'bg-emerald-700 text-white shadow-2xs font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                Semua Obat
            </a>
            <a href="{{ route('proposals.index', array_merge(request()->except('min_doctors', 'page'), ['min_doctors' => 2, 'tab' => 'consensus'])) }}"
               class="px-3 py-1.5 rounded-xl font-medium transition-colors flex items-center gap-1.5 {{ (($filters['min_doctors'] ?? null) == 2) ? 'bg-emerald-700 text-white shadow-2xs font-semibold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                <svg class="w-3.5 h-3.5 {{ (($filters['min_doctors'] ?? null) == 2) ? 'text-white' : 'text-emerald-600' }}" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                </svg>
                <span>Hanya Usulan Konsensus (≥ 2 Dokter)</span>
            </a>
        </div>

        <!-- Search Form -->
        <form method="GET" action="{{ route('proposals.index') }}" class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-1">
            <input type="hidden" name="tab" value="consensus">
            @if (!empty($filters['period_year']))
                <input type="hidden" name="period_year" value="{{ $filters['period_year'] }}">
            @endif
            @if (!empty($filters['min_doctors']))
                <input type="hidden" name="min_doctors" value="{{ $filters['min_doctors'] }}">
            @endif

            <div class="w-full sm:flex-1 relative">
                <input type="text" 
                       name="search" 
                       value="{{ $filters['search'] ?? '' }}" 
                       placeholder="Cari nama obat konsensus..." 
                       class="w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-500 focus:outline-hidden">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                <a href="{{ route('proposals.index', ['tab' => 'consensus']) }}" 
                   class="px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors">
                    Reset
                </a>
                <button type="submit" 
                        class="px-5 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold shadow-xs transition-colors">
                    Terapkan Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Consensus Medicine Cards List -->
    <div class="space-y-4">
        @forelse ($consensusItems ?? [] as $consensusItem)
            <div x-data="{ open: false }" class="bg-white rounded-2xl border border-slate-200/80 shadow-xs hover:border-slate-300 transition-all overflow-hidden">
                <!-- Card Header -->
                <div class="p-5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="space-y-1.5">
                            <div class="flex items-center flex-wrap gap-2">
                                <h4 class="text-base font-bold text-slate-900 tracking-tight">
                                    {{ $consensusItem->medicine_name }}
                                </h4>

                                <!-- Distinct Doctors Badge -->
                                @if ($consensusItem->distinct_doctors_count >= 2)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                                        </svg>
                                        <span>{{ $consensusItem->distinct_doctors_count }} Dokter</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200/60">
                                        <svg class="w-3.5 h-3.5 text-slate-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                        </svg>
                                        <span>{{ $consensusItem->distinct_doctors_count }} Dokter</span>
                                    </span>
                                @endif

                                <!-- Total Proposals Count -->
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-500">
                                    {{ $consensusItem->total_proposals_count }} Usulan
                                </span>
                            </div>

                            <!-- Principal / Manufacturer Pills -->
                            @if (!empty($consensusItem->principals))
                                <div class="flex items-center flex-wrap gap-1.5 pt-0.5">
                                    <span class="text-[11px] text-slate-400 font-medium">Principle:</span>
                                    @foreach ($consensusItem->principals as $principal)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-50 text-slate-700 border border-slate-200/80">
                                            {{ $principal }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <!-- Accordion Toggle Button -->
                        <div class="flex items-center sm:self-center">
                            <button type="button" 
                                    @click="open = !open" 
                                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-slate-200 bg-slate-50/80 hover:bg-emerald-50 hover:border-emerald-200 hover:text-emerald-800 text-xs font-semibold text-slate-700 transition-all cursor-pointer">
                                <span x-text="open ? 'Sembunyikan Rincian' : 'Lihat Rincian Usulan'">Lihat Rincian Usulan</span>
                                <svg class="w-4 h-4 transition-transform duration-200 text-slate-500" 
                                     :class="open ? 'rotate-180 text-emerald-700' : ''" 
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Accordion Body: Grid Pengusul DPJP -->
                <div x-show="open" 
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-1"
                     class="border-t border-slate-100 bg-slate-50/50 p-5"
                     style="display: none;">
                    
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-3 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Rincian Pengusul ({{ count($consensusItem->proposals) }} Pengajuan):
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                        @foreach ($consensusItem->proposals as $prop)
                            <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-2xs space-y-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <span class="font-bold text-xs text-slate-900 block">{{ $prop->doctor_name }}</span>
                                        @if ($prop->principal_name)
                                            <span class="inline-block mt-0.5 px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600">
                                                {{ $prop->principal_name }}
                                            </span>
                                        @endif
                                    </div>
                                    <span class="text-[11px] text-slate-400 font-medium whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($prop->proposal_date)->translatedFormat('d M Y') }}
                                    </span>
                                </div>

                                @if ($prop->clinical_justification)
                                    <div class="pt-1 border-t border-slate-100 text-xs">
                                        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block mb-0.5">Alasan Klinis:</span>
                                        <blockquote class="text-slate-700 italic bg-emerald-50/40 border-l-2 border-emerald-400 px-2.5 py-1.5 rounded-r-lg text-xs leading-relaxed">
                                            &ldquo;{{ $prop->clinical_justification }}&rdquo;
                                        </blockquote>
                                    </div>
                                @endif

                                @if (!empty($prop->notes))
                                    <div class="pt-1 text-[11px] text-slate-600">
                                        <span class="font-semibold text-slate-500">Catatan Pengusul:</span>
                                        <p class="text-slate-600 mt-0.5 whitespace-pre-line">{{ $prop->notes }}</p>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200/80 p-12 text-center text-slate-400">
                <svg class="mx-auto h-8 w-8 text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <p class="font-medium text-slate-600">Tidak ada usulan obat konsensus.</p>
                <p class="text-xs text-slate-400 mt-1">Coba sesuaikan filter tahun, kata kunci pencarian, atau tingkat konsensus.</p>
            </div>
        @endforelse

        @if ($consensusItems && $consensusItems->hasPages())
            <div class="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs">
                {{ $consensusItems->links() }}
            </div>
        @endif
    </div>
</div>
