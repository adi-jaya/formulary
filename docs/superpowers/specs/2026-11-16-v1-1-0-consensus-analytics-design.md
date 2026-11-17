# Design Document: FormulariumRS v1.1.0 — Analitik Konsensus Usulan Obat Sidang KFT

**Tanggal:** 2026-11-16  
**Target Rilis:** `v1.1.0`  
**Status:** Disetujui (Builder Review Passed: Ready for Tier 2 Implementation Plan)  
**Dokumen Terkait:** [ROADMAP.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/ROADMAP.md), [AGENTS.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/AGENTS.md)

---

## 1. Intent & The Why

Dalam sidang penetapan Formularium Rumah Sakit oleh Komite Farmasi dan Terapi (KFT) di RS Aisyiyah Siti Fatimah Tulangan, salah satu parameter klinis terpenting untuk meloloskan suatu obat baru adalah **tingkat konsensus pengusulan**. Obat yang diusulkan oleh beberapa Dokter Penanggung Jawab Pelayanan (DPJP) dari berbagai SMF/Poliklinik secara independen memiliki prioritas tertinggi untuk masuk ke dalam formularium rumah sakit.

Saat ini pada halaman rekapitulasi (`/recap`), data disajikan per-baris usulan obat individual. Akibatnya:
1. Anggota KFT harus melakukan pencarian berulang kali atau memilah secara manual di Excel untuk mengetahui obat mana saja yang didukung oleh lebih dari satu dokter spesialis.
2. Tidak ada visualisasi komparatif yang memperlihatkan spektrum dokter pengusul beserta masing-masing alasan klinisnya dalam satu wadah evaluasi terpadu.

Rilis `v1.1.0` memperkenalkan **Tab Analitik Konsensus Usulan Obat**, yaitu modul analitik berbasis web yang secara cerdas mengelompokkan usulan obat, merangking obat berdasarkan dukungan dokter terbanyak, dan menyajikan perbandingan justifikasi klinis antar-DPJP dalam tampilan kartu accordion interaktif.

---

## 2. Goals, Non-Goals, and Anti-Goals

### Goals
- **Segmented Tab Switch pada Halaman Rekapitulasi:**
  - Navigasi tab interaktif di `/recap` dengan parameter URL `tab=items` (tabel rincian item bawaan) dan `tab=consensus` (matriks konsensus).
  - Preservasi query filter utama (tahun periode dan kata kunci pencarian) saat berpindah antar-tab.
- **Two-Stage Hybrid Query Object (`ProposalConsensusQuery`):**
  - Mengelompokkan usulan berdasarkan nama obat (`GROUP BY proposal_items.medicine_name`).
  - Menghitung jumlah dokter spesialis unik yang mengusulkan (`COUNT(DISTINCT proposals.doctor_name) AS distinct_doctors_count`).
  - Menghitung total permohonan usulan (`COUNT(proposal_items.id) AS total_proposals_count`).
  - Mengurutkan secara deterministik: obat dengan dukungan dokter terbanyak di posisi teratas (`distinct_doctors_count DESC, total_proposals_count DESC, medicine_name ASC`).
  - Mendukung paginasi database 15 obat per halaman.
  - Melakukan *eager hydration* rincian usulan (nama dokter, tanggal, pabrikan, dan justifikasi klinis) hanya untuk 15 obat yang berada di halaman aktif.
- **Filter Cepat Konsensus (*Quick Filter Chips*):**
  - Opsi filter instan: *"Semua Obat"* vs *"Hanya Usulan Konsensus (≥ 2 Dokter)"* (`min_doctors=2`).
- **Kartu Accordion Interaktif (Alpine.js + Tailwind CSS v4):**
  - Header kartu: Nama obat tegas, badge jumlah dokter (hijau zamrud tebal untuk ≥ 2 dokter, netral untuk 1 dokter), badge total frekuensi, dan pil badge pabrikan yang diusulkan.
  - Bagian dalam accordion (collapsible): Daftar kartu DPJP pengusul yang menampilkan nama dokter, tanggal usulan, usulan principle pabrik, dan teks alasan klinis lengkap.
- **Metrik Ringkasan Konsensus:**
  - Indikator KPI di atas tab: Total Obat Unik Diusulkan, Total Obat Konsensus (≥ 2 Dokter), dan Obat Paling Banyak Didukung.

### Non-Goals
- Tidak menyertakan modul cetak fisik A4 (fitur cetak A4 PKPO telah diputuskan untuk dihapus sesuai arahan pengguna).
- Tidak memutasi data atau mengubah skema basis data SQLite (100% operasi pembacaan analitik).
- Tidak menggunakan dependensi JavaScript berat (tetap mengandalkan Alpine.js bawaan Vite).

### Anti-Goals
- **Dilarang menggunakan `GROUP_CONCAT(DISTINCT ...)` bawaan SQLite** untuk menggabungkan nama dokter dan teks alasan klinis karena batasan 1-argumen SQLite akan memecah gelar dokter berkoma (`dr. X, Sp.PD`) secara keliru.
- **Dilarang me-load seluruh baris usulan ke memori PHP** (`Collection::all()`) yang dapat menyebabkan lonjakan RAM saat data berjumlah ribuan.

