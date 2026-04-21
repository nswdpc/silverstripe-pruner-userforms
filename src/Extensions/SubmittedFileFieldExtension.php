<?php

namespace NSWDPC\Pruner;

use SilverStripe\Core\Extension;
use SilverStripe\Assets\File;

/**
 * SubmittedFileField extension handling
 * @extends \SilverStripe\Core\Extension<(\SilverStripe\UserForms\Model\Submission\SubmittedFileField & static)>
 */
class SubmittedFileFieldExtension extends Extension
{
    /**
     * Prior to field delete, remove linked file
     */
    public function onBeforeDelete()
    {
        $file = $this->getOwner()->UploadedFile();
        if ($file && $file->exists()) {
            $result = $file->deleteFile();
            $result = $file->doArchive();
        }
    }

}
