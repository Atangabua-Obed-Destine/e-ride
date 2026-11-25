<?php

namespace App\Http\Controllers\Ledger\Payroll;

use App\Http\Controllers\Ledger\LedgerController;
use App\Ledger\Ledger;
use App\Ledger\Models\PrintSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * paxhitestbed Admin\PaySlipSettingController: the pay slip's print setting (title, header left /
 * centre / right, body, footer left / centre / right, logos, background, width, prefix). Pictures
 * are kept on the public disk under ledger/pay-slip/<books>.
 */
class PaySlipSettingController extends LedgerController
{
    public function index()
    {
        $this->allow('pay-slip-setting-view');

        return view('ledger.payroll.pay-slip-setting.index', ['title' => __('ledger_payroll.pay_slip_settings'), 'row' => PrintSetting::payslip()]);
    }

    public function store(Request $request)
    {
        $this->allow('pay-slip-setting-view');
        $request->validate([
            'title' => 'nullable|string|max:191',
            'header_left' => 'nullable|string|max:2000', 'header_center' => 'nullable|string|max:2000', 'header_right' => 'nullable|string|max:2000',
            'body' => 'nullable|string|max:10000',
            'footer_left' => 'nullable|string|max:2000', 'footer_center' => 'nullable|string|max:2000', 'footer_right' => 'nullable|string|max:2000',
            'logo_left' => 'nullable|image|max:2048', 'logo_right' => 'nullable|image|max:2048', 'background' => 'nullable|image|max:4096',
            'width' => 'nullable|string|max:20', 'prefix' => 'nullable|string|max:20',
        ]);
        $row = PrintSetting::payslip();
        $row->fill($request->only(['title', 'header_left', 'header_center', 'header_right', 'body', 'footer_left', 'footer_center', 'footer_right', 'width', 'prefix']));
        foreach (['logo_left', 'logo_right', 'background'] as $field) {
            if ($request->hasFile($field)) {
                if ($row->{$field}) {
                    Storage::disk('public')->delete($row->{$field});
                }
                $row->{$field} = $request->file($field)->store('ledger/pay-slip/'.Ledger::businessId(), 'public');
            }
        }
        $row->save();

        return $this->ok(back(), __('ledger_payroll.updated'));
    }
}
