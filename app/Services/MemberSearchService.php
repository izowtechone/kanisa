<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class MemberSearchService
{
    public function __construct(private readonly MobilePhoneNumber $phones) {}

    /**
     * @return Collection<int, Member>
     */
    public function searchActive(string $search, int $limit = 10, bool $includeCommunity = false): Collection
    {
        $query = Member::query()
            ->select([
                'id',
                'membership_number',
                'envelope_number',
                'first_name',
                'middle_name',
                'last_name',
                'phone',
                'community_id',
            ])
            ->where('status', 'active');

        foreach (preg_split('/\s+/', trim($search)) ?: [] as $term) {
            $like = '%'.$term.'%';
            $phoneDigits = preg_replace('/\D+/', '', $term) ?? '';
            $phoneAlternates = $this->phones->searchAlternates($term);

            $query->where(function (Builder $query) use ($like, $phoneDigits, $phoneAlternates): void {
                $query->where('first_name', 'like', $like)
                    ->orWhere('middle_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('membership_number', 'like', $like)
                    ->orWhere('envelope_number', 'like', $like)
                    ->orWhere('phone', 'like', $like);

                if ($phoneDigits !== '') {
                    $query->orWhere('phone', 'like', '%'.$phoneDigits.'%');
                }

                foreach ($phoneAlternates as $alternate) {
                    $query->orWhere('phone', 'like', '%'.$alternate.'%');
                }
            });
        }

        if ($includeCommunity) {
            $query->with('community:id,code,name,status');
        }

        return $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }
}
