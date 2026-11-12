<?php

namespace App\Http\Requests\Sifi;

use App\Models\SifiIssueCandidate;
use Illuminate\Foundation\Http\FormRequest;

final class RunIntelligenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $candidate = $this->route('candidate');

        return $candidate instanceof SifiIssueCandidate && $this->user()?->can('update', $candidate) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['revision' => ['required', 'integer', 'min:1', 'max:1000000']];
    }
}
