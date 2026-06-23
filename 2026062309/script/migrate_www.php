<?php
/**
 * 迁移脚本：将 static/ 目录从项目根迁移到 /www/static/
 *         + 将后台静态资源迁移到 /www/static/admin/
 *         + 批量替换模板中的 {APP_THEME_DIR} 为 /static/admin/
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
define('ADMIN_VIEW_SRC', ROOT_PATH . '/apps/admin/view/default');
define('ADMIN_STATIC_DST', WWW_PATH . '/static/admin');

echo "=== DoyeCMS 入口迁移脚本 ===\n\n";

// 检查 www/ 目录是否已存在入口文件
if (!file_exists(WWW_PATH . '/index.php')) {
    echo "[ERROR] www/index.php 不存在，请先通过更新机制部署入口文件\n";
    exit(1);
}

// ============================================================
// 步骤 1: 创建 www/ 目录
// ============================================================
echo "[1/6] 检查 www/ 目录...\n";
if (!is_dir(WWW_PATH)) {
    mkdir(WWW_PATH, 0755, true);
    echo "  -> 已创建 www/\n";
} else {
    echo "  -> www/ 已存在，跳过\n";
}

// ============================================================
// 步骤 2: 迁移 static/ -> www/static/
// ============================================================
echo "[2/6] 检查 static/ 源目录...\n";
if (!is_dir(STATIC_SRC)) {
    echo "  -> static/ 不存在，跳过迁移\n";
} elseif (is_dir(STATIC_DST) && count(scandir(STATIC_DST)) > 2) {
    echo "  -> www/static/ 已存在且非空，跳过迁移\n";
} else {
    echo "  -> 迁移 static/ -> www/static/ ...\n";

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
    echo "  -> 验证迁移完整性...\n";
    $srcCount = count_files(STATIC_SRC);
    $dstCount = count_files(STATIC_DST);

    if ($srcCount === $dstCount) {
        echo "  -> 验证通过: 源 {$srcCount} 个文件, 目标 {$dstCount} 个文件\n";
        echo "  -> 删除原 static/ 目录...\n";
        path_delete(STATIC_SRC, true);
        echo "  -> 已删除 static/\n";
    } else {
        echo "  -> [WARN] 文件数不一致: 源 {$srcCount}, 目标 {$dstCount}\n";
        echo "  -> 保留原 static/ 目录，请手动检查\n";
    }
}

// ============================================================
// 步骤 3: 迁移后台静态资源 -> www/static/admin/
// ============================================================
echo "[3/6] 迁移后台静态资源...\n";

// 需要迁移的静态资源目录
$adminStaticDirs = array('css', 'js', 'layui', 'font-awesome', 'images');

if (!is_dir(ADMIN_VIEW_SRC)) {
    echo "  -> apps/admin/view/default 不存在，跳过后台资源迁移\n";
} else {
    $adminCopied = 0;
    $adminFailed = 0;

    foreach ($adminStaticDirs as $dir) {
        $src = ADMIN_VIEW_SRC . '/' . $dir;
        $dst = ADMIN_STATIC_DST . '/' . $dir;

        if (!is_dir($src)) {
            echo "  -> {$dir}/ 不存在，跳过\n";
            continue;
        }

        if (is_dir($dst) && count(scandir($dst)) > 2) {
            echo "  -> static/admin/{$dir}/ 已存在，跳过\n";
            continue;
        }

        echo "  -> 复制 {$dir}/ -> www/static/admin/{$dir}/\n";

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $dest = $dst . '/' . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($dest)) {
                    mkdir($dest, 0755, true);
                }
            } else {
                if (!is_dir(dirname($dest))) {
                    mkdir(dirname($dest), 0755, true);
                }
                if (copy($item->getPathname(), $dest)) {
                    $adminCopied++;
                } else {
                    echo "    [WARN] 复制失败: " . $item->getPathname() . "\n";
                    $adminFailed++;
                }
            }
        }
    }

    echo "  -> 后台资源复制完成: {$adminCopied} 个文件";
    if ($adminFailed > 0) {
        echo " ({$adminFailed} 个失败)";
    }
    echo "\n";
}

// ============================================================
// 步骤 4: 替换模板中的 {APP_THEME_DIR} 为 /static/admin/
// ============================================================
echo "[4/6] 替换模板中的 {APP_THEME_DIR}...\n";

if (!is_dir(ADMIN_VIEW_SRC)) {
    echo "  -> apps/admin/view/default 不存在，跳过替换\n";
} else {
    $replaced = 0;
    $htmlFiles = find_html_files(ADMIN_VIEW_SRC);

    foreach ($htmlFiles as $file) {
        $content = file_get_contents($file);
        if ($content === false) {
            echo "  [WARN] 读取失败: {$file}\n";
            continue;
        }

        if (strpos($content, '{APP_THEME_DIR}') === false) {
            continue;
        }

        $newContent = str_replace('{APP_THEME_DIR}', '/static/admin', $content);
        if (file_put_contents($file, $newContent) !== false) {
            $replaced++;
            echo "  -> 已替换: " . str_replace(ROOT_PATH . '/', '', $file) . "\n";
        } else {
            echo "  [WARN] 写入失败: {$file}\n";
        }
    }

    echo "  -> 替换完成: {$replaced} 个文件\n";
}

// ============================================================
// 步骤 5: 创建软链接（可选，兼容旧入口）
// ============================================================
echo "[5/6] 检查旧入口兼容性...\n";
if (is_dir(STATIC_DST) && !is_dir(STATIC_SRC)) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec('mklink /J "' . STATIC_SRC . '" "' . STATIC_DST . '" 2>&1', $output, $ret);
        if ($ret === 0) {
            echo "  -> 已创建目录联接 static/ -> www/static/ (Windows)\n";
        } else {
            echo "  -> [WARN] 创建目录联接失败，请手动创建: mklink /J static www\\static\n";
        }
    } else {
        if (symlink(STATIC_DST, STATIC_SRC)) {
            echo "  -> 已创建符号链接 static/ -> www/static/ (Linux)\n";
        } else {
            echo "  -> [WARN] 创建符号链接失败，请手动创建: ln -s www/static static\n";
        }
    }
} elseif (is_dir(STATIC_SRC) && is_dir(STATIC_DST)) {
    echo "  -> static/ 和 www/static/ 均存在，跳过软链接\n";
} else {
    echo "  -> 状态正常\n";
}

// ============================================================
// 步骤 6: 完成
// ============================================================
echo "[6/6] 迁移完成\n";

echo "\n=== 迁移摘要 ===\n";
echo "  www/static/       : " . (is_dir(STATIC_DST) ? '已就绪' : '未创建') . "\n";
echo "  www/static/admin/ : " . (is_dir(ADMIN_STATIC_DST) ? '已就绪' : '未创建') . "\n";
echo "\n接下来请:\n";
echo "  1. 修改 Nginx 配置: root 指向 /www 目录\n";
echo "  2. 重载 Nginx: nginx -s reload\n";
echo "  3. 访问网站验证功能正常\n";


// ============================================================
// 辅助函数
// ============================================================

/**
 * 递归统计目录下文件数量
 */
function count_files($dir)
{
    $count = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $f) {
        $count++;
    }
    return $count;
}

/**
 * 递归查找目录下所有 .html 文件
 */
function find_html_files($dir)
{
    $files = array();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'html') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

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
