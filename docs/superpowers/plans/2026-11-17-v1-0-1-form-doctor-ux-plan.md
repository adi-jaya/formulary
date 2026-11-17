# FormulariumRS v1.0.1 Implementation Plan: UX/UI Micro-Polish pada Formulir Dokter

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menghadirkan perlindungan data otomatis (*auto-save* ke `localStorage` dengan pemulihan non-blocking) dan peningkatan ergonomi seluler (*tab guard*, shortcut keyboard submit, safe-area mobile floating action bar, autofocus baris baru, serta counter karakter dinamis) pada formulir usulan obat dokter tanpa mengubah skema basis data maupun sifat *zero-friction* sistem.

**Architecture:** Arsitektur formulir Blade + Alpine.js diperkaya dengan pemisahan status server-client yang tegas melalui tag data carrier JSON (`#proposal-form-data`). Sisi klien Alpine.js mengelola siklus hidup penyimpanan lokal `localStorage` ber-kunci `formulary_draft_v1` dengan aturan prioritas berjenjang (flash sukses membersihkan draf; error validasi memprioritaskan `old()`; sesi bersih memulihkan draf), memantau perubahan input secara debounced (500ms), menangani sinyal navigasi peramban (`beforeunload`), mendeteksi keyboard virtual via `window.visualViewport`, serta menyediakan bar aksi mengambang ramah layar sentuh seluler.

**Tech Stack:** PHP `^8.3`, Laravel `^12.0`, Blade Components, Alpine.js `^3.17`, Tailwind CSS `^4.0`, Vite `^7.0`, Pest PHP `^3.7`, Laravel Pint `^1.24`.

**Spec:** [docs/superpowers/specs/2026-11-15-v1-0-1-form-doctor-ux-design.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/docs/superpowers/specs/2026-11-15-v1-0-1-form-doctor-ux-design.md)

## Global Constraints

- Wajib mematuhi pedoman teknis [AGENTS.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/AGENTS.md): `declare(strict_types=1);` di berkas PHP baru/dimodifikasi, tanpa generic repository pattern, dan tanpa penambahan library CDN eksternal.
- Tidak mengubah skema tabel database `proposals` maupun `proposal_items` (peningkatan sepenuhnya berada di lapisan presentasi Blade & Alpine.js).
- Tidak menambahkan sistem login/autentikasi atau dialog pemblokir (mempertahankan sifat *zero-friction* DPJP).
- Seluruh pengujian Pest PHP (`./vendor/bin/pest`) wajib 100% PASS (baseline 28 tests, 159 assertions tidak boleh ada yang patah).
- Standarisasi formatting Laravel Pint (`./vendor/bin/pint --test`) wajib bersih tanpa error.
- Kompilasi aset Vite (`npm run build`) wajib berhasil tanpa warning atau pemutusan bundle.

## Review Focus

1. **Konflik antara Draf `localStorage` vs Data Error Validasi Server (`old()`):** Ketika pengguna mengirimkan data tidak valid (misal nama dokter kosong) lalu diredirect kembali, form wajib menampilkan data `old()` dari server dan tidak boleh tertimpa oleh draf lama di `localStorage`. *(Diuji di Task 1 & Task 2).*
2. **Pembersihan Draf Pasca-Submit Sukses (Pencegahan *Zombie Draft*):** Ketika dokter berhasil mengirimkan usulan obat (muncul flash summary sukses), draf di `localStorage` wajib dibersihkan secara tuntas agar tidak bangkit kembali sebagai draf belum terkirim saat halaman direfresh. *(Diuji di Task 1 & Task 2).*
3. **Pembersihan Otomatis Saat Form Dikosongkan Manual (Pencegahan *Ghost Saves*):** Jika dokter menghapus seluruh baris obat dan mengosongkan nama dokter, `saveDraft()` tidak boleh menyimpan entri JSON kosong yang memicu banner pemulihan palsu, melainkan harus menghapus `formulary_draft_v1`. *(Diuji di Task 2).*
4. **Interferensi Keyboard Virtual Ponsel (`visualViewport`):** Saat keyboard virtual ponsel aktif, bar aksi mengambang (*floating action bar*) wajib otomatis disembunyikan (`isKeyboardVisible = true`) agar tidak menutupi input teks yang sedang diketik atau mengacaukan scrolling Safari iOS. *(Diuji di Task 3 & Task 4).*
5. **Eksekusi Submit via Shortcut Keyboard (`Ctrl+Enter` / `Cmd+Enter`):** Menekan `Ctrl+Enter` atau `Cmd+Enter` di area form manapun memanggil `submitForm()`, memicu validasi native HTML5, mengaktifkan status loading `submitting = true`, dan mencegah submit berulang. *(Diuji di Task 3 & Task 4).*

