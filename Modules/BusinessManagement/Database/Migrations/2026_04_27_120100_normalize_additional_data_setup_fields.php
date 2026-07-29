<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\BusinessManagement\Lib\AdditionalDataFieldNormalizer;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('business_settings')
            ->where('settings_type', 'additional_data_setup')
            ->get();

        foreach ($rows as $row) {
            $value = is_string($row->value) ? json_decode($row->value, true) : $row->value;
            if (!is_array($value)) {
                continue;
            }

            $normalized = array_values(array_filter(array_map(
                fn ($field) => is_array($field) ? AdditionalDataFieldNormalizer::normalizeField($field) : null,
                $value
            ), fn ($field) => is_array($field) && !empty($field['title']) && !empty($field['type'])));

            DB::table('business_settings')
                ->where('id', $row->id)
                ->update(['value' => json_encode($normalized)]);
        }
    }

    public function down(): void
    {
        // No-op: original casing of titles cannot be recovered.
    }
};
