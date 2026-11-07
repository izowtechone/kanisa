<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;

class BulkSmsRecipientSelector
{
    public function __construct(private readonly MobilePhoneNumber $phones) {}

    /**
     * @param  array<string, mixed>  $criteria
     * @return Builder<Member>
     */
    public function query(array $criteria): Builder
    {
        return Member::query()
            ->where('sms_opt_in', true)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->when(
                isset($criteria['member_ids']),
                fn (Builder $query) => $query->whereIn('id', $criteria['member_ids']),
            )
            ->when($criteria['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('middle_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('membership_number', 'like', '%'.$search.'%')
                        ->orWhere('envelope_number', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');

                    foreach ($this->phones->searchAlternates($search) as $alternate) {
                        $query->orWhere('phone', 'like', '%'.$alternate.'%');
                    }
                });
            })
            ->when($criteria['department_id'] ?? null, function (Builder $query, int|string $departmentId): void {
                $query->whereHas('groups', fn (Builder $groups) => $groups->where('department_id', $departmentId));
            })
            ->when($criteria['group_id'] ?? null, function (Builder $query, int|string $groupId): void {
                $query->whereHas('groups', fn (Builder $groups) => $groups->whereKey($groupId));
            })
            ->when($criteria['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($criteria['gender'] ?? null, fn (Builder $query, string $gender) => $query->where('gender', $gender))
            ->when($criteria['registered_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('membership_date', '>=', $date))
            ->when($criteria['registered_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('membership_date', '<=', $date));
    }
}
