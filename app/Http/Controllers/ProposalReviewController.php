<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ProposalItem;
use App\Support\TextNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProposalReviewController extends Controller
{
    /**
     * Authenticate KFT reviewer session with secret passcode.
     */
    public function auth(Request $request): JsonResponse
    {
        $request->validate([
            'secret' => ['required', 'string'],
            'reviewer_name' => ['nullable', 'string', 'max:255'],
        ]);

        $configuredSecret = (string) config('formulary.review_secret', '');
        $inputSecret = (string) $request->input('secret', '');

        if ($configuredSecret === '' || ! hash_equals($configuredSecret, $inputSecret)) {
            throw ValidationException::withMessages([
                'secret' => ['Passcode telaah tidak valid.'],
            ]);
        }

        $reviewerName = TextNormalizer::collapseWhitespace($request->input('reviewer_name'));
        if ($reviewerName === '') {
            $reviewerName = 'Tim Farmasi';
        }

        $request->session()->put('kft_authenticated', true);
        $request->session()->put('reviewer_name', $reviewerName);

        return response()->json([
            'success' => true,
            'reviewer_name' => $reviewerName,
        ]);
    }

    /**
     * Terminate KFT reviewer session.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->session()->forget(['kft_authenticated', 'reviewer_name']);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Update review status and notes for a proposal item.
     */
    public function update(Request $request, ProposalItem $item): JsonResponse
    {
        if ($request->session()->get('kft_authenticated') !== true) {
            abort(403, 'Akses ditolak. Sesi telaah KFT belum diautentikasi.');
        }

        $validated = $request->validate([
            'review_status' => ['required', 'string', Rule::in(['pending', 'approved', 'rejected', 'substituted'])],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $collapsedNotes = TextNormalizer::collapseWhitespace($validated['review_notes'] ?? null);
        $reviewNotes = $collapsedNotes !== '' ? $collapsedNotes : null;

        $reviewerName = TextNormalizer::collapseWhitespace((string) $request->session()->get('reviewer_name', 'Tim Farmasi'));
        if ($reviewerName === '') {
            $reviewerName = 'Tim Farmasi';
        }

        $item->update([
            'review_status' => $validated['review_status'],
            'review_notes' => $reviewNotes,
            'reviewed_at' => now(),
            'reviewer_name' => $reviewerName,
        ]);

        return response()->json([
            'success' => true,
            'item' => $item->fresh(),
        ]);
    }
}
