<?php

declare(strict_types=1);

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
     * @param  array<string, mixed>  $filters
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

        /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
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
                $item->principals = $medicineDetails->pluck('principal_name')
                    ->filter(fn ($val) => $val !== null && trim((string) $val) !== '')
                    ->unique()
                    ->values()
                    ->all();

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
        $topSupportedCount = (int) ($results->max('distinct_doctors_count') ?? 0);

        return [
            'total_medicines' => $totalMedicines,
            'consensus_medicines' => $consensusMedicines,
            'top_supported_count' => $topSupportedCount,
        ];
    }
}
