<?php

namespace Modules\UserManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\UserManagement\Service\IdVerificationService;

/** Customer app: E-Ride ID Verified (status, and sending an ID card or passport with a selfie). */
class IdVerificationController extends Controller
{
    public function __construct(protected IdVerificationService $ids)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_200, $this->ids->present($this->ids->current($request->user()))));
    }

    /** multipart: doc_type, doc_number, full_name, expires_on, front, back, selfie */
    public function submit(Request $request): JsonResponse
    {
        try {
            $record = $this->ids->submit($request->user(), $request->only(['doc_type', 'doc_number', 'full_name', 'expires_on']) + [
                'front' => $request->file('front'), 'back' => $request->file('back'), 'selfie' => $request->file('selfie'),
            ]);
        } catch (ValidationException $e) {
            $errors = [];
            foreach ($e->errors() as $field => $messages) {
                $errors[] = ['error_code' => $field, 'message' => $messages[0]];
            }
            return response()->json(responseFormatter(DEFAULT_400, errors: $errors), 403);
        }
        return response()->json(responseFormatter(DEFAULT_200, $this->ids->present($record)));
    }
}
