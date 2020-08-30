<?php

use datagutten\renamevideo;
use datagutten\tools;
use datagutten\tools\files\files;
use datagutten\xmltv;
use datagutten\xmltv\tools\data\RecordingFile;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

require 'vendor/autoload.php';


$utils = new renamevideo\utils();

if (empty($_GET['folder']) && isset($argv[1])) {
    $_GET['folder'] = $argv[1];
}

try {
    if (isset($argv[1]) && file_exists($argv[1])) {
        $pathinfo = pathinfo($argv[1]);
        $folder = new renamevideo\FilePath($pathinfo['dirname'], $pathinfo['filename']);
    } else {
        $folder = new renamevideo\FilePath($utils->config['video_path'], $_GET['folder']);
    }
} catch (FileNotFoundException $e) {
    echo $utils->render('error.twig', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}

$filesystem = new Filesystem();
$log = '';

if (!empty($_POST)) {
    if (!file_exists($folder->folder_delete)) {
        $filesystem->mkdir($folder->folder_delete);
    }

    $renames = [];
    foreach ($_POST['epname'] as $basename => $new_name) {
        if (empty($new_name)) {
            continue;
        }

        try {
            $recording = new RecordingFile(files::path_join($folder->folder, $basename));
        } catch (FileNotFoundException $e) {
            $log .= $e->getMessage()."\n";
            continue;
        }

        if ($new_name !== 'del') {
            // Add series name
            if (strpos($new_name, $folder->sub_folder) !== 0) {
                $new_name = $folder->sub_folder . ' ' . $new_name;
            }
            $new_name = filnavn($new_name);

            $check_file = $folder->filePath($new_name, $recording->pathinfo['extension']);

            if (file_exists($check_file)) {
                $new_name .= " dupe " . time();
                $log .= "Dupe: $new_name<br />\n";
            }
        }

        foreach ($utils->config['extensions'] as $extension) {
            $file = $folder->filePath($recording->pathinfo['filename'], $extension);

            if ($new_name === 'del') {
                $new_file = sprintf(
                    '%s/%s.del%s',
                    $folder->folder_delete,
                    $recording->pathinfo['filename'],
                    $extension
                );
            } else {
                $new_file = $folder->filePath($new_name, $extension);
            }

            if (file_exists($file) && !file_exists($new_file)) {
                try {
                    $filesystem->rename($file, $new_file);
                    $log .= sprintf('Rename "%s" to "%s"'."\n", basename($file), basename($new_file));
                    $renames[$file] = $new_file;
                } catch (IOException $e) {
                    echo $e->getMessage();
                }
            }
        }
        if (file_exists($folder->folder_snapshots . '/' . $basename)) {
            try {
                $filesystem->remove($folder->folder_snapshots . '/' . $basename);
            } catch (IOException $e) {
                echo $e->getMessage();
            }
        }
    }
}

$files = tools\files\files::get_files($folder->folder, ['ts'], false);
$recordings = [];
$start = time();
$count = 0;

foreach ($files as $file) {
    try {
        datagutten\dreambox\recording_info::parse_file_name($file);
        $recording = new renamevideo\RecordingTwig($file);
        $recordings[$recording->basename()] = $recording;
    } catch (xmltv\tools\exceptions\XMLTVException|FileNotFoundException $e) {
        continue;
    }

    if ($count >= 50) {
        break;
    }
    $count++;
}

ksort($recordings);
echo $utils->render('renamevideo.twig', ['recordings' => $recordings, 'folder' => $_GET['folder'], 'log'=>$log]);
$end = time();
printf('Runtime: %d', $end - $start);
