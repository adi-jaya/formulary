# FormulariumRS v1.1.0 Implementation Plan: Analitik Konsensus Usulan Obat Sidang KFT

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun modul Analitik Konsensus Usulan Obat pada halaman rekapitulasi (`/proposals?tab=consensus`) yang mengelompokkan usulan per-obat, merangking obat berdasarkan jumlah dukungan dokter unik (DPJP) terbanyak, menyediakan metrik KPI konsensus, serta menampilkan perbandingan justifikasi klinis multidokter dalam kartu accordion interaktif.

**Architecture:** Menggunakan arsitektur query dua tahap (*two-stage hybrid query*) pada `ProposalConsensusQuery`: Tahap 1 mengeksekusi agregasi SQL `GROUP BY` dan paginasi 15 obat per halaman berdasarkan ranking dukungan terbanyak (`distinct_doctors_count DESC, total_proposals_count DESC`); Tahap 2 menghidrasi rincian baris usulan dokter hanya untuk 15 obat di halaman aktif. Antarmuka disajikan melalui segmented tab di `proposals/index.blade.php` dan partial `proposals/partials/consensus-tab.blade.php` ditenagai kartu accordion interaktif Alpine.js.

**Tech Stack:** PHP `^8.3`, Laravel `^12.0`, SQLite 3 (`WAL`), Blade Components, Tailwind CSS `^4.0`, Alpine.js `^3.17`, Pest PHP `^3.7`, Laravel Pint `^1.24`.

**Spec:** [docs/superpowers/specs/2026-11-16-v1-1-0-consensus-analytics-design.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/docs/superpowers/specs/2026-11-16-v1-1-0-consensus-analytics-design.md)

## Global Constraints

- Wajib mematuhi pedoman teknis [AGENTS.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/AGENTS.md): `declare(strict_types=1);` di setiap berkas PHP baru, tanpa generic repository pattern, dan tanpa library eksternal.
- Dilarang keras menggunakan SQLite `GROUP_CONCAT` untuk memecah data teks bergelar dokter (`dr. X, Sp.PD`) di SQL.
- Dilarang memuat seluruh koleksi ke memori PHP (`Collection::all()`); paginasi wajib ditangani di level SQL (15 obat per halaman).
- Seluruh pengujian Pest PHP (`./vendor/bin/pest`) wajib 100% PASS (minimal 49 tests baseline).
- Standarisasi formatting Laravel Pint (`./vendor/bin/pint --test`) wajib bersih tanpa error.

## Review Focus

1. **Agregasi Dokter Unik & Ranking Konsensus:** Obat yang diusulkan oleh beberapa dokter berbeda wajib memiliki `distinct_doctors_count` yang akurat (tidak menghitung dokter yang sama berulang kali) dan diurutkan di peringkat teratas. *(Diuji di Task 1).*
2. **Isolasi Stage 2 Eager Hydration:** Tahap kedua hidrasi rincian dokter hanya boleh memuat baris untuk obat-obat yang tampil di halaman aktif dan wajib mematuhi filter `period_year` aktif. *(Diuji di Task 1).*
3. **Resiliensi Data Kosong pada Metrik Konsensus:** Ketika tidak ada usulan obat atau hasil pencarian nihil, `summaryMetrics()` wajib mengembalikan angka 0 secara aman tanpa error null pointer atau division by zero. *(Diuji di Task 1).*
4. **Preservasi Filter Saat Berpindah Tab:** Mengklik switch tab antara `items` dan `consensus` wajib mempertahankan parameter pencarian `period_year` dan `search` sambil me-reset parameter `page` ke null. *(Diuji di Task 2).*
5. **Aksesibilitas dan Interaktivitas Accordion Alpine.js:** Accordion perbandingan klinis dokter wajib dapat dibuka/tutup secara independen tanpa memengaruhi kartu obat lain. *(Diuji di Task 3).*

---

### Task 1: Two-Stage Hybrid Query Object `ProposalConsensusQuery`

**Files:**
- Create: `app/Queries/ProposalConsensusQuery.php`
- Create: `tests/Feature/ProposalConsensusQueryTest.php`

