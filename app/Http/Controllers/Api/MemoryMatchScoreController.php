<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemoryMatchScoreRequest;
use App\Models\MemoryMatchScore;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class MemoryMatchScoreController extends Controller
{
    use ApiResponseTrait;

    public function store(StoreMemoryMatchScoreRequest $request)
    {
        $validated = $request->validated();
        $score = $this->calculateScore($validated['elapsed_seconds'], $validated['moves']);

        MemoryMatchScore::create([
            'user_id' => $validated['user_id'],
            'user_name' => $validated['user_name'],
            'moves' => $validated['moves'],
            'elapsed_seconds' => $validated['elapsed_seconds'],
            'matched_pairs' => $validated['matched_pairs'],
            'score' => $score,
            'played_at' => $validated['played_at'] ?? now(),
        ]);

        $rank = $this->rankByUserId((int) $validated['user_id']);

        return $this->successResponse([
            'user_id' => (int) $validated['user_id'],
            'score' => $score,
            'rank' => $rank,
        ], 'Puntaje registrado correctamente.', 201);
    }

    public function leaderboard(Request $request)
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = (int) ($validated['limit'] ?? 20);

        $rows = $this->orderedBestScoresQuery()
            ->limit($limit)
            ->get()
            ->values()
            ->map(function (MemoryMatchScore $score, int $index) {
                return [
                    'rank_position' => $index + 1,
                    'user_id' => $score->user_id,
                    'user_name' => $score->user_name,
                    'score' => $score->score,
                    'elapsed_seconds' => $score->elapsed_seconds,
                    'moves' => $score->moves,
                    'matched_pairs' => $score->matched_pairs,
                    'played_at' => optional($score->played_at)->toISOString(),
                ];
            });

        return $this->successResponse([
            'leaderboard' => $rows,
            'limit' => $limit,
        ], 'Leaderboard global obtenido correctamente.');
    }

    public function myScore(int $userId)
    {
        $bestScore = $this->orderedBestScoresQuery()
            ->where('mms.user_id', $userId)
            ->first();

        if (! $bestScore) {
            return $this->errorResponse('No se encontraron puntajes para el usuario.', 404);
        }

        $rank = $this->rankByBestRow($bestScore);
        $bestTime = MemoryMatchScore::query()->where('user_id', $userId)->min('elapsed_seconds');
        $bestMoves = MemoryMatchScore::query()->where('user_id', $userId)->min('moves');

        return $this->successResponse([
            'user_id' => $userId,
            'user_name' => $bestScore->user_name,
            'best_score' => $bestScore->score,
            'best_elapsed_seconds' => $bestTime,
            'best_moves' => $bestMoves,
            'rank' => $rank,
        ], 'Mejor puntaje del usuario obtenido correctamente.');
    }

    private function calculateScore(int $elapsedSeconds, int $moves): int
    {
        return max(0, 10000 - ($elapsedSeconds * 20) - ($moves * 35));
    }

    private function orderedBestScoresQuery(): Builder
    {
        return $this->bestScoresPerUserQuery()
            ->orderByDesc('mms.score')
            ->orderBy('mms.elapsed_seconds')
            ->orderBy('mms.moves')
            ->orderBy('mms.id');
    }

    private function bestScoresPerUserQuery(): Builder
    {
        return MemoryMatchScore::query()
            ->from('memory_match_scores as mms')
            ->select('mms.*')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('memory_match_scores as better')
                    ->whereColumn('better.user_id', 'mms.user_id')
                    ->where(function ($where) {
                        $where->whereColumn('better.score', '>', 'mms.score')
                            ->orWhere(function ($tie1) {
                                $tie1->whereColumn('better.score', 'mms.score')
                                    ->whereColumn('better.elapsed_seconds', '<', 'mms.elapsed_seconds');
                            })
                            ->orWhere(function ($tie2) {
                                $tie2->whereColumn('better.score', 'mms.score')
                                    ->whereColumn('better.elapsed_seconds', 'mms.elapsed_seconds')
                                    ->whereColumn('better.moves', '<', 'mms.moves');
                            })
                            ->orWhere(function ($tie3) {
                                $tie3->whereColumn('better.score', 'mms.score')
                                    ->whereColumn('better.elapsed_seconds', 'mms.elapsed_seconds')
                                    ->whereColumn('better.moves', 'mms.moves')
                                    ->whereColumn('better.id', '<', 'mms.id');
                            });
                    });
            });
    }

    private function rankByUserId(int $userId): ?int
    {
        $best = $this->orderedBestScoresQuery()
            ->where('mms.user_id', $userId)
            ->first();

        if (! $best) {
            return null;
        }

        return $this->rankByBestRow($best);
    }

    private function rankByBestRow(MemoryMatchScore $best): int
    {
        $count = $this->bestScoresPerUserQuery()
            ->where(function ($where) use ($best) {
                $where->where('mms.score', '>', $best->score)
                    ->orWhere(function ($tie1) use ($best) {
                        $tie1->where('mms.score', $best->score)
                            ->where('mms.elapsed_seconds', '<', $best->elapsed_seconds);
                    })
                    ->orWhere(function ($tie2) use ($best) {
                        $tie2->where('mms.score', $best->score)
                            ->where('mms.elapsed_seconds', $best->elapsed_seconds)
                            ->where('mms.moves', '<', $best->moves);
                    })
                    ->orWhere(function ($tie3) use ($best) {
                        $tie3->where('mms.score', $best->score)
                            ->where('mms.elapsed_seconds', $best->elapsed_seconds)
                            ->where('mms.moves', $best->moves)
                            ->where('mms.id', '<', $best->id);
                    });
            })
            ->count();

        return $count + 1;
    }
}

