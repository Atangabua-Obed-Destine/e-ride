<?php

namespace Modules\ChattingManagement\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class ConversationFileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'file_type' => $this->file_type,
            'file_size' => file_exists(storage_path('app/public/conversation/' . $this->file_name))
                ? number_format(filesize(storage_path('app/public/conversation/' . $this->file_name)) / 1024, 2) . ' KB'
                : '0 KB',
        ];

    }
}
