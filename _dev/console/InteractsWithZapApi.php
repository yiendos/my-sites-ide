<?php

namespace Yiendos\MySitesIde;

use Symfony\Component\Console\Style\SymfonyStyle;

trait InteractsWithZapApi
{
    /**
     * Call the ZAP API via `docker compose exec` into the running zaproxy
     * container - the API port isn't reachable directly from the host (it
     * silently drops connections that don't present as true loopback, which
     * a host->container port-forward does not on Docker Desktop for Mac).
     *
     * @param SymfonyStyle $io
     * @param string $path e.g. "context/action/newContext"
     * @param array<string, string> $params
     * @param boolean $allowMissing suppress the "does_not_exist" error (e.g. removeContext on a first run)
     * @return array<string, mixed>
     */
    private function zapApi(SymfonyStyle $io, string $path, array $params, bool $allowMissing = false): array
    {
        $command = ['docker', 'compose', 'exec', 'zaproxy', 'curl', '-s', "http://localhost:8090/JSON/{$path}/"];

        foreach ($params as $key => $value) {
            $command[] = '--data-urlencode';
            $command[] = "{$key}={$value}";
        }

        $commandString = implode(' ', array_map('escapeshellarg', $command));
        $response = shell_exec($commandString);
        $decoded = json_decode($response ?? '', true) ?? [];

        if (isset($decoded['code']) && !($allowMissing && $decoded['code'] === 'does_not_exist')) {
            $io->error("ZAP API error on {$path}: " . ($decoded['message'] ?? $response));
        }

        return $decoded;
    }
}
