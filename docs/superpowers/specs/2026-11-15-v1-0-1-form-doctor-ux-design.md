# Design Document: FormulariumRS v1.0.1 — UX/UI Micro-Polish pada Formulir Dokter

**Tanggal:** 2026-11-15  
**Target Rilis:** `v1.0.1`  
**Status:** Disetujui (Builder Review Passed: Ready for Tier 2 Implementation Plan)  
**Dokumen Terkait:** [ROADMAP.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/ROADMAP.md), [AGENTS.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/AGENTS.md)

---

## 1. Intent & The Why

Dokter Penanggung Jawab Pelayanan (DPJP) di RS Aisyiyah Siti Fatimah Tulangan mengusulkan obat formularium di sela mobilitas tinggi (saat visitasi bangsal atau pergantian pasien di poliklinik). Penginputan kerap dilakukan menggunakan peramban seluler (smartphone) atau laptop portabel.

Saat ini terdapat friksi kecil yang berisiko merugikan pengguna:
1. **Kehilangan Data Akibat Navigasi Tak Sengaja:** Jika peramban tertutup atau dokter ter-*swipe back* di layar ponsel, usulan obat yang sedang diketik hilang seketika karena belum ada penyimpanan sementara di sisi klien.
2. **Kelelahan Scroll pada Formulir Panjang:** Tombol submit berada di paling bawah halaman kartu obat, menyulitkan pengiriman usulan jika dokter menginput lebih dari 3–5 obat di layar seluler.
3. **Visibilitas Kuota Karakter:** Kolom justifikasi klinis dan catatan pengusul dibatasi maksimal 2.000 karakter di database, namun dokter tidak memiliki umpan balik visual mengenai sisa kuota yang telah diketik.
4. **Alur Penambahan Obat Manual:** Setelah menekan tombol "Tambah Baris Obat Lainnya", kursor tidak otomatis berpindah ke baris obat baru sehingga dokter harus melakukan ketukan (*tap/click*) tambahan.

---

## 2. Goals, Non-Goals, and Anti-Goals

### Goals
- **Auto-Save Draf Lokal (`localStorage`):** Menyimpan status input formulir dokter (`doctorInput`, `items`, `notes`) secara berkala dan otomatis ke penyimpanan lokal peramban.
- **Restorasi Draf Non-Blocking:** Memulihkan draf secara langsung saat formulir dibuka kembali tanpa dialog pemblokir, disertai indikator banner ramah yang memungkinkan dokter membuang draf jika ingin mulai dari awal.
- **Pembersihan Draf Otomatis:** Menghapus draf lokal secara tuntas saat formulir sukses dikirimkan ke server (`session('submitted_summary')`).
- **Tab Guard (`beforeunload`):** Memberikan konfirmasi peramban standar jika dokter hendak meninggalkan atau memuat ulang halaman saat terdapat ketikan usulan yang belum tersubmit.
- **Safe-Area Mobile Floating Action Bar:** Tombol aksi mengambang (*sticky bar*) di layar seluler (`sm:hidden`) dengan `env(safe-area-inset-bottom)` yang otomatis disembunyikan saat keyboard virtual terbuka (`window.visualViewport`).
- **Shortcut Submit Keyboard:** Dukungan `Ctrl+Enter` / `Cmd+Enter` pada formulir untuk mempercepat pengiriman usulan dari perangkat desktop/laptop.
- **Auto-Focus Baris Baru:** Memindahkan fokus kursor ke input nama obat pada baris baru saat dokter menekan "Tambah Baris Obat Lainnya".
- **Dynamic Character Counter:** Menampilkan indikator jumlah karakter `x-text` (`0 / 2.000`) di bawah textarea alasan klinis dan catatan tambahan.

### Non-Goals
- Tidak menambahkan sistem autentikasi, login, PIN, atau session server untuk dokter (mempertahankan sifat *zero-friction*).
- Tidak menyimpan draf di sisi server/database SQLite (seluruh draf sepenuhnya berdiam di peramban klien via `localStorage`).
- Tidak mengubah skema tabel database `proposals` atau `proposal_items`.
- Tidak mengubah kontrak endpoint API saran dokter atau obat.

