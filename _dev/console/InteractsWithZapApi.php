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

    /**
     * Database-specific Active Scan rules, matched by name (not pinned IDs -
     * same reasoning as the DOM XSS lookup in ide:zap-daemon). The generic
     * "SQL Injection" rule isn't listed, so it always runs.
     *
     * @return array<string, string> database => rule-name regex
     */
    private static function databaseRulePatterns(): array
    {
        return [
            'mysql' => '^SQL Injection - MySQL',
            'postgresql' => '^SQL Injection - PostgreSQL',
            'oracle' => '^SQL Injection - Oracle',
            'mssql' => '^SQL Injection - MsSQL',
            'hypersonic' => '^SQL Injection - Hypersonic',
            'sqlite' => '^SQL Injection - SQLite',
            'mongodb' => '^NoSQL Injection - MongoDB',
        ];
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
        $patterns = self::databaseRulePatterns();
        $wanted = array_filter(array_map('trim', explode(',', strtolower((string) getenv('ZAP_ASCAN_DATABASES')))));

        foreach (array_diff($wanted, array_keys($patterns)) as $unknown) {
            $io->warning("Unknown database '{$unknown}' in ZAP_ASCAN_DATABASES - expected one of: " . implode(', ', array_keys($patterns)) . '.');
        }

        $enable = $wanted === [] ? $patterns : array_intersect_key($patterns, array_flip($wanted));
        $disable = array_diff_key($patterns, $enable);
        $combine = fn (array $list): string => $list === [] ? '' : '(' . implode('|', $list) . ')';

        return ['enable' => $combine($enable), 'disable' => $combine($disable)];
    }
}
