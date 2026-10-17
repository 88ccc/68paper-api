<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;
use think\facade\Db;

/**
 * 数据库升级迁移服务
 *
 * 解决的问题：开源项目代码可以覆盖更新，但数据库字段变更无法自动同步。
 *
 * 设计原则：
 * 1. 零依赖 —— 不引入 topthink/think-migration，用户不需要执行 composer install
 * 2. 幂等 —— 已执行过的迁移记录在 pt_migration 表中，重复执行不会二次变更
 * 3. 可跨版本 —— 从 1.0.0 直接升到 1.3.0 时，中间的迁移会按版本号依次补齐
 * 4. 宽容 —— 老站点结构可能已手工改过，字段/索引已存在这类错误默认跳过而非中断
 */
class UpgradeService
{
    /** 迁移记录表名（不含前缀） */
    public const TABLE = 'migration';

    /** 迁移脚本目录（相对项目根目录） */
    public const DIR = 'database/migrations';

    /** 默认可忽略的 MySQL 错误码：都是"已经存在/已经不存在"这类非破坏性冲突 */
    public const DEFAULT_IGNORE_ERRORS = [
        1060, // Duplicate column name                字段已存在
        1061, // Duplicate key name                   索引已存在
        1068, // Multiple primary key defined         主键已存在
        1091, // Can't DROP, column/key doesn't exist 字段或索引不存在
    ];

    /**
     * 表前缀：优先取数据库配置，未配置时兜底 pt_（与模型中的硬编码保持一致）
     */
    public static function prefix(): string
    {
        $default = (string)Config::get('database.default', 'mysql');
        $prefix  = (string)Config::get('database.connections.' . $default . '.prefix', '');
        return $prefix !== '' ? $prefix : 'pt_';
    }

    public static function table(): string
    {
        return self::prefix() . self::TABLE;
    }

    public static function dir(): string
    {
        return root_path() . self::DIR;
    }

    /** 当前代码版本 */
    public static function codeVersion(): string
    {
        return (string)Config::get('version.version', '0.0.0');
    }

    /** 迁移基线版本 */
    public static function baseline(): string
    {
        return (string)Config::get('version.baseline', '1.0.0');
    }

