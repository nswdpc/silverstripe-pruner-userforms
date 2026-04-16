<?php

namespace NSWDPC\Pruner\Tests;

use DNADesign\ElementalUserForms\Model\ElementForm;
use NSWDPC\Pruner\Pruner;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Assets\File;
use SilverStripe\ORM\DataObject;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\UserForms\Model\Submission\SubmittedForm;

/**
 * Test for ElementForm as a parent support from module
 * @author James
 */
class ElementFormTest extends SapphireTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @var string
     */
    protected static $fixture_file = 'ElementFormTest.yml';

    /**
     * @var int
     */
    protected $days_ago = 30;

    /**
     * @var int
     */
    protected $limit = 500;

    #[\Override]
    public function setUp(): void
    {

        if (class_exists(ElementForm::class)) {
            parent::setUp();

            TestAssetStore::activate('ElementFormTest');
            $fileIDs = $this->allFixtureIDs(File::class);
            foreach ($fileIDs as $fileID) {
                /** @var File $file */
                $file = DataObject::get_by_id(File::class, $fileID);
                $file->setFromString(str_repeat('x', 1000000), $file->getFilename());
                $file->write();
            }

        }
    }

    #[\Override]
    public function tearDown(): void
    {
        if (class_exists(ElementForm::class)) {
            parent::tearDown();
            TestAssetStore::reset();
        }
    }

    public function testPruneSubmittedFormInElementForm(): void
    {
        /**
         * If the class doesn't exist, the module is not installed
         */
        if (!class_exists(ElementForm::class)) {
            return;
        }

        $target_models = [
            SubmittedForm::class
        ];

        $pruner = Pruner::create();

        $totalRecords = SubmittedForm::get();
        $totalRecordsCount = $totalRecords->count();
        $removeFiles = [];
        $keepFiles = [];
        $files = File::get();
        foreach ($files as $file) {
            if (str_starts_with($file->Name, "remove")) {
                $removeFiles[$file->ID] = TestAssetStore::getLocalPath($file);
            } elseif (str_starts_with($file->Name, "keep")) {
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

        File::get()->filter(['ID' => $keepFiles])->column('Name');

        $this->assertEquals(array_keys($keepFiles), File::get()->filter(['ID' => array_keys($keepFiles)])->column('ID'), "Kept files match");
        $this->assertEquals(0, File::get()->filter(['ID' => $removeFiles])->count(), "Remove files gone");
        foreach ($keepFiles as $keepFilePath) {
            $this->assertTrue(file_exists($keepFilePath));
        }

        foreach ($removeFiles as $removeFilePath) {
            $this->assertFalse(file_exists($removeFilePath));
        }

        $this->assertEmpty($results['keys'], 'Keys in results are empty');
        $this->assertFalse($results['report_only'], 'Was not report_only');


    }

}
