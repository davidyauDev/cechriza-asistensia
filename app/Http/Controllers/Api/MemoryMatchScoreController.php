<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemoryMatchScoreRequest;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemoryMatchScoreController extends Controller
{
    use ApiResponseTrait;

    public function store(StoreMemoryMatchScoreRequest $request)
    {
        $validated = $request->validated();
        $score = $this->calculateScore($validated['elapsed_seconds'], $validated['moves']);
        $playedAt = $validated['played_at'] ?? now();
        $userId = (int) $validated['user_id'];
        $connection = DB::connection('mysql_external');

        $current = $connection
            ->table('memory_match_leaderboard')
            ->where('user_id', $userId)
            ->first();

        if (! $current) {
            $connection->table('memory_match_leaderboard')->insert([
                'user_id' => $userId,
                'user_name' => $validated['user_name'],
                'best_score' => $score,
                'best_moves' => $validated['moves'],
                'best_elapsed_seconds' => $validated['elapsed_seconds'],
                'matched_pairs' => $validated['matched_pairs'],
                'last_played_at' => $playedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $isBetter = $this->isCandidateBetter(
                $score,
                (int) $validated['elapsed_seconds'],
                (int) $validated['moves'],
                (int) $current->best_score,
                (int) $current->best_elapsed_seconds,
                (int) $current->best_moves
            );

            $update = [
                'user_name' => $validated['user_name'],
                'last_played_at' => $playedAt,
                'updated_at' => now(),
            ];

            if ($isBetter) {
                $update['best_score'] = $score;
                $update['best_elapsed_seconds'] = $validated['elapsed_seconds'];
                $update['best_moves'] = $validated['moves'];
                $update['matched_pairs'] = $validated['matched_pairs'];
            }

            $connection->table('memory_match_leaderboard')
                ->where('user_id', $userId)
                ->update($update);
        }

        $rank = $this->rankByUserId($userId);
        $best = $connection
            ->table('memory_match_leaderboard')
            ->where('user_id', $userId)
            ->first();

        return $this->successResponse([
            'user_id' => $userId,
            'user_name' => $validated['user_name'],
            'last_game_score' => $score,
            'best_score' => (int) $best->best_score,
            'best_elapsed_seconds' => (int) $best->best_elapsed_seconds,
            'best_moves' => (int) $best->best_moves,
            'rank' => $rank,
        ], 'Puntaje registrado correctamente.', 201);
    }

    public function leaderboard(Request $request)
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = (int) ($validated['limit'] ?? 20);

        $rows = DB::connection('mysql_external')
            ->table('memory_match_leaderboard')
            ->orderByDesc('best_score')
            ->orderBy('best_elapsed_seconds')
            ->orderBy('best_moves')
            ->orderBy('user_id')
            ->limit($limit)
            ->get()
            ->values()
            ->map(function (object $score, int $index) {
                return [
                    'rank_position' => $index + 1,
                    'user_id' => $score->user_id,
                    'user_name' => $score->user_name,
                    'score' => (int) $score->best_score,
                    'elapsed_seconds' => (int) $score->best_elapsed_seconds,
                    'moves' => (int) $score->best_moves,
                    'matched_pairs' => $score->matched_pairs,
                    'played_at' => $score->last_played_at,
                ];
            });

        return $this->successResponse([
            'leaderboard' => $rows,
            'limit' => $limit,
        ], 'Leaderboard global obtenido correctamente.');
    }

    public function myScore(int $userId)
    {
        $bestScore = DB::connection('mysql_external')
            ->table('memory_match_leaderboard')
            ->where('user_id', $userId)
            ->first();

        if (! $bestScore) {
            return $this->errorResponse('No se encontraron puntajes para el usuario.', 404);
        }

        $rank = $this->rankByUserId($userId);

        return $this->successResponse([
            'user_id' => $userId,
            'user_name' => $bestScore->user_name,
            'best_score' => (int) $bestScore->best_score,
            'best_elapsed_seconds' => (int) $bestScore->best_elapsed_seconds,
            'best_moves' => (int) $bestScore->best_moves,
            'rank' => $rank,
        ], 'Mejor puntaje del usuario obtenido correctamente.');
    }

    private function calculateScore(int $elapsedSeconds, int $moves): int
    {
        return max(0, 10000 - ($elapsedSeconds * 20) - ($moves * 35));
    }

    private function rankByUserId(int $userId): ?int
    {
        $connection = DB::connection('mysql_external');
        $best = $connection
            ->table('memory_match_leaderboard')
            ->where('user_id', $userId)
            ->first();

        if (! $best) {
            return null;
        }

        $count = $connection
            ->table('memory_match_leaderboard')
            ->where(function ($where) use ($best) {
                $where->where('best_score', '>', $best->best_score)
                    ->orWhere(function ($tie1) use ($best) {
                        $tie1->where('best_score', $best->best_score)
                            ->where('best_elapsed_seconds', '<', $best->best_elapsed_seconds);
                    })
                    ->orWhere(function ($tie2) use ($best) {
                        $tie2->where('best_score', $best->best_score)
                            ->where('best_elapsed_seconds', $best->best_elapsed_seconds)
                            ->where('best_moves', '<', $best->best_moves);
                    })
                    ->orWhere(function ($tie3) use ($best) {
                        $tie3->where('best_score', $best->best_score)
                            ->where('best_elapsed_seconds', $best->best_elapsed_seconds)
                            ->where('best_moves', $best->best_moves)
                            ->where('user_id', '<', $best->user_id);
                    });
            })
            ->count();

        return $count + 1;
    }

    private function isCandidateBetter(
        int $candidateScore,
        int $candidateElapsed,
        int $candidateMoves,
        int $currentScore,
        int $currentElapsed,
        int $currentMoves
    ): bool {
        if ($candidateScore > $currentScore) {
            return true;
        }

        if ($candidateScore < $currentScore) {
            return false;
        }

        if ($candidateElapsed < $currentElapsed) {
            return true;
        }

        if ($candidateElapsed > $currentElapsed) {
            return false;
        }

        return $candidateMoves < $currentMoves;
    }
}