---

### Task 1: Server Hydration & Session State Flags in Blade

**Files:**
- Modify: `resources/views/proposals/create.blade.php:3-9`
- Test: `tests/Feature/ProposalSubmissionTest.php`

**Interfaces:**
- Consumes: `session()->hasOldInput()`, `session()->has('submitted_summary')`, `old('items')`, `old('doctor_name')`, `old('notes')`
- Produces: Elemen `<script id="proposal-form-data" type="application/json">` yang memuat flag boolean `hasOldInput` dan `hasFlashSuccess` secara aman untuk dikonsumsi Alpine.js.

- [ ] **Step 1: Write the failing test**

Tambahkan pengujian baru di `tests/Feature/ProposalSubmissionTest.php`:
```php
it('renders hydration json carrier with server flags for fresh load, validation errors, and flash success', function (): void {
    // 1. Fresh load
    $response = $this->get(route('proposals.create'))->assertOk();
    $content = $response->getContent();
    expect($content)->toContain('"hasOldInput":false')
        ->and($content)->toContain('"hasFlashSuccess":false');

    // 2. Validation error redirect
    $invalidResponse = $this->from(route('proposals.create'))->post(route('proposals.store'), [
        'doctor_name' => '',
        'proposal_date' => '2026-11-17',
        'items' => [['medicine_name' => 'Obat A', 'principal_name' => 'Sanbe']],
    ]);
    $invalidResponse->assertRedirect(route('proposals.create'));

    $followedInvalid = $this->followRedirects($invalidResponse);
    expect($followedInvalid->getContent())->toContain('"hasOldInput":true')
        ->and($followedInvalid->getContent())->toContain('"hasFlashSuccess":false');

    // 3. Successful submission redirect
    $validResponse = $this->from(route('proposals.create'))->post(route('proposals.store'), [
        'doctor_name' => 'dr. Nadhir Sp.U',
        'proposal_date' => '2026-11-17',
        'items' => [['medicine_name' => 'Obat B', 'principal_name' => 'Kalbe']],
    ]);
    $validResponse->assertRedirect(route('proposals.create'));

    $followedValid = $this->followRedirects($validResponse);
    expect($followedValid->getContent())->toContain('"hasFlashSuccess":true');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/ProposalSubmissionTest.php`
Expected: FAIL dengan pesan kegagalan assertion bahwa string `"hasOldInput":false` tidak ditemukan dalam konten view.

- [ ] **Step 3: Implement server flags in `resources/views/proposals/create.blade.php`**

Mutakhirkan script `#proposal-form-data` pada baris 3–9:
```blade
    <!-- Safe JSON Data Carrier for Alpine Component (prevents HTML attribute quote truncation) -->
    <script id="proposal-form-data" type="application/json">
        {!! json_encode([
            'initialItems' => old('items', [['medicine_name' => '', 'principal_name' => '', 'clinical_justification' => '']]),
            'initialDoctor' => old('doctor_name', ''),
            'initialNotes' => old('notes', ''),
            'hasOldInput' => session()->hasOldInput(),
            'hasFlashSuccess' => session()->has('submitted_summary'),
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}
    </script>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/ProposalSubmissionTest.php`
Expected: PASS (seluruh 6 test di `ProposalSubmissionTest` lolos).

- [ ] **Step 5: Commit**

```bash
git add resources/views/proposals/create.blade.php tests/Feature/ProposalSubmissionTest.php
git commit -m "feat(ux): add server hydration flags to proposal form json carrier"
```

---

### Task 2: Alpine.js Client-Side Draft Storage Engine

**Files:**
- Modify: `resources/js/app.js:4-144`

