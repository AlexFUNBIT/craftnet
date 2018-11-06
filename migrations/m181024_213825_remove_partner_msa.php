<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;

/**
 * m181024_213825_remove_partner_msa migration.
 */
class m181024_213825_remove_partner_msa extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->dropColumn('craftnet_partners', 'msaAssetId');

        $volumes = Craft::$app->getVolumes();
        $volume = $volumes->getVolumeByHandle('partnerDocuments');

        if ($volume) {
            $volumes->deleteVolume($volume);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->addColumn('craftnet_partners', 'msaAssetId', $this->integer());
        return true;
    }
}
