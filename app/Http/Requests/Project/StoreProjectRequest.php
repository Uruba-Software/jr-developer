<?php

namespace App\Http\Requests\Project;

use App\Enums\OperatingMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:projects,name,NULL,id,user_id,' . $this->user()->id,
            ],
            'description'    => ['nullable', 'string', 'max:1000'],
            'repository_url' => ['nullable', 'url', 'max:500'],
            'local_path'     => ['nullable', 'string', 'max:500'],
            'default_branch' => ['nullable', 'string', 'max:100'],
            'operating_mode' => ['nullable', new Enum(OperatingMode::class)],
        ];
    }
}