**Interfaces:**
- Consumes: JSON contract `#proposal-form-data` (`hasOldInput`, `hasFlashSuccess`, `initialDoctor`, `initialItems`, `initialNotes`)
- Produces: Properti dan metode `proposalForm` Alpine:
  - `storageKey: 'formulary_draft_v1'`
  - `isDraftRestored: false`
  - `autoSaveTimer: null`
  - `init(): void` (Logika prioritas 3-tier: Flash sukses -> hapus draf; Old input -> lewati draf; Fresh -> pulihkan draf)
  - `isFormEmpty(): boolean`
  - `saveDraft(): void`
  - `scheduleAutoSave(): void`
  - `clearDraft(): void`

- [ ] **Step 1: Write verification script to test draft engine logic**

Jalankan pengecekan bundle sebelum modifikasi untuk memastikan baseline bersih:
Run: `npm run build`
Expected: PASS dengan keluaran aset di `public/build`.

- [ ] **Step 2: Run verification to record baseline**

Run: `node -c resources/js/app.js`
Expected: PASS (syntax valid).

- [ ] **Step 3: Implement draft persistence engine in `resources/js/app.js`**

Perbarui definisi `Alpine.data('proposalForm', ...)` di `resources/js/app.js`:
```javascript
Alpine.data('proposalForm', () => {
    let initialData = {
        initialItems: [],
        initialDoctor: '',
        initialNotes: '',
        hasOldInput: false,
        hasFlashSuccess: false,
    };
    const dataElement = document.getElementById('proposal-form-data');
    if (dataElement && dataElement.textContent) {
        try {
            initialData = Object.assign(initialData, JSON.parse(dataElement.textContent));
        } catch (e) {
            console.error('Failed to parse proposal initial data', e);
        }
    }

    const initialItems = initialData.initialItems && initialData.initialItems.length
        ? initialData.initialItems.map((it) => ({
            medicine_name: it.medicine_name || '',
            principal_name: it.principal_name || '',
            clinical_justification: it.clinical_justification || '',
            autoFilled: false,
        }))
        : [{ medicine_name: '', principal_name: '', clinical_justification: '', autoFilled: false }];

    return {
        doctorInput: initialData.initialDoctor || '',
        doctorSuggestions: [],
        doctorIsOpen: false,
        doctorLoading: false,
        doctorHighlightedIndex: -1,
        doctorDebounceTimer: null,
        doctorAbortController: null,
        items: initialItems,
        notes: initialData.initialNotes || '',
        submitting: false,

        // Draft Management State
        storageKey: 'formulary_draft_v1',
        isDraftRestored: false,
        autoSaveTimer: null,

        init() {
            // Priority 1: Submission success -> unconditionally purge draft
            if (initialData.hasFlashSuccess) {
                try {
                    localStorage.removeItem(this.storageKey);
                } catch (e) {
                    console.warn('LocalStorage access error on purge', e);
                }
                return;
            }

            // Priority 2: Validation errors present -> server old() input takes strict precedence
            if (initialData.hasOldInput) {
                return;
            }

            // Priority 3: Clean fresh form -> restore draft from localStorage if available
            try {
                const rawDraft = localStorage.getItem(this.storageKey);
                if (rawDraft) {
                    const parsed = JSON.parse(rawDraft);
                    const hasDoctor = typeof parsed.doctorInput === 'string' && parsed.doctorInput.trim() !== '';
                    const hasNotes = typeof parsed.notes === 'string' && parsed.notes.trim() !== '';
                    const hasItems = Array.isArray(parsed.items) && parsed.items.some(
                        (it) => typeof it.medicine_name === 'string' && it.medicine_name.trim() !== ''
                    );

                    if (hasDoctor || hasNotes || hasItems) {
                        this.doctorInput = parsed.doctorInput || '';
                        this.notes = parsed.notes || '';
                        if (Array.isArray(parsed.items) && parsed.items.length > 0) {
                            this.items = parsed.items.map((it) => ({
                                medicine_name: it.medicine_name || '',
                                principal_name: it.principal_name || '',
                                clinical_justification: it.clinical_justification || '',
                                autoFilled: Boolean(it.autoFilled),
                            }));
                        }
                        this.isDraftRestored = true;
                    }
                }
            } catch (e) {
                console.warn('Failed to restore draft from localStorage', e);
            }

            // Setup reactive deep watchers for auto-save
            this.$watch('doctorInput', () => this.scheduleAutoSave());
            this.$watch('notes', () => this.scheduleAutoSave());
            this.$watch('items', () => this.scheduleAutoSave(), { deep: true });
        },

        isFormEmpty() {
            const noDoctor = !this.doctorInput || this.doctorInput.trim() === '';
            const noNotes = !this.notes || this.notes.trim() === '';
            const noItems = !this.items || this.items.length === 0 || (
                this.items.length === 1 &&
                (!this.items[0].medicine_name || this.items[0].medicine_name.trim() === '') &&
                (!this.items[0].principal_name || this.items[0].principal_name.trim() === '') &&
                (!this.items[0].clinical_justification || this.items[0].clinical_justification.trim() === '')
            );
            return noDoctor && noNotes && noItems;
        },

        scheduleAutoSave() {
            clearTimeout(this.autoSaveTimer);
            this.autoSaveTimer = setTimeout(() => {
                this.saveDraft();
            }, 500);
        },

        saveDraft() {
            if (this.submitting) return;

            try {
                if (this.isFormEmpty()) {
                    localStorage.removeItem(this.storageKey);
                    return;
                }

                const payload = {
                    doctorInput: this.doctorInput,
                    items: this.items,
                    notes: this.notes,
                    savedAt: Date.now(),
                };
                localStorage.setItem(this.storageKey, JSON.stringify(payload));
            } catch (e) {
                console.warn('Failed to save draft to localStorage', e);
            }
        },

        clearDraft() {
            try {
                localStorage.removeItem(this.storageKey);
            } catch (e) {
                console.warn('Failed to clear draft from localStorage', e);
            }

            this.doctorInput = '';
            this.notes = '';
            this.items = [{ medicine_name: '', principal_name: '', clinical_justification: '', autoFilled: false }];
            this.isDraftRestored = false;
        },
```

