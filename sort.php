<?Php

use datagutten\renamevideo\Recording;
use datagutten\tools\files\files;
use datagutten\xmltv\tools\exceptions;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

require 'vendor/autoload.php';
$config = require 'config.php';
$files = files::get_files(files::path_join($config['video_path'], $argv[1]), ['ts'], false);
$filesystem = new Filesystem();

foreach ($files as $file) {
    try {
        $recording = new Recording($file);
    } catch (exceptions\InvalidFileNameException $e) {
        continue;
    } catch (FileNotFoundException | exceptions\XMLTVException $e) {
        echo $e->getMessage() . "\n";
        continue;
    }

    echo $file."\n";

    try {
        $program = $recording->nearestProgram();
        $offset = $program->start_timestamp - $recording->start_timestamp;
    } catch (exceptions\XMLTVException $e) {
        echo $e->getMessage()."\n";
        continue;
    }

    echo $offset."\n";

    if (($argv[1] != 'sort_zero' && $offset < 0) && $offset != 0 && ($offset < 60 * 5 || $offset > 60 * 10)) {
        continue;
    } else {
        $file = str_replace('.ts', '', $file);
        $pathinfo=pathinfo($file);

        $file_title = filnavn((string)$program->{'title'});

        $outdir = files::path_join($recording->pathinfo['dirname'], $file_title);
        if (!file_exists($outdir))
            mkdir($outdir);
        foreach ($config['extensions'] as $extension) {
            $file = files::path_join($recording->pathinfo['dirname'], $recording->pathinfo['filename'].$extension);
            if (file_exists($file)) {
                echo "mv '$file' '$outdir'\n";
                $outfile=$outdir.'/'.basename($file);
                try {
                    $filesystem->rename($file, $outfile);
                    chmod($outfile, 0777);
                } catch (IOException $e) {
                    echo $e->getMessage() . "\n";
                }
            } else {
                echo $file . "\n";
            }
        }
    }
}
