<?php

namespace Yiendos\MySitesIde;

use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Enums\TargetDatabase;

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

    /**
     * Splits the database rules into those to enable and disable, from
     * ZAP_ASCAN_DATABASES (comma-separated, e.g. "mysql"). Unset or empty
     * means every database rule runs. Both halves are always applied, since
     * the policy persists in the zap-home volume - a rule disabled for one
     * target would otherwise stay disabled for the next.
     *
     * @return array{enable: string, disable: string} combined regexes, '' when nothing matches
     */
    private function databaseRuleRegexes(SymfonyStyle $io): array
    {
        $names = array_filter(array_map('trim', explode(',', strtolower((string) getenv('ZAP_ASCAN_DATABASES')))));
        $wanted = [];

        foreach ($names as $name) {
            $database = TargetDatabase::tryFrom($name);

            if ($database === null) {
                $io->warning("Unknown database '{$name}' in ZAP_ASCAN_DATABASES - expected one of: " . implode(', ', array_column(TargetDatabase::cases(), 'value')) . '.');
                continue;
            }

            $wanted[] = $database;
        }

        $all = TargetDatabase::cases();
        $enable = $names === [] ? $all : array_filter($all, fn (TargetDatabase $database): bool => in_array($database, $wanted, true));
        $disable = array_filter($all, fn (TargetDatabase $database): bool => !in_array($database, $enable, true));
        $combine = fn (array $list): string => $list === [] ? '' : '(' . implode('|', array_map(fn (TargetDatabase $database): string => $database->rulePattern(), $list)) . ')';

        return ['enable' => $combine($enable), 'disable' => $combine($disable)];
    }
}
