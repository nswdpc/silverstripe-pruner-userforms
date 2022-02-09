<?php

namespace NSWDPC\Pruner;

use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Assets\File;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;

/**
 * Provides methods that the {@link PrunerModel} requires to prune SubmittedForm records
 * @note remove backup
 */
class SubmittedFormExtension extends DataExtension implements PrunerInterface
{

    /**
     * @returns ArrayList
     * @note due to Parent relationship changing, a list of Parent classes is found, then tested for the AutoPrune field
     * In this case, $limit is per parent class
     * @todo write a test for non UDF parent classes
     */
    public function pruneList(int $days_ago, int $limit)
    {
        $dt = new \DateTime('now -' . $days_ago . ' days');
        $datetime_formatted = $dt->format('Y-m-d H:i:s');

        // get all possible parents
        $result = DB::query("SELECT `ParentClass` FROM `SubmittedForm` GROUP BY `ParentClass`");
        $parents = [];
        if ($result) {
            foreach ($result as $record) {
                $parents[] = $record['ParentClass'];
            }
        }

        $multilist = ArrayList::create();
        if (!empty($parents)) {
            foreach ($parents as $parent_class) {
                try {
                    $table = Config::inst()->get($parent_class, 'table_name', Config::UNINHERITED);
                    if (!$table) {
                        $table = $parent_class;
                    }
                    $list = \SilverStripe\UserForms\Model\Submission\SubmittedForm::get()
                                // limit to autoprune parents
                                ->innerJoin(
                                    $table,
                                    "\"SubmittedForm\".\"ParentID\" = \"" . Convert::raw2sql($table) . "\".\"ID\""
                                    . " AND \"" . Convert::raw2sql($table) . "\".\"AutoPrune\" = 1"
                                )
                                ->filter('Created:LessThan', $datetime_formatted)
                                ->sort('Created ASC')
                                ->limit($limit);
                    if ($list) {
                        $multilist->merge($list);
                    }
                } catch (\Exception $e) {
                    Log::log("Failed to get SubmittedForm record list. Error=" . $e->getMesssage(), Log::NOTICE);
                }
            }
        }
        return $multilist;
    }

    /**
     * Called by {@link Pruner} prior call to prune()
     * See {@link SubmittedForm::onBeforeDelete} - SubmittedFileField does not appear to delete its attached file
     * Submitted Form values are deleted in {@link SubmittedForm::onBeforeDelete}
     */
    public function onBeforePrune()
    {
        // delete all files
        $files = $this->pruneFilesList();
        foreach ($files as $file) {
            $file->delete();
        }
    }

    /**
     * Called by {@link Pruner} after call to prune()
     */
    public function onAfterPrune()
    {
    }

    /**
     * @return DataList
     * Every SubmittedForm with an upload field has a {@link SubmittedFormField} which is an instance of {@link SubmittedFileField}
     */
    public function pruneFilesList()
    {
        $list = ArrayList::create();
        if ($fields = $this->owner->Values()) {
            foreach ($fields as $field) {
                // push files that match the following fields
                if ($field instanceof \SilverStripe\UserForms\Model\Submission\SubmittedFileField) {
                    $file = $field->UploadedFile();
                    if (!empty($file->ID) && $file instanceof File) {
                        $list->push($file);
                    }
                }
            }
        }
        return $list;
    }
}