- [ ] **Step 4: Run build and syntax verification to verify it passes**

Run: `node -c resources/js/app.js && npm run build`
Expected: Output `✓ built in ...` tanpa error syntax.

- [ ] **Step 5: Commit**

```bash
git add resources/js/app.js
git commit -m "feat(ux): implement localStorage auto-save, priority restore, and draft clearance engine"
```

---

### Task 3: Alpine.js Micro-Interactions (Tab Guard, Autofocus, Keyboard Submit, & Viewport Resizing)

**Files:**
- Modify: `resources/js/app.js`

**Interfaces:**
- Consumes: `proposalForm` instance from Task 2
- Produces:
  - `isKeyboardVisible: boolean`
  - `hasUnsavedChanges(): boolean`
  - `submitForm(): void`
  - Enhanced `addItem()` dengan autofocus `[data-medicine-input]` baris baru via `$nextTick`
  - Lifecycle listeners di `init()`: `beforeunload` window listener dan `visualViewport.resize` listener

- [ ] **Step 1: Write failing verification or dry-run check**

Verifikasi bahwa metode `hasUnsavedChanges`, `submitForm`, dan properti `isKeyboardVisible` belum ada pada `resources/js/app.js`:
Run: `grep -q "hasUnsavedChanges" resources/js/app.js || echo "MISSING_INTERACTIONS"`
Expected: Output `MISSING_INTERACTIONS`.

- [ ] **Step 2: Implement micro-interactions and event listeners in `resources/js/app.js`**

Tambahkan properti dan metode berikut ke dalam `proposalForm` di `resources/js/app.js`:
1. Pada state objek:
   ```javascript
   isKeyboardVisible: false,
   ```
2. Pada akhir method `init()`:
   ```javascript
            // Tab Guard: Prompt user before leaving with unsaved changes
            window.addEventListener('beforeunload', (e) => {
                if (this.submitting) return;
                if (this.hasUnsavedChanges()) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            // Mobile Virtual Keyboard Detection via Visual Viewport
            if (window.visualViewport) {
                const initialHeight = window.visualViewport.height;
                window.visualViewport.addEventListener('resize', () => {
                    this.isKeyboardVisible = window.visualViewport.height < initialHeight - 120;
                });
            }
   ```
