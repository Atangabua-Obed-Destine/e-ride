<?php

namespace Modules\TransactionManagement\Http\Controllers\Web\Admin\Transaction;

use App\Http\Controllers\BaseController;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\TransactionManagement\Service\Interfaces\TransactionServiceInterface;
use App\Exports\StyledReport\ColumnFormat;

class TransactionController extends BaseController
{
    use AuthorizesRequests;

    protected $transactionService;

    public function __construct(TransactionServiceInterface $transactionService)
    {
        parent::__construct($transactionService);
        $this->transactionService = $transactionService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('transaction_view');
        $transactions = $this->transactionService->index(criteria: $request?->all(), relations: ['user'], orderBy : ['created_at' => 'desc'], limit: paginationLimit(), offset:$request['page']??1);
        return view('transactionmanagement::admin.transaction.index', compact('transactions'));
    }

    public function export(Request $request)
    {
        $this->authorize('transaction_export');
        $exportData = $this->transactionService->export(criteria: $request->all());
        $config = styledExportConfig(
            $exportData,
            title: 'Transaction List',
            summary: ['Total Records' => $exportData->count()],
            filters: [
                'Search' => $request->search ?? translate('N/A'),
            ],
            columnFormats: [
                'Credit'           => ColumnFormat::CURRENCY,
                'Debit'            => ColumnFormat::CURRENCY,
                'Balance'          => ColumnFormat::CURRENCY,
                'Added Bonus'      => ColumnFormat::CURRENCY,
                'Transaction Date' => ColumnFormat::DATETIME,
            ],
            fileName: 'transactions-' . time() . '.xlsx',
            headings: ['Transaction Id', 'Reference', 'Type', 'Transaction Date', 'Transaction To', 'Credit', 'Debit', 'Balance'],
        );
        return exportData($exportData, $request['file'],'', $config);
    }
}