### Anti-Goals
- **Dilarang memunculkan modal dialog pemblokir (blocking prompt):** Tidak boleh ada pop-up konfirmasi yang memaksa dokter mengklik sebelum halaman form dapat diakses.
- **Dilarang menimpa data error validasi server (`old()`):** Jika pengguna mengalami kegagalan validasi server (misal nama obat duplikat atau field wajib belum terisi), data yang dirender wajib berasal dari `old()` Laravel, bukan dari `localStorage`.
- **Dilarang menutupi input teks di HP:** Floating action bar tidak boleh menumpuk atau menutupi input yang sedang diketik ketika keyboard virtual aktif.

---

## 3. Constraints

- **Prinsip AGENTS.md:**
  - Wajib mempertahankan arsitektur Blade Components + Tailwind CSS v4 + Alpine.js lokal yang dibundel Vite (tanpa script CDN eksternal).
  - Seluruh pengujian Pest PHP (`./vendor/bin/pest`) wajib 100% PASS (28 tests, 159 assertions baseline).
  - Standarisasi kode Laravel Pint (`./vendor/bin/pint --test`) wajib bersih.
- **Kompatibilitas Seluler:** Mendukung Safari iOS (Webkit) dan Chrome Android tanpa menimbulkan bug visual pada safe area notch dan keyboard virtual.

---

## 4. Approaches

### Opsi yang Dipertimbangkan:
1. **Opsi 1: Seamless Non-Blocking Auto-Restore (Dipilih)**
   - Auto-save ke `localStorage` (debounced 500ms).
   - Memulihkan draf secara langsung saat halaman dibuka (jika tidak ada `old()` dan bukan flash success), disertai banner informatif: *"Memulihkan draf belum terkirim dari perangkat ini — [Kosongkan & Mulai Baru]"*.
   - Floating Action Bar di perangkat seluler yang otomatis sembunyi saat keyboard virtual terbuka (`visualViewport`).
   - Shortcut keyboard `Ctrl+Enter` / `Cmd+Enter` dan autofocus baris baru.
   - *Alasan Pemilihan:* Memberikan proteksi maksimal dari insiden kehilangan data tanpa menambah friksi interaksi DPJP.

2. **Opsi 2: Explicit Modal Confirmation Prompt**
   - Menampilkan pop-up dialog setiap kali ada draf tersimpan.
   - *Alasan Tidak Dipilih:* Menghambat alur cepat dokter di bangsal karena mewajibkan 1 klik modal konfirmasi sebelum bisa mengetik.

3. **Opsi 3: Lightweight Client Polish (Tanpa Mobile Floating Bar)**
   - Menerapkan draf dan shortcut saja tanpa floating bar mobile.
   - *Alasan Tidak Dipilih:* Tidak menyelesaikan keluhan dokter terkait keharusan scroll panjang ke dasar halaman saat mengusulkan banyak obat di smartphone.

---

## 5. Detailed Architecture & Decisions