3. Metode pembantu:
   ```javascript
        hasUnsavedChanges() {
            if (this.submitting) return false;
            const hasDoctor = Boolean(this.doctorInput && this.doctorInput.trim() !== '');
            const hasNotes = Boolean(this.notes && this.notes.trim() !== '');
            const hasItems = Array.isArray(this.items) && this.items.some(
                (it) => it.medicine_name && it.medicine_name.trim() !== ''
            );
            return hasDoctor || hasNotes || hasItems;
        },

        submitForm() {
            if (this.submitting) return;
            const form = document.getElementById('proposal-form');
            if (!form) return;

            // Trigger standard HTML5 validation
            if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
                return;
            }

            this.submitting = true;
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        },
   ```
4. Perbarui `addItem()`:
   ```javascript
        addItem() {
            if (this.items.length < 50) {
                this.items.push({ medicine_name: '', principal_name: '', clinical_justification: '', autoFilled: false });
                this.$nextTick(() => {
                    const inputs = document.querySelectorAll('[data-medicine-input]');
                    if (inputs.length > 0) {
                        inputs[inputs.length - 1].focus();
                    }
                });
            }
        },
   ```

- [ ] **Step 3: Run build and syntax verification to verify it passes**

Run: `node -c resources/js/app.js && npm run build`
Expected: Output `✓ built in ...` tanpa error syntax atau warning.

- [ ] **Step 4: Commit**

```bash
git add resources/js/app.js
git commit -m "feat(ux): add tab guard beforeunload, autofocus new row, keyboard submit, and viewport detector"
```

---

### Task 4: Blade View Integration (Banner, Character Counters, Form Shortcuts, & Mobile Action Bar)

**Files:**
- Modify: `resources/views/proposals/create.blade.php`
- Test: `tests/Feature/ProposalSubmissionTest.php`

**Interfaces:**
- Consumes: Alpine state & methods (`isDraftRestored`, `clearDraft()`, `isKeyboardVisible`, `submitForm()`, `addItem()`) from Tasks 2 & 3.
- Produces: Antarmuka formulir terpadu yang memuat banner pemulihan draf, counter kuota karakter `2.000`, shortcut `Ctrl+Enter` / `Cmd+Enter`, atribut `data-medicine-input`, dan safe-area sticky action bar seluler.

- [ ] **Step 1: Write the failing test**

Tambahkan pengujian di `tests/Feature/ProposalSubmissionTest.php`:
```php
it('renders draft restoration banner, character counters, mobile floating bar, and shortcut handlers in proposal form', function (): void {
    $response = $this->get(route('proposals.create'))->assertOk();

    // 1. Draft restoration banner markup & button
    $response->assertSee('x-show="isDraftRestored"', false)
        ->assertSee('Memulihkan draf usulan belum terkirim dari penyimpanan perangkat Anda.')
        ->assertSee('@click="clearDraft()"', false);

    // 2. Form shortcut attributes and id
    $response->assertSee('id="proposal-form"', false)
        ->assertSee('@keydown.ctrl.enter.prevent="submitForm()"', false)
        ->assertSee('@keydown.meta.enter.prevent="submitForm()"', false);

    // 3. Medicine input autofocus attribute hook
    $response->assertSee('data-medicine-input', false);

    // 4. Character counters for clinical justification and notes
    $response->assertSee('(item.clinical_justification || \'\').length', false)
        ->assertSee('(notes || \'\').length', false)
        ->assertSee('/ 2.000 karakter', false);

    // 5. Mobile floating action bar with safe area inset and submit binding
    $response->assertSee('x-show="!isKeyboardVisible"', false)
        ->assertSee('env(safe-area-inset-bottom', false)
        ->assertSee('@click="submitForm()"', false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/ProposalSubmissionTest.php`
Expected: FAIL dengan kegagalan assertion bahwa `x-show="isDraftRestored"` atau elemen UI baru belum ditemukan dalam HTML.

- [ ] **Step 3: Update `resources/views/proposals/create.blade.php`**

Lakukan perubahan berikut pada `resources/views/proposals/create.blade.php`:
1. Sisipkan **Banner Pemulihan Draf** tepat sebelum tag `<form>`:
   ```blade
           <!-- Unsaved Draft Restored Notice Banner -->
           <div x-show="isDraftRestored"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-2"
                class="rounded-xl bg-amber-50 border border-amber-200 p-3.5 flex items-center justify-between text-xs text-amber-900 shadow-xs"
                style="display: none;">
               <div class="flex items-center gap-2">
                   <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                   </svg>
                   <span>Memulihkan draf usulan belum terkirim dari penyimpanan perangkat Anda.</span>
               </div>
               <button type="button" @click="clearDraft()" class="font-semibold text-rose-700 hover:text-rose-900 underline ml-3 shrink-0">
                   Kosongkan & Mulai Baru
               </button>
           </div>
   ```

