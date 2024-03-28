<?php

namespace NSWDPC\Pruner;

use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\SS_List;
use SilverStripe\Assets\File;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\UserForms\Model\Submission\SubmittedForm;

/**
 * Provides methods that the {@link PrunerModel} requires to prune SubmittedForm records
 * @note remove backup
 */
class SubmittedFormExtension extends DataExtension implements PrunerInterface
{

    /**
     * @note due to Parent relationship changing, a list of Parent classes is found, then tested for the AutoPrune field
     * In this case, $limit is per parent class
     * @param int $beforeDaysAgo set upper limit of age of record
     * @param int $limit limit of records to get
     * @return SS_List
     */
    public function pruneList(int $beforeDaysAgo, int $limit) : SS_List
    {

        try {
            $multiList = ArrayList::create();
            // get all possible parents
            $submittedFormTableName = DataObject::getSchema()->tableName( SubmittedForm::class );
            $result = DB::query("SELECT \"ParentClass\" "
                . " FROM \"" . Convert::raw2sql($submittedFormTableName) . "\""
                . " GROUP BY \"ParentClass\"");
            $seen = [];
            if ($result) {
                foreach ($result as $record) {
                    // The classname to use is the parent data class that has a table
                    $parentDataClass = $this->getDataClassAncestor($record['ParentClass']);
                    if(!$parentDataClass) {
                        Logger::log("Class '{$record['ParentClass']}' has no ancestor ancestor that can be queried", "INFO");
                        continue;
                    }
                    // may have already retrieve for this data class
                    if(in_array($parentDataClass, $seen)) {
                        continue;
                    }
                    $seen[] = $parentDataClass;// record as 'seen'
                    $list = $this->getSubmittedForms($parentDataClass, $beforeDaysAgo, $limit);
                    if ($list) {
                        // Merge into current list
                        $multiList->merge($list);
                    }
                }
            }
        } catch (\Exception $e) {
            Logger::log("Failed to get list for pruning. Error=" . $e->getMessage(), "NOTICE");
        }
        return $multiList;
    }

    /**
     * Get submitted forms for a parent class, taking into account that the parent class
     * may be subclassed, and that sub class has no table
     * The parent class should have the AutoPruneExtension applied
     * @param string $parentClass FQCN of the parent class of the submitted form
     * @param int $beforeDaysAgo set upper limit of age of record
     * @param int $limit limit of records to get
     */
    public function getSubmittedForms(string $parentClass, string $beforeDaysAgo, int $limit) : ?DataList {
        $tableName = DataObject::getSchema()->tableName( $parentClass );
        if(!$tableName) {
            return null;
        }
        $hasTable = ClassInfo::hasTable($tableName);
        if(!$hasTable) {
            // This would be a parent class of a SubmittedForm that has no DB fields
            // and therefore no table
            return null;
        }
        // Age boundary
        $dt = new \DateTime();
        $dt->modify("now -{$beforeDaysAgo} days");
        $beforeDate = $dt->format('Y-m-d H:i:s');
        /**
         * include all subclasses of this parent class
         * as this will cover subclasses of the parent with no tables
         */
        $subClasses = ClassInfo::subclassesFor($parentClass, true);
        $list = SubmittedForm::get()
            ->innerJoin(
                Convert::raw2sql($tableName),
                "\"SubmittedForm\".\"ParentID\" = \"" . Convert::raw2sql($tableName) . "\".\"ID\""
                . " AND \"" . Convert::raw2sql($tableName) . "\".\"AutoPrune\" = 1"
            )->filter([
                "Created:LessThan" => $beforeDate,
                "ParentClass" => $subClasses
            ])->sort('Created ASC')
            ->limit($limit);
        return $list ? $list : null;
    }

    /**
     * Get the first ancestor class of this class that has a DB table
     * and has the AutoPrune field, starting with root class
     * @param string $className
     * @return string|null
     */
    protected function getDataClassAncestor(string $className) : ?string {
        $ancestry = array_reverse(ClassInfo::ancestry($className, true));
        foreach($ancestry as $ancestorClassName) {
            $fieldSpec = DataObject::getSchema()->fieldSpec(
                $ancestorClassName,
                "AutoPrune",
                DataObjectSchema::DB_ONLY|DataObjectSchema::UNINHERITED
            );
            if($fieldSpec) {
                return $ancestorClassName;
            }
        }
        return null;
    }

    /**
     * Called by {@link Pruner} prior call to prune()
     * See {@link SubmittedForm::onBeforeDelete} - SubmittedFileField does not appear to delete its attached file
     * Submitted Form values are deleted in {@link SubmittedForm::onBeforeDelete}
     */
    public function onBeforePrune() : void
    {
    }

    /**
     * Called by {@link Pruner} after call to prune()
     */
    public function onAfterPrune() : void
    {
    }

    /**
     * Since silverstripe/userforms:5.9.2 files are automatically deleted in SubmittedFileField
     * @see composer.json version restriction
     */
    public function pruneFilesList() : SS_List
    {
        return ArrayList::create();
    }
}
