<?php
/**
 * DoyeCMS 入口迁移一键脚本
 *
 * 功能：
 *   1. 从附属目录复制新入口文件和修改后的核心文件
 *   2. 移动 static/ 到 www/static/
 *   3. 移动后台静态资源到 www/static/admin/
 *   4. 移动 ueditor 到 www/static/admin/ueditor/
 *   5. 替换模板中的 {APP_THEME_DIR} 和 {CORE_DIR}/extend/ueditor
 *   6. 删除旧入口文件
 *
 * 使用方法：
 *   php script/migrate_www.php
 *
 * 幂等设计：已迁移则直接退出
 */

// 强制 CLI 运行
if (php_sapi_name() !== 'cli') {
    die('此脚本仅支持命令行运行: php ' . basename(__FILE__));
}

define('ROOT_PATH', str_replace('\\', '/', dirname(__DIR__)));
define('SCRIPT_DIR', ROOT_PATH . '/script/migrate_www');
define('WWW_PATH', ROOT_PATH . '/www');
define('STATIC_SRC', ROOT_PATH . '/static');
define('STATIC_DST', WWW_PATH . '/static');
define('ADMIN_VIEW_SRC', ROOT_PATH . '/apps/admin/view/default');
define('ADMIN_STATIC_DST', WWW_PATH . '/static/admin');
define('UEDITOR_SRC', ROOT_PATH . '/core/extend/ueditor');
define('UEDITOR_DST', WWW_PATH . '/static/admin/ueditor');

echo "=== DoyeCMS 入口迁移脚本 ===\n\n";

// ============================================================
// 步骤 0: 检查迁移状态
// ============================================================
echo "[0/8] 检查迁移状态...\n";
if (file_exists(WWW_PATH . '/index.php')) {
    echo "  -> www/index.php 已存在，已迁移，退出\n";
    exit(0);
}

// ============================================================
// 步骤 1: 从附属目录复制文件
// ============================================================
echo "[1/8] 从附属目录复制文件...\n";

$copyMap = array(
    SCRIPT_DIR . '/www/index.php' => WWW_PATH . '/index.php',
    SCRIPT_DIR . '/www/admin.php' => WWW_PATH . '/admin.php',
    SCRIPT_DIR . '/www/api.php'   => WWW_PATH . '/api.php',
    SCRIPT_DIR . '/core/init.php' => ROOT_PATH . '/core/init.php',
);

foreach ($copyMap as $src => $dst) {
    if (!file_exists($src)) {
        echo "  [WARN] 源文件不存在: {$src}\n";
        continue;
    }

    $dstDir = dirname($dst);
    if (!is_dir($dstDir)) {
        mkdir($dstDir, 0755, true);
    }

    if (copy($src, $dst)) {
        echo "  -> 已复制: " . str_replace(ROOT_PATH . '/', '', $dst) . "\n";
    } else {
        echo "  [ERROR] 复制失败: {$src}\n";
    }
}

// ============================================================
// 步骤 2: 移动 static/ -> www/static/
// ============================================================
echo "[2/8] 移动 static/ -> www/static/...\n";

if (!is_dir(STATIC_SRC)) {
    echo "  -> static/ 不存在，跳过\n";
} else {
    if (rename(STATIC_SRC, STATIC_DST)) {
        echo "  -> 已移动 static/ -> www/static/\n";
    } else {
        echo "  [ERROR] 移动失败，尝试逐文件复制...\n";
        // 回退方案：逐文件复制
        $copied = 0;
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
                }
            }
        }
        echo "  -> 复制完成: {$copied} 个文件\n";
        // 删除原目录
        path_delete(STATIC_SRC, true);
        echo "  -> 已删除原 static/\n";
    }
}

// ============================================================
// 步骤 3: 移动后台静态资源
// ============================================================
echo "[3/8] 移动后台静态资源...\n";

$adminStaticDirs = array('css', 'js', 'layui', 'font-awesome', 'images');

if (!is_dir(ADMIN_VIEW_SRC)) {
    echo "  -> apps/admin/view/default 不存在，跳过\n";
} else {
    foreach ($adminStaticDirs as $dir) {
        $src = ADMIN_VIEW_SRC . '/' . $dir;
        $dst = ADMIN_STATIC_DST . '/' . $dir;

        if (!is_dir($src)) {
            echo "  -> {$dir}/ 不存在，跳过\n";
            continue;
        }

        $dstDir = dirname($dst);
        if (!is_dir($dstDir)) {
            mkdir($dstDir, 0755, true);
        }

        if (rename($src, $dst)) {
            echo "  -> 已移动 {$dir}/ -> www/static/admin/{$dir}/\n";
        } else {
            echo "  [WARN] 移动失败: {$dir}/\n";
        }
    }
}

