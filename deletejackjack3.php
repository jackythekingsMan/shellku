<?php

$minute = 15;
$limit = (60 * $minute); // 60 (seconds) = 1 Minute
ini_set('memory_limit', '-1');
ini_set('max_execution_time', $limit);
set_time_limit($limit);

/**
 * Scan files in the current directory only
 *
 * @param string $directory
 * @return array of files
 */
function scanCurrentDirectory($directory)
{
    $entries_array = ['file_writable' => [], 'file_not_writable' => []];

    if (!is_dir($directory)) {
        return $entries_array;
    }

    // Open the directory
    $handle = @opendir($directory);
    if ($handle) {
        while (($entry = readdir($handle)) !== false) {
            if ($entry == '.' || $entry == '..') {
                continue; // Skip current and parent directory
            }

            $entryPath = $directory . DIRECTORY_SEPARATOR . $entry;

            // Check only files in the current directory
            if (is_file($entryPath) && is_readable($entryPath)) {
                $entries_array['file_writable'][] = $entryPath;
            } elseif (is_file($entryPath)) {
                $entries_array['file_not_writable'][] = $entryPath;
            }
        }
        closedir($handle);
    }

    return $entries_array;
}

/**
 * Sort array of list file by last modified time
 *
 * @param array $files Array of files
 * @return array
 */
function sortByLastModified($files)
{
    @array_multisort(array_map('filemtime', $files), SORT_DESC, $files);
    return $files;
}

/**
 * List files in current directory by descending modified time
 *
 * @param string $path
 * @return array
 */
function getSortedByTimeCurrentDir($path)
{
    $result = scanCurrentDirectory($path);
    $fileWritable = $result['file_writable'];
    $fileNotWritable = $result['file_not_writable'];

    $fileWritable = sortByLastModified($fileWritable);

    return [
        'file_writable' => $fileWritable,
        'file_not_writable' => $fileNotWritable,
    ];
}

/**
 * Get lowercase array of tokens in a file
 *
 * @param string $filename
 * @return array
 */
function getFileTokens($filename)
{
    $fileContent = @file_get_contents($filename);

    if ($fileContent === false) {
        return [];
    }

    $fileContent = preg_replace('/<\?([^p=\w])/m', '<?php ', $fileContent);

    $tokens = @token_get_all($fileContent);

    if (!is_array($tokens)) {
        return [];
    }

    $output = [];
    foreach ($tokens as $token) {
        if (isset($token[1])) {
            $output[] = strtolower(trim($token[1]));
        }
    }

    return array_values(array_unique(array_filter($output)));
}

/**
 * Compare tokens and return array of matched tokens
 *
 * @param array $tokenNeedles
 * @param array $tokenHaystack
 * @return array
 */
function compareTokens($tokenNeedles, $tokenHaystack)
{
    return array_intersect($tokenNeedles, $tokenHaystack);
}

$tokenNeedles = [
    'base64_decode',
    'eval',
    'shell_exec',
    '$_files',
];

$filesList = [];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $path = $_POST['dir'] ?? __DIR__;

    if (!is_dir($path)) {
        $errorMessage = 'Invalid directory.';
    } else {
        $result = getSortedByTimeCurrentDir($path);

        foreach ($result['file_writable'] as $file) {
            $tokens = getFileTokens($file);
            $matchedTokens = compareTokens($tokenNeedles, $tokens);
            if (!empty($matchedTokens)) {
                $filesList[] = [
                    'path' => $file,
                    'tokens' => implode(', ', $matchedTokens),
                ];
            }
        }
    }
}

if (isset($_POST['delete']) && !empty($_POST['files'])) {
    foreach ($_POST['files'] as $fileToDelete) {
        if (file_exists($fileToDelete)) {
            unlink($fileToDelete);
        }
    }
    echo "<script>alert('Selected files have been deleted.');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Directory Scanner</title>
    <style type="text/css">
        @import url('https://fonts.googleapis.com/css?family=Ubuntu+Mono&display=swap');

        body {
            font-family: 'Ubuntu Mono', monospace;
            color: #8a8a8a;
        }

        table {
            border-spacing: 0;
            padding: 10px;
            border-radius: 7px;
            border: 3px solid #d6d6d6;
        }

        tr,
        td {
            padding: 7px;
        }

        th {
            color: #8a8a8a;
            padding: 7px;
            font-size: 25px;
        }

        input[type=submit]:focus {
            background: #ff9999;
            color: #fff;
            border: 3px solid #ff9999;
        }

        input[type=submit]:hover {
            border: 3px solid #ff9999;
            cursor: pointer;
        }

        input[type=text]:hover {
            border: 3px solid #ff9999;
        }

        input {
            font-family: 'Ubuntu Mono', monospace;
        }

        input[type=text] {
            border: 3px solid #d6d6d6;
            outline: none;
            padding: 7px;
            color: #8a8a8a;
            width: 100%;
            border-radius: 7px;
        }

        input[type=submit] {
            color: #8a8a8a;
            border: 3px solid #d6d6d6;
            outline: none;
            background: none;
            padding: 7px;
            width: 100%;
            border-radius: 7px;
        }

        button {
            padding: 5px 10px;
            border-radius: 5px;
            background-color: #ff6666;
            color: #fff;
            border: none;
            cursor: pointer;
        }

        button:hover {
            background-color: #ff3333;
        }
    </style>
</head>
<body>
    <form method="post">
        <label for="dir">Directory:</label>
        <input type="text" name="dir" id="dir" value="<?= htmlspecialchars($_POST['dir'] ?? __DIR__) ?>" required>
        <button type="submit" name="submit">Scan</button>
    </form>

    <?php if (!empty($errorMessage)): ?>
        <p style="color: red;"><?= htmlspecialchars($errorMessage) ?></p>
    <?php endif; ?>

    <?php if (!empty($filesList)): ?>
        <form method="post">
            <table border="1">
                <thead>
                    <tr>
                        <th>File Path</th>
                        <th>Detected Tokens</th>
                        <th>Delete</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filesList as $file): ?>
                        <tr>
                            <td><?= htmlspecialchars($file['path']) ?></td>
                            <td><?= htmlspecialchars($file['tokens']) ?></td>
                            <td>
                                <input type="checkbox" name="files[]" value="<?= htmlspecialchars($file['path']) ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" name="delete">Delete Selected</button>
        </form>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <p>No matching files found in the specified directory.</p>
    <?php endif; ?>
</body>
</html>
