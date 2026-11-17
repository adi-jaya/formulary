@props([
    'column',
    'label' => null,
    'currentSortBy' => null,
    'currentSortDir' => 'asc',
    'defaultDir' => 'asc',
])

@php
    $currentSortBy = $currentSortBy ?? request('sort_by');
    $currentSortDir = $currentSortDir ?? request('sort_dir', 'asc');
    $isActive = $currentSortBy === $column;
    $nextDir = $isActive ? ($currentSortDir === 'asc' ? 'desc' : 'asc') : $defaultDir;
    $url = request()->fullUrlWithQuery(['sort_by' => $column, 'sort_dir' => $nextDir, 'page' => null]);
@endphp

<th scope="col" {{ $attributes->merge(['class' => 'py-3 px-4']) }} @if($isActive) aria-sort="{{ $currentSortDir === 'asc' ? 'ascending' : 'descending' }}" @endif>
    <a href="{{ $url }}" 
       class="group inline-flex items-center gap-1.5 font-semibold transition-colors {{ $isActive ? 'text-emerald-800' : 'text-slate-600 hover:text-slate-900' }}">
        <span>{{ $label ?? $slot }}</span>
        <span class="inline-flex items-center shrink-0">
            @if ($isActive && $currentSortDir === 'asc')
                {{-- Active ASC: Emerald Up Arrow --}}
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                </svg>
            @elseif ($isActive && $currentSortDir === 'desc')
                {{-- Active DESC: Emerald Down Arrow --}}
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                </svg>
            @else
                {{-- Neutral: Inactive Gray Arrow Pair (⇅) --}}
                <svg class="w-3 h-3 text-slate-400 group-hover:text-slate-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 4v12m0 0l4-4m-4 4l-4-4" />
                </svg>
            @endif
        </span>
    </a>
</th>
