<?Php
//https://trac.ffmpeg.org/wiki/Create%20a%20thumbnail%20image%20every%20X%20seconds%20of%20the%20video
//http://www.nrg-media.de/2010/11/creating-screenshots-with-ffmpeg-is-slow/

use datagutten\renamevideo\FilePath;
use datagutten\renamevideo\Recording;
use datagutten\tools\files\files;
use datagutten\video_tools\video;
use datagutten\video_tools\exceptions as video_exceptions;
use datagutten\xmltv\tools\exceptions\InvalidFileNameException;
use datagutten\xmltv\tools\exceptions\XMLTVException;
use Symfony\Component\Filesystem\Filesystem;

require 'vendor/autoload.php';
$config = require 'config.php';
$filesystem = new Filesystem();
umask(0);

if (is_file($argv[1])) { //Argument is an absolute path to a file
    $pathinfo = pathinfo($argv[1]);
    $files = array($pathinfo['basename']);
    $dir_video = $pathinfo['dirname'];
    $all = true;
} elseif (is_dir($argv[1])) { //Argument is an absolute path to a folder
    $dir_video = $argv[1];
    $all = true;
} else { //Argument is a sub folder to video path
    try {
        $folder = new FilePath($config['video_path'], $argv[1]);
        $dir_video = $folder->folder;
    } catch (FileNotFoundException $e) {
        die($e->getMessage() . "\n");
    }
    $all = false;
}

$file_tools = new datagutten\tools\files\files();
$files = $file_tools->get_files($dir_video, ['ts'], false);
$dir_snapshots=files::path_join($dir_video, 'snapshots');
foreach ($files as $file) {
    if (!$all) {
        try {
            datagutten\dreambox\recording_info::parse_file_name($file);
            $recording = new Recording($file, $all);
        } catch (InvalidFileNameException|FileNotFoundException|XMLTVException $e) {
            continue;
        }
    }

    if (file_exists($recording->snapshotFolder('time')))
        continue;

    echo "Creating snapshots for $file\n";
    try {
        $steps = video::snapshotsteps($file, 4, true, true);
    } catch (DependencyFailedException|video_exceptions\DurationNotFoundException $e) {
        echo $e->getMessage() . "\n";
        break;
    }
    try {
        $snapshots = video::snapshots($file, $steps, $recording->snapshotFolder('time'));
    } catch (FileNotFoundException|Exception $e) {
        echo $e->getMessage() . "\n";
        continue;
    }
}
