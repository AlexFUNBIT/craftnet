<?php

namespace craftnet\plugins;

use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craftnet\base\EditionInterface;
use craftnet\base\License;
use craftnet\cms\CmsLicense;
use craftnet\Module;
use DateTime;

/**
 * @property string $shortKey
 */
class PluginLicense extends License
{
    public $id;
    public $pluginId;
    public $editionId;
    public $ownerId;
    public $cmsLicenseId;
    public $pluginHandle;
    public $edition;
    public $expirable = true;
    public $expired = false;
    public $autoRenew = false;
    public $reminded = false;
    public $renewalPrice;
    public $email;
    public $key;
    public $notes;
    public $privateNotes;
    public $lastVersion;
    public $lastAllowedVersion;
    public $lastActivityOn;
    public $lastRenewedOn;
    public $expiresOn;
    public $dateCreated;
    public $dateUpdated;
    public $uid;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['expirable', 'expired', 'plugin', 'edition', 'email', 'key'], 'required'],
            [['id', 'pluginId', 'editionId', 'ownerId', 'cmsLicenseId'], 'number', 'integerOnly' => true, 'min' => 1],
            [['email'], 'email'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributes()
    {
        $names = parent::attributes();
        ArrayHelper::removeValue($names, 'privateNotes');
        return $names;
    }

    /**
     * @inheritdoc
     */
    public function extraFields()
    {
        return [
            'plugin',
        ];
    }

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'lastActivityOn';
        $attributes[] = 'lastRenewedOn';
        $attributes[] = 'expiresOn';
        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @inheritdoc
     */
    public function getOwnerId(): ?int
    {
        return $this->ownerId;
    }

    /**
     * @inheritdoc
     */
    public function getIsExpirable(): bool
    {
        return $this->expirable;
    }

    /**
     * @inheritdoc
     */
    public function getExpiryDate(): ?DateTime
    {
        return DateTimeHelper::toDateTime($this->expiresOn, false, false) ?: null;
    }

    /**
     * @inheritdoc
     */
    public function getWillAutoRenew(): bool
    {
        return $this->autoRenew;
    }

    /**
     * @inheritdoc
     */
    public function getRenewalPrice(): float
    {
        return $this->renewalPrice;
    }

    /**
     * @inheritdoc
     */
    public function setRenewalPrice(float $renewalPrice)
    {
        $this->renewalPrice = $renewalPrice;
        Module::getInstance()->getPluginLicenseManager()->saveLicense($this, false);
    }

    /**
     * @inheritdoc
     */
    public function markAsReminded()
    {
        $this->reminded = true;
        Module::getInstance()->getPluginLicenseManager()->saveLicense($this, false);
    }

    /**
     * @inheritdoc
     */
    public function getWasReminded(): bool
    {
        return $this->reminded;
    }

    /**
     * @inheritdoc
     */
    public function markAsExpired()
    {
        $this->expired = true;
        $this->reminded = false;
        Module::getInstance()->getPluginLicenseManager()->saveLicense($this, false);
    }

    /**
     * @inheritdoc
     */
    public function getLastVersion(): ?string
    {
        return $this->lastVersion;
    }

    /**
     * @inheritdoc
     */
    public function getLastAllowedVersion(): ?string
    {
        return $this->lastAllowedVersion;
    }

    /**
     * @inheritdoc
     */
    public function getEdition(): EditionInterface
    {
        return PluginEdition::find()
            ->id($this->editionId)
            ->anyStatus()
            ->one();
    }

    /**
     * @inheritdoc
     */
    public function getEditUrl(): string
    {
        return 'https://id.craftcms.com/licenses/plugins/' . $this->id;
    }

    /**
     * @inheritdoc
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @inheritdoc
     */
    public function getShortKey(): string
    {
        return substr($this->key, 0, 4);
    }

    /**
     * @return Plugin
     */
    public function getPlugin(): Plugin
    {
        return Plugin::find()
            ->id($this->pluginId)
            ->status(null)
            ->one();
    }

    /**
     * @return CmsLicense|null
     */
    public function getCmsLicense()
    {
        if (!$this->cmsLicenseId) {
            return null;
        }
        return Module::getInstance()->getCmsLicenseManager()->getLicenseById($this->cmsLicenseId);
    }
}
