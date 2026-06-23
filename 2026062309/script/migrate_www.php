<?php
/**
 * 迁移脚本：将 static/ 目录从项目根迁移到 /www/static/
 *
 * 使用方法：在服务器上执行一次
 *   php script/migrate_www.php
 *
 * 幂等设计：可重复运行，已迁移的内容会跳过
 */

// 强制 CLI 运行
if (php_sapi_name() !== 'cli') {
    die('此脚本仅支持命令行运行: php ' . basename(__FILE__));
}

define('ROOT_PATH', str_replace('\\', '/', dirname(__DIR__)));
define('WWW_PATH', ROOT_PATH . '/www');
define('STATIC_SRC', ROOT_PATH . '/static');
define('STATIC_DST', WWW_PATH . '/static');

echo "=== DoyeCMS 入口迁移脚本 ===\n\n";

// 检查 www/ 目录是否已存在入口文件
if (!file_exists(WWW_PATH . '/index.php')) {
    echo "[ERROR] www/index.php 不存在，请先通过更新机制部署入口文件\n";
    exit(1);
}

// 步骤 1: 创建 www/ 目录
echo "[1/4] 检查 www/ 目录...\n";
if (!is_dir(WWW_PATH)) {
    mkdir(WWW_PATH, 0755, true);
    echo "  -> 已创建 www/\n";
} else {
    echo "  -> www/ 已存在，跳过\n";
}

// 步骤 2: 检查 static/ 源目录
echo "[2/4] 检查 static/ 源目录...\n";
if (!is_dir(STATIC_SRC)) {
    echo "  -> static/ 不存在，跳过迁移\n";
} elseif (is_dir(STATIC_DST) && count(scandir(STATIC_DST)) > 2) {
    echo "  -> www/static/ 已存在且非空，跳过迁移\n";
    echo "  -> 如需重新迁移，请先手动删除 www/static/\n";
} else {
    // 步骤 3: 备份并移动
    echo "[3/4] 迁移 static/ -> www/static/ ...\n";

    // 递归复制
    $copied = 0;
    $failed = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(STATIC_SRC, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $dest = STATIC_DST . '/' . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($dest)) {
                mkdir($dest, 0755, true);
            }
        } else {
            if (!is_dir(dirname($dest))) {
                mkdir(dirname($dest), 0755, true);
            }
            if (copy($item->getPathname(), $dest)) {
                $copied++;
            } else {
                echo "  [WARN] 复制失败: " . $item->getPathname() . "\n";
                $failed++;
            }
        }
    }

    echo "  -> 复制完成: {$copied} 个文件";

    if ($failed > 0) {
        echo " ({$failed} 个失败)";
    }
    echo "\n";

    // 验证完整性
    echo "[3.5/4] 验证迁移完整性...\n";
    $srcCount = 0;
    $checkIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(STATIC_SRC, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($checkIterator as $f) {
        $srcCount++;
    }

    $dstCount = 0;
    $checkIterator2 = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(STATIC_DST, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($checkIterator2 as $f) {
        $dstCount++;
    }

    if ($srcCount === $dstCount) {
        echo "  -> 验证通过: 源 {$srcCount} 个文件, 目标 {$dstCount} 个文件\n";

        // 删除源目录
        echo "[3.8/4] 删除原 static/ 目录...\n";
        path_delete(STATIC_SRC, true);
        echo "  -> 已删除 static/\n";
    } else {
        echo "  -> [WARN] 文件数不一致: 源 {$srcCount}, 目标 {$dstCount}\n";
        echo "  -> 保留原 static/ 目录，请手动检查\n";
    }
}

// 步骤 4: 创建软链接（可选，兼容旧入口）
echo "[4/4] 检查旧入口兼容性...\n";
if (is_dir(STATIC_DST) && !is_dir(STATIC_SRC)) {
    // 在 Windows 上创建目录 junction，在 Linux 上创建 symlink
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows: 使用 mklink /J 创建目录联接
        exec('mklink /J "' . STATIC_SRC . '" "' . STATIC_DST . '" 2>&1', $output, $ret);
        if ($ret === 0) {
            echo "  -> 已创建目录联接 static/ -> www/static/ (Windows)\n";
        } else {
            echo "  -> [WARN] 创建目录联接失败，请手动创建: mklink /J static www\\static\n";
        }
    } else {
        // Linux: 使用 symlink
        if (symlink(STATIC_DST, STATIC_SRC)) {
            echo "  -> 已创建符号链接 static/ -> www/static/ (Linux)\n";
        } else {
            echo "  -> [WARN] 创建符号链接失败，请手动创建: ln -s www/static static\n";
        }
    }
} elseif (is_dir(STATIC_SRC) && is_dir(STATIC_DST)) {
    echo "  -> static/ 和 www/static/ 均存在，跳过软链接\n";
} elseif (!is_dir(STATIC_DST)) {
    echo "  -> www/static/ 不存在，跳过软链接\n";
} else {
    echo "  -> 状态正常\n";
}

echo "\n=== 迁移完成 ===\n";
echo "\n接下来请:\n";
echo "  1. 修改 Nginx 配置: root 指向 /www 目录\n";
echo "  2. 重载 Nginx: nginx -s reload\n";
echo "  3. 访问网站验证功能正常\n";


/**
 * 删除目录及目录下所有文件或删除指定文件
 * 复制自 core/function/file.php 以保持脚本独立运行
 */
function path_delete($path, $delDir = false, $exFile = array())
{
    $result = true;
    if (!file_exists($path)) {
        return $result;
    }
    if (is_dir($path)) {
        if (!!$dirs = scandir($path)) {
            foreach ($dirs as $value) {
                if ($value != "." && $value != ".." && !in_array($value, $exFile)) {
                    $dir = $path . '/' . $value;
                    $result = is_dir($dir) ? path_delete($dir, $delDir, $exFile) : unlink($dir);
                }
            }
            if ($result && $delDir) {
                return rmdir($path);
            } else {
                return $result;
            }
        } else {
            return false;
        }
    } else {
        return unlink($path);
    }
}