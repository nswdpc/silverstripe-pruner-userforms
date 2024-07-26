<?php

namespace NSWDPC\Pruner;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Assets\File;

/**
 * SubmittedFileField extension handling
 */
class SubmittedFileFieldExtension extends DataExtension
{

    /**
     * Prior to field delete, remove linked file
     */
    public function onBeforeDelete()
    {
        $file = $this->owner->UploadedFile();
        if ($file && $file->exists()) {
            $result = $file->deleteFile();
            $result = $file->doArchive();
        }
    }

}
