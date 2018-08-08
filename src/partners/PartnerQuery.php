<?php

namespace craftnet\partners;

use Craft;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use yii\db\Connection;

/**
 * @method Partner[]|array all($db = null)
 * @method Partner|array|null one($db = null)
 * @method Partner|array|null nth(int $n, Connection $db = null)
 */
class PartnerQuery extends ElementQuery
{
    /**
     * @var string|string[]|null Id of the managing user
     */
    public $ownerId;

    /**
     * @var string|string[]|null Name of the business
     */
    public $businessName;

    /**
     * @var string|string[]|null Primary contact full name
     */
    public $primaryContactName;

    /**
     * @var string|string[]|null Primary contact email address
     */
    public $primaryContactEmail;

    /**
     * @var string|string[]|null Primary contact phone number
     */
    public $primaryContactPhone;

    /**
     * @var string|string[]|null Short description of the business
     */
    public $businessSummary;

    /**
     * @var int|int[]|null Minimum budget in USD
     */
    public $minimumBudget;

    /**
     * @var string|string[]|null URL of the business’ Master Service Agreement or equivalent
     */
    public $msaLink;

    /**
     * Sets the [[ownerId]] property.
     *
     * @param int|int[]|null $value The property value
     *
     * @return static self reference
     */
    public function ownerId($value)
    {
        $this->ownerId = $value;
        return $this;
    }

    /**
     * Sets the [[businessName]] property.
     *
     * @param string|string[]|null $value The property value
     *
     * @return static self reference
     */
    public function businessName($value)
    {
        $this->businessName = $value;
        return $this;
    }

    /**
     * Sets the [[primaryContactName]] property.
     *
     * @param string|string[]|null $value The property value
     *
     * @return static self reference
     */
    public function primaryContactName($value)
    {
        $this->primaryContactName = $value;
        return $this;
    }

    /**
     * Sets the [[primaryContactEmail]] property.
     *
     * @param string|string[]|null $value The property value
     *
     * @return static self reference
     */
    public function primaryContactEmail($value)
    {
        $this->primaryContactEmail = $value;
        return $this;
    }

    /**
     * Sets the [[primaryContactPhone]] property.
     *
     * @param string|string[]|null $value The property value
     *
     * @return static self reference
     */
    public function primaryContactPhone($value)
    {
        $this->primaryContactPhone = $value;
        return $this;
    }

    /**
     * Sets the [[businessSummary]] property.
     *
     * @param string|string[]|null $value The property value
     *
     * @return static self reference
     */
    public function businessSummary($value)
    {
        $this->businessSummary = $value;
        return $this;
    }

    /**
     * Sets the [[$this->minimumBudget]] property.
     *
     * @param int|int[]|null $value The property value
     *
     * @return static self reference
     */
    public function minimumBudget($value)
    {
        $this->minimumBudget = $value;
        return $this;
    }

    /**
     * Sets the [[msaLink]] property.
     *
     * @param string|string[]|null $value The property value
     *
     * @return static self reference
     */
    public function msaLink($value)
    {
        $this->msaLink = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('craftnet_partners');

        $this->query->select([
            'craftnet_partners.ownerId',
            'craftnet_partners.businessName',
            'craftnet_partners.primaryContactName',
            'craftnet_partners.primaryContactEmail',
            'craftnet_partners.primaryContactPhone',
            'craftnet_partners.businessSummary',
            'craftnet_partners.minimumBudget',
            'craftnet_partners.msaLink',
        ]);

        $andColumns = [
            'ownerId',
            'businessName',
            'primaryContactName',
            'primaryContactEmail',
            'primaryContactPhone',
            'businessSummary',
            'minimumBudget',
            'msaLink',
        ];

        foreach($andColumns as $column) {
            if ($this->{$column}) {
                $this->subQuery->andWhere(Db::parseParam('craftnet_partners.' . $column, $this->{$column}));
            }
        }

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    protected function statusCondition(string $status)
    {
//        if ($status === Plugin::STATUS_PENDING) {
//            return ['elements.enabled' => false, 'craftnet_plugins.pendingApproval' => true];
//        }

        return parent::statusCondition($status);
    }
}
