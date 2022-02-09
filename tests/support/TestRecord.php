<?php

namespace NSWDPC\Pruner\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * A test record with no files
 */
class TestRecord extends DataObject implements TestOnly, PrunerInterface
{

    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'PrunerTestRecord';

    /**
     * Database fields
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar(255)',
        'DateCheck' => 'DBDatetime',
    ];

    public function pruneList($days_ago, $limit)
    {
        $dt = new \DateTime();
        $dt->modify('now -' . $days_ago . ' days');
        $datetime_formatted = $dt->format('Y-m-d H:i:s');
        $list = TestRecord::get()->filter('DateCheck:LessThan', $datetime_formatted);
        return $list;
    }

    public function onBeforePrune()
    {
    }

    public function onAfterPrune()
    {
    }

    public function pruneFilesList()
    {
        return false;
    }
}
