<?php

namespace Modules\TransactionManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\TransactionManagement\Service\Interfaces\TransactionServiceInterface;
use Modules\TransactionManagement\Transformers\TransactionResource;


class TransactionController extends Controller
{

    protected $transactionService;

    public function __construct(TransactionServiceInterface $transactionService)
    {
        $this->transactionService = $transactionService;
    }


    public function list(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|integer',
            'offset' => 'required|integer',
            'transaction_type' =>  Rule::in(['debit', 'credit', 'both']),
            'filter' => Rule::in([THIS_WEEK, THIS_MONTH, THIS_YEAR, ALL_TIME, CUSTOM_DATE]),
            'start' => 'required_if:filter,==,custom_date|required_with:end',
            'end' => 'required_if:filter,==,custom_date|required_with:end',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $criteria = ['user_id' => auth('api')->id()];
        if (!is_null($request->type)) {
            $criteria['account'] = $request->type;
        }
        if (!is_null($request->transaction_type) && in_array($request->transaction_type, ['debit', 'credit'])) {

            $criteria[] = [$request->transaction_type, '>', 0];
        }

        if (!is_null($request->filter) && $request->filter != CUSTOM_DATE) {
            $date = getDateRange($request->filter);
        } elseif (!is_null($request->filter)) {
            $date = getDateRange([
                'start' => $request->start,
                'end' => $request->end
            ]);
        }

        $whereBetweenCriteria = [];
        if (!empty($date)) {
            $whereBetweenCriteria = ['created_at' => [$date['start'], $date['end']]];
        }

        $data = $this->transactionService->getBy(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, relations: ['user'], orderBy: ['readable_id'=>'desc'], limit: $request->limit, offset: $request->offset);

        $transactions = TransactionResource::collection($data);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $transactions, limit: $request->limit, offset: $request->offset));
    }

    public function referralEarningHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|integer',
            'offset' => 'required|integer',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $criteria = [
            'user_id' => auth()->user()->id,
            'attribute' => 'referral_earning',
            'account' => 'wallet_balance',
        ];
        $data = $this->transactionService->getBy(criteria: $criteria, relations: ['user'], orderBy: ['readable_id'=>'desc'], limit: $request->limit, offset: $request->offset);
        $transactions = TransactionResource::collection($data);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $transactions, limit: $request->limit, offset: $request->offset));
    }
}