**Interfaces:**
- Consumes: Model `ProposalItem`, `Proposal`, tabel `proposals`, `proposal_items`
- Produces:
  - `ProposalConsensusQuery::paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator`
  - `ProposalConsensusQuery::summaryMetrics(?int $periodYear = null): array` (`total_medicines`, `consensus_medicines`, `top_supported_count`)

- [ ] **Step 1: Write the failing test**

Buat `tests/Feature/ProposalConsensusQueryTest.php`:
```php
<?php

declare(strict_types=1);

use App\Models\Proposal;
use App\Queries\ProposalConsensusQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('aggregates medicines by distinct doctors and ranks multi-doctor consensus on top', function (): void {
    // Proposal 1: dr. Budi proposes Paracetamol and Diarvit
    $p1 = Proposal::create(['doctor_name' => 'dr. Budi Sp.An', 'proposal_date' => '2026-11-01', 'period_year' => 2026]);
    $p1->items()->create(['medicine_name' => 'Paracetamol 500 Mg', 'principal_name' => 'Sanbe', 'clinical_justification' => 'Analgetik aman']);
    $p1->items()->create(['medicine_name' => 'Diarvit 100 Mg Tab', 'principal_name' => 'Novell', 'clinical_justification' => 'Diare akut']);

    // Proposal 2: dr. Siti also proposes Diarvit
    $p2 = Proposal::create(['doctor_name' => 'dr. Siti Sp.PD', 'proposal_date' => '2026-11-02', 'period_year' => 2026]);
    $p2->items()->create(['medicine_name' => 'Diarvit 100 Mg Tab', 'principal_name' => 'Novell', 'clinical_justification' => 'First-line diare']);

    // Proposal 3: dr. Siti proposes Diarvit again (same doctor duplicate proposal)
    $p3 = Proposal::create(['doctor_name' => 'dr. Siti Sp.PD', 'proposal_date' => '2026-11-03', 'period_year' => 2026]);
    $p3->items()->create(['medicine_name' => 'Diarvit 100 Mg Tab', 'principal_name' => 'Novell', 'clinical_justification' => 'Tambahan bangsal']);

    $paginator = ProposalConsensusQuery::paginate(['period_year' => 2026]);
    $items = $paginator->items();

    expect($items)->toHaveCount(2);

    // Diarvit has 2 distinct doctors, 3 total proposals -> Ranked #1
    $top = $items[0];
    expect($top->medicine_name)->toBe('Diarvit 100 Mg Tab')
        ->and((int) $top->distinct_doctors_count)->toBe(2)
        ->and((int) $top->total_proposals_count)->toBe(3)
        ->and($top->proposals)->toHaveCount(3)
        ->and($top->principals)->toBe(['Novell']);

    // Paracetamol has 1 doctor, 1 proposal -> Ranked #2
    $second = $items[1];
    expect($second->medicine_name)->toBe('Paracetamol 500 Mg')
        ->and((int) $second->distinct_doctors_count)->toBe(1)
        ->and((int) $second->total_proposals_count)->toBe(1);

    // Summary metrics verification
    $metrics = ProposalConsensusQuery::summaryMetrics(2026);
    expect($metrics['total_medicines'])->toBe(2)
        ->and($metrics['consensus_medicines'])->toBe(1)
        ->and($metrics['top_supported_count'])->toBe(2);
});

it('filters consensus by min_doctors and search term', function (): void {
    $p1 = Proposal::create(['doctor_name' => 'dr. A', 'proposal_date' => '2026-11-01', 'period_year' => 2026]);
    $p1->items()->create(['medicine_name' => 'Obat Ramai']);
    $p1->items()->create(['medicine_name' => 'Obat Sepi']);

    $p2 = Proposal::create(['doctor_name' => 'dr. B', 'proposal_date' => '2026-11-02', 'period_year' => 2026]);
    $p2->items()->create(['medicine_name' => 'Obat Ramai']);

    // min_doctors = 2 only returns Obat Ramai
    $res = ProposalConsensusQuery::paginate(['min_doctors' => 2]);
    expect($res->items())->toHaveCount(1)
        ->and($res->items()[0]->medicine_name)->toBe('Obat Ramai');

    // search filter
    $searchRes = ProposalConsensusQuery::paginate(['search' => 'Sepi']);
    expect($searchRes->items())->toHaveCount(1)
        ->and($searchRes->items()[0]->medicine_name)->toBe('Obat Sepi');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/ProposalConsensusQueryTest.php`
