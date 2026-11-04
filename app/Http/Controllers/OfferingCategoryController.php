<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveOfferingCategoryRequest;
use App\Models\FinanceCategory;
use App\Models\FinancialImport;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OfferingCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission($request, 'view offering categories');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));

        $categories = FinanceCategory::query()
            ->where('type', 'offering')
            ->withCount('transactions')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%')
                        ->orWhere('name_en', 'like', '%'.$search.'%')
                        ->orWhere('name_sw', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->when(
                ($filters['status'] ?? '') !== '',
                fn ($query) => $query->where('is_active', $filters['status'] === 'active'),
            )
            ->orderByDesc('is_active')
            ->orderBy('name_en')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('finance.offering-categories.index', [
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'status' => $filters['status'] ?? '',
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizePermission($request, 'create offering categories');

        return view('finance.offering-categories.form', [
            'category' => new FinanceCategory,
        ]);
    }

    public function store(SaveOfferingCategoryRequest $request): RedirectResponse
    {
        FinanceCategory::query()->create([
            ...$request->validated(),
            'code' => $request->validated('code') ?? $this->generateCode($request->validated('name_en')),
            'type' => 'offering',
            'name' => $request->validated('name_en'),
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('finance.offering-categories.index')
            ->with('success', __('finance.offering_category_created'));
    }

    public function edit(Request $request, FinanceCategory $category): View
    {
        $this->authorizePermission($request, 'edit offering categories');
        abort_unless($category->type === 'offering', 404);

        return view('finance.offering-categories.form', compact('category'));
    }

    public function update(
        SaveOfferingCategoryRequest $request,
        FinanceCategory $category,
    ): RedirectResponse {
        abort_unless($category->type === 'offering', 404);

        $category->update([
            ...$request->validated(),
            'code' => $request->validated('code') ?? $category->code,
            'name' => $request->validated('name_en'),
        ]);

        return redirect()
            ->route('finance.offering-categories.index')
            ->with('success', __('finance.offering_category_updated'));
    }

    public function toggle(Request $request, FinanceCategory $category): RedirectResponse
    {
        $this->authorizePermission($request, 'toggle offering categories');
        abort_unless($category->type === 'offering', 404);

        $category->update(['is_active' => ! $category->is_active]);

        return back()->with('success', __('finance.offering_category_status_updated'));
    }

    public function destroy(Request $request, FinanceCategory $category): RedirectResponse
    {
        $this->authorizePermission($request, 'delete offering categories');
        abort_unless($category->type === 'offering', 404);

        if ($category->transactions()->exists()
            || FinancialImport::query()->where('category_id', $category->id)->exists()) {
            return back()->with('error', __('finance.offering_category_delete_blocked'));
        }

        try {
            $category->delete();
        } catch (QueryException) {
            return back()->with('error', __('finance.offering_category_delete_blocked'));
        }

        return redirect()
            ->route('finance.offering-categories.index')
            ->with('success', __('finance.offering_category_deleted'));
    }

    private function generateCode(string $name): string
    {
        $base = 'OFFERING-'.Str::upper(Str::limit(Str::slug($name), 28, ''));
        $code = $base;
        $suffix = 2;

        while (FinanceCategory::query()->where('type', 'offering')->where('code', $code)->exists()) {
            $code = $base.'-'.$suffix++;
        }

        return $code;
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless(
            $request->user()?->can($permission)
                || $request->user()?->can('manage finance categories'),
            403,
        );
    }
}
