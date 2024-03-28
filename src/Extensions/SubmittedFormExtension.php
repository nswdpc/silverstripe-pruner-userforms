<?php

namespace NSWDPC\Pruner;

use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\ArrayList;
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
     * @return ArrayList
     */
    public function pruneList(int $days_ago, int $limit) : SS_List
    {

        // Age boundary
        $dt = new \DateTime('now -' . $days_ago . ' days');
        $age = $dt->format('Y-m-d H:i:s');

        // get all possible parents
        $result = DB::query("SELECT `ParentClass` FROM `SubmittedForm` GROUP BY `ParentClass`");
        $parents = [];
        if ($result) {
            foreach ($result as $record) {
                $parents[ $record['ParentClass'] ] = DataObject::getSchema()->tableName( $record['ParentClass'] );
            }
        }

        $multilist = ArrayList::create();
        if (!empty($parents)) {
            foreach ($parents as $parentClass => $table) {
                try {
                    $list = SubmittedForm::get()
                        ->innerJoin(
                            $table,
                            "`SubmittedForm`.`ParentID` = `" . Convert::raw2sql($table) . "`.`ID`"
                            . " AND `SubmittedForm`.`ParentClass` = '" . Convert::raw2sql($parentClass) . "'"
                            . " AND `" . Convert::raw2sql($table) . "`.`AutoPrune` = 1"
                        )
                        ->filter('Created:LessThan', $age)
                        ->sort('Created ASC')
                        ->limit($limit);
                    if ($list) {
                        $multilist->merge($list);
                    }
                } catch (\Exception $e) {
                    Logger::log("Failed to get SubmittedForm record list. Error=" . $e->getMessage(), Logger::NOTICE);
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
