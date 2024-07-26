<?php

namespace NSWDPC\Pruner\Tests;

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
 * for SubmittedForm records with a parent that has no DB table
 * @author James
 */
class SubmittedFormParentWithNoTableTest extends SapphireTest
{

    /**
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @var string
     */
    protected static $fixture_file = 'SubmittedFormParentWithNoTableTest.yml';

    /**
     * @var array
     */
    protected static $extra_dataobjects = [
        ParentWithNoTable::class
    ];

    /**
     * @var int
     */
    protected $days_ago = 30;

    /**
     * @var int
     */
    protected $limit = 500;

    public function setUp() : void
    {
        parent::setUp();

        TestAssetStore::activate('SubmittedFormParentWithNoTableTest');
        $fileIDs = $this->allFixtureIDs(File::class);
        foreach ($fileIDs as $fileID) {
            /** @var File $file */
            $file = DataObject::get_by_id(File::class, $fileID);
            $file->setFromString(str_repeat('x', 1000000), $file->getFilename());
            $file->write();
        }
    }

    public function tearDown() : void
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
                $removeFiles[$file->ID] = TestAssetStore::getLocalPath($file);
            } else if( strpos($file->Name, "keep") === 0 ) {
                $keepFiles[$file->ID] = TestAssetStore::getLocalPath($file);
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

        $this->assertEquals( array_keys($keepFiles), File::get()->filter(['ID' => array_keys($keepFiles)])->column('ID'), "Kept files match" );
        $this->assertEquals( 0, File::get()->filter(['ID' => array_keys($removeFiles)])->count(), "Remove files gone" );
        foreach($keepFiles as $keepFileId => $keepFilePath) {
            $this->assertTrue(file_exists($keepFilePath));
        }
        foreach($removeFiles as $removeFileId => $removeFilePath) {
            $this->assertFalse(file_exists($removeFilePath));
        }

        $this->assertEmpty($results['keys'], 'Keys in results are empty');
        $this->assertFalse($results['report_only'], 'Was not report_only');


    }

}
