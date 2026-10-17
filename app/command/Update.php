<?php
declare(strict_types=1);

namespace app\command;

use app\service\UpgradeService;
use think\console\Command;
use think\console\input\Option;
use think\console\Input;
use think\console\Output;

/**
 * 数据库升级命令
 *
 * 常用法：
 *   php think update              # 备份 + 执行所有未执行的迁移
 *   php think update --check      # 只看有哪些待执行，不动数据库
 *   php think update --mark       # 全新安装导入最新 SQL 后，把已有迁移标记为已执行
 *   php think update --dry-run    # 演练，只列出会执行哪些脚本
 *   php think update --no-backup  # 跳过自动备份
 */
class Update extends Command
{
    protected function configure(): void
    {
        $this->setName('update')
            ->addOption('check', null, Option::VALUE_NONE, '只查看待执行的迁移，不执行')
            ->addOption('mark', null, Option::VALUE_NONE, '把全部迁移标记为已执行（全新安装导入最新 SQL 后使用）')
            ->addOption('dry-run', null, Option::VALUE_NONE, '演练模式，不真正写库')
            ->addOption('no-backup', null, Option::VALUE_NONE, '跳过 mysqldump 自动备份')
            ->setDescription('升级数据库：执行 database/migrations 下未执行过的迁移脚本');
    }

    protected function execute(Input $input, Output $output): int
    {
        $output->writeln('<info>68paper 数据库升级工具</info>');
        $output->writeln('代码版本：' . UpgradeService::codeVersion());

        try {
            if ($input->getOption('mark')) {
                $n = UpgradeService::withLock(fn(): int => UpgradeService::markAll());
                $output->writeln("<info>已把 {$n} 个迁移标记为已执行（基线同步）</info>");
                return 0;
            }

            $info = UpgradeService::info();
            $output->writeln('数据库版本：' . $info['db_version']);

            if (empty($info['pending'])) {
                $output->writeln('<info>数据库已是最新，无需升级</info>');
                return 0;
            }

            $output->writeln('待执行迁移：' . count($info['pending']) . ' 个');
            foreach ($info['pending'] as $item) {
                $output->writeln('  - v' . $item['version'] . '  ' . $item['name']);
            }

            if ($input->getOption('check')) {
                return 0;
            }

            if (!$input->getOption('no-backup') && !$input->getOption('dry-run')) {
                $output->write('正在备份数据库... ');
                $file = UpgradeService::dump();
                if ($file) {
                    $output->writeln("<info>已备份到 {$file}</info>");
                } else {
                    $output->writeln('<comment>已跳过（mysqldump 不可用，请自行确认已备份）</comment>');
                }
            }

            $dry = (bool)$input->getOption('dry-run');
            $result = UpgradeService::withLock(function () use ($dry, $output): array {
                return UpgradeService::run($dry, function (string $level, string $msg) use ($output): void {
                    $tag = ['ok' => 'info', 'skip' => 'comment', 'fail' => 'error'][$level] ?? 'info';
                    $output->writeln("<{$tag}>{$msg}</{$tag}>");
                });
            });

            $output->writeln(sprintf(
                '执行结果：成功 %d 个，跳过 %d 个，失败 %d 个',
                count($result['ok']),
                count($result['skip']),
                count($result['fail'])
            ));

            if (!empty($result['fail'])) {
                $output->writeln('<error>升级中断，请根据上面的错误信息修复后重新执行 php think update</error>');
                return 1;
            }

            $output->writeln('<info>升级完成</info>');
            return 0;
        } catch (\Throwable $e) {
            $output->writeln('<error>升级失败：' . $e->getMessage() . '</error>');
            $output->writeln('<comment>请检查 .env / config/database.php 的数据库配置，确认数据库服务可正常访问、账号有建表改表权限</comment>');
            return 1;
        }
    }
}
