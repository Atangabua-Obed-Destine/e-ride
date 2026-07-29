<?php

namespace Modules\UserManagement\Http\Controllers\Web\Admin\Customer;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\TransactionManagement\Service\Interfaces\TransactionServiceInterface;
use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\UserManagement\Http\Requests\CustomerWalletStoreOrUpdateRequest;
use Modules\UserManagement\Service\Interfaces\CustomerAccountServiceInterface;
use Modules\UserManagement\Service\Interfaces\CustomerServiceInterface;
use App\Exports\StyledReport\ColumnFormat;

class CustomerWalletController extends BaseController
{
    use AuthorizesRequests, TransactionTrait;

    protected $customerAccountService;
    protected $transactionService;
    protected $customerService;

    public function __construct(CustomerAccountServiceInterface $customerAccountService, TransactionServiceInterface $transactionService, CustomerServiceInterface $customerService)
    {
        parent::__construct($customerAccountService);
        $this->customerAccountService = $customerAccountService;
        $this->transactionService = $transactionService;
        $this->customerService = $customerService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('user_view');
        $request?->validate([
            'data' => Rule::in([ALL_TIME,THIS_WEEK,LAST_WEEK,THIS_MONTH,LAST_MONTH,THIS_YEAR,CUSTOM_DATE]),
            'start' => 'required_if:data,custom_date',
            'end' => 'required_if:data,custom_date',
        ]);
        $transactions = $this->transactionService->customerWalletTransaction(data: $request?->all(), orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset:$request['page']??1);
        return view('usermanagement::admin.customer.wallet.index', compact('transactions'));
    }

    public function store(CustomerWalletStoreOrUpdateRequest $request)
    {
        $this->authorize('user_add');
        DB::beginTransaction();
        if ($request['customer_id'] == "all") {
            $relations = [
                'user' => [
                    ['user_type', '=', CUSTOMER],
                    ['is_active', '=', true],
                ],
            ];
            $whereHasRelations = [
                'user' => ['user_type' => 'CUSTOMER', 'is_active' => true]
            ];
            $customerAccountIds = $this->customerAccountService->getBy(whereHasRelations: $whereHasRelations, relations: $relations)->pluck('id')->toArray();
            $this->customerAccountService->updateManyWithIncrement(ids: $customerAccountIds, column: 'wallet_balance', amount: $request['amount']);
            $customerAccounts = $this->customerAccountService->getBy(whereHasRelations: $whereHasRelations, relations: $relations);
            foreach ($customerAccounts as $customerAccount) {
                $this->addWalletFundDigitally(customer: $customerAccount, data: $request->validated(), attribute: 'fund_by_admin');
                $push = getNotification('fund_added_by_admin');
                sendDeviceNotification(fcm_token: $customerAccount?->user?->fcm_token,
                    title: translate(key: $push['title'], locale: $customerAccount?->user?->current_language_key),
                    description: textVariableDataFormat(value: $push['description'],walletAmount: set_currency_symbol($request['amount']), locale: $customerAccount?->user?->current_language_key),
                    status: $push['status'],
                    ride_request_id: $customerAccount?->user?->id,
                    notification_type: 'fund',
                    action: $push['action'],
                    user_id: $customerAccount?->user?->id
                );
            }
        } else {
            $customerAccount = $this->customerAccountService->findOneBy(criteria: ['user_id' => $request['customer_id']]);
            $this->customerAccountService->update(id: $customerAccount->id, data:[
                'wallet_balance' => $customerAccount->wallet_balance + $request['amount']
            ]);
            $customerAccount = $this->customerAccountService->findOneBy(criteria: ['user_id' => $request['customer_id']],relations: ['user']);
            $this->addWalletFundDigitally(customer: $customerAccount, data: $request->validated(), attribute: 'fund_by_admin');
            $push = getNotification('fund_added_by_admin');
            sendDeviceNotification(fcm_token: $customerAccount->user->fcm_token,
                title: translate(key: $push['title'], locale: $customerAccount?->user?->current_language_key),
                description: textVariableDataFormat(value: $push['description'], walletAmount: set_currency_symbol($request['amount']), locale: $customerAccount?->user?->current_language_key),
                status: $push['status'],
                ride_request_id: $customerAccount->user->id,
                notification_type: 'fund',
                action: $push['action'],
                user_id: $customerAccount->user->id
            );
        }
        Toastr::success(CUSTOMER_FUND_STORE_200['message']);
        DB::commit();
        return redirect()->back();
    }

    public function export(Request $request)
    {
        $this->authorize('user_export');
        $transactions = $this->transactionService->customerWalletTransaction($request?->all(), orderBy: ['created_at' => 'desc']);
        $exportData = $this->customerAccountService->export($transactions);

        $customerName = translate('All');
        if ($request->user_id && $request->user_id === 'all') {
            $customerName = translate('All Customer');
        } elseif ($request->user_id) {
            $user = $this->customerService->findOne(id: $request->user_id);
            if ($user) {
                $customerName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            }
        }

        $config = styledExportConfig(
            $exportData,
            title: 'Customer Wallet Transactions',
            summary: ['Total Records' => $exportData->count()],
            filters: [
                'Customer' => $customerName,
                'Range'    => $request->data   ?? translate('All Time'),
                'From'     => $request->start  ?? translate('N/A'),
                'To'       => $request->end    ?? translate('N/A'),
                'Search'   => $request->search ?? translate('N/A'),
            ],
            columnFormats: [
                'Debit'            => ColumnFormat::CURRENCY,
                'Credit'           => ColumnFormat::CURRENCY,
                'Balance'          => ColumnFormat::CURRENCY,
                'Added Bonus'      => ColumnFormat::CURRENCY,
                'Transaction Date' => ColumnFormat::DATETIME,
            ],
            fileName: 'customer-wallet-' . time() . '.xlsx',
            headings: ['Transaction Id', 'Reference', 'Transaction Date', 'Transaction To', 'Debit', 'Credit', 'Balance', 'Added Bonus'],
        );
        return exportData($exportData, $request['file'], 'usermanagement::admin.customer.wallet.print', $config);
    }
}
