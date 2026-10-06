<?php

namespace Yiendos\MySitesIde\Enums;

/**
 * The database engines a target app can run on, as named in
 * ZAP_ASCAN_DATABASES, each mapped to its database-specific Active Scan
 * rules. Matched by rule name, not pinned IDs - same reasoning as the DOM
 * XSS lookup in ide:zap-daemon. The generic "SQL Injection" rule isn't
 * listed, so it always runs.
 */
enum TargetDatabase: string
{
    case MySql = 'mysql';
    case PostgreSql = 'postgresql';
    case Oracle = 'oracle';
    case MsSql = 'mssql';
    case Hypersonic = 'hypersonic';
    case Sqlite = 'sqlite';
    case MongoDb = 'mongodb';

    /**
     * Regex matching this database's Active Scan rule names
     *
     * @return string
     */
    public function rulePattern(): string
    {
        return match ($this) {
            self::MySql => '^SQL Injection - MySQL',
            self::PostgreSql => '^SQL Injection - PostgreSQL',
            self::Oracle => '^SQL Injection - Oracle',
            self::MsSql => '^SQL Injection - MsSQL',
            self::Hypersonic => '^SQL Injection - Hypersonic',
            self::Sqlite => '^SQL Injection - SQLite',
            self::MongoDb => '^NoSQL Injection - MongoDB',
        };
    }
}
