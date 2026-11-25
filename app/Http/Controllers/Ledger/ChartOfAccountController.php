<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Ledger;
use App\Ledger\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Chart of accounts (plan comptable): paxhitestbed Admin\ChartOfAccountController,
 * same screens and rules, on this business's accounts only (BelongsToBusiness).
 */
class ChartOfAccountController extends LedgerController
{
    const TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense', 'other', 'analytical'];

    const CATEGORIES = ['detail', 'heading', 'total', 'subtotal'];

    /**
     * Display chart of accounts in hierarchical tree view
     */
    public function index(Request $request)
    {
        $this->allow('chart-of-accounts-view');

        $query = ChartOfAccount::with('parent');
        if ($request->has('class') && $request->class) {
            $query->where('class_number', $request->class);
        }
        $data['accounts'] = $query->orderBy('account_code')->get();

        // indentation: one id => parent_id map rather than a query per step up the tree
        $parentOf = ChartOfAccount::pluck('parent_id', 'id')->all();
        foreach ($data['accounts'] as $account) {
            $account->level = $this->levelFromMap($account->parent_id, $parentOf);
        }

        $data['statistics'] = [
            'total_accounts' => ChartOfAccount::count(),
            'active_accounts' => ChartOfAccount::where('is_active', true)->count(),
            'detail_accounts' => ChartOfAccount::where('account_category', 'detail')->count(),
            'total_balance' => ChartOfAccount::where('account_category', 'detail')->sum('current_balance'),
        ];

        return view('ledger.chart-of-accounts.index', $data);
    }

    private function levelFromMap($parentId, array $parentOf, $level = 0, $guard = 0)
    {
        if (! $parentId || $guard > 20) {
            return 0;
        }
        if (array_key_exists($parentId, $parentOf)) {
            return $this->levelFromMap($parentOf[$parentId], $parentOf, $level + 1, $guard + 1) + 1;
        }

        return $level;
    }

    /**
     * Show the form for creating a new account
     */
    public function create()
    {
        $this->allow('chart-of-accounts-create');
        $data['parentAccounts'] = ChartOfAccount::where('is_active', true)->orderBy('account_code')->get();

        return view('ledger.chart-of-accounts.create', $data);
    }

