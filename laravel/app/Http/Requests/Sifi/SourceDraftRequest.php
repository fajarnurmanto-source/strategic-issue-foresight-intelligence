<?php

namespace App\Http\Requests\Sifi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SourceDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:180'],
            'source_type' => ['required', Rule::in(['OFFICIAL', 'MEDIA', 'RESEARCH', 'OPEN_DATA', 'OTHER'])],
            'homepage_url' => ['nullable', 'url:http,https', 'max:500'],
            'source_geography' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'revision' => [$this->isMethod('patch') ? 'required' : 'prohibited', 'integer', 'min:1'],
            // Deliberately refuse rights/collector authority from client payload.
            'rights_state' => ['prohibited'], 'collector_state' => ['prohibited'],
        ];
    }
}
