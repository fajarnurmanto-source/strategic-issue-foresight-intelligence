<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

/*
 * PHPStan-focused, documentation-only type inference.
 * Applies to an isolated git-archive snapshot, never the live Laravel runtime.
 * Keep at level 0 until the diff and static-analysis delta are reviewed.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/../laravel/app',
    ])
    ->withTypeCoverageDocblockLevel(0);
