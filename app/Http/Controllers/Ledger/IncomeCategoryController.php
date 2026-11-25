<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Ledger;
use App\Ledger\Models\IncomeCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * paxhitestbed Admin\IncomeCategoryController (and, through side(), the expense one):
 * the list with the add form beside it, rename / take out of use in a modal.
 *
 * E-Ride: a category with records is not deleted (paxhitestbed's FK cascade would
 * delete its incomes); it is taken out of use instead. Its ledger account is set
 * under Mapping settings (Income categories / Expense categories).
 */
class IncomeCategoryController extends LedgerController
{
    protected function side(): string
    {
        return 'income';
    }

    protected function model(): string
    {
        return IncomeCategory::class;
    }

    private function access(): string
    {
        return $this->side().'-category';
    }

    private function table(): string
    {
        return $this->side() === 'income' ? 'income_categories' : 'expense_categories';
    }

    public function index()
    {
        $this->allow($this->access().'-view', $this->access().'-create', $this->access().'-edit', $this->access().'-delete');
        $class = $this->model();

        return view('ledger.account.category_index', [
            'title' => __('ledger_accounts.'.$this->side().'_category'),
            'route' => 'ledger.'.$this->access(),
            'path' => $this->access(),
            'access' => $this->access(),
            'kind' => $this->side(),
            'rows' => $class::withCount($this->side() === 'income' ? 'incomes' : 'expenses')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->allow($this->access().'-create');
        $request->validate(['title' => ['required', 'max:191', Rule::unique($this->table(), 'name')->where('business_id', Ledger::businessId())->whereNull('deleted_at')]],
            [], ['title' => __('ledger_accounts.field_title')]);
        $class = $this->model();
        $class::create(['name' => $request->title, 'slug' => Str::slug($request->title, '-'), 'description' => $request->description,
            'status' => 1, 'created_by' => auth()->id()]);

        return $this->ok(back(), __('ledger_accounts.created'));
    }

    public function update(Request $request, $id)
    {
        $this->allow($this->access().'-edit');
        $class = $this->model();
        $row = $class::findOrFail($id);
        $request->validate(['title' => ['required', 'max:191', Rule::unique($this->table(), 'name')->where('business_id', Ledger::businessId())->whereNull('deleted_at')->ignore($row->id)],
            'status' => 'nullable|in:0,1'], [], ['title' => __('ledger_accounts.field_title')]);
        $row->update(['name' => $request->title, 'slug' => Str::slug($request->title, '-'), 'description' => $request->description ?? $row->description,
            'status' => $request->has('status') ? (int) $request->status : $row->status]);

        return $this->ok(back(), __('ledger_accounts.updated'));
    }

    public function destroy($id)
    {
        $this->allow($this->access().'-delete');
        $class = $this->model();
        $row = $class::findOrFail($id);
        $used = $this->side() === 'income' ? $row->incomes()->exists()
            : ($row->expenses()->exists() || \App\Ledger\Models\SupplierBillLine::where('expense_category_id', $row->id)->exists());
        if ($used) {
            return $this->err(back(), __('ledger_accounts.err_category_used'));
        }
        $row->delete();
        \App\Ledger\Models\DefaultAccountMapping::where('mapping_type', $this->side().'_category')->where('category_key', (string) $row->id)->delete();

        return $this->ok(back(), __('ledger_accounts.deleted'));
    }
}