    /**
     * Store a newly created account
     */
    public function store(Request $request)
    {
        $this->allow('chart-of-accounts-create');
        $request->validate($this->rules() + [
            'opening_balance' => 'nullable|numeric|min:0',
        ], [], $this->attributes());

        try {
            DB::beginTransaction();

            ChartOfAccount::create([
                'account_code' => $request->account_code,
                'account_name' => $request->account_name,
                'account_name_fr' => $request->account_name_fr,
                'description' => $request->description,
                'parent_id' => $request->parent_id,
                'class_number' => $request->class_number,
                'account_type' => $request->account_type,
                'account_category' => $request->account_category,
                'opening_balance' => $request->opening_balance ?? 0,
                'current_balance' => $request->opening_balance ?? 0,
                'normal_balance' => $request->normal_balance,
                // the form sends 1 or 0 (paxhitestbed read has('is_active'), always true for a select)
                'is_active' => $request->boolean('is_active', true),
                'display_order' => (int) ($request->display_order ?? 0),
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            return $this->ok(route('ledger.chart-of-accounts.index'), __('ledger_coa.account_created_successfully'));
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->err(back(), __('ledger_coa.msg_created_error').': '.$e->getMessage());
        }
    }

    /**
     * Display the specified account with transaction history
     */
    public function show($id)
    {
        $this->allow('chart-of-accounts-view');
        $data['account'] = ChartOfAccount::with(['parent', 'children'])->findOrFail($id);

        // the last 10 posted lines
        $data['transactions'] = $data['account']->journalEntryLines()
            ->with('journalEntry')
            ->whereHas('journalEntry', function ($q) {
                $q->where('is_posted', true);
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('ledger.chart-of-accounts.show', $data);
    }

    /**
     * Show the form for editing the account
     */
    public function edit($id)
    {
        $this->allow('chart-of-accounts-edit');
        $data['account'] = ChartOfAccount::findOrFail($id);
        $data['parentAccounts'] = ChartOfAccount::where('is_active', true)
            ->where('id', '!=', $id)
            ->orderBy('account_code')
            ->get();

        return view('ledger.chart-of-accounts.edit', $data);
    }

    /**
     * Update the specified account
     */
    public function update(Request $request, $id)
    {
        $this->allow('chart-of-accounts-edit');
        $account = ChartOfAccount::findOrFail($id);

        if ($account->is_system) {
            return $this->err(route('ledger.chart-of-accounts.index'), __('ledger_coa.cannot_edit_system_account'));
        }

        $request->validate($this->rules($account->id), [], $this->attributes());

        try {
            DB::beginTransaction();

            if ($request->parent_id && $this->wouldCreateCircularReference($id, $request->parent_id)) {
                throw new \Exception(__('ledger_coa.circular_reference_error'));
            }

            $account->update([
                'account_code' => $request->account_code,
                'account_name' => $request->account_name,
                'account_name_fr' => $request->account_name_fr,
                'description' => $request->description,
                'parent_id' => $request->parent_id,
                'class_number' => $request->class_number,
                'account_type' => $request->account_type,
                'account_category' => $request->account_category,
                'normal_balance' => $request->normal_balance,
                'is_active' => $request->boolean('is_active', true),
                'display_order' => (int) ($request->display_order ?? 0),
                'updated_by' => Auth::id(),
            ]);

            DB::commit();

            return $this->ok(route('ledger.chart-of-accounts.index'), __('ledger_coa.account_updated_successfully'));
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->err(back(), __('ledger_coa.msg_update_error').': '.$e->getMessage());
        }
    }

    /**
     * Remove the specified account
     */
    public function destroy($id)
    {
        $this->allow('chart-of-accounts-delete');
        $to = route('ledger.chart-of-accounts.index');
        try {
            $account = ChartOfAccount::findOrFail($id);

            if ($account->is_system) {
                return $this->err($to, __('ledger_coa.cannot_delete_system_account'));
            }
            if ($account->children()->count() > 0) {
                return $this->err($to, __('ledger_coa.cannot_delete_account_with_children'));
            }
            if ($account->journalEntryLines()->count() > 0) {
                return $this->err($to, __('ledger_coa.cannot_delete_account_with_transactions'));
            }

            $account->delete();

            return $this->ok($to, __('ledger_coa.account_deleted_successfully'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            return $this->err($to, __('ledger_coa.msg_delete_error').': '.$e->getMessage());
        }
    }

    /**
     * Toggle account status (active/inactive)
     */
    public function toggleStatus($id)
    {
        $this->allow('chart-of-accounts-activate');
        try {
            $account = ChartOfAccount::findOrFail($id);

            if ($account->is_system && $account->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => __('ledger_coa.cannot_deactivate_system_account'),
                ]);
            }

            $account->is_active = ! $account->is_active;
            $account->updated_by = Auth::id();
            $account->save();

            return response()->json([
                'success' => true,
                'message' => __('ledger_coa.account_status_updated'),
                'is_active' => $account->is_active,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => __('ledger_coa.no_data_found')], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ledger_coa.msg_error').': '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get accounts by class (AJAX)
     */
    public function getByClass(Request $request)
    {
        $this->allow('chart-of-accounts-view', 'chart-of-accounts-create', 'chart-of-accounts-edit');
        $query = ChartOfAccount::where('class_number', (int) $request->input('class_number'))
            ->where('is_active', true)
            ->orderBy('account_code');

        if ($request->input('postable_only', false)) {
            $query->where('account_category', 'detail');
        }

        return response()->json($query->get(['id', 'account_code', 'account_name', 'account_name_fr']));
    }

    /* ------------------------------------------------------------------ */

    /**
     * The form's rules (paxhitestbed's), with the account code unique within
     * this business and the parent one of this business's accounts. A deleted
     * account keeps its code, as in paxhitestbed (and the database index).
     */
    private function rules($ignoreId = null): array
    {
        $bid = Ledger::businessId();
        $unique = Rule::unique('chart_of_accounts', 'account_code')->where('business_id', $bid);
        if ($ignoreId) {
            $unique->ignore($ignoreId);
        }

        return [
            'account_code' => ['required', 'string', 'max:20', $unique],
            'account_name' => 'required|string|max:255',
            'account_name_fr' => 'nullable|string|max:255',
            'class_number' => 'required|integer|between:1,9',
            'account_type' => ['required', Rule::in(self::TYPES)],
            'account_category' => ['required', Rule::in(self::CATEGORIES)],
            'normal_balance' => 'required|in:debit,credit',
            'parent_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('business_id', $bid)->whereNull('deleted_at')],
            'description' => 'nullable|string',
            'display_order' => 'nullable|integer',
        ];
    }

    private function attributes(): array
    {
        return [
            'account_code' => __('ledger_coa.account_code'),
            'account_name' => __('ledger_coa.account_name'),
            'account_name_fr' => __('ledger_coa.account_name').' '.__('ledger_coa.in_french'),
            'class_number' => __('ledger_coa.classe'),
            'account_type' => __('ledger_coa.account_type'),
            'account_category' => __('ledger_coa.account_category'),
            'normal_balance' => __('ledger_coa.normal_balance'),
            'parent_id' => __('ledger_coa.parent_account'),
            'opening_balance' => __('ledger_coa.opening_balance'),
            'display_order' => __('ledger_coa.display_order'),
        ];
    }

    /**
     * Check if setting parent would create circular reference
     */
    private function wouldCreateCircularReference($accountId, $parentId)
    {
        if (! $parentId) {
            return false;
        }
        $parent = ChartOfAccount::find($parentId);
        $guard = 0;
        while ($parent && $guard++ < 50) {
            if ($parent->id == $accountId) {
                return true;
            }
            $parent = $parent->parent;
        }

        return false;
    }
}
