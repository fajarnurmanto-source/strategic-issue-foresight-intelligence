<?php
namespace App\Contracts;

interface SifiResearchProvider
{
    /** Returns inert, bounded metadata. Search never retrieves linked documents. */
    public function search(array $context): array;
}
