<?php

namespace Modules\UserManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\UserManagement\Entities\IdVerification;
use Modules\UserManagement\Service\IdVerificationService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** E-Ride admin: the ID verification queue (document and selfie side by side), and how IDs are checked. */
class IdVerificationAdminController extends Controller
{
    public function __construct(protected IdVerificationService $ids)
    {
    }

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), ['pending', 'verified', 'refused'], true) ? $request->query('tab') : 'pending';
        $records = IdVerification::with('user')->where('status', $tab)->orderBy($tab === 'pending' ? 'created_at' : 'reviewed_at', $tab === 'pending' ? 'asc' : 'desc')
            ->paginate(20)->withQueryString();
        $counts = IdVerification::whereIn('status', ['pending', 'verified', 'refused'])->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $settings = IdVerificationService::settings();
        $autoAvailable = IdVerificationService::autoAvailable();
        $current = $request->filled('id') ? IdVerification::with('user')->find($request->query('id')) : $records->first();
        return view('usermanagement::admin.id-verification.index', compact('tab', 'records', 'counts', 'settings', 'autoAvailable', 'current'));
    }

    public function file(IdVerification $record, string $side): StreamedResponse
    {
        $path = $this->ids->file($record, $side);
        abort_unless($path, 404);
        return Storage::disk(IdVerificationService::DISK)->response($path, null, ['Cache-Control' => 'private, no-store']);
    }

    public function approve(IdVerification $record): RedirectResponse
    {
        try {
            $this->ids->approve($record, auth()->id());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        Toastr::success(translate('ID verified. The person was told.'));
        return redirect()->route('admin.id-verification.index');
    }

    public function refuse(Request $request, IdVerification $record): RedirectResponse
    {
        try {
            $this->ids->refuse($record, auth()->id(), (string)$request->input('reason', ''));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        Toastr::success(translate('Refused. The person was told why.'));
        return redirect()->route('admin.id-verification.index');
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate(['method' => 'required|in:staff,auto', 'threshold' => 'required|integer|min:70|max:99']);
        if ($data['method'] === 'auto' && !IdVerificationService::autoAvailable()) {
            return back()->withErrors(['method' => translate('Automatic face match needs the face verification API (Configurations, 3rd party) set up and on.')]);
        }
        updateBusinessSetting(keyName: 'id_verification_method', settingType: IdVerificationService::TYPE, value: $data['method']);
        updateBusinessSetting(keyName: 'id_verification_threshold', settingType: IdVerificationService::TYPE, value: (string)$data['threshold']);
        Toastr::success(translate('Saved'));
        return back();
    }
}