    /**
     * 确保迁移记录表存在（老站点首次升级时自动创建）
     */
    public static function ensureTable(): void
    {
        $table = self::table();
        Db::execute("CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `version` varchar(20) NOT NULL DEFAULT '' COMMENT '所属版本号',
            `name` varchar(150) NOT NULL DEFAULT '' COMMENT '迁移脚本文件名',
            `exec_time` datetime DEFAULT NULL COMMENT '执行时间',
            `memo` varchar(255) NOT NULL DEFAULT '' COMMENT '备注（基线标记/忽略原因）',
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_name` (`name`),
            KEY `idx_version` (`version`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='数据库迁移记录'");
    }

    /** 已执行过的迁移文件名列表 */
    public static function executedNames(): array
    {
        self::ensureTable();
        return Db::table(self::table())->column('name');
    }

    /** 数据库当前版本（已执行迁移中的最大版本号） */
    public static function dbVersion(): string
    {
        $max = self::baseline();
        foreach (self::executedNames() as $name) {
            $v = self::parseVersion($name);
            if ($v !== null && version_compare($v, $max, '>')) {
                $max = $v;
            }
        }
        return $max;
    }

    /** 从文件名中解析版本号：v1.2.3_xxx.sql => 1.2.3 */
    public static function parseVersion(string $name): ?string
    {
        if (preg_match('/^v(\d+\.\d+\.\d+)[_-]/i', $name, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * 扫描迁移目录，返回全部迁移脚本（按版本号升序）
     * 只认 v{版本}_{描述}.sql 和 v{版本}_{描述}.php，其他文件忽略
     */
    public static function all(): array
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir . '/*.*') ?: [];
        $list  = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (!preg_match('/^v(\d+\.\d+\.\d+)[_-].+\.(sql|php)$/i', $name, $m)) {
                continue;
            }
            $list[] = [
                'version' => $m[1],
                'name'    => $name,
                'path'    => $file,
                'type'    => strtolower($m[2]),
            ];
        }
        usort($list, function (array $a, array $b): int {
            $cmp = version_compare($a['version'], $b['version']);
            return $cmp !== 0 ? $cmp : strcmp($a['name'], $b['name']);
        });
        return $list;
    }

    /** 待执行的迁移（排除已执行过的） */
    public static function pending(): array
    {
        $done = array_flip(self::executedNames());
        return array_values(array_filter(
            self::all(),
            fn(array $item): bool => !isset($done[$item['name']])
        ));
    }

    /** 升级概览，供后台页面展示 */
    public static function info(): array
    {
        $pending = self::pending();
        return [
            'code_version'  => self::codeVersion(),
            'db_version'    => self::dbVersion(),
            'table'         => self::table(),
            'dir'           => self::DIR,
            'pending'       => $pending,
            'pending_count' => count($pending),
            'up_to_date'    => empty($pending),
        ];
    }

    /**
     * 执行升级
     *
     * @param bool          $dryRun 只演练不落库
     * @param callable|null $log    回调 function(string $level, string $message)
     * @return array{total:int, ok:array, skip:array, fail:array}
     */
    public static function run(bool $dryRun = false, ?callable $log = null): array
    {
        $result = ['total' => 0, 'ok' => [], 'skip' => [], 'fail' => []];

        self::ensureTable();
        $pending       = self::pending();
        $result['total'] = count($pending);

        foreach ($pending as $item) {
            try {
                if ($dryRun) {
                    $result['ok'][] = ['name' => $item['name'], 'version' => $item['version'], 'dry_run' => true];
                    continue;
                }
                $statements = self::execOne($item);
                self::mark($item, '');
                $result['ok'][] = [
                    'name'       => $item['name'],
                    'version'    => $item['version'],
                    'statements' => count($statements),
                ];
                $log && $log('ok', sprintf('[完成] %s（%d 条语句）', $item['name'], count($statements)));
            } catch (\Throwable $e) {
                if (self::isIgnorable($e, self::ignoreCodesOf($item))) {
                    self::mark($item, 'ignored: ' . mb_substr($e->getMessage(), 0, 200));
                    $result['skip'][] = [
                        'name'    => $item['name'],
                        'version' => $item['version'],
                        'reason'  => $e->getMessage(),
                    ];
                    $log && $log('skip', sprintf('[跳过] %s：%s', $item['name'], $e->getMessage()));
                    continue;
                }
                $result['fail'][] = [
                    'name'    => $item['name'],
                    'version' => $item['version'],
                    'error'   => $e->getMessage(),
                ];
                $log && $log('fail', sprintf('[失败] %s：%s', $item['name'], $e->getMessage()));
                // 中途失败立即停止：后续迁移很可能依赖本次的字段，继续跑只会错上加错
                break;
            }
        }

        return $result;
    }

    /**
     * 把当前所有（或指定版本之前的）迁移标记为已执行
     * 用于全新安装：刚导入的最新版 68paper.sql 已经包含这些结构，不需要真跑一遍
     */
    public static function markAll(?string $untilVersion = null): int
    {
        self::ensureTable();
        $done = array_flip(self::executedNames());
        $n    = 0;
        foreach (self::all() as $item) {
            if (isset($done[$item['name']])) {
                continue;
            }
            if ($untilVersion !== null && version_compare($item['version'], $untilVersion, '>')) {
                continue;
            }
            self::mark($item, 'baseline');
            $n++;
        }
        return $n;
    }

    /**
     * 尽力而为的 mysqldump 备份，失败返回 null（共享主机常禁用 exec，不能因此阻断升级）
     */
    public static function dump(): ?string
    {
        if (!function_exists('exec')) {
            return null;
        }
        $default = (string)Config::get('database.default', 'mysql');
        $cfg     = (array)Config::get('database.connections.' . $default, []);
        if (($cfg['type'] ?? 'mysql') !== 'mysql') {
            return null;
        }

        $dir = runtime_path() . 'backup' . DIRECTORY_SEPARATOR;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }

        $file = $dir . 'db_' . date('Ymd_His') . '.sql';
        putenv('MYSQL_PWD=' . (string)($cfg['password'] ?? ''));
        $cmd = sprintf(
            'mysqldump -h%s -P%s -u%s %s > %s 2>&1',
            escapeshellarg((string)($cfg['hostname'] ?? '127.0.0.1')),
            escapeshellarg((string)($cfg['hostport'] ?? '3306')),
            escapeshellarg((string)($cfg['username'] ?? 'root')),
            escapeshellarg((string)($cfg['database'] ?? '')),
            escapeshellarg($file)
        );
        $out  = [];
        $code = 0;
        try {
            @exec($cmd, $out, $code);
        } finally {
            putenv('MYSQL_PWD');
        }

        if ($code !== 0 || !is_file($file) || filesize($file) < 100) {
            @unlink($file);
            return null;
        }

        self::keepLatestBackups($dir, 5);
        return $file;
    }

    /**
     * 加文件锁执行，避免用户连点按钮导致并发跑迁移
     * @throws \RuntimeException 已有升级在进行中
     */
    public static function withLock(callable $callback): mixed
    {
        $dir = runtime_path() . 'lock' . DIRECTORY_SEPARATOR;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return $callback();
        }
        $fp = @fopen($dir . 'upgrade.lock', 'c+');
        if ($fp === false) {
            return $callback();
        }
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            throw new \RuntimeException('已有升级任务正在执行，请稍后再试');
        }
        try {
            return $callback();
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    // ------------------------------------------------------------------ 内部方法

    /**
     * 执行单个迁移脚本，返回实际执行的语句数
     * @return array 语句列表
     */
    protected static function execOne(array $item): array
    {
        if ($item['type'] === 'php') {
            $parsed = self::parsePhpFile($item['path']);
        } else {
            $parsed = self::parseSqlFile($item['path']);
        }

        foreach ($parsed['sqls'] as $sql) {
            Db::execute($sql);
        }

        return $parsed['sqls'];
    }

    /**
     * 解析 .sql 迁移文件
     * 支持文件头指令：
     *   -- @ignore-error: 1062,1054    额外忽略指定错误码
     * 支持占位符：
     *   {{prefix}}  替换为实际表前缀（如 pt_）
     */
    public static function parseSqlFile(string $path): array
    {
        $content = (string)file_get_contents($path);

        $ignore = self::DEFAULT_IGNORE_ERRORS;
        if (preg_match('/@ignore-error\s*[:：]\s*([0-9,\s]+)/i', $content, $m)) {
            $extra  = array_map('intval', preg_split('/[,\s]+/', trim($m[1])) ?: []);
            $ignore = array_values(array_unique(array_merge($ignore, array_filter($extra))));
        }

        // 去掉注释（先去注释再切分语句，避免注释里的分号被误判）
        $content = preg_replace('~/\*.*?\*/~s', '', $content) ?? $content;
        $content = preg_replace('/^\s*(--|#)[^\n]*/m', '', $content) ?? $content;

        // 占位符替换
        $content = str_replace(['{{prefix}}', '{prefix}'], self::prefix(), $content);

        return ['ignore' => $ignore, 'sqls' => self::splitStatements($content)];
    }

    /**
     * 解析 .php 迁移文件：可返回 SQL 字符串、SQL 数组或匿名函数
     * 匿名函数签名为 function(): array|string
     */
    protected static function parsePhpFile(string $path): array
    {
        $ret = require $path;
        if (is_callable($ret)) {
            $ret = $ret();
        }
        if (is_string($ret)) {
            $ret = self::splitStatements(str_replace(['{{prefix}}', '{prefix}'], self::prefix(), $ret));
        }
        if (!is_array($ret)) {
            throw new \RuntimeException('PHP 迁移文件必须返回 SQL 字符串、SQL 数组或 callable');
        }
        $sqls = array_values(array_filter(array_map('trim', $ret), fn($s): bool => $s !== ''));

        return ['ignore' => self::DEFAULT_IGNORE_ERRORS, 'sqls' => $sqls];
    }

    /**
     * 按分号切分 SQL，正确处理字符串/标识符引号内的分号
     */
    public static function splitStatements(string $sql): array
    {
        $out   = [];
        $buf   = '';
        $quote = null;
        $len   = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            if ($quote !== null) {
                $buf .= $ch;
                if ($ch === '\\' && $i + 1 < $len) {
                    $buf .= $sql[++$i];
                } elseif ($ch === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $quote = $ch;
                $buf  .= $ch;
                continue;
            }
            if ($ch === ';') {
                if (trim($buf) !== '') {
                    $out[] = trim($buf);
                }
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        if (trim($buf) !== '') {
            $out[] = trim($buf);
        }

        return $out;
    }

    /** 迁移脚本声明的可忽略错误码 + 默认错误码 */
    protected static function ignoreCodesOf(array $item): array
    {
        if ($item['type'] !== 'sql' || !is_file($item['path'])) {
            return self::DEFAULT_IGNORE_ERRORS;
        }
        return self::parseSqlFile($item['path'])['ignore'];
    }

    /** 判断异常是否属于"可忽略"的冲突类错误 */
    protected static function isIgnorable(\Throwable $e, array $codes): bool
    {
        $haystack = $e->getMessage() . ' ' . $e->getCode() . ' ' . ($e->getPrevious() ? $e->getPrevious()->getMessage() : '');
        foreach ($codes as $code) {
            if (strpos($haystack, (string)$code) !== false) {
                return true;
            }
        }
        return false;
    }

    /** 写入迁移记录（重复执行时更新，不报错） */
    protected static function mark(array $item, string $memo): void
    {
        $table = self::table();
        Db::execute(
            "INSERT INTO `{$table}` (`version`,`name`,`exec_time`,`memo`) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE `exec_time` = VALUES(`exec_time`), `memo` = VALUES(`memo`)",
            [$item['version'], $item['name'], date('Y-m-d H:i:s'), $memo]
        );
    }

    /** 只保留最近 N 份备份，避免把磁盘撑爆 */
    protected static function keepLatestBackups(string $dir, int $keep): void
    {
        $files = glob($dir . 'db_*.sql') ?: [];
        if (count($files) <= $keep) {
            return;
        }
        usort($files, fn($a, $b): int => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
        }
    }
}
