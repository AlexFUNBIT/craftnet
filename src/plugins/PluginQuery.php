<?php

namespace craftnet\plugins;

use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use craftnet\Module;
use yii\db\Connection;

/**
 * @method Plugin[]|array all($db = null)
 * @method Plugin|array|null one($db = null)
 * @method Plugin|array|null nth(int $n, Connection $db = null)
 */
class PluginQuery extends ElementQuery
{
    /**
     * @var string|string[]|null The handle(s) that the resulting plugins must have.
     */
    public $handle;

    /**
     * @var string|string[]|null The license(s) that the resulting plugins must have.
     */
    public $license;

    /**
     * @var int|int[]|null The category ID(s) that the resulting plugins must have.
     */
    public $categoryId;

    /**
     * @var int|int[]|null The user ID(s) that the resulting plugins’ developers must have.
     */
    public $developerId;

    /**
     * @var int|int[]|null The Composer package ID(s) that the resulting plugins must be associated with.
     */
    public $packageId;

    /**
     * @var bool Whether info about the latest release should be included
     */
    public $withLatestReleaseInfo = false;

    /**
     * @var string|null Craft version the latest release must be compatible with
     */
    public $cmsVersion;

    /**
     * @var string|null Minimum stability the latest release must have
     */
    public $minStability;

    /**
     * @var bool Whether a stable release should be returned if possible
     */
    public $preferStable = true;

    /**
     * @var bool Whether info about the total purchases should be included
     */
    public $withTotalPurchases = false;

    /**
     * @var \DateTime How for back to look for total purchases
     */
    public $totalPurchasesSince;

    /**
     * @inheritdoc
     */
    public function __construct($elementType, array $config = [])
    {
        // Default orderBy
        if (!isset($config['orderBy'])) {
            $config['orderBy'] = 'name';
        }

        parent::__construct($elementType, $config);
    }

    /**
     * Sets the [[handle]] property.
     *
     * @param string|string[]|null $value The property value
     *
     * @return static self reference
     */
    public function handle($value)
    {
        $this->handle = $value;
        return $this;
    }

    /**
     * Sets the [[license]] property.
     *
     * @param string|string[]|null $value The property value
     *
     * @return static self reference
     */
    public function license($value)
    {
        $this->license = $value;
        return $this;
    }

    /**
     * Sets the [[categoryId]] property.
     *
     * @param int|int[]|null $value The property value
     *
     * @return static self reference
     */
    public function categoryId($value)
    {
        $this->categoryId = $value;
        return $this;
    }

    /**
     * Sets the [[developerId]] property.
     *
     * @param int|int[]|null $value The property value
     *
     * @return static self reference
     */
    public function developerId($value)
    {
        $this->developerId = $value;
        return $this;
    }

    /**
     * Sets the [[packageId]] property.
     *
     * @param int|int[]|null $value The property value
     *
     * @return static self reference
     */
    public function packageId($value)
    {
        $this->packageId = $value;
        return $this;
    }

    /**
     * Sets the [[withLatestReleaseInfo]], [[cmsVersion]], and [[minStability]] properties.
     *
     * @param bool $withLatestReleaseInfo
     * @param string|null $cmsVersion
     * @param string|null $minStability
     * @param bool $preferStable
     * @return static self reference
     */
    public function withLatestReleaseInfo(bool $withLatestReleaseInfo = true, string $cmsVersion = null, string $minStability = null, $preferStable = true)
    {
        $this->withLatestReleaseInfo = $withLatestReleaseInfo;
        $this->cmsVersion = $cmsVersion;
        $this->minStability = $minStability;
        $this->preferStable = $preferStable;
        return $this;
    }

