<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** paxhitestbed PrintSetting, for the pay slip (slug 'pay-slip'): its header, body, footer, logos and width. */
class PrintSetting extends Model
{
    use BelongsToBusiness;

    protected $table = 'print_settings';

    protected $fillable = ['business_id', 'slug', 'title', 'header_left', 'header_center', 'header_right', 'body', 'footer_left',
        'footer_center', 'footer_right', 'logo_left', 'logo_right', 'background', 'width', 'prefix', 'status'];

    /** The pay slip's settings: made with plain defaults the first time, so a payslip always prints. */
    public static function payslip(): self
    {
        return static::firstOrCreate(['slug' => 'pay-slip'], [
            'title' => __('ledger_payroll.pay_slip'),
            'header_left' => \App\Ledger\ERide\Books::current()['name'] ?? null,
            'width' => '800px',
            'prefix' => 'PS',
            'status' => 1,
        ]);
    }

    public static function fileUrl(?string $file): ?string
    {
        return $file ? Storage::disk('public')->url($file) : null;
    }
}
