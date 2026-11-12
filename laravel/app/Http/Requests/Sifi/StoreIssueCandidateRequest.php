<?php

namespace App\Http\Requests\Sifi;

use Illuminate\Foundation\Http\FormRequest;

final class StoreIssueCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['investigation_id' => ['required', 'uuid']];
    }
}
