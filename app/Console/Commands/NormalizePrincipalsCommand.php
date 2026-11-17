<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProposalItem;
use App\Models\ReferenceMedicine;
use App\Support\TextNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizePrincipalsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'formulary:normalize-principals {--dry-run : Menampilkan pratinjau perubahan tanpa memutasi basis data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menormalisasi nama principle obat pada usulan dan referensi obat sesuai aturan TextNormalizer';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY-RUN] Pratinjau normalisasi nama principle (tidak ada perubahan yang disimpan ke basis data):');
        }

        /** @var array<int, array{model: ProposalItem, original: string|null, normalized: string|null}> $proposalItemChanges */
        $proposalItemChanges = [];
        ProposalItem::query()
            ->whereNotNull('principal_name')
            ->orderBy('id')
            ->chunk(200, function ($items) use (&$proposalItemChanges): void {
                foreach ($items as $item) {
                    $original = $item->principal_name;
                    $normalized = TextNormalizer::normalizePrincipal($original);

                    if ($normalized !== $original) {
                        $proposalItemChanges[] = [
                            'model' => $item,
                            'original' => $original,
                            'normalized' => $normalized,
                        ];
                    }
                }
            });

        /** @var array<int, array{model: ReferenceMedicine, original: string|null, normalized: string|null}> $refMedicineChanges */
        $refMedicineChanges = [];
        ReferenceMedicine::query()
            ->whereNotNull('principal_name')
            ->orderBy('id')
            ->chunk(200, function ($refs) use (&$refMedicineChanges): void {
                foreach ($refs as $ref) {
                    $original = $ref->principal_name;
                    $normalized = TextNormalizer::normalizePrincipal($original);

                    if ($normalized !== $original) {
                        $refMedicineChanges[] = [
                            'model' => $ref,
                            'original' => $original,
                            'normalized' => $normalized,
                        ];
                    }
                }
            });

        $totalChanges = count($proposalItemChanges) + count($refMedicineChanges);

        if ($totalChanges === 0) {
            $this->info('Semua nama principle sudah ternormalisasi. Tidak ada perubahan yang diperlukan.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $rows = [];
            foreach ($proposalItemChanges as $change) {
                $rows[] = [
                    'ProposalItem',
                    (string) $change['model']->id,
                    $change['model']->medicine_name,
                    $change['original'] ?? '(null)',
                    $change['normalized'] ?? '(null)',
                ];
            }

            foreach ($refMedicineChanges as $change) {
                $rows[] = [
                    'ReferenceMedicine',
                    (string) $change['model']->id,
                    $change['model']->medicine_name,
                    $change['original'] ?? '(null)',
                    $change['normalized'] ?? '(null)',
                ];
            }

            $this->table(['Model', 'ID', 'Nama Obat', 'Principle Awal', 'Principle Baru'], $rows);
            $this->info(sprintf('[DRY-RUN] Total %d ProposalItem dan %d ReferenceMedicine akan dinormalisasi.', count($proposalItemChanges), count($refMedicineChanges)));

            return self::SUCCESS;
        }

        DB::transaction(function () use ($proposalItemChanges, $refMedicineChanges): void {
            foreach ($proposalItemChanges as $change) {
                $change['model']->principal_name = $change['normalized'];
                $change['model']->save();
            }

            foreach ($refMedicineChanges as $change) {
                $change['model']->principal_name = $change['normalized'];
                $change['model']->save();
            }
        });

        $this->info(sprintf('Berhasil menormalisasi %d ProposalItem dan %d ReferenceMedicine.', count($proposalItemChanges), count($refMedicineChanges)));

        return self::SUCCESS;
    }
}
