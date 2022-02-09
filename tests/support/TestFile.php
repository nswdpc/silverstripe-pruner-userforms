<?php

namespace NSWDPC\Pruner\Tests;

use SilverStripe\Assets\File;
use SilverStripe\Dev\TestOnly;

/**
 * A test file
 * @author James
 */
class TestFile extends File implements TestOnly
{

    /**
    * Database fields
    * @var array
    */
    private static $has_one = [
        'TestRecord' => TestWithFile::class,
    ];
}
