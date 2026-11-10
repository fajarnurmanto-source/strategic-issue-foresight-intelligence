<?php
namespace App\Services\Sifi;
use App\Contracts\SifiResearchProvider;

final class CatalogMultiResearchProvider implements SifiResearchProvider
{
    public const ID='SYNTHETIC_CATALOG';
    public function search(array $context):array
    {
        $entries=array_values(array_filter(app(DiscoveryCatalog::class)->entries(),fn($e)=>($e['synthetic']??null)===true&&MultiSourceFixtureSupport::matches($e,$context)));
        return MultiSourceFixtureSupport::packet(self::ID,$context,$entries);
    }
}
