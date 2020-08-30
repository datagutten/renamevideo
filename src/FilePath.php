<?php


namespace datagutten\renamevideo;

use datagutten\tools\files\files;
use FileNotFoundException;

class FilePath
{
    public $folder;
    public $folder_delete;
    public $folder_snapshots;
    public $sub_folder;

    /**
     * recording_path constructor.
     * @param $root_path
     * @param $sub_folder
     * @throws FileNotFoundException
     */
    public function __construct($root_path, $sub_folder)
    {
        if (!file_exists($root_path)) {
            throw new FileNotFoundException($root_path);
        }
        $this->sub_folder = $sub_folder;
        $this->folder = files::path_join($root_path, $sub_folder);
        if (!file_exists($this->folder)) {
            throw new FileNotFoundException($this->folder);
        }
        $this->folder_delete = $this->folder.'/delete';
        $this->folder_snapshots = $this->folder.'/snapshots';
        umask(0);
    }

    public function filePath($file_name, $extension = null)
    {
        if (empty($extension)) {
            return files::path_join($this->folder, $file_name);
        } else {
            if ($extension[0] != '.') {
                $extension = '.'.$extension;
            }
            //return $this->folder . '/' . $file_name . $extension;
            return files::path_join($this->folder, $file_name.$extension);
        }
    }
}
