<?php

namespace NSWDPC\Pruner\Tests;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;

/**
 * A test record with files
 */
class TestRecordWithFile extends DataObject implements TestOnly, PrunerInterface
{

    /**
     * Database fields
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar(255)',
        'DateCheck' => 'DBDatetime',
    ];
    
    /*
     * @var array
     */
    private static $has_many = [
        'Files' => TestFile::class
    ];

    public function pruneList($days_ago, $limit)
    {
        $dt = new \DateTime();
        $dt->modify('now -' . $days_ago . ' days');
        $datetime_formatted = $dt->format('Y-m-d H:i:s');
        $list = TestWithFile::get()->filter('DateCheck:LessThan', $datetime_formatted);
        return $list;
    }

    public function onBeforeDelete()
    {
        parent::onBeforeDelete();
        // delete all linked files
        $files = $this->Files();
        foreach ($files as $file) {
            $file->delete();
        }
    }

    public function onBeforePrune()
    {
    }

    public function onAfterPrune()
    {
    }

    public function pruneFilesList()
    {
        return $this->Files();
    }
}