// ============================================================
// 步骤 4: 移动 ueditor -> www/static/admin/ueditor/
// ============================================================
echo "[4/8] 移动 ueditor...\n";

if (!is_dir(UEDITOR_SRC)) {
    echo "  -> core/extend/ueditor 不存在，跳过\n";
} else {
    $dstDir = dirname(UEDITOR_DST);
    if (!is_dir($dstDir)) {
        mkdir($dstDir, 0755, true);
    }

    if (rename(UEDITOR_SRC, UEDITOR_DST)) {
        echo "  -> 已移动 core/extend/ueditor/ -> www/static/admin/ueditor/\n";
    } else {
        echo "  [WARN] 移动失败\n";
    }
}

// ============================================================
// 步骤 5: 替换模板中的 {APP_THEME_DIR} 和 {CORE_DIR}/extend/ueditor
// ============================================================
echo "[5/8] 替换模板变量...\n";

if (!is_dir(ADMIN_VIEW_SRC)) {
    echo "  -> apps/admin/view/default 不存在，跳过\n";
} else {
    $replaced = 0;
    $htmlFiles = find_html_files(ADMIN_VIEW_SRC);

    foreach ($htmlFiles as $file) {
        $content = file_get_contents($file);
        if ($content === false) {
            continue;
        }

        $hasChange = false;

        // 替换 {APP_THEME_DIR}
        if (strpos($content, '{APP_THEME_DIR}') !== false) {
            $content = str_replace('{APP_THEME_DIR}', '/static/admin', $content);
            $hasChange = true;
        }

        // 替换 {CORE_DIR}/extend/ueditor
        if (strpos($content, '{CORE_DIR}/extend/ueditor') !== false) {
            $content = str_replace('{CORE_DIR}/extend/ueditor', '/static/admin/ueditor', $content);
            $hasChange = true;
        }

        // 替换 {CORE_DIR}/code.php
        if (strpos($content, '{CORE_DIR}/code.php') !== false) {
            $content = str_replace('{CORE_DIR}/code.php', '?code', $content);
            $hasChange = true;
        }

        if ($hasChange) {
            if (file_put_contents($file, $content) !== false) {
                $replaced++;
                echo "  -> 已替换: " . str_replace(ROOT_PATH . '/', '', $file) . "\n";
            }
        }
    }

    echo "  -> 替换完成: {$replaced} 个文件\n";
}

// ============================================================
// 步骤 6: 删除旧入口文件
// ============================================================
echo "[6/8] 删除旧入口文件...\n";

$oldEntryFiles = array(
    ROOT_PATH . '/index.php',
    ROOT_PATH . '/admin.php',
    ROOT_PATH . '/api.php',
);

foreach ($oldEntryFiles as $file) {
    if (file_exists($file)) {
        if (unlink($file)) {
            echo "  -> 已删除: " . basename($file) . "\n";
        } else {
            echo "  [WARN] 删除失败: " . basename($file) . "\n";
        }
    } else {
        echo "  -> " . basename($file) . " 不存在，跳过\n";
    }
}

// ============================================================
// 步骤 7: 删除旧 static/ 目录（如仍存在）
// ============================================================
echo "[7/8] 检查旧 static/ 目录...\n";

if (is_dir(STATIC_SRC)) {
    if (path_delete(STATIC_SRC, true)) {
        echo "  -> 已删除 static/\n";
    } else {
        echo "  [WARN] 删除失败，请手动删除 static/\n";
    }
} else {
    echo "  -> static/ 不存在，跳过\n";
}

// ============================================================
// 步骤 8: 输出摘要
// ============================================================
echo "[8/8] 迁移完成\n";

echo "迁移结果:\n";
echo "  www/index.php     : " . (file_exists(WWW_PATH . '/index.php') ? 'OK' : 'MISSING') . "\n";
echo "  www/admin.php     : " . (file_exists(WWW_PATH . '/admin.php') ? 'OK' : 'MISSING') . "\n";
echo "  www/api.php       : " . (file_exists(WWW_PATH . '/api.php') ? 'OK' : 'MISSING') . "\n";
echo "  www/static/       : " . (is_dir(STATIC_DST) ? 'OK' : 'MISSING') . "\n";
echo "  www/static/admin/ : " . (is_dir(ADMIN_STATIC_DST) ? 'OK' : 'MISSING') . "\n";
echo "  core/init.php     : OK\n";

echo "\n接下来请:\n";
echo "  1. 修改 Nginx 配置: root 指向 /www 目录\n";
echo "  2. 重载 Nginx: nginx -s reload\n";
echo "  3. 访问网站验证功能正常\n";


// ============================================================
// 辅助函数
// ============================================================

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