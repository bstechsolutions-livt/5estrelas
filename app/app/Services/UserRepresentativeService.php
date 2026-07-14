<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserRepresentative;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserRepresentativeService
{
    /**
     * IDs de usuários que $representative representa agora (no escopo informado).
     *
     * @return list<int>
     */
    public function representedUserIds(User $representative, string $scope = UserRepresentative::SCOPE_FINANCEIRO_APROVACAO, ?Carbon $on = null): array
    {
        $on = $on ?? now();

        return UserRepresentative::query()
            ->where('representative_id', $representative->id)
            ->where('is_active', true)
            ->whereDate('starts_at', '<=', $on->toDateString())
            ->where(function ($q) use ($on) {
                $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', $on->toDateString());
            })
            ->get()
            ->filter(fn (UserRepresentative $row) => $row->coversScope($scope))
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function isActiveRepresentative(
        User $representative,
        User|int $user,
        string $scope = UserRepresentative::SCOPE_FINANCEIRO_APROVACAO,
        ?Carbon $on = null,
    ): bool {
        $userId = $user instanceof User ? $user->id : (int) $user;

        return in_array($userId, $this->representedUserIds($representative, $scope, $on), true);
    }

    /**
     * @param list<array{representative_id:int, starts_at:string, ends_at?:?string, reason?:?string, scopes?:?array}> $rows
     */
    public function syncForUser(User $user, array $rows, ?User $actor = null): void
    {
        DB::transaction(function () use ($user, $rows, $actor) {
            $keepIds = [];

            foreach ($rows as $row) {
                $repId = (int) ($row['representative_id'] ?? 0);
                if ($repId <= 0 || $repId === $user->id) {
                    continue;
                }

                $startsAt = Carbon::parse($row['starts_at'])->toDateString();
                $endsAt = ! empty($row['ends_at']) ? Carbon::parse($row['ends_at'])->toDateString() : null;
                $scopes = $row['scopes'] ?? [UserRepresentative::SCOPE_FINANCEIRO_APROVACAO];
                if (! is_array($scopes) || $scopes === []) {
                    $scopes = [UserRepresentative::SCOPE_FINANCEIRO_APROVACAO];
                }

                $existingId = isset($row['id']) ? (int) $row['id'] : null;
                $model = $existingId
                    ? UserRepresentative::query()->where('user_id', $user->id)->whereKey($existingId)->first()
                    : null;

                if (! $model) {
                    $model = new UserRepresentative(['user_id' => $user->id]);
                }

                $model->fill([
                    'representative_id' => $repId,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'scopes' => array_values($scopes),
                    'reason' => isset($row['reason']) ? trim((string) $row['reason']) ?: null : null,
                    'is_active' => (bool) ($row['is_active'] ?? true),
                    'created_by' => $model->exists ? $model->created_by : ($actor?->id),
                ]);
                $model->save();
                $keepIds[] = $model->id;
            }

            UserRepresentative::query()
                ->where('user_id', $user->id)
                ->when($keepIds !== [], fn ($q) => $q->whereNotIn('id', $keepIds))
                ->delete();
        });
    }

    public function payloadForUser(User $user): Collection
    {
        return UserRepresentative::query()
            ->where('user_id', $user->id)
            ->with('representative:id,name,email')
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (UserRepresentative $r) => [
                'id' => $r->id,
                'representative_id' => $r->representative_id,
                'representative_name' => $r->representative?->name,
                'starts_at' => $r->starts_at?->toDateString(),
                'ends_at' => $r->ends_at?->toDateString(),
                'scopes' => $r->scopes ?? [UserRepresentative::SCOPE_FINANCEIRO_APROVACAO],
                'reason' => $r->reason,
                'is_active' => $r->is_active,
                'currently_active' => $r->isCurrentlyActive(),
            ]);
    }
}
