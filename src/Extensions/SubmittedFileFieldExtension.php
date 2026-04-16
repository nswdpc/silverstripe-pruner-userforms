<?php

namespace NSWDPC\Pruner;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Assets\File;

/**
 * SubmittedFileField extension handling
 * @extends \SilverStripe\ORM\DataExtension<(\SilverStripe\UserForms\Model\Submission\SubmittedFileField & static)>
 */
class SubmittedFileFieldExtension extends DataExtension
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
