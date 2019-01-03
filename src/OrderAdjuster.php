<?php

namespace craftnet;

use craft\commerce\adjusters\Discount;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\helpers\DateTimeHelper;
use craftnet\base\EditionInterface;
use craftnet\errors\LicenseNotFoundException;
use craftnet\helpers\OrderHelper;

class OrderAdjuster implements AdjusterInterface
{
    // Constants
    // =========================================================================

    /**
     * The discount adjustment type.
     */
    const ADJUSTMENT_TYPE = 'discount';

    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function adjust(Order $order): array
    {
        $adjustments = [];

        foreach ($order->getLineItems() as $lineItem) {
            $purchasable = $lineItem->getPurchasable();
            if ($purchasable instanceof EditionInterface) {
                $this->_adjustForEdition($order, $lineItem, $purchasable, $adjustments);
            }
        }

        return $adjustments;
    }

    /**
     * Sets order adjustments for an edition.
     *
     * @param Order $order
     * @param LineItem $lineItem
     * @param EditionInterface $edition
     * @param array $adjustments
     * @throws LicenseNotFoundException
     */
    private function _adjustForEdition(Order $order, LineItem $lineItem, EditionInterface $edition, array &$adjustments)
    {
        $options = $lineItem->getOptions();
        $expiryDate = OrderHelper::expiryStr2Obj($options['expiryDate']);
        $renewal = $edition->getRenewal();

        // If the expiry date is over a year from now, charge for it
        $nextYear = (new \DateTime('now', new \DateTimeZone('UTC')))->modify('+1 year');
        if ($expiryDate > $nextYear) {
            $paidRenewalYears = OrderHelper::dateDiffInYears($nextYear, $expiryDate);

            $adjustments[] = new OrderAdjustment([
                'order' => $order,
                'lineItem' => $lineItem,
                'type' => Discount::ADJUSTMENT_TYPE,
                'name' => 'Updates until ' . OrderHelper::expiryObj2Str($expiryDate),
                'amount' => round($renewal->getPrice() * $paidRenewalYears, 2),
                'sourceSnapshot' => [
                    'renewalPrice' => $renewal->getPrice(),
                    'expiryDate' => $expiryDate->format(\DateTime::ATOM),
                    'paidRenewalYears' => $paidRenewalYears,
                ],
            ]);
        }

        // Existing license?
        if (strpos($options['licenseKey'], 'new:') !== 0) {
            $license = $edition->getLicenseByKey($options['licenseKey']);
            $oldEdition = $license->getEdition();
            $editionUpgradeDiscount = min($oldEdition->getPrice(), $edition->getPrice());

            if ($editionUpgradeDiscount > 0) {
                $adjustments[] = new OrderAdjustment([
                    'order' => $order,
                    'lineItem' => $lineItem,
                    'type' => Discount::ADJUSTMENT_TYPE,
                    'name' => 'Edition upgrade discount',
                    'amount' => -$editionUpgradeDiscount,
                    'sourceSnapshot' => [
                        'oldEdition' => $oldEdition->getHandle(),
                        'oldEditionPrice' => $oldEdition->getPrice(),
                    ],
                ]);
            }

            // Was the old expiration date over a year away?
            if ($license->getIsExpirable()) {
                $oldExpiryDate = $license->getExpiryDate();

                if ($oldExpiryDate > $nextYear) {
                    $oldRenewal = $oldEdition->getRenewal();
                    $renewalUpgradeDiscount = min($oldRenewal->getPrice(), $renewal->getPrice());

                    if ($renewalUpgradeDiscount > 0) {
                        $oldPaidRenewalYears = OrderHelper::dateDiffInYears($nextYear, $oldExpiryDate);

                        $adjustments[] = new OrderAdjustment([
                            'order' => $order,
                            'lineItem' => $lineItem,
                            'type' => Discount::ADJUSTMENT_TYPE,
                            'name' => 'Renewal upgrade discount',
                            'amount' => -round($renewalUpgradeDiscount * $oldPaidRenewalYears, 2),
                            'sourceSnapshot' => [
                                'oldRenewalPrice' => $oldRenewal->getPrice(),
                                'oldExpiryDate' => $oldExpiryDate->format(\DateTime::ATOM),
                                'oldPaidRenewalYears' => $oldPaidRenewalYears,
                            ],
                        ]);
                    }
                }
            }
        }
    }
}