Expected: FAIL (`Class "App\Queries\ProposalConsensusQuery" not found`).

- [ ] **Step 3: Implement `app/Queries/ProposalConsensusQuery.php`**

Buat `app/Queries/ProposalConsensusQuery.php` dengan deklarasi `declare(strict_types=1);` dan implementasikan method `paginate()` serta `summaryMetrics()` sesuai spesifikasi teknis.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/ProposalConsensusQueryTest.php`
Expected: PASS (2 tests pass).

- [ ] **Step 5: Commit**

```bash
git add app/Queries/ProposalConsensusQuery.php tests/Feature/ProposalConsensusQueryTest.php
git commit -m "feat(analytics): implement ProposalConsensusQuery two-stage hybrid aggregation"
```

---

### Task 2: Controller Tab Integration & Segmented Switch

**Files:**
- Modify: `app/Http/Controllers/ProposalRecapController.php`
- Modify: `resources/views/proposals/index.blade.php:1-35`
- Create: `tests/Feature/ProposalConsensusTabTest.php`

**Interfaces:**
- Consumes: `ProposalConsensusQuery`, parameter request `tab`
- Produces: Variabel `$activeTab`, `$consensusItems`, `$consensusMetrics` di view `proposals.index`, segmented tab switch header.

- [ ] **Step 1: Write the failing test**

Buat `tests/Feature/ProposalConsensusTabTest.php`:
```php
<?php

declare(strict_types=1);

use App\Models\Proposal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the consensus tab with metrics and switches between items and consensus tabs', function (): void {
    $p1 = Proposal::create(['doctor_name' => 'dr. Budi Sp.An', 'proposal_date' => '2026-11-01', 'period_year' => 2026]);
    $p1->items()->create(['medicine_name' => 'Diarvit 100 Mg Tab', 'principal_name' => 'Novell', 'clinical_justification' => 'Justifikasi A']);

    $p2 = Proposal::create(['doctor_name' => 'dr. Siti Sp.PD', 'proposal_date' => '2026-11-02', 'period_year' => 2026]);
    $p2->items()->create(['medicine_name' => 'Diarvit 100 Mg Tab', 'principal_name' => 'Novell', 'clinical_justification' => 'Justifikasi B']);

    // Default tab is items
    $this->get(route('proposals.index'))
        ->assertOk()
        ->assertSee('Daftar Usulan Item (Tabel)')
        ->assertSee('Analitik Konsensus Obat')
        ->assertSee('tab=consensus', false);

    // Consensus tab renders consensus view data
    $response = $this->get(route('proposals.index', ['tab' => 'consensus']))->assertOk();
    $response->assertSee('Analitik Konsensus Usulan Obat')
        ->assertSee('Diarvit 100 Mg Tab')
        ->assertSee('2 Dokter')
        ->assertSee('dr. Budi Sp.An')
        ->assertSee('dr. Siti Sp.PD')
        ->assertSee('Justifikasi A')
        ->assertSee('Justifikasi B');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/ProposalConsensusTabTest.php`
Expected: FAIL (`tab=consensus` belum menampilkan konten analitik).

- [ ] **Step 3: Update `ProposalRecapController.php` & add tab header in `index.blade.php`**

1. Di `app/Http/Controllers/ProposalRecapController.php`:
   - Tangkap `$activeTab = $request->query('tab') === 'consensus' ? 'consensus' : 'items';`
   - Ambil `$consensusMetrics = ProposalConsensusQuery::summaryMetrics($filters['period_year'] ? (int) $filters['period_year'] : null);`
   - Jika `$activeTab === 'consensus'`: panggil `ProposalConsensusQuery::paginate($filters, 15)` simpan ke `$consensusItems`.
   - Teruskan `$activeTab`, `$consensusMetrics`, dan `$consensusItems` (jika konsensus) ke view.
2. Di `resources/views/proposals/index.blade.php`:
   - Sisipkan Segmented Tab Header di bawah judul rekapitulasi.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/ProposalConsensusTabTest.php`
