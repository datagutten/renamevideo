<?php


namespace datagutten\renamevideo;

use datagutten\tools\files\files;
use UnexpectedValueException;

/**
 * Extension of recording with additional methods for use in the renamevideo project
 * @package datagutten\renamevideo
 */
class Recording extends \datagutten\xmltv\tools\data\Recording
{
    public function __construct($file, $ignore_file_names = false)
    {
        $config = require 'config.php';
        parent::__construct($file, $config['xmltv_path'], $config['xmltv_sub_folders'], $ignore_file_names);
    }

    public function snapshotFolder()
    {
        return sprintf('%s/snapshots/%s', $this->pathinfo['dirname'], $this->pathinfo['basename']);
    }

    /**
     * Get snapshots for the recording
     * @return array
     * @throws UnexpectedValueException If the path cannot be found
     */
    public function snapshots()
    {
        $dir_snapshots = $this->snapshotFolder();
        if (!file_exists($dir_snapshots)) {
            return [];
        }

        $folders = files::sub_folders($dir_snapshots);
        if (empty($folders)) {
            return ['snapshots' => files::get_files($dir_snapshots, ['png'], false)];
        } else {
            $snapshots = [];
            foreach ($folders as $folder) {
                $folder_name = basename($folder);
                $snapshots[$folder_name] = files::get_files($folder, ['png'], false);
            }
            return $snapshots;
        }
    }
}
