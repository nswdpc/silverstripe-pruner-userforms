<?php

namespace NSWDPC\Pruner;

use SilverStripe\Dev\SapphireTest;
use Silverstripe\Assets\File;
use Silverstripe\Assets\Folder;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

/**
 * Test pruning of {@link SubmittedForm} with the {@link NSWDPC\Utility\Pruner\SubmittedFormExtension}
 */
class SubmittedFormTest extends SapphireTest
{
    const TEST_FILE = 'prIk6PdCrgg.jpg';

    protected $test_file_hash = "";
    protected $copied_files = [];
    protected $test_asset_directory = "";
    private $test_asset_folder;

    protected $usesDatabase = true;

    public function __construct()
    {
        parent::__construct();

        // handle stupidty around Elemental's TestPage gatecrashing
        if (class_exists('DNADesign\Elemental\Models\BaseElement')) {
            self::$extra_dataobjects[] = 'DNADesign\Elemental\Tests\Src\TestPage';
        }
    }

    public function setUp()
    {
        parent::setUp();

        // do this only once
        $source = dirname(__FILE__) . '/files/' . self::TEST_FILE;
        $this->test_file_hash = hash('sha256', file_get_contents($source));

        // create an assets dir to store files in
        $this->test_asset_directory = 'PrunerSubmittedFormTest';
    }

    public function tearDown()
    {
        $this->unlinkCopiedFiles();
        parent::tearDown();
    }

    private function unlinkCopiedFiles()
    {
        if ($this->test_asset_folder instanceof Folder) {
            $this->test_asset_folder->delete();
        }
    }

    private function checkFileHash($data)
    {
        $target_hash = hash('sha256', $data);
        return $target_hash == $this->test_file_hash;
    }

    /**
    * @returns File
    */
    private function createFile($prefix, $record_id)
    {
        $folder = Folder::find_or_make($this->test_asset_directory);
        $this->assertTrue($folder instanceof Folder && !empty($folder->ID));

        $this->test_asset_folder = $folder;

        $filename = $prefix . "_" . self::TEST_FILE;
        $file_filename = $this->test_asset_directory . "/" . $filename;

        $source = dirname(__FILE__) . '/files/' . self::TEST_FILE;
        $this->assertTrue(is_readable($source), "Source {$source} is not readable");

        $file = File::create();
        ;
        $file->ParentID = $folder->ID;
        $file->Name = $file->Title = $filename;
        $file_id = $file->write();

        $file->setFromString(file_get_contents($source), $file_filename);
        $file->write();

        $this->assertEquals($file_id, $file->ID);
        return $file;
    }

    /**
     * 1. Create a SubmittedForm
     * 2. Attach a field to it
     * 3. Attach a file to it
     */
    public function testSubmittedForm()
    {
        if (!class_exists("\SilverStripe\UserForms\Model\Submission\SubmittedForm")) {
            return;
        }

        $days_ago = 30;
        $limit = 500;

        $model = \SilverStripe\UserForms\Model\Submission\SubmittedForm::class;
        $targets = [
            $model
        ];

        $pruner = Pruner::create();

        // create a UserDefinedForm as the parent
        $user_defined_form = \SilverStripe\UserForms\Model\UserDefinedForm::create();
        $user_defined_form->AutoPrune = 1;// enable pruning
        $user_defined_form->Title = "testSubmittedForm";
        $udf_id = $user_defined_form->write();

        $keep = $discard = 0;
        $ids = [];

        $total_test_records = 4;

        $dt = new DateTime();
        $dt->modify("-{$days_ago} days");// put it on the boundary
        $discard_dt = clone($dt);
        $discard_dt->modify("-5 days");// 35 days ago will discard
        $keep_dt = clone($dt);
        $keep_dt->modify("+5 days");// 25 days will keep

        $this->copied_files = [];
        for ($i=0; $i<$total_test_records; $i++) {
            if ($i % 2 == 0) {
                $datetime_formatted = $discard_dt->format('Y-m-d H:i:s');
                $discard++;
            } else {
                $datetime_formatted = $keep_dt->format('Y-m-d H:i:s');
                $keep++;
            }

            // SubmittedForm records have no Title
            $data = [
                'Created' => $datetime_formatted
            ];

            // create the submitted form record
            $record = \SilverStripe\UserForms\Model\Submission\SubmittedForm::create($data);
            $record->ParentID = $udf_id;// link to the parent allowing opt-ins
            $record->ParentClass = \SilverStripe\UserForms\Model\UserDefinedForm::class;// record can now have any Parent
            $id = $record->write();
            if (!$id) {
                throw new Exception("Failed to write SubmittedForm record");
            }

            Logger::log("Wrote SubmittedForm record #{$id}", Logger::DEBUG);

            // a test field
            $field = \SilverStripe\UserForms\Model\Submission\SubmittedFormField::create([
                "Name" => "Name of SubmittedFormField",
                "Value" => "SubmittedFormField value for form {$id}",
                "Title" => "Title of SubmittedFormField",
                "ParentID" => $id
            ]);

            $field_id = $field->write();

            // create two files
            $submitted_file = $this->createFile("sf" . $id, $id);
            Logger::log("Created SF {$submitted_file->ID}", Logger::DEBUG);
            // store the file - for later checks
            $this->copied_files[] = [
                'RecordID' => $id,
                'FileID' => $submitted_file->ID,
                'FilePath' => $submitted_file->Filename,
            ];
            // a test  SubmittedFileField
            $submitted_file_field = \SilverStripe\UserForms\Model\Submission\SubmittedFileField::create([
                "UploadedFileID" => $submitted_file->ID,// link to the file just created
                "Value" => "SubmittedFormField value for form {$id}",
                "Title" => "Title of SubmittedFormField",
                "ParentID" => $id
            ]);
            $submitted_file_field->write();

            Logger::log("Completed SubmittedForm write #{$id}", Logger::DEBUG);

            // store records created
            $ids[] = $id;
        }// end record creation

        $records = \SilverStripe\UserForms\Model\Submission\SubmittedForm::get();

        $records_count = $records->count();

        $this->assertEquals($records_count, $total_test_records, "Records count ({$records_count}) != total test records ({$total_test_records})");

        $results = $pruner->prune($days_ago, $limit, $targets);

        $this->assertTrue(is_array($results) && isset($results['total']) && isset($results['pruned']), "Result is not sane");
        // the amount pruned must match what we expect
        $this->assertEquals($results['pruned'], $discard, "Pruned ({$results['pruned']}) != discard ({$discard})");

        $unpruned = $results['total'] - $results['pruned'];

        // check that they have been deleted
        $kept = 0;
        foreach ($ids as $id) {
            $record = \SilverStripe\UserForms\Model\Submission\SubmittedForm::get()->byId($id);
            if (!empty($record->ID)) {
                $kept++;
            }
        }

        // records in the table should equal the records we have kept
        $this->assertTrue($kept == $keep, "Not all records pruned {$kept}/{$keep}");

        $this->assertTrue(!empty($results['keys']), 'Keys in results are empty');
        $this->assertTrue(!empty($results['file_keys']), 'File_Keys in results are empty');
    } // end test
}
