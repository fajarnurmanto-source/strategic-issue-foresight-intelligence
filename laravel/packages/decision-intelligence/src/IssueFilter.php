<?php

namespace DecisionIntelligence;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class IssueFilter
{
    public static function validate(array $data): array
    {
        // No DB changes. Run this on validated request content, then persist via existing domain model.
        $rules = [
            'query' => ['required', 'string', 'max:600'],
            'period.start' => ['required', 'date_format:Y-m-d'],
            'period.end_exclusive' => ['required', 'date_format:Y-m-d', 'after:period.start'],
            'region' => ['required', 'string', 'max:80'],
            'media' => ['required', 'array', 'min:1'],
            'media.*' => ['required', Rule::in(['news', 'video', 'audio', 'image', 'document']), 'distinct'],
            'sentiment' => ['required', Rule::in(['positive', 'negative', 'neutral', 'mixed', 'all', 'unknown'])],
        ];

        return Validator::make($data, $rules)->validate();
    }
}
