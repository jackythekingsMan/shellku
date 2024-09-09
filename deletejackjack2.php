<?php

$minute = 15;
$limit = (60 * $minute); // 60 (seconds) = 1 Minutes
ini_set('memory_limit', '-1');
ini_set('max_execution_time', $limit);
set_time_limit($limit);

/**
 * Recursive listing files
 *
 * @param string $directory
 * @param array $entries_array optional
 * @return array of files
 */
function recursiveScan($directory, &$entries_array = array())
{
    // link can cause endless loop
    $handle = @opendir($directory);
    if ($handle) {
        while (($entry = readdir($handle)) !== false) {
            if ($entry == '.' || $entry == '..') {
                continue;
            }
            $entry = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($entry) && is_readable($directory) && !is_link($directory)) {
                $entries_array = recursiveScan($entry, $entries_array);
            } elseif (is_file($entry) && is_readable($entry)) {
                $entries_array['file_writable'][] = $entry;
            } else {
                $entries_array['file_not_writable'][] = $entry;
            }
        }
        closedir($handle);
    }
    return $entries_array;
}

/**
 *
 * Sort array of list file by lastest modified time
 *
 * @param array  $files Array of files
 *
 * @return array
 *
 */
function sortByLastModified($files)
{
    @array_multisort(array_map('filemtime', $files), SORT_DESC, $files);
    return $files;
}

/**
 * Recurisively list a file by descending modified time
 *
 * @param string $path
 *
 * @return array
 *
 */
function getSortedByTime($path)
{
    $result = recursiveScan($path);
    $fileWritable = $result['file_writable'];
    $fileNotWritable = isset($result['file_not_writable']) ? $result['file_not_writable'] : false;
    $fileWritable = sortByLastModified($fileWritable);

    return array(
        'file_writable' => $fileWritable,
        'file_not_writable' => $fileNotWritable
    );
}

/**
 * Get lowercase Array of tokens in a file
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
    $output = array();
    foreach ($tokenNeedles as $tokenNeedle) {
        if (in_array($tokenNeedle, $tokenHaystack)) {
            $output[] = $tokenNeedle;
        }
    }
    return $output;
}

$ext = array(
    'php',
    'phps',
    'pht',
    'phpt',
    'phtml',
    'phar',
    'php3',
    'php4',
    'php5',
    'php7',
    'php8',
    'suspected'
);

$tokenNeedles = array(
    'base64_decode',
    'eval',
    'shell_exec',
    '$_files'
);

if (isset($_POST['delete']) && !empty($_POST['files'])) {
    $filesToDelete = $_POST['files'];
    foreach ($filesToDelete as $fileToDelete) {
        if (file_exists($fileToDelete)) {
            unlink($fileToDelete);
        }
    }
    echo "<script>alert('Selected files have been deleted.');</script>";
}

$filesList = [];
if (isset($_POST['submit'])) {
    $path = $_POST['dir'];
    $result = getSortedByTime($path);

    $fileWritable = $result['file_writable'];
    $fileWritable = sortByLastModified($fileWritable);

    foreach ($fileWritable as $file) {
        $filePath = str_replace('\\', '/', $file);
        $tokens = getFileTokens($filePath);
        $cmp = compareTokens($tokenNeedles, $tokens);
        $cmpStr = implode(', ', $cmp);

        if (!empty($cmp)) {
            $filesList[] = ['path' => $filePath, 'tokens' => $cmpStr];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en-us">

<head>
    <title>Pussy Finder</title>
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
    <script type="text/javascript">
        function copytable(el) {
            var urlField = document.getElementById(el)
            var range = document.createRange()
            range.selectNode(urlField)
            window.getSelection().addRange(range)
            document.execCommand('copy')
        }
    </script>
    <form method="post">
        <table align="center" width="30%">
            <tr>
                <th>
                    Pussy Finder
                </th>
            </tr>
            <tr>
                <td>
                <input type="text" name="dir" value="<?= htmlspecialchars(isset($_POST['dir']) ? $_POST['dir'] : getcwd()) ?>">
                </td>
            </tr>
            <tr>
                <td>
                    <input type="submit" name="submit" value="SEARCH">
                </td>
            </tr>
        </table>

        <?php if (!empty($filesList)) { ?>
            <form method="post">
                <table id="result" align="center" width="60%">
                    <tr>
                        <th>File Path</th>
                        <th>Detected Tokens</th>
                        <th>Select</th>
                    </tr>
                    <?php foreach ($filesList as $file) { ?>
                        <tr>
                            <td><span style="color:red;"><?= htmlspecialchars($file['path']) ?></span></td>
                            <td><?= htmlspecialchars($file['tokens']) ?></td>
                            <td><input type="checkbox" name="files[]" value="<?= htmlspecialchars($file['path']) ?>"></td>
                        </tr>
                    <?php } ?>
                    <tr>
                        <td colspan="3" align="center">
                            <button type="submit" name="delete">Delete Selected</button>
                        </td>
                    </tr>
                </table>
            </form>
        <?php } ?>
    </form>
</body>

</html>
