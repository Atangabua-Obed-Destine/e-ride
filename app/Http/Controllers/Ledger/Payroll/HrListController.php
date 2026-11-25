<?php

namespace App\Http\Controllers\Ledger\Payroll;

use App\Http\Controllers\Ledger\LedgerController;
use App\Ledger\Ledger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * paxhitestbed's DesignationController, DepartmentController, AllowanceTypeController and
 * DeductionTypeController: the same small screen four times (the add form beside the list, edit in
 * a modal, delete with a confirmation). One base here, one view (ledger/payroll/lists/index).
 * A row a staff member or a payroll still uses is taken out of use rather than deleted.
 */
abstract class HrListController extends LedgerController
{
    /** route and permission stem, e.g. 'designation' */
    abstract protected function path(): string;

    abstract protected function model(): string;

    /** the title on the page (ledger_payroll key) */
    abstract protected function titleKey(): string;

    /** Columns besides title and status: 'description', 'is_taxable'. */
    protected function columns(): array
    {
        return ['description'];
    }

    /** Is this row still used somewhere (then it is switched off instead of deleted)? */
    protected function inUse($row): bool
    {
        return false;
    }

    protected function base(): array
    {
        return ['title' => __('ledger_payroll.'.$this->titleKey()), 'route' => 'ledger.'.$this->path(), 'access' => $this->path(),
            'columns' => $this->columns()];
    }

    public function index()
    {
        $this->allow($this->path().'-view', $this->path().'-create', $this->path().'-edit', $this->path().'-delete');

        return view('ledger.payroll.lists.index', $this->base() + ['rows' => ($this->model())::orderBy('title')->get()]);
    }

    public function store(Request $request)
    {
        $this->allow($this->path().'-create');
        $this->check($request);
        $class = $this->model();
        $row = new $class();
        $this->fill($row, $request, true);
        $row->save();

        return $this->ok(route('ledger.'.$this->path().'.index'), __('ledger_payroll.created'));
    }

    public function update(Request $request, $id)
    {
        $this->allow($this->path().'-edit');
        $row = ($this->model())::findOrFail($id);
        $this->check($request, $row->id);
        $this->fill($row, $request, false);
        $row->save();

        return $this->ok(route('ledger.'.$this->path().'.index'), __('ledger_payroll.updated'));
    }

    public function destroy($id)
    {
        $this->allow($this->path().'-delete');
        $row = ($this->model())::findOrFail($id);
        if ($this->inUse($row)) {
            $row->status = 0;
            $row->save();

            return $this->ok(back(), __('ledger_payroll.switched_off_in_use'));
        }
        $row->delete();

        return $this->ok(back(), __('ledger_payroll.deleted'));
    }

    protected function check(Request $request, $ignore = null): void
    {
        $table = (new ($this->model()))->getTable();
        $request->validate([
            'title' => ['required', 'string', 'max:191', Rule::unique($table, 'title')->where('business_id', Ledger::businessId())->ignore($ignore)],
            'description' => 'nullable|string|max:2000',
            'is_taxable' => 'nullable|boolean',
            'status' => 'nullable|boolean',
        ], [], ['title' => __('ledger_payroll.field_title')]);
    }

    protected function fill($row, Request $request, bool $new): void
    {
        $row->title = trim($request->title);
        if (in_array('slug', $row->getFillable(), true)) {
            $row->slug = Str::slug($request->title, '-');
        }
        if (in_array('description', $this->columns(), true) || $request->has('description')) {
            $row->description = $request->description;
        }
        if (in_array('is_taxable', $this->columns(), true)) {
            $row->is_taxable = $request->boolean('is_taxable');
        }
        $row->status = $request->has('status') ? $request->boolean('status') : ($new ? 1 : $row->status);
    }
}
