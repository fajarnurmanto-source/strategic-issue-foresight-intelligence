<?php
namespace App\Contracts;

interface SifiResearchProvider
{
    /**
     * Returns inert, bounded metadata. Search never retrieves linked documents.
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function search(array $context): array;
}
