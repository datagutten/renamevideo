<?php


namespace datagutten\renamevideo;

use datagutten\xmltv\tools\exceptions\XMLTVException;
use FileNotFoundException;
use UnexpectedValueException;

/**
 * Extension of recording with twig-friendly exception handling
 * @package datagutten\renamevideo
 */
class RecordingTwig extends Recording
{
    public function programs()
    {
        try {
            return parent::programs();
        } catch (XMLTVException $e) {
            return [['error' => $e]];
        }
    }

    public function snapshots()
    {
        try {
            return parent::snapshots();
        } catch (UnexpectedValueException $e) {
            return ['error' => $e];
        }
    }

    public function eitInfo()
    {
        try {
            return parent::eitInfo();
        } catch (FileNotFoundException $e) {
            return null;
        }
    }
}
