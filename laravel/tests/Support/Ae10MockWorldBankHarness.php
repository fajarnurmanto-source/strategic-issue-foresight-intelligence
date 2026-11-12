<?php

use App\Services\Sifi\WorldBankGdpSourceAdapter;
use App\Services\Sifi\WorldBankOneShotAcquisition;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;

/** Test-only harness; never installed in application app/ or operational bindings. */
class Ae10MockWorldBankHarness extends WorldBankOneShotAcquisition
{
    protected function supportsLocalMock(): bool
    {
        return app()->environment('testing');
    }

    protected function request(string $endpoint, array $options): Response
    {
        if (! app()->environment('testing') || $endpoint !== WorldBankGdpSourceAdapter::ENDPOINT) {
            throw new RuntimeException('LOCAL_TEST_REQUIRED');
        }
        $options['protocols'] = ['http'];

        return (new Factory)->withOptions($options)->connectTimeout(2)->timeout(5)->get('http://127.0.0.1:39601/data');
    }
}
