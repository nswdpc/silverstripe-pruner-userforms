<?php

namespace NSWDPC\Pruner;

use NSWDPC\Pruner\Pruner;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;
use Silverstripe\Assets\Dev\TestAssetStore;
use SilverStripe\UserForms\Model\Submission\SubmittedForm;
use SilverStripe\UserForms\Model\UserDefinedForm;
use SilverStripe\UserForms\Model\Submission\SubmittedFileField;

/**
 * Test pruning of {@link SubmittedForm} via the {@link NSWDPC\Pruner\SubmittedFormExtension}
 * @author James
 */
class SubmittedFormTest extends SapphireTest
{

    /**
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @var string
     */
    protected static $fixture_file = 'SubmittedFormTest.yml';

    /**
     * @var int
     */
    protected $days_ago = 30;

    /**
     * @var int
     */
    protected $limit = 500;

    public function setUp()
    {
        parent::setUp();

        TestAssetStore::activate('SubmittedFormTest');
        $fileIDs = $this->allFixtureIDs(File::class);
        foreach ($fileIDs as $fileID) {
            /** @var File $file */
            $file = DataObject::get_by_id(File::class, $fileID);
            $file->setFromString(str_repeat('x', 1000000), $file->getFilename());
        }
    }

    public function tearDown()
    {
        parent::tearDown();
        TestAssetStore::reset();
    }

    public function testPruneSubmittedForm()
    {

        $target_models = [
            SubmittedForm::class
        ];

        $pruner = Pruner::create();

        $totalRecords = SubmittedForm::get();
        $totalRecordsCount = $totalRecords->count();

        $removeFiles = $keepFiles = [];
        $files = File::get();
        foreach($files as $file) {
            if( strpos($file->Name, "remove") === 0 ) {
                $removeFiles[] = $file->ID;
            } else if( strpos($file->Name, "keep") === 0 ) {
                $keepFiles[] = $file->ID;
            } else {
                throw new \InvalidArgumentException("File names should be prefixed remove or keep for this test");
            }
        }

        $results = $pruner->prune($this->days_ago, $this->limit, $target_models);

        $this->assertTrue(is_array($results) && isset($results['total']) && isset($results['pruned']), "Result is sane");

        // get not pruned
        $unpruned = $totalRecordsCount - $results['pruned'];

        // check record count removed
        $this->assertEquals(1, $results['pruned'], "Pruned == expectedToRemove count");
        // check records remaining
        $this->assertEquals(1, $unpruned, "Unpruned == expectedToKeep count");

        $fileNames = File::get()->filter(['ID' => $keepFiles])->column('Name');

        $this->assertEquals( $keepFiles, File::get()->filter(['ID' => $keepFiles])->column('ID'), "Kept files match" );
        $this->assertEquals( 0, File::get()->filter(['ID' => $removeFiles])->count(), "Remove files gone" );

        $this->assertEmpty($results['keys'], 'Keys in results are empty');
        $this->assertFalse($results['report_only'], 'Was not report_only');


    }

}