Expected: PASS (atau lanjut ke Task 3 untuk view partial lengkap).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ProposalRecapController.php resources/views/proposals/index.blade.php tests/Feature/ProposalConsensusTabTest.php
git commit -m "feat(ui): integrate segmented tab switch and consensus data in ProposalRecapController"
```

---

### Task 3: Consensus Partial View & Interactive Accordion Cards

**Files:**
- Create: `resources/views/proposals/partials/consensus-tab.blade.php`
- Modify: `resources/views/proposals/index.blade.php`
- Test: `tests/Feature/ProposalConsensusTabTest.php`

**Interfaces:**
- Consumes: `$consensusItems`, `$consensusMetrics`, `$filters`
- Produces: Kartu KPI konsensus, bilah filter cepat (`min_doctors=2`), daftar kartu obat dengan accordion rincian usulan dokter (Alpine.js).

- [ ] **Step 1: Write failing assertions for consensus cards, KPI badges, and quick filter chips**

Tambahkan pengujian di `tests/Feature/ProposalConsensusTabTest.php`:
```php
it('supports quick filtering for consensus medicines and renders KPI summary cards', function (): void {
    $p1 = Proposal::create(['doctor_name' => 'dr. Budi', 'proposal_date' => '2026-11-01', 'period_year' => 2026]);
    $p1->items()->create(['medicine_name' => 'Obat Multi DPJP']);
    $p1->items()->create(['medicine_name' => 'Obat Single DPJP']);

    $p2 = Proposal::create(['doctor_name' => 'dr. Ahmad', 'proposal_date' => '2026-11-02', 'period_year' => 2026]);
    $p2->items()->create(['medicine_name' => 'Obat Multi DPJP']);

    $response = $this->get(route('proposals.index', ['tab' => 'consensus', 'min_doctors' => 2]))->assertOk();
    $response->assertSee('Obat Multi DPJP')
        ->assertDontSee('Obat Single DPJP')
        ->assertSee('Hanya Usulan Konsensus (≥ 2 Dokter)');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/ProposalConsensusTabTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement `resources/views/proposals/partials/consensus-tab.blade.php`**

Buat partial `resources/views/proposals/partials/consensus-tab.blade.php`:
- Grid 3 kartu KPI: Total Obat Diusulkan, Obat Konsensus (≥ 2 Dokter), Dukungan Tertinggi.
- Filter Bar Konsensus: Pencarian nama obat, filter tahun, dan chip toggle filter konsensus (`min_doctors`).
- List Kartu Obat:
  - Header: Nama obat, badge jumlah dokter pengusul (`x dokter`), total usulan, daftar pabrikan.
  - Button expand accordion (`x-data="{ open: false }"`).
  - Body accordion (`x-show="open"`): Grid permohonan dokter pengusul menampilkan nama dokter, poli, tanggal usulan, dan alasan klinis.
- Tampilkan partial secara kondisional di `index.blade.php` saat `$activeTab === 'consensus'`.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/ProposalConsensusTabTest.php`
Expected: PASS (semua test di `ProposalConsensusTabTest` lolos).

- [ ] **Step 5: Commit**

```bash
git add resources/views/proposals/partials/consensus-tab.blade.php resources/views/proposals/index.blade.php tests/Feature/ProposalConsensusTabTest.php
git commit -m "feat(ui): create consensus-tab partial with KPI cards, quick filters, and interactive accordions"
```

---

### Task 4: End-to-End Quality Gate & Release Verification for `v1.1.0`

**Files:**
- Verify: Full codebase

**Interfaces:**
- Consumes: Seluruh fitur Tasks 1–3
- Produces: 100% lulus seluruh suite Pest PHP (minimal 53 tests, 300+ assertions), 0 pelanggaran Pint, build produksi Vite sukses.

- [ ] **Step 1: Run complete Pest PHP test suite**

Run: `./vendor/bin/pest`
Expected: PASS.

- [ ] **Step 2: Run Laravel Pint code styling check**

Run: `./vendor/bin/pint --test`
Expected: `{"tool":"pint","result":"passed"}` (0 violations).

- [ ] **Step 3: Run Vite asset compilation for production**

Run: `npm run build`
Expected: Output `✓ built in ...` dengan berkas aset manifest terbit di `public/build`.

- [ ] **Step 4: Verify working tree cleanliness**

Run: `git status`
Expected: `nothing to commit, working tree clean`.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-11-17-v1-1-0-consensus-analytics-plan.md`.
Siap dieksekusi secara otonom melalui **Subagent-Driven Development (SDD)**.
