<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\ProposalItem;
use Illuminate\Database\Eloquent\Builder;

class ProposalItemRecapQuery
{
    /**
     * Whitelist mapping for user-driven sort columns.
     *
     * @var array<string, string>
     */
    private const SORT_MAP = [
        'date' => 'proposals.proposal_date',
        'period' => 'proposals.period_year',
        'doctor' => 'proposals.doctor_name',
        'medicine' => 'proposal_items.medicine_name',
        'principal' => 'proposal_items.principal_name',
        'created_at' => 'proposal_items.created_at',
    ];

    /**
     * Builds standard recap query joining proposal_items and proposals.
     * Compatible with both SQLite and MariaDB.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<ProposalItem>
     */
    public static function build(array $filters = []): Builder
    {
        $query = ProposalItem::query()
            ->join('proposals', 'proposals.id', '=', 'proposal_items.proposal_id')
            ->select([
                'proposal_items.id',
                'proposal_items.proposal_id',
                'proposal_items.medicine_name',
                'proposal_items.principal_name',
                'proposal_items.clinical_justification',
                'proposal_items.created_at',
                'proposals.doctor_name',
                'proposals.proposal_date',
                'proposals.period_year',
                'proposals.notes',
            ]);

        if (! empty($filters['period_year'])) {
            $query->where('proposals.period_year', (int) $filters['period_year']);
        }

        if (! empty($filters['doctor'])) {
            $query->where('proposals.doctor_name', (string) $filters['doctor']);
        }

        if (! empty($filters['principal'])) {
            $query->where('proposal_items.principal_name', (string) $filters['principal']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('proposals.proposal_date', '>=', (string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('proposals.proposal_date', '<=', (string) $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $term = addcslashes(trim((string) $filters['search']), '%_');
            $query->where(function (Builder $q) use ($term): void {
                $q->where('proposal_items.medicine_name', 'like', "%{$term}%")
                    ->orWhere('proposals.doctor_name', 'like', "%{$term}%")
                    ->orWhere('proposal_items.principal_name', 'like', "%{$term}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? null;
        $sortDir = strtolower((string) ($filters['sort_dir'] ?? '')) === 'asc' ? 'asc' : 'desc';

        if (is_string($sortBy) && isset(self::SORT_MAP[$sortBy])) {
            $query->orderBy(self::SORT_MAP[$sortBy], $sortDir);

            if ($sortBy === 'date') {
                $query->orderBy('proposals.id', $sortDir);
            }

            return $query->orderBy('proposal_items.id');
        }

        return $query
            ->orderByDesc('proposals.proposal_date')
            ->orderByDesc('proposals.id')
            ->orderBy('proposal_items.id');
    }
}
