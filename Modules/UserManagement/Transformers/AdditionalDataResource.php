<?php

namespace Modules\UserManagement\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\UserManagement\Lib\AdditionalDataForm;

class AdditionalDataResource extends JsonResource
{
    protected string $userType;

    public function __construct($resource, string $userType)
    {
        parent::__construct($resource);

        $this->userType = $userType;
    }

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request)
    {
        $additionalData = is_array($this->additional_data) ? $this->additional_data : [];
        $data = [];

        foreach (AdditionalDataForm::fields($this->userType) as $field) {
            $title = $field['title'] ?? null;
            $value = is_string($title) && array_key_exists($title, $additionalData)
                ? $additionalData[$title]
                : null;
            $value = is_array($value) ? $value : (is_null($value) ? null : (array) $value);

            if ($this->isEmptyValue($value)) {
                continue;
            }

            $data[] = array_merge($field, [
                'value' => $value,
            ]);
        }

        return $data;
    }

    private function isEmptyValue(?array $value): bool
    {
        if ($value === null || $value === []) {
            return true;
        }

        return count(array_filter($value, fn ($item) => $item !== null && $item !== '')) === 0;
    }
}