### 5.1 Hydration Data & Server-Client Boundary
Di [resources/views/proposals/create.blade.php](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/resources/views/proposals/create.blade.php):
Elemen JSON safe carrier dimutakhirkan untuk meneruskan status server:
```blade
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

### 5.2 Alpine.js Component (`resources/js/app.js`)
Pembaruan pada component `proposalForm`:
1. **State:**
   - `isDraftRestored: false`
   - `isKeyboardVisible: false`
   - `storageKey: 'formulary_draft_v1'`
   - `autoSaveTimer: null`
2. **Inisialisasi & Logika Prioritas (`init()`):**
   - **Prioritas 1 (Flash Success):** Jika `hasFlashSuccess === true`, jalankan `localStorage.removeItem(this.storageKey)`.
   - **Prioritas 2 (Validation Error `old()`):** Jika `hasOldInput === true`, gunakan data `old()` dari server; jangan pulihkan dari `localStorage`.
   - **Prioritas 3 (Pemulihan Draf):** Jika tidak ada `old()` dan tidak ada flash success, periksa `localStorage.getItem(this.storageKey)`. Jika terdapat data draf yang valid dan memiliki konten, isi state `doctorInput`, `items`, dan `notes`, lalu set `isDraftRestored = true`.
3. **Mekanisme Auto-Save:**
   - Metode `saveDraft()` menyimpan `{ doctorInput, items, notes, savedAt: Date.now() }` ke `localStorage`.
   - Dipicu dengan debounce 500ms setiap ada mutasi pada data form.
   - Jika dokter menghapus semua obat dan nama dokter kosong, draf di `localStorage` otomatis dibersihkan.
4. **Metode `clearDraft()`:**
   - Menghapus key `formulary_draft_v1` dari `localStorage`.
   - Mereset form ke kondisi default (1 baris obat kosong, nama dokter kosong, catatan kosong).
   - Set `isDraftRestored = false`.
5. **Guard Tab Unsaved (`beforeunload`):**
   - Pasang event listener pada window:
     ```javascript
     window.addEventListener('beforeunload', (e) => {
         if (this.submitting) return;
         if (this.hasUnsavedChanges()) {
             e.preventDefault();
             e.returnValue = '';
         }
     });
     ```
   - Helper `hasUnsavedChanges()` mengecek apakah nama dokter terisi atau terdapat minimal satu baris obat yang `medicine_name`-nya tidak kosong.
6. **Autofocus Baris Obat Baru:**
   - Pada `addItem()`:
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
     }
     ```
7. **Deteksi Keyboard Virtual Seluler:**
   - Menggunakan `window.visualViewport`:
     ```javascript
     if (window.visualViewport) {
         const initialHeight = window.visualViewport.height;
         window.visualViewport.addEventListener('resize', () => {
             // Jika tinggi viewport berkurang lebih dari 120px, asumsikan keyboard terbuka
             this.isKeyboardVisible = window.visualViewport.height < initialHeight - 120;
         });
     }
     ```

### 5.3 UI & Markup Blade (`create.blade.php`)
1. **Banner Pemulihan Draf:**
   - Ditempatkan tepat di atas formulir kartu dokter:
     ```blade
     <div x-show="isDraftRestored" x-transition class="rounded-xl bg-amber-50 border border-amber-200 p-3.5 flex items-center justify-between text-xs text-amber-900 shadow-xs">
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
2. **Shortcut Keyboard pada Form:**
   - `<form id="proposal-form" method="POST" action="..." @submit="submitting = true" @keydown.ctrl.enter.prevent="submitForm()" @keydown.meta.enter.prevent="submitForm()">`
3. **Atribut `data-medicine-input` pada Input Obat:**
   - Memudahkan pemilihan elemen target autofocus saat baris baru dirender.
4. **Dynamic Character Counter:**
   - Ditambahkan di bawah textarea alasan klinis pada template per-baris:
     ```blade
     <div class="flex justify-end text-[11px] text-slate-400 mt-1">
         <span x-text="(item.clinical_justification || '').length"></span> / 2.000 karakter
     </div>
     ```
   - Ditambahkan di bawah textarea catatan pengusul:
     ```blade
     <div class="flex justify-end text-[11px] text-slate-400 mt-1">
         <span x-text="(notes || '').length"></span> / 2.000 karakter
     </div>
     ```
5. **Mobile Floating Action Bar:**
   - Ditambahkan sebelum penutup tag formulir:
     ```blade
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

---

## 6. What's Left to the Builder

- Pengikatan event listener submit formulir antara tombol desktop dan mobile bar (memastikan validasi HTML5 form tetap berjalan normal saat `submitForm()` dipanggil via JavaScript).
- Menjalankan kompilasi aset `npm run build` dan memverifikasi tidak ada warning Vite / Tailwind CSS.
- Menjalankan uji Pest PHP `./vendor/bin/pest` untuk memastikan skenario submit formulir existing tetap 100% lulus.
- Memastikan `./vendor/bin/pint --test` bersih dari pelanggaran gaya penulisan kode PHP.