2. Tambahkan `id="proposal-form"` dan shortcut keyboard pada pembuka `<form>`:
   ```blade
           <form id="proposal-form"
                 method="POST"
                 action="{{ route('proposals.store') }}"
                 @submit="submitting = true"
                 @keydown.ctrl.enter.prevent="submitForm()"
                 @keydown.meta.enter.prevent="submitForm()"
                 class="space-y-6">
   ```

3. Tambahkan atribut `data-medicine-input` pada input nama obat di repeater:
   ```blade
                                       <input type="text"
                                              :name="`items[${index}][medicine_name]`"
                                              x-model="item.medicine_name"
                                              data-medicine-input
                                              @input="handleInput($event)"
   ```

4. Tambahkan **Dynamic Character Counter** di bawah textarea `clinical_justification`:
   ```blade
                                   <div class="flex justify-end text-[11px] text-slate-400 mt-1">
                                       <span x-text="(item.clinical_justification || '').length"></span> / 2.000 karakter
                                   </div>
   ```

5. Tambahkan **Dynamic Character Counter** di bawah textarea `notes`:
   ```blade
                       <div class="flex justify-end text-[11px] text-slate-400 mt-1">
                           <span x-text="(notes || '').length"></span> / 2.000 karakter
                       </div>
   ```

6. Sisipkan **Safe-Area Mobile Floating Action Bar** tepat sebelum penutup `</form>`:
   ```blade
               <!-- Safe-Area Mobile Floating Action Bar -->
               <div x-show="!isKeyboardVisible"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-4"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 translate-y-4"
                    class="fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur-md border-t border-slate-200/80 p-3 sm:hidden z-30 shadow-xl pb-[calc(0.75rem+env(safe-area-inset-bottom,0px))]">
                   <div class="flex items-center justify-between gap-3 max-w-lg mx-auto">
                       <div class="text-xs font-medium text-slate-600">
                           Total: <span class="font-bold text-slate-900" x-text="items.length"></span> obat
                       </div>
                       <button type="button"
                               @click="submitForm()"
                               :disabled="submitting"
                               class="inline-flex items-center justify-center gap-1.5 px-5 py-2.5 rounded-xl bg-emerald-700 active:bg-emerald-800 text-white font-semibold text-xs shadow-sm transition-all disabled:opacity-50">
                           <svg x-show="!submitting" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                           </svg>
                           <span x-text="submitting ? 'Mengirim...' : 'Kirim Usulan'"></span>
                       </button>
                   </div>
               </div>
   ```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/ProposalSubmissionTest.php`
Expected: PASS (seluruh 7 test di `ProposalSubmissionTest` lolos).

- [ ] **Step 5: Commit**

```bash
git add resources/views/proposals/create.blade.php tests/Feature/ProposalSubmissionTest.php
git commit -m "feat(ux): add draft restored banner, character counters, mobile floating bar, and shortcut handlers to blade form"
```

---

### Task 5: End-to-End Quality Gate & Release Verification

**Files:**
- Verify: `tests/Feature/ProposalSubmissionTest.php`
- Verify: `resources/js/app.js`
- Verify: `resources/views/proposals/create.blade.php`

**Interfaces:**
- Consumes: Seluruh artefak kode dan uji dari Task 1 hingga Task 4.
- Produces: Kepastian 100% lulus pada seluruh suite uji Pest PHP (minimal 29 tests, 170+ assertions), nihil pelanggaran style PHP Laravel Pint, serta build produksi Vite yang valid.

- [ ] **Step 1: Run complete Pest PHP test suite**

Run: `./vendor/bin/pest`
Expected: PASS (29 tests passed, >= 170 assertions).

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

Plan complete and saved to `docs/superpowers/plans/2026-11-17-v1-0-1-form-doctor-ux-plan.md`.

Sesuai arahan metodologi **Two-Tier Specs** pada [ROADMAP.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/ROADMAP.md#L122), rencana ini siap dieksekusi secara otonom melalui **Subagent-Driven Development (SDD)** dan perintah `/goal`.
