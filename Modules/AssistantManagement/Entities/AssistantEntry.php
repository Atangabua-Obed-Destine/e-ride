<?php

namespace Modules\AssistantManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * One fact of the knowledge pack. With a help_key it also shows as an info sheet
 * on that app screen; with a tip_action, as a "Did you know?" card on Home.
 */
class AssistantEntry extends Model
{
    use HasUuid;

    protected $fillable = ['help_key', 'service', 'tip_action', 'tip_label_en', 'tip_label_fr', 'search_words',
        'title_en', 'title_fr', 'body_en', 'body_fr', 'is_active', 'sort'];

    protected $casts = ['is_active' => 'boolean', 'sort' => 'integer'];

    public function title(string $lang): string
    {
        return $lang === 'fr' ? $this->title_fr : $this->title_en;
    }

    public function body(string $lang): string
    {
        return $lang === 'fr' ? $this->body_fr : $this->body_en;
    }
}
