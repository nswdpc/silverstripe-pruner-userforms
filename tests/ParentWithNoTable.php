<?php

namespace NSWDPC\Pruner\Tests;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;
use SilverStripe\UserForms\Model\UserDefinedForm;

/**
 * A parent with no DB table, for testing that aspect
 */
class ParentWithNoTable extends UserDefinedForm implements TestOnly
{

    public function someSpecificMethod() : ?string {
        return null;
    }
}
