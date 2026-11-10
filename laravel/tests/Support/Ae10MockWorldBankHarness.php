<?php
/** Test-only harness; never installed in application app/ or operational bindings. */
class Ae10MockWorldBankHarness extends \App\Services\Sifi\WorldBankOneShotAcquisition
{
    protected function supportsLocalMock():bool {return app()->environment('testing');}
    protected function request(string $endpoint,array $options):\Illuminate\Http\Client\Response
    {
        if(!app()->environment('testing')||$endpoint!==\App\Services\Sifi\WorldBankGdpSourceAdapter::ENDPOINT)throw new \RuntimeException('LOCAL_TEST_REQUIRED');
        $options['protocols']=['http'];
        return (new \Illuminate\Http\Client\Factory)->withOptions($options)->connectTimeout(2)->timeout(5)->get('http://127.0.0.1:39601/data');
    }
}