---

## 3. Constraints

- **Prinsip AGENTS.md:**
  - Wajib `declare(strict_types=1);` di setiap berkas PHP baru.
  - Mengikuti pola Controller ➔ Query Object ➔ View tanpa Generic Repository Pattern.
  - Kompatibel penuh dengan SQLite 3 WAL dan MariaDB.
  - Suite pengujian Pest PHP (`./vendor/bin/pest`) wajib 100% PASS.
  - Formatting Laravel Pint (`./vendor/bin/pint --test`) wajib bersih.

---

## 4. Approaches

### Opsi yang Dipertimbangkan:
1. **Opsi 1: Two-Stage Hybrid Query Object (Dipilih)**
   - **Tahap 1:** Query SQL `GROUP BY` untuk menghitung agregat kuantitatif (`distinct_doctors_count`, `total_proposals_count`) dan memaginasi nama obat (15 per halaman).
   - **Tahap 2:** Query kedua mengambil rincian baris usulan dokter hanya untuk 15 nama obat di halaman aktif (`whereIn('proposal_items.medicine_name', $activeMedicines)`).
   - *Alasan Pemilihan:* Memberikan akurasi data 100%, bebas batasan delimiter string SQLite, sangat hemat memori (< 5 MB), dan query berjalan secepat kilat (< 30ms).

2. **Opsi 2: Raw SQLite `GROUP_CONCAT`**
   - Menggabungkan data anak menjadi string delimitasi koma.
   - *Alasan Ditolak:* Gelar dokter berkoma (`dr. Ahmad, Sp.A`) dan teks justifikasi klinis multi-line rusak saat di-split kembali di PHP.

3. **Opsi 3: Full In-Memory Collection Grouping**
   - Menarik semua data ke PHP lalu melakukan `Collection::groupBy`.
   - *Alasan Ditolak:* Tidak scalable dan merusak mekanisme paginasi native database.

---

## 5. Detailed Architecture & Design Decisions

### 5.1 Query Object Konsensus (`app/Queries/ProposalConsensusQuery.php`)

Struktur kelas query analitik:
```php
namespace App\Queries;

use App\Models\ProposalItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProposalConsensusQuery
{
    /**
     * Membangun query agregasi konsensus dan menghidrasi rincian baris untuk halaman aktif.
     *
     * @param array<string, mixed> $filters
     */
    public static function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        // Tahap 1: Agregasi Tingkat Obat
        $query = ProposalItem::query()
            ->join('proposals', 'proposals.id', '=', 'proposal_items.proposal_id')
            ->select([
                'proposal_items.medicine_name',
                DB::raw('COUNT(DISTINCT proposals.doctor_name) as distinct_doctors_count'),
                DB::raw('COUNT(proposal_items.id) as total_proposals_count'),
            ])
            ->groupBy('proposal_items.medicine_name');

        if (! empty($filters['period_year'])) {
            $query->where('proposals.period_year', (int) $filters['period_year']);
        }

        if (! empty($filters['search'])) {
            $term = addcslashes(trim((string) $filters['search']), '%_');
            $query->where('proposal_items.medicine_name', 'like', "%{$term}%");
        }

        if (! empty($filters['min_doctors']) && (int) $filters['min_doctors'] > 1) {
            $query->having('distinct_doctors_count', '>=', (int) $filters['min_doctors']);
        }

        $paginator = $query
            ->orderByDesc('distinct_doctors_count')
            ->orderByDesc('total_proposals_count')
            ->orderBy('proposal_items.medicine_name')
            ->paginate($perPage)
            ->withQueryString();

        // Tahap 2: Hydrate Rincian Dokter untuk Obat pada Halaman Aktif
        $activeMedicines = $paginator->getCollection()->pluck('medicine_name')->all();

        if (! empty($activeMedicines)) {
            $details = ProposalItem::query()
                ->join('proposals', 'proposals.id', '=', 'proposal_items.proposal_id')
                ->whereIn('proposal_items.medicine_name', $activeMedicines)
                ->when(! empty($filters['period_year']), function (Builder $q) use ($filters): void {
                    $q->where('proposals.period_year', (int) $filters['period_year']);
                })
                ->select([
                    'proposal_items.id',
                    'proposal_items.medicine_name',
                    'proposal_items.principal_name',
                    'proposal_items.clinical_justification',
                    'proposals.doctor_name',
                    'proposals.proposal_date',
                    'proposals.period_year',
                    'proposals.notes',
                ])
                ->orderByDesc('proposals.proposal_date')
                ->get()
                ->groupBy('medicine_name');

            $paginator->getCollection()->transform(function ($item) use ($details) {
                $medicineDetails = $details->get($item->medicine_name, collect());
                
                $item->proposals = $medicineDetails;
                $item->principals = $medicineDetails->pluck('principal_name')->filter()->unique()->values()->all();
                
                return $item;
            });
        }

        return $paginator;
    }

    /**
     * Menghitung metrik ringkasan konsensus untuk periode tertentu.
     *
     * @return array{total_medicines: int, consensus_medicines: int, top_supported_count: int}
     */
    public static function summaryMetrics(?int $periodYear = null): array
    {
        $query = ProposalItem::query()
            ->join('proposals', 'proposals.id', '=', 'proposal_items.proposal_id')
            ->select([
                'proposal_items.medicine_name',
                DB::raw('COUNT(DISTINCT proposals.doctor_name) as distinct_doctors_count'),
            ])
            ->groupBy('proposal_items.medicine_name');

        if ($periodYear !== null) {
            $query->where('proposals.period_year', $periodYear);
        }

        $results = $query->get();

        $totalMedicines = $results->count();
        $consensusMedicines = $results->where('distinct_doctors_count', '>=', 2)->count();
        $topSupportedCount = (int) $results->max('distinct_doctors_count');

        return [
            'total_medicines' => $totalMedicines,
            'consensus_medicines' => $consensusMedicines,
            'top_supported_count' => $topSupportedCount,
        ];
    }
}
```