    /**
     * Sets the [[withTotalPurchases]] and [[totalPurchasesSince]] properties.
     *
     * @param bool $withTotalPurchases
     * @param \DateTime|null $since
     * @return static self reference
     */
    public function withTotalPurchases(bool $withTotalPurchases = true, \DateTime $since = null)
    {
        $this->withTotalPurchases = $withTotalPurchases;
        $this->totalPurchasesSince = $since;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('craftnet_plugins');

        $this->query->select([
            'craftnet_plugins.developerId',
            'craftnet_plugins.packageId',
            'craftnet_plugins.iconId',
            'craftnet_plugins.packageName',
            'craftnet_plugins.repository',
            'craftnet_plugins.name',
            'craftnet_plugins.handle',
            'craftnet_plugins.license',
            'craftnet_plugins.shortDescription',
            'craftnet_plugins.longDescription',
            'craftnet_plugins.documentationUrl',
            'craftnet_plugins.changelogPath',
            'craftnet_plugins.activeInstalls',
            'craftnet_plugins.pendingApproval',
            'craftnet_plugins.keywords',
            'craftnet_plugins.dateApproved',
            'craftnet_plugins.published',
        ]);

        if ($this->handle) {
            $this->subQuery->andWhere(Db::parseParam('craftnet_plugins.handle', $this->handle));
        }

        if ($this->license) {
            $this->subQuery->andWhere(Db::parseParam('craftnet_plugins.license', $this->license));
        }

        if ($this->developerId) {
            $this->subQuery->andWhere(Db::parseParam('craftnet_plugins.developerId', $this->developerId));
        }

        if ($this->packageId) {
            $this->subQuery->andWhere(Db::parseParam('craftnet_plugins.packageId', $this->packageId));
        }

        if ($this->categoryId) {
            $this->subQuery
                ->innerJoin(['craftnet_plugincategories pc'], '[[pc.pluginId]] = [[elements.id]]')
                ->andWhere(Db::parseParam('pc.categoryId', $this->categoryId));
        }

        if ($this->withLatestReleaseInfo) {
            $maxCol = $this->preferStable ? 'stableOrder' : 'order';
            $latestReleaseQuery = (new Query())
                ->select(["max([[s_vo.{$maxCol}]])"])
                ->from(['craftnet_pluginversionorder s_vo'])
                ->innerJoin(['craftnet_packageversions s_v'], '[[s_v.id]] = [[s_vo.versionId]]')
                ->where('[[s_v.packageId]] = [[craftnet_plugins.packageId]]')
                ->groupBy(['s_v.packageId']);

            $packageManager = Module::getInstance()->getPackageManager();

            if ($this->cmsVersion) {
                $cmsRelease = $packageManager->getRelease('craftcms/cms', $this->cmsVersion);
                if ($cmsRelease) {
                    $latestReleaseQuery
                        ->innerJoin(['craftnet_pluginversioncompat s_vc'], '[[s_vc.pluginVersionId]] = [[s_v.id]]')
                        ->andWhere(['s_vc.cmsVersionId' => $cmsRelease->id]);
                }
            }

            if ($this->minStability) {
                $latestReleaseQuery->andWhere([
                    's_v.stability' => $packageManager->getStabilities($this->minStability)
                ]);
            }

            $this->subQuery
                ->addSelect(['v.version as latestVersion', 'v.time as latestVersionTime'])
                ->innerJoin(['craftnet_packageversions v'], '[[v.packageId]] = [[craftnet_plugins.packageId]]')
                ->innerJoin(['craftnet_pluginversionorder vo'], '[[vo.versionId]] = [[v.id]]')
                ->andWhere(["vo.{$maxCol}" => $latestReleaseQuery]);
            $this->query
                ->addSelect(['latestVersion', 'latestVersionTime']);
        }

        if ($this->withTotalPurchases) {
            $totalPurchasesSubquery = (new Query())
                ->select(['count(*)'])
                ->from(['craftnet_plugins p'])
                ->innerJoin('craftnet_pluginlicenses pl', '[[pl.pluginId]] = [[p.id]]')
                ->innerJoin('craftnet_pluginlicenses_lineitems pl_li', '[[pl_li.licenseId]] = [[pl.id]]')
                ->where('[[p.id]] = [[craftnet_plugins.id]]');

            if ($this->totalPurchasesSince) {
                $totalPurchasesSubquery->andWhere(['>=', 'pl.dateCreated', Db::prepareDateForDb($this->totalPurchasesSince)]);
            }

            $this->subQuery
                ->addSelect([
                    'totalPurchases' => $totalPurchasesSubquery
                ]);
            $this->query
                ->addSelect(['totalPurchases']);
        }

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    protected function statusCondition(string $status)
    {
        if ($status === Plugin::STATUS_PENDING) {
            return ['elements.enabled' => false, 'craftnet_plugins.pendingApproval' => true];
        }

        return parent::statusCondition($status);
    }
}
