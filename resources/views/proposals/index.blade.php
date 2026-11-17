<x-layout :title="'Rekapitulasi Usulan Obat Formularium'" :activeTab="'recap'">
    <div class="space-y-6" x-data="recapPage()">

        <!-- Top Header & Action Controls -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                    <span class="w-2.5 h-6 bg-emerald-600 rounded-full inline-block"></span>
                    Rekapitulasi Usulan Obat Formularium
                </h2>
                <p class="text-xs text-slate-500 mt-1">
                    Seluruh usulan obat dari para dokter tercatat secara real-time untuk ditinjau oleh Farmasi.
                </p>
            </div>

            <div class="flex items-center flex-wrap gap-2.5">
                <!-- KFT Review Mode Controls -->
                @if (session('kft_authenticated'))
                    <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-emerald-50 border border-emerald-300 text-emerald-800 text-xs font-semibold shadow-2xs">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Mode KFT Aktif: {{ session('reviewer_name', 'Tim Farmasi') }}</span>
                    </div>
                    <button type="button" 
                            @click="logoutKft()" 
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white border border-rose-200 hover:border-rose-400 hover:bg-rose-50 text-rose-700 text-xs font-semibold shadow-2xs transition-all cursor-pointer">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Keluar Mode KFT
                    </button>
                @else
                    <button type="button" 
                            @click="showKftAuthModal = true" 
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 text-amber-900 border border-amber-300/80 hover:border-amber-400 text-xs font-semibold shadow-2xs transition-all cursor-pointer">
                        <svg class="w-4 h-4 text-amber-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Mode Telaah KFT
                    </button>
                @endif

                <!-- Connect Live Spreadsheet Modal Button -->
                <button type="button" 
                        @click="showSpreadsheetModal = true"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white border border-slate-300 hover:border-emerald-500 hover:bg-emerald-50/50 text-slate-800 hover:text-emerald-900 text-xs font-semibold shadow-2xs transition-all cursor-pointer">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Hubungkan Live Spreadsheet
                </button>

                <!-- Export to Excel XLSX Button -->
                <a href="{{ url('/exports/proposals.xlsx?' . http_build_query(request()->query())) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 active:bg-emerald-900 text-white text-xs font-semibold shadow-xs transition-all">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Export ke Excel (.xlsx)
                </a>
            </div>
        </div>

        <!-- Segmented Tab Navigation -->
        <div class="flex items-center gap-2 p-1 bg-slate-100 rounded-xl w-fit border border-slate-200/80">
            <a href="{{ route('proposals.index', array_merge(request()->except('page'), ['tab' => 'items'])) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold transition-all {{ $activeTab === 'items' ? 'bg-white text-emerald-800 shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900' }}">
                <svg class="w-4 h-4 {{ $activeTab === 'items' ? 'text-emerald-700' : 'text-slate-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                <span>Daftar Usulan Item (Tabel)</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $activeTab === 'items' ? 'bg-emerald-50 text-emerald-800 font-bold' : 'bg-slate-200 text-slate-700' }}">{{ $totalItems }}</span>
            </a>
            <a href="{{ route('proposals.index', array_merge(request()->except('page'), ['tab' => 'consensus'])) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold transition-all {{ $activeTab === 'consensus' ? 'bg-white text-emerald-800 shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900' }}">
                <svg class="w-4 h-4 {{ $activeTab === 'consensus' ? 'text-emerald-700' : 'text-slate-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span>Analitik Konsensus Obat</span>
                @if (($consensusMetrics['consensus_medicines'] ?? 0) > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-emerald-100 text-emerald-800 font-bold">
                        {{ $consensusMetrics['consensus_medicines'] }} Konsensus
                    </span>
                @endif
            </a>
        </div>

        @if ($activeTab === 'consensus')
            @include('proposals.partials.consensus-tab')
        @else
        <!-- 3 Summary Metrics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Obat Diusulkan</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">{{ number_format($totalItems, 0, ',', '.') }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 mt-2">Termasuk obat dari berbagai principle</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Dokter Berpartisipasi</p>
                        <p class="text-2xl font-bold text-emerald-700 mt-1">{{ number_format($totalDoctors, 0, ',', '.') }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 mt-2">Dokter spesialis dan dokter umum aktif</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Pengiriman Formulir</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">{{ number_format($totalProposals, 0, ',', '.') }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 mt-2">Sesi pengajuan formulir terdaftar</p>
            </div>
        </div>

        <!-- Filter Bar Card -->
        <div class="relative z-20 bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5">
            <form method="GET" action="{{ route('proposals.index') }}" class="space-y-4">
                @if (!empty($filters['period_year']))
                    <input type="hidden" name="period_year" value="{{ $filters['period_year'] }}">
                @endif
                @if (request('sort_by'))
                    <input type="hidden" name="sort_by" value="{{ request('sort_by') }}">
                    <input type="hidden" name="sort_dir" value="{{ request('sort_dir') }}">
                @endif

                <!-- Quick Year Filter Chips -->
                <div class="flex items-center flex-wrap gap-1.5 pb-2 border-b border-slate-100 text-xs">
                    <span class="font-semibold text-slate-500 mr-1.5">Tahun Periode:</span>
                    <a href="{{ route('proposals.index', array_merge(request()->except('period_year', 'page'), ['period_year' => ''])) }}"
                       class="px-3 py-1 rounded-full font-medium transition-colors {{ empty($filters['period_year']) ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        Semua Tahun
                    </a>
                    @foreach ($availableYears as $year)
                        <a href="{{ route('proposals.index', array_merge(request()->except('period_year', 'page'), ['period_year' => $year])) }}"
                           class="px-3 py-1 rounded-full font-medium transition-colors {{ ($filters['period_year'] ?? null) == $year ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                            {{ $year }}
                        </a>
                    @endforeach
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 relative z-20">
                    <!-- Dropdown Dokter (Custom Select) -->
                    <x-custom-select 
                        name="doctor"
                        label="Dokter"
                        :options="$availableDoctors"
                        :selected="$filters['doctor'] ?? ''"
                        placeholder="Semua Dokter"
                        search-placeholder="Cari nama dokter..." />

                    <!-- Dropdown Principle (Custom Select) -->
                    <x-custom-select 
                        name="principal"
                        label="Principle / Pabrik"
                        :options="$availablePrincipals"
                        :selected="$filters['principal'] ?? ''"
                        placeholder="Semua Principle"
                        search-placeholder="Cari principle / pabrik..." />

                    <!-- Dropdown Status Telaah (Custom Select) -->
                    <x-custom-select 
                        name="review_status"
                        label="Status Telaah"
                        :options="$availableReviewStatuses"
                        :selected="$filters['review_status'] ?? ''"
                        placeholder="Semua Status"
                        search-placeholder="Cari status telaah..." />

                    <!-- Dari Tanggal -->
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Dari Tanggal</label>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="w-full h-[38px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-500 focus:outline-hidden">
                    </div>

                    <!-- Sampai Tanggal -->
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Sampai Tanggal</label>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="w-full h-[38px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-500 focus:outline-hidden">
                    </div>
                </div>

                <!-- Baris Pencarian Kata Kunci & Tombol Filter -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2 relative z-10">
                    <div class="w-full sm:flex-1 relative">
                        <input type="text" 
                               name="search" 
                               value="{{ $filters['search'] ?? '' }}" 
                               placeholder="Cari nama obat, dokter, atau principle..." 
                               class="w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-500 focus:outline-hidden">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                        <a href="{{ route('proposals.index') }}" 
                           class="px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors">
                            Reset
                        </a>
                        <button type="submit" 
                                class="px-5 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold shadow-xs transition-colors cursor-pointer">
                            Terapkan Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Data Table Card -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-600 uppercase tracking-wider">
                        <tr>
                            <th scope="col" class="py-3 px-4 w-12 text-center">No</th>
                            <x-table-sort-header column="date" label="Tanggal Usulan" :current-sort-by="$sortBy" :current-sort-dir="$sortDir" default-dir="desc" />
                            <x-table-sort-header column="period" label="Periode" :current-sort-by="$sortBy" :current-sort-dir="$sortDir" />
                            <x-table-sort-header column="doctor" label="Nama Dokter" :current-sort-by="$sortBy" :current-sort-dir="$sortDir" />
                            <x-table-sort-header column="medicine" label="Nama Obat Diusulkan" :current-sort-by="$sortBy" :current-sort-dir="$sortDir" />
                            <x-table-sort-header column="principal" label="Principle / Pabrik" :current-sort-by="$sortBy" :current-sort-dir="$sortDir" />
                            <th scope="col" class="py-3 px-4 min-w-[220px]">Alasan Klinis</th>
                            <th scope="col" class="py-3 px-4 min-w-[200px]">Catatan Pengusul</th>
                            <x-table-sort-header column="status" label="Status Telaah" :current-sort-by="$sortBy" :current-sort-dir="$sortDir" />
                            <th scope="col" class="py-3 px-4 min-w-[220px]">Catatan KFT</th>
                            <x-table-sort-header column="created_at" label="Waktu Input" :current-sort-by="$sortBy" :current-sort-dir="$sortDir" default-dir="desc" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($items as $index => $item)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="py-3 px-4 text-center text-slate-400 font-medium">
                                    {{ $items->firstItem() + $index }}
                                </td>
                                <td class="py-3 px-4 font-semibold text-slate-900 whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($item->proposal_date)->translatedFormat('d M Y') }}
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/50">
                                        {{ $item->period_year }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-medium text-slate-800">
                                    {{ $item->doctor_name }}
                                </td>
                                <td class="py-3 px-4 font-semibold text-emerald-950">
                                    {{ $item->medicine_name }}
                                </td>
                                <td class="py-3 px-4">
                                    @if ($item->principal_name)
                                        <span class="inline-block px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-700">
                                            {{ $item->principal_name }}
                                        </span>
                                    @else
                                        <span class="text-slate-400 italic text-[11px]">-</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-slate-700 whitespace-pre-line leading-relaxed">
                                    @if ($item->clinical_justification)
                                        {{ $item->clinical_justification }}
                                    @else
                                        <span class="text-slate-400 italic text-[11px]">-</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-slate-600 whitespace-pre-line leading-relaxed">
                                    @if ($item->notes)
                                        {{ $item->notes }}
                                    @else
                                        <span class="text-slate-400 italic text-[11px]">-</span>
                                    @endif
                                </td>
                                <!-- Status Telaah Badge & Edit Trigger -->
                                <td class="py-3 px-4 whitespace-nowrap">
                                    @php
                                        $status = $item->review_status ?? 'pending';
                                    @endphp
                                    @if ($status === 'approved')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Disetujui
                                        </span>
                                    @elseif ($status === 'rejected')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-800 border border-rose-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                            Ditolak
                                        </span>
                                    @elseif ($status === 'substituted')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-sky-50 text-sky-800 border border-sky-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                                            Substitusi
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                            Menunggu
                                        </span>
                                    @endif

                                    @if (session('kft_authenticated'))
                                        <div class="mt-1.5">
                                            <button type="button"
                                                    @click="openReviewModal(@js([
                                                        'id' => $item->id,
                                                        'medicine_name' => $item->medicine_name,
                                                        'doctor_name' => $item->doctor_name,
                                                        'review_status' => $item->review_status ?? 'pending',
                                                        'review_notes' => $item->review_notes ?? '',
                                                    ]))"
                                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 hover:text-emerald-900 underline underline-offset-2 transition-colors cursor-pointer">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                Edit Telaah
                                            </button>
                                        </div>
                                    @endif
                                </td>
                                <!-- Catatan Pertimbangan KFT -->
                                <td class="py-3 px-4 text-slate-700 leading-relaxed">
                                    @if ($item->review_notes)
                                        <div class="whitespace-pre-line text-xs">{{ $item->review_notes }}</div>
                                    @else
                                        <span class="text-slate-400 italic text-[11px]">-</span>
                                    @endif
                                    @if ($item->reviewer_name || $item->reviewed_at)
                                        <div class="text-[10px] text-slate-400 mt-1 flex items-center gap-1">
                                            <svg class="w-3 h-3 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            <span>
                                                {{ $item->reviewer_name ?? 'Tim Farmasi' }}
                                                @if ($item->reviewed_at)
                                                    &bull; {{ \Carbon\Carbon::parse($item->reviewed_at)->translatedFormat('d/m/Y H:i') }}
                                                @endif
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-slate-400 whitespace-nowrap text-[11px]">
                                    {{ $item->created_at ? $item->created_at->translatedFormat('d/m/Y H:i') : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="py-12 text-center text-slate-400">
                                    <svg class="mx-auto h-8 w-8 text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                    </svg>
                                    <p class="font-medium text-slate-600">Tidak ada usulan obat yang cocok dengan filter saat ini.</p>
                                    <p class="text-xs text-slate-400 mt-1">Coba ubah kata kunci atau hapus beberapa filter pencarian.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($items->hasPages())
                <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                    {{ $items->links() }}
                </div>
            @endif
        </div>
        @endif

        <!-- Live Spreadsheet Modal (Alpine.js) -->
        <div x-show="showSpreadsheetModal"
             x-transition.opacity.duration.200ms
             class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
             style="display: none;">
            <div class="relative bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-4"
                 @click.outside="showSpreadsheetModal = false">
                
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Hubungkan Live Spreadsheet
                    </h3>
                    <button type="button" @click="showSpreadsheetModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-3 text-xs text-slate-600">
                    <p>
                        Sinkronisasikan seluruh data usulan obat ke <strong>Google Sheets</strong> atau <strong>Microsoft Excel</strong> secara otomatis tanpa perlu unduh file berulang kali.
                    </p>

                    <!-- Google Sheets Formula 1-Click Copy -->
                    <div class="bg-slate-50 rounded-xl p-3 border border-slate-200">
                        <label class="block font-semibold text-slate-700 mb-1">Rumus Google Sheets (8 Kolom Default):</label>
                        <div class="flex items-center gap-2">
                            <input type="text" 
                                   readonly 
                                   value="{{ $importDataFormula }}" 
                                   id="formulaInput"
                                   class="w-full bg-white border border-slate-300 rounded-lg px-2.5 py-1.5 font-mono text-[11px] text-slate-800 select-all">
                            <button type="button"
                                    @click="navigator.clipboard.writeText('{{ $importDataFormula }}'); copied = true; setTimeout(() => copied = false, 2500)"
                                    class="px-3 py-1.5 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white font-semibold text-xs whitespace-nowrap transition-colors cursor-pointer">
                                <span x-show="!copied">Salin</span>
                                <span x-show="copied" class="text-emerald-200">Tersalin!</span>
                            </button>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1.5">
                            Tempelkan rumus di atas pada sel <code>A1</code> di lembar Google Sheets Anda.
                        </p>
                        <div class="mt-2.5 p-2.5 bg-amber-50 rounded-lg border border-amber-200/80 text-[11px] text-amber-900 flex items-start gap-2">
                            <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span><strong>Tips Kolom Tanggal:</strong> Jika Google Sheets menampilkan angka seri (seperti <code>46340</code>), cukup blok <strong>Kolom B</strong> &rarr; buka menu <strong>Format &gt; Angka &gt; Tanggal</strong>.</span>
                        </div>
                        <div class="mt-2 p-2.5 bg-sky-50 rounded-lg border border-sky-200/80 text-[11px] text-sky-950 flex items-start gap-2">
                            <svg class="w-4 h-4 text-sky-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span><strong>Pembaruan Usulan Baru:</strong> Google Sheets meng-cache data eksternal berkala (tiap &plusmn;1–2 jam). Jika ingin data usulan baru muncul seketika, cukup hapus sel <code>A1</code> lalu tekan <strong>Ctrl + Z</strong> (Undo).</span>
                        </div>
                    </div>

                    <!-- 12 Canonical Columns (Opt-in Review) Guide -->
                    <div class="p-3 bg-emerald-50/60 rounded-xl border border-emerald-200 text-slate-700 space-y-2">
                        <div class="flex items-center gap-1.5 text-emerald-950 font-semibold text-xs">
                            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Sertakan 4 Kolom Telaah KFT (12 Kolom Kanonikal)</span>
                        </div>
                        <p class="text-[11px] text-slate-600 leading-relaxed">
                            Secara default formula di atas mengekspor 8 kolom awal tanpa merusak tata letak lembar kerja lama. Untuk menarik 4 kolom tambahan telaah KFT (<em>Status Telaah, Catatan Telaah, Penelaah, Waktu Input</em>), tambahkan parameter <code class="px-1.5 py-0.5 rounded bg-white border border-emerald-300 font-mono text-emerald-800 font-semibold">&amp;include_review=1</code>:
                        </p>
                        <div class="flex items-center gap-2">
                            <input type="text" 
                                   readonly 
                                   value='=IMPORTDATA("{{ $liveCsvUrl }}&include_review=1")' 
                                   class="w-full bg-white border border-emerald-300 rounded-lg px-2.5 py-1 font-mono text-[10px] text-slate-800 select-all">
                        </div>
                    </div>

                    <!-- Excel Guide -->
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-slate-700 space-y-1">
                        <p class="font-semibold text-slate-900">Cara Pakai di Microsoft Excel:</p>
                        <ol class="list-decimal list-inside space-y-0.5 text-[11px] text-slate-600">
                            <li>Buka Excel &rarr; tab <strong>Data</strong> &rarr; pilih <strong>Dari Web (From Web)</strong>.</li>
                            <li>Masukkan URL CSV Feed ber-token berikut:</li>
                        </ol>
                        <input type="text" readonly value="{{ $liveCsvUrl }}" class="w-full bg-white border border-slate-300 rounded-lg px-2 py-1 font-mono text-[10px] text-slate-700 select-all mt-1">
                        <p class="text-[10px] text-slate-500 mt-1">
                            <em>Tips Refresh:</em> Klik tombol <strong>Segarkan Semua (Refresh All)</strong> di tab <strong>Data</strong> untuk menarik usulan terbaru, atau atur auto-refresh per 5 menit di <em>Properties</em> kueri.
                        </p>
                    </div>
                </div>

                <div class="pt-2 flex justify-end">
                    <button type="button" 
                            @click="showSpreadsheetModal = false"
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition-colors cursor-pointer">
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- Auth Passcode Modal (Alpine.js) -->
        @if (! session('kft_authenticated'))
        <div x-show="showKftAuthModal"
             x-transition.opacity.duration.200ms
             class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
             style="display: none;">
            <div class="relative bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4"
                 @click.outside="showKftAuthModal = false">
                
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Masuk Mode Telaah KFT
                    </h3>
                    <button type="button" @click="showKftAuthModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <p class="text-xs text-slate-600">
                    Masukkan passcode rahasia telaah KFT untuk mengaktifkan sesi penelaahan usulan formularium obat bagi Tim Farmasi.
                </p>

                <template x-if="kftAuthError">
                    <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2">
                        <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span x-text="kftAuthError"></span>
                    </div>
                </template>

                <form @submit.prevent="submitKftAuth()" class="space-y-3">
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">
                            Passcode Telaah KFT <span class="text-rose-500">*</span>
                        </label>
                        <input type="password" 
                               x-model="kftSecret" 
                               placeholder="Masukkan passcode..." 
                               required
                               class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-hidden">
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">
                            Nama Penelaah / Apoteker (Opsional)
                        </label>
                        <input type="text" 
                               x-model="kftReviewerName" 
                               placeholder="Contoh: apt. Siti Rahma, S.Farm" 
                               class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 focus:outline-hidden">
                        <p class="text-[10px] text-slate-400 mt-1">Default: Tim Farmasi</p>
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-2">
                        <button type="button" 
                                @click="showKftAuthModal = false"
                                class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition-colors cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" 
                                :disabled="kftAuthLoading"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white rounded-xl text-xs font-semibold shadow-xs transition-colors cursor-pointer">
                            <span x-show="!kftAuthLoading">Buka Sesi KFT</span>
                            <span x-show="kftAuthLoading" class="inline-flex items-center gap-1.5">
                                <svg class="animate-spin w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                Memverifikasi...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        <!-- Review Edit Modal (Alpine.js) -->
        @if (session('kft_authenticated'))
        <div x-show="showReviewModal"
             x-transition.opacity.duration.200ms
             class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
             style="display: none;">
            <div class="relative bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-4"
                 @click.outside="showReviewModal = false">
                
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        Edit Telaah Obat KFT
                    </h3>
                    <button type="button" @click="showReviewModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Target Info Box -->
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Nama Obat:</span>
                        <span class="font-bold text-emerald-950 text-right" x-text="reviewMedicineName"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Dokter Pengusul:</span>
                        <span class="font-medium text-slate-800 text-right" x-text="reviewDoctorName"></span>
                    </div>
                </div>

                <template x-if="reviewError">
                    <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2">
                        <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span x-text="reviewError"></span>
                    </div>
                </template>

                <template x-if="reviewSuccess">
                    <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span x-text="reviewSuccess"></span>
                    </div>
                </template>

                <form @submit.prevent="submitReview()" class="space-y-4">
                    <!-- Status Dropdown (Alpine Custom Select) -->
                    <div class="relative" x-data="{ statusOpen: false }">
                        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">
                            Status Telaah
                        </label>
                        <button type="button" 
                                @click="statusOpen = !statusOpen"
                                class="w-full flex items-center justify-between h-[38px] px-3 rounded-xl border border-slate-300 bg-white text-xs text-slate-800 shadow-2xs hover:border-slate-400 focus:outline-hidden cursor-pointer">
                            <span class="flex items-center gap-2">
                                <template x-if="reviewStatus === 'pending'">
                                    <span class="inline-flex items-center gap-1.5 text-amber-800 font-semibold"><span class="w-2 h-2 rounded-full bg-amber-500"></span> Menunggu Telaah</span>
                                </template>
                                <template x-if="reviewStatus === 'approved'">
                                    <span class="inline-flex items-center gap-1.5 text-emerald-800 font-semibold"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Disetujui</span>
                                </template>
                                <template x-if="reviewStatus === 'rejected'">
                                    <span class="inline-flex items-center gap-1.5 text-rose-800 font-semibold"><span class="w-2 h-2 rounded-full bg-rose-500"></span> Ditolak</span>
                                </template>
                                <template x-if="reviewStatus === 'substituted'">
                                    <span class="inline-flex items-center gap-1.5 text-sky-800 font-semibold"><span class="w-2 h-2 rounded-full bg-sky-500"></span> Substitusi</span>
                                </template>
                            </span>
                            <svg class="w-4 h-4 text-slate-400 transition-transform" :class="{ 'rotate-180': statusOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="statusOpen" 
                             @click.outside="statusOpen = false"
                             class="absolute z-30 mt-1 w-full bg-white rounded-xl border border-slate-200 shadow-lg py-1 text-xs"
                             style="display: none;">
                            <div @click="reviewStatus = 'pending'; statusOpen = false" class="px-3 py-2 cursor-pointer hover:bg-amber-50 flex items-center justify-between text-amber-900">
                                <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-amber-500"></span> Menunggu Telaah</span>
                                <span x-show="reviewStatus === 'pending'" class="text-amber-600 font-bold">&check;</span>
                            </div>
                            <div @click="reviewStatus = 'approved'; statusOpen = false" class="px-3 py-2 cursor-pointer hover:bg-emerald-50 flex items-center justify-between text-emerald-900">
                                <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Disetujui</span>
                                <span x-show="reviewStatus === 'approved'" class="text-emerald-600 font-bold">&check;</span>
                            </div>
                            <div @click="reviewStatus = 'rejected'; statusOpen = false" class="px-3 py-2 cursor-pointer hover:bg-rose-50 flex items-center justify-between text-rose-900">
                                <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-rose-500"></span> Ditolak</span>
                                <span x-show="reviewStatus === 'rejected'" class="text-rose-600 font-bold">&check;</span>
                            </div>
                            <div @click="reviewStatus = 'substituted'; statusOpen = false" class="px-3 py-2 cursor-pointer hover:bg-sky-50 flex items-center justify-between text-sky-900">
                                <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-sky-500"></span> Substitusi</span>
                                <span x-show="reviewStatus === 'substituted'" class="text-sky-600 font-bold">&check;</span>
                            </div>
                        </div>
                    </div>

                    <!-- Catatan Pertimbangan KFT -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                Catatan Pertimbangan KFT
                            </label>
                            <span class="text-[10px] text-slate-400" :class="{ 'text-rose-600 font-bold': reviewNotes.length > 2000 }">
                                <span x-text="reviewNotes.length"></span> / 2000
                            </span>
                        </div>
                        <textarea x-model="reviewNotes"
                                  maxlength="2000"
                                  rows="4"
                                  placeholder="Tuliskan catatan telaah, alasan keputusan sidang, atau arahan substitusi obat..."
                                  class="w-full rounded-xl border border-slate-300 bg-white p-3 text-xs text-slate-800 placeholder-slate-400 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-500 focus:outline-hidden"></textarea>
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-2">
                        <button type="button" 
                                @click="showReviewModal = false"
                                class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition-colors cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" 
                                :disabled="reviewLoading"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 text-white rounded-xl text-xs font-semibold shadow-xs transition-colors cursor-pointer">
                            <span x-show="!reviewLoading">Simpan Telaah</span>
                            <span x-show="reviewLoading" class="inline-flex items-center gap-1.5">
                                <svg class="animate-spin w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                Menyimpan...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>

    <!-- Alpine Component Logic -->
    <script>
        function recapPage() {
            return {
                showSpreadsheetModal: false,
                copied: false,
                showKftAuthModal: false,
                kftSecret: '',
                kftReviewerName: @js(session('reviewer_name', '')),
                kftAuthError: '',
                kftAuthLoading: false,

                showReviewModal: false,
                reviewItemId: null,
                reviewMedicineName: '',
                reviewDoctorName: '',
                reviewStatus: 'pending',
                reviewNotes: '',
                reviewLoading: false,
                reviewError: '',
                reviewSuccess: '',

                openReviewModal(item) {
                    this.reviewItemId = item.id;
                    this.reviewMedicineName = item.medicine_name;
                    this.reviewDoctorName = item.doctor_name;
                    this.reviewStatus = item.review_status || 'pending';
                    this.reviewNotes = item.review_notes || '';
                    this.reviewError = '';
                    this.reviewSuccess = '';
                    this.showReviewModal = true;
                },

                submitKftAuth() {
                    if (!this.kftSecret.trim()) {
                        this.kftAuthError = 'Passcode wajib diisi.';
                        return;
                    }
                    this.kftAuthError = '';
                    this.kftAuthLoading = true;

                    fetch('{{ route("recap.auth") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            secret: this.kftSecret,
                            reviewer_name: this.kftReviewerName
                        })
                    })
                    .then(async (res) => {
                        const data = await res.json().catch(() => ({}));
                        return { status: res.status, ok: res.ok, data };
                    })
                    .then(({ ok, data }) => {
                        this.kftAuthLoading = false;
                        if (ok && data.success) {
                            window.location.reload();
                        } else {
                            this.kftAuthError = (data.errors && data.errors.secret) 
                                ? data.errors.secret[0] 
                                : (data.message || 'Passcode salah.');
                        }
                    })
                    .catch(() => {
                        this.kftAuthLoading = false;
                        this.kftAuthError = 'Terjadi kesalahan jaringan.';
                    });
                },

                logoutKft() {
                    if (!confirm('Keluar dari sesi telaah KFT?')) return;
                    fetch('{{ route("recap.logout") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    })
                    .then(() => window.location.reload())
                    .catch(() => window.location.reload());
                },

                submitReview() {
                    if (!this.reviewItemId) return;
                    this.reviewLoading = true;
                    this.reviewError = '';
                    this.reviewSuccess = '';

                    const url = '{{ url("/proposals/items") }}/' + this.reviewItemId + '/review';
                    fetch(url, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            review_status: this.reviewStatus,
                            review_notes: this.reviewNotes
                        })
                    })
                    .then(async (res) => {
                        const data = await res.json().catch(() => ({}));
                        return { status: res.status, ok: res.ok, data };
                    })
                    .then(({ ok, data }) => {
                        this.reviewLoading = false;
                        if (ok && data.success) {
                            this.reviewSuccess = 'Telaah berhasil disimpan!';
                            setTimeout(() => {
                                window.location.reload();
                            }, 500);
                        } else {
                            this.reviewError = data.message || 'Gagal menyimpan telaah.';
                        }
                    })
                    .catch(() => {
                        this.reviewLoading = false;
                        this.reviewError = 'Terjadi kesalahan jaringan saat menyimpan telaah.';
                    });
                }
            };
        }
    </script>
</x-layout>