### 5.2 Controller Integration (`app/Http/Controllers/ProposalRecapController.php`)

Pembaruan pada `ProposalRecapController::index()`:
1. Membaca tab aktif: `$activeTab = $request->query('tab') === 'consensus' ? 'consensus' : 'items'`.
2. Jika `$activeTab === 'consensus'`:
   - Memanggil `ProposalConsensusQuery::paginate($filters, 15)`.
   - Mengambil metrik: `ProposalConsensusQuery::summaryMetrics($filters['period_year'] ? (int) $filters['period_year'] : null)`.
   - Mengirim variabel `$consensusItems` dan `$consensusMetrics` ke view.
3. Menjaga query string tetap utuh saat navigasi antar-tab.

### 5.3 UI & Blade View (`resources/views/proposals/index.blade.php`)

1. **Segmented Tab Header:**
   Ditempatkan di bagian atas halaman rekapitulasi, tepat di bawah judul:
   ```blade
   <div class="flex items-center gap-2 border-b border-slate-200 mb-6">
       <a href="{{ route('proposals.index', array_merge(request()->query(), ['tab' => 'items', 'page' => null])) }}"
          class="pb-3 px-4 text-xs font-semibold border-b-2 transition-colors {{ $activeTab === 'items' ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
           Daftar Usulan Item (Tabel)
       </a>
       <a href="{{ route('proposals.index', array_merge(request()->query(), ['tab' => 'consensus', 'page' => null])) }}"
          class="pb-3 px-4 text-xs font-semibold border-b-2 transition-colors flex items-center gap-1.5 {{ $activeTab === 'consensus' ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
           <span>Analitik Konsensus Obat</span>
           @if(($consensusMetrics['consensus_medicines'] ?? 0) > 0)
               <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                   {{ $consensusMetrics['consensus_medicines'] }}
               </span>
           @endif
       </a>
   </div>
   ```

2. **Partial Blade Konsensus (`resources/views/proposals/partials/consensus-tab.blade.php`):**
   - **KPI Card Grid:**
     - Total Obat Unik Diusulkan.
     - Obat Konsensus Multidokter (≥ 2 DPJP).
     - Dukungan Tertinggi (Maks. N DPJP untuk 1 Obat).
   - **Quick Filter Bar:**
     - Pilihan Periode Tahun.
     - Input pencarian nama obat.
     - Chip: *"Semua Obat"* vs *"Hanya Konsensus (≥ 2 Dokter)"*.
   - **Accordion Card List:**
     - Dibangun dengan state Alpine.js `x-data="{ open: false }"`.
     - Baris utama: Nama obat tebal, badge `distinct_doctors_count` dengan aksen hijau zamrud mencolok (`bg-emerald-50 text-emerald-700 border-emerald-200`), total usulan, dan pill pabrikan.
     - Panel rincian: Daftar dokter pengusul dalam kartu grid mini, menampilkan tanggal pengajuan, pabrikan pilihan dokter, serta kutipan alasan klinis DPJP secara jelas.

---

## 6. What's Left to the Builder

- Implementasi kelas `ProposalConsensusQuery`.
- Integrasi kondisional tab pada `ProposalRecapController`.
- Pembuatan view partial `resources/views/proposals/partials/consensus-tab.blade.php`.
- Penulisan test suite komprehensif di Pest PHP:
  - `ProposalConsensusQueryTest`: Menguji agregasi `distinct_doctors_count`, urutan sorting obat terbanyak, filter `min_doctors`, dan eager hydration rincian usulan.
  - `ProposalConsensusTabTest`: Menguji render tab `tab=consensus`, switch tab, serta pencarian nama obat di tab konsensus.
- Memastikan `./vendor/bin/pest` 100% PASS.
- Memastikan `./vendor/bin/pint --test` bersih dari pelanggaran gaya kode.
- Menjalankan `npm run build` untuk memverifikasi kompatibilitas Tailwind v4.
