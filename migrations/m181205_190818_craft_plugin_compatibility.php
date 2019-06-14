<?php

namespace craft\contentmigrations;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use craftnet\Module;

/**
 * m181205_190818_craft_plugin_compatibility migration.
 */
class m181205_190818_craft_plugin_compatibility extends Migration
{
    private $_stabilities = [
        'alpha' => 1,
        'beta' => 2,
        'RC' => 3,
        'stable' => 4,
    ];

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->dropColumn('craftnet_packages', 'latestVersion');
        $this->dropColumn('craftnet_plugins', 'latestVersion');

        $this->createTable('craftnet_pluginversionorder', [
            'versionId' => $this->integer()->notNull(),
            'pluginId' => $this->integer()->notNull(),
            'order' => $this->smallInteger()->unsigned()->notNull(),
            'stableOrder' => $this->smallInteger()->unsigned()->notNull(),
            'PRIMARY KEY([[versionId]])',
        ]);
        $this->createIndex(null, 'craftnet_pluginversionorder', ['versionId', 'order']);
        $this->createIndex(null, 'craftnet_pluginversionorder', ['versionId', 'stableOrder']);
        $this->createIndex(null, 'craftnet_pluginversionorder', ['pluginId']);
        $this->addForeignKey(null, 'craftnet_pluginversionorder', ['versionId'], 'craftnet_packageversions', ['id'], 'CASCADE');

        $this->createTable('craftnet_pluginversioncompat', [
            'pluginVersionId' => $this->integer()->notNull(),
            'cmsVersionId' => $this->integer()->notNull(),
            'PRIMARY KEY([[pluginVersionId]], [[cmsVersionId]])',
        ]);
        $this->addForeignKey(null, 'craftnet_pluginversioncompat', ['cmsVersionId'], 'craftnet_packageversions', ['id'], 'CASCADE');
        $this->addForeignKey(null, 'craftnet_pluginversioncompat', ['pluginVersionId'], 'craftnet_packageversions', ['id'], 'CASCADE');

        // get all the Craft releases
        echo '    > fetching Craft releases ... ';
        $cmsReleases = Module::getInstance()->getPackageManager()->getAllReleases('craftcms/cms', null);
        echo "done\n";

        // get mapping of plugin release ID => Craft constraints
        echo '    > fetching plugin releases ... ';
        $pluginData = (new Query())
            ->select(['p.id as pluginId', 'v.id as versionId', 'v.normalizedVersion as version', 'v.stability', 'd.constraints'])
            ->from(['craftnet_packageversions v'])
            ->innerJoin(['craftnet_plugins p'], '[[p.packageId]] = [[v.packageId]]')
            ->leftJoin(['craftnet_packagedeps d'], [
                'and',
                '[[d.versionId]] = [[v.id]]',
                ['d.name' => 'craftcms/cms']
            ])
            ->where(['v.valid' => true])
            ->all();
        echo "done\n";

        // build the order & compatibility data
        $pluginData = ArrayHelper::index($pluginData, 'versionId', 'pluginId');
        $orderData = [];
        $compatData = [];

        foreach ($pluginData as $pluginId => $releases) {
            echo '    > building order & compatibility data for plugin ' . $pluginId . ' ... ';

            // create the sort order arrays
            $releaseOrder = $this->_releaseOrder($releases);
            $releaseStableOrder = $this->_releaseStableOrder($releases, $releaseOrder);

            // process the releases
            foreach ($releases as $releaseId => $release) {
                $orderData[] = [
                    $release['versionId'],
                    $pluginId,
                    $releaseOrder[$releaseId],
                    $releaseStableOrder[$releaseId],
                ];

                // see which Craft versions this release works with
                if ($release['constraints'] !== null) {
                    foreach ($cmsReleases as $cmsRelease) {
                        if (Semver::satisfies($cmsRelease->version, $release['constraints'])) {
                            $compatData[] = [$release['versionId'], $cmsRelease->id];
                        }
                    }
                }
            }

            echo "done\n";
        }

        // insert the data
        $this->batchInsert('craftnet_pluginversionorder', [
            'versionId',
            'pluginId',
            'order',
            'stableOrder',
        ], $orderData, false);

        $this->batchInsert('craftnet_pluginversioncompat', [
            'pluginVersionId',
            'cmsVersionId',
        ], $compatData, false);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m181205_190818_craft_plugin_compatibility cannot be reverted.\n";
        return false;
    }

    /**
     * @param array $releases
     * @return array
     */
    private function _releaseOrder(array &$releases): array
    {
        // get versionId => version map
        $versions = array_column($releases, 'version', 'versionId');

        // sort it by version oldest-to-newest, preserving versionId keys
        uasort($versions, function($a, $b): int {
            return Comparator::equalTo($a, $b) ? 0 : (Comparator::lessThan($a, $b) ? -1 : 1);
        });

        // return versionId => sort order map
        return array_flip(array_keys($versions));
    }

    /**
     * @param array $releases
     * @param array $releaseOrder
     * @return array
     */
    private function _releaseStableOrder(array &$releases, array &$releaseOrder): array
    {
        // Create our multisort arrays
        $stabilities = [];
        $orders = [];
        $releaseIds = [];

        foreach ($releases as &$release) {
            $stabilities[] = $this->_stabilities[$release['stability']];
            $orders[] = $releaseOrder[$release['versionId']];
            $releaseIds[] = $release['versionId'];
        }
        unset($release);

        array_multisort($stabilities, SORT_NUMERIC, $orders, SORT_NUMERIC, $releaseIds);
        return array_flip($releaseIds);
    }
}
