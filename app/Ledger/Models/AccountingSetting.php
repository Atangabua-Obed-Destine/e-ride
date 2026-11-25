<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/** Per-business accounting settings (key / value). */
class AccountingSetting extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'setting_key', 'setting_value', 'setting_type', 'category', 'description'];

    public static function getValue(string $key, $default = null)
    {
        $row = static::where('setting_key', $key)->first();
        if (! $row) {
            return $default;
        }
        switch ($row->setting_type) {
            case 'boolean': return (bool) $row->setting_value;
            case 'integer': return (int) $row->setting_value;
            case 'json': return json_decode($row->setting_value, true);
            default: return $row->setting_value;
        }
    }

    public static function setValue(string $key, $value, string $type = 'string', string $category = 'general'): void
    {
        static::updateOrCreate(['setting_key' => $key], [
            'setting_value' => $type === 'json' ? json_encode($value) : (is_bool($value) ? (int) $value : $value),
            'setting_type' => $type,
            'category' => $category,
        ]);
    }
}
