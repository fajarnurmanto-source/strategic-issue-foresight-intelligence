<?php

use App\Services\Sifi\ResearchWorldBankAcquisition;
use App\Services\Sifi\WorldBankGdpSourceAdapter;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Crypt;

/** In-process HTTP simulator. Never bound in production application code. */
class Net02InProcessWorldBankHarness extends ResearchWorldBankAcquisition
{
    protected function supportsLocalMock(): bool
    {
        return app()->environment('testing');
    }

    protected function request(string $endpoint, array $options): Illuminate\Http\Client\Response
    {
        if (! app()->environment('testing') || $endpoint !== WorldBankGdpSourceAdapter::ENDPOINT) {
            throw new RuntimeException('TEST_ONLY');
        }
        $permit = json_decode(Crypt::decryptString(file_get_contents($this->permitPath())), true);
        if (($permit['consumed'] ?? null) !== true) {
            throw new RuntimeException('NOT_CONSUMED_BEFORE_IO');
        }
        $packet = json_decode(file_get_contents(resource_path('sifi/ae09-wb-response.json')), true);
        $packet[0]['lastupdated'] = now()->utc()->format('Y-m-d');
        foreach ($packet[1] as $i => &$row) {
            $row['value'] = [1.25, 2.5, 3.75][$i];
        }unset($row);
        $s = config('sifi_net02.scenario', 'valid');
        $status = 200;
        $headers = ['Content-Type' => 'application/json'];
        if ($s === 'redirect') {
            $status = 302;
            $headers['Location'] = 'http://127.0.0.1/denied';
        }if ($s === 'mime') {
            $headers['Content-Type'] = 'text/html';
        }if ($s === 'encoding') {
            $headers['Content-Encoding'] = 'gzip';
        }if ($s === 'rate') {
            $status = 429;
        }if ($s === 'timeout') {
            throw new RuntimeException('AE10_TIMEOUT');
        }if ($s === 'dns') {
            throw new RuntimeException('AE10_DNS_POLICY');
        }if ($s === 'duplicate') {
            $packet[1][1] = $packet[1][0];
        }
        $raw = json_encode($packet, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($s === 'malformed') {
            $raw = '{bad';
        }if ($s === 'oversize') {
            $raw = str_repeat('x', 65537);
        }if ($s === 'partial') {
            $raw = substr($raw, 0, 100);
        }
        $native = new Response($status, $headers, $raw);
        $options['on_headers']($native);
        $options['sink']->write($raw);

        return new Illuminate\Http\Client\Response($native);
    }
}
