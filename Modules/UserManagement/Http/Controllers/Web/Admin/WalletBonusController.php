<?php

namespace Modules\UserManagement\Http\Controllers\Web\Admin;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\UserManagement\Http\Requests\WalletBonusStoreOrUpdateRequest;
use Modules\UserManagement\Service\Interfaces\WalletBonusServiceInterface;
use App\Exports\StyledReport\ColumnFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WalletBonusController extends BaseController
{
    protected $walletBonusService;

    public function __construct(WalletBonusServiceInterface $walletBonusService)
    {
        parent::__construct($walletBonusService);
        $this->walletBonusService = $walletBonusService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $walletBonuses = $this->walletBonusService->index(criteria: $request?->all(), orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $request['page'] ?? 1);
        return view('usermanagement::admin.wallet-bonus.index', compact('walletBonuses'));
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(WalletBonusStoreOrUpdateRequest $request)
    {
        $this->authorize('user_add');
        $data = $request->validated();
        $data['user_type'] = $request->filled('user_type') ? $request->user_type : ['customer'];
        $criteria = [
            'name' => $data['bonus_title'],
            'description' => $data['short_desc'],
            'bonus_amount' => $data['bonus_amount'],
            'amount_type' => $data['amount_type'],
            'min_add_amount' => $data['minimum_add_amount'],
            'max_bonus_amount' => $data['maximum_bonus'] ?? 0,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_active' => 1,
            'user_type' => $data['user_type']
        ];
        $exists = $this->walletBonusService->findOneBy(criteria: $criteria);
        if ($exists) {
            Toastr::error(WALLET_BONUS_ALREADY_EXISTS['message']);

            return redirect()->back()->withInput();
        }

        $this->walletBonusService->create(data: $criteria);

        Toastr::success(WALLET_BONUS_STORE_200['message']);


        return redirect(route('admin.wallet-bonus.index'));
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $walletBonus = $this->walletBonusService->findOne(id: $id);

        return response()->json(view('usermanagement::admin.wallet-bonus.partial.edit', ['walletBonus' => $walletBonus])->render());
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(WalletBonusStoreOrUpdateRequest $request, $id)
    {
        $this->authorize('user_add');
        $data = $request->validated();
        $data['user_type'] = $request->filled('user_type') ? $request->user_type : ['customer'];
        $criteria = [
            ['id', '!=', $id],
            'name' => $data['bonus_title'],
            'description' => $data['short_desc'],
            'bonus_amount' => $data['bonus_amount'],
            'amount_type' => $data['amount_type'],
            'min_add_amount' => $data['minimum_add_amount'],
            'max_bonus_amount' => $data['maximum_bonus'] ?? 0,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_active' => 1,
            'user_type' => $data['user_type']
        ];
        $exists = $this->walletBonusService->findOneBy(criteria: $criteria);
        if ($exists) {
            return response()->json(['errors' => [WALLET_BONUS_ALREADY_EXISTS]], 200);
        }
        $this->walletBonusService->update(id: $id, data: $criteria);

        return response()->json(WALLET_BONUS_UPDATE_200['message']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): RedirectResponse
    {
        $this->authorize('user_view');
        $this->walletBonusService->delete(id: $id);
        Toastr::success(translate(WALLET_BONUS_DESTROY_200['message']));

        return redirect()->route('admin.wallet-bonus.index');
    }

    public function status(Request $request): JsonResponse
    {
        $this->authorize('user_add');
        $data = $this->walletBonusService->findOneBy(criteria: ['id' => $request->id]);
        $criteria = [
            ['id', '!=', $data['id']],
            'name' => $data['name'],
            'description' => $data['description'],
            'bonus_amount' => $data['bonus_amount'],
            'amount_type' => $data['amount_type'],
            'min_add_amount' => $data['min_add_amount'],
            'max_bonus_amount' => $data['max_bonus_amount'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_active' => 1
        ];
        $exists = $this->walletBonusService->findOneBy(criteria: $criteria);
        if ($exists) {
            return response()->json(['errors' => [WALLET_BONUS_ALREADY_EXISTS]], 200);
        }
        $model = $this->walletBonusService->statusChange(id: $request->id, data: $request->all());

        return response()->json($model);
    }

    public function export(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('user_view');
        $criteria = $request->all();
        $items = $this->walletBonusService->index(criteria: $criteria, orderBy: ['created_at' => 'desc']);
        $rows = $items->map(function ($item) {
            $bonusInfo = 'Minimum Add Amount - ' . set_currency_symbol($item->min_add_amount);
            if ($item->max_bonus_amount > 0) {
                $bonusInfo .= ', Maximum Bonus Amount - ' . set_currency_symbol($item->max_bonus_amount);
            }
            return [
                'Bonus Title'  => $item['name'],
                'Bonus Info'   => $bonusInfo,
                'Bonus Amount' => $item->amount_type === 'percent' ? (float)$item->bonus_amount : (float)$item->bonus_amount,
                'Amount Type'  => ucfirst((string)$item->amount_type),
                'Started On'   => $item->start_date?->format('Y-m-d'),
                'Expires On'   => $item->end_date?->format('Y-m-d'),
                'Status'       => $item['is_active'] ? 'Active' : 'Inactive',
            ];
        });
        $config = styledExportConfig(
            $rows,
            title: 'Wallet Bonus',
            summary: [
                'Total Bonus'    => $rows->count(),
                'Active Bonus'   => $rows->where('Status', 'Active')->count(),
                'Inactive Bonus' => $rows->where('Status', 'Inactive')->count(),
            ],
            filters: [
                'Search' => $request->search ?? translate('N/A'),
            ],
            columnFormats: [
                'Bonus Amount' => ColumnFormat::DECIMAL,
                'Started On'   => ColumnFormat::DATE,
                'Expires On'   => ColumnFormat::DATE,
                'Status'       => ColumnFormat::STATUS,
            ],
            fileName: 'wallet-bonus-' . time() . '.xlsx',
            headings: ['Bonus Title', 'Bonus Info', 'Bonus Amount', 'Amount Type', 'Started On', 'Expires On', 'Status'],
        );
        return exportData($rows, $request['file'], '', $config);
    }
}
