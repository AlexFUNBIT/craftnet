<?php

namespace craftnet;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\events\PdfEvent;
use craft\commerce\models\Discount;
use craft\commerce\services\OrderAdjustments;
use craft\commerce\services\Pdf;
use craft\commerce\services\Purchasables;
use craft\elements\db\UserQuery;
use craft\elements\User;
use craft\events\DefineBehaviorsEvent;
use craft\events\DeleteElementEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterEmailMessagesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\UserEvent;
use craft\models\SystemMessage;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\SystemMessages;
use craft\services\UserPermissions;
use craft\services\Users;
use craft\services\Utilities;
use craft\web\twig\variables\Cp;
use craft\web\UrlManager;
use craft\web\View;
use craftnet\cms\CmsEdition;
use craftnet\cms\CmsLicenseManager;
use craftnet\composer\JsonDumper;
use craftnet\composer\PackageManager;
use craftnet\developers\UserBehavior;
use craftnet\developers\UserQueryBehavior;
use craftnet\discounts\DiscountBehavior;
use craftnet\fields\Plugins;
use craftnet\invoices\InvoiceManager;
use craftnet\orders\OrderBehavior;
use craftnet\orders\PdfRenderer;
use craftnet\plugins\Plugin;
use craftnet\plugins\PluginEdition;
use craftnet\plugins\PluginLicenseManager;
use craftnet\sales\SaleManager;
use craftnet\services\Oauth;
use craftnet\utilities\PullProduction;
use craftnet\utilities\SalesReport;
use craftnet\utilities\UnavailablePlugins;
use yii\base\Event;

/**
 * @property CmsLicenseManager $cmsLicenseManager
 * @property InvoiceManager $invoiceManager
 * @property JsonDumper $jsonDumper
 * @property Oauth $oauth
 * @property PackageManager $packageManager
 * @property PluginLicenseManager $pluginLicenseManager
 * @property SaleManager $saleManager
 */
class Module extends \yii\base\Module
{
    const MESSAGE_KEY_RECEIPT = 'craftnet_receipt';
    const MESSAGE_KEY_VERIFY = 'verify_email';
    const MESSAGE_KEY_DEVELOPER_SALE = 'developer_sale';
    const MESSAGE_KEY_LICENSE_REMINDER = 'license_reminder';
    const MESSAGE_KEY_LICENSE_NOTIFICATION = 'license_notification';

    /**
     * @inheritdoc
     */
    public function init()
    {
        Craft::setAlias('@craftnet', __DIR__);

        // define custom behaviors
        Event::on(UserQuery::class, UserQuery::EVENT_DEFINE_BEHAVIORS, function(DefineBehaviorsEvent $e) {
            $e->behaviors['cn.userQuery'] = UserQueryBehavior::class;
        });
        Event::on(User::class, User::EVENT_DEFINE_BEHAVIORS, function(DefineBehaviorsEvent $e) {
            $e->behaviors['cn.user'] = UserBehavior::class;
        });
        Event::on(Order::class, Order::EVENT_DEFINE_BEHAVIORS, function(DefineBehaviorsEvent $e) {
            $e->behaviors['cn.order'] = OrderBehavior::class;
        });
        Event::on(Discount::class, Discount::EVENT_DEFINE_BEHAVIORS, function(DefineBehaviorsEvent $e) {
            $e->behaviors['cn.discount'] = DiscountBehavior::class;
        });

        // register custom component types
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function(RegisterComponentTypesEvent $e) {
            $e->types[] = Plugins::class;
        });
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITY_TYPES, function(RegisterComponentTypesEvent $e) {
            $e->types[] = UnavailablePlugins::class;
            $e->types[] = SalesReport::class;
            $e->types[] = PullProduction::class;
        });
        Event::on(Purchasables::class, Purchasables::EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES, function(RegisterComponentTypesEvent $e) {
            $e->types[] = CmsEdition::class;
            $e->types[] = PluginEdition::class;
        });
        Event::on(OrderAdjustments::class, OrderAdjustments::EVENT_REGISTER_ORDER_ADJUSTERS, function(RegisterComponentTypesEvent $e) {
            $e->types[] = OrderAdjuster::class;
        });

        // register our custom receipt system message
        Event::on(SystemMessages::class, SystemMessages::EVENT_REGISTER_MESSAGES, function(RegisterEmailMessagesEvent $e) {
            $e->messages[] = new SystemMessage([
                'key' => self::MESSAGE_KEY_RECEIPT,
                'heading' => 'When someone places an order:',
                'subject' => 'Your receipt from {{ fromName }}',
                'body' => file_get_contents(__DIR__ . '/emails/receipt.md'),
            ]);
            $e->messages[] = new SystemMessage([
                'key' => self::MESSAGE_KEY_VERIFY,
                'heading' => 'When someone wants to claim licenses by an email address:',
                'subject' => 'Verify your email',
                'body' => file_get_contents(__DIR__ . '/emails/verify.md'),
            ]);
            $e->messages[] = new SystemMessage([
                'key' => self::MESSAGE_KEY_DEVELOPER_SALE,
                'heading' => 'When a plugin developer makes a sale:',
                'subject' => 'Craft Plugin Store Sale',
                'body' => file_get_contents(__DIR__ . '/emails/developer_sale.md'),
            ]);
            $e->messages[] = new SystemMessage([
                'key' => self::MESSAGE_KEY_LICENSE_REMINDER,
                'heading' => 'When licenses will be expiring/auto-renewing soon:',
                'subject' => 'Important license info',
                'body' => file_get_contents(__DIR__ . '/emails/license_reminder.md'),
            ]);
            $e->messages[] = new SystemMessage([
                'key' => self::MESSAGE_KEY_LICENSE_NOTIFICATION,
                'heading' => 'When licenses have expired/auto-renewed::',
                'subject' => 'Important license info',
                'body' => file_get_contents(__DIR__ . '/emails/license_notification.md'),
            ]);
        });

        // claim Craft/plugin licenses after user activation
        Event::on(Users::class, Users::EVENT_AFTER_ACTIVATE_USER, function(UserEvent $e) {
            $this->getCmsLicenseManager()->claimLicenses($e->user);
            $this->getPluginLicenseManager()->claimLicenses($e->user);
        });

        // provide custom order receipt PDF generation
        Event::on(Pdf::class, Pdf::EVENT_BEFORE_RENDER_PDF, function(PdfEvent $e) {
            $e->pdf = (new PdfRenderer())->render($e->order);
        });

        // hard-delete plugins
        Event::on(Elements::class, Elements::EVENT_BEFORE_DELETE_ELEMENT, function(DeleteElementEvent $e) {
            if ($e->element instanceof Plugin) {
                $e->hardDelete = true;
            }
        });

        // request type-specific stuff
        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest()) {
            $this->controllerNamespace = 'craftnet\\console\\controllers';
        } else {
            $this->controllerNamespace = 'craftnet\\controllers';

            if ($request->getIsCpRequest()) {
                $this->_initCpRequest();
            } else {
                $this->_initSiteRequest();
            }
        }

        parent::init();
    }

    /**
     * @return CmsLicenseManager
     */
    public function getCmsLicenseManager(): CmsLicenseManager
    {
        return $this->get('cmsLicenseManager');
    }

    /**
     * @return InvoiceManager
     */
    public function getInvoiceManager(): InvoiceManager
    {
        return $this->get('invoiceManager');
    }

    /**
     * @return PluginLicenseManager
     */
    public function getPluginLicenseManager(): PluginLicenseManager
    {
        return $this->get('pluginLicenseManager');
    }

    /**
     * @return PackageManager
     */
    public function getPackageManager(): PackageManager
    {
        return $this->get('packageManager');
    }

    /**
     * @return JsonDumper
     */
    public function getJsonDumper(): JsonDumper
    {
        return $this->get('jsonDumper');
    }

    /**
     * @return Oauth
     */
    public function getOauth(): Oauth
    {
        return $this->get('oauth');
    }

    /**
     * @return SaleManager
     */
    public function getSaleManager(): SaleManager
    {
        return $this->get('saleManager');
    }

    private function _initCpRequest()
    {
        $this->controllerNamespace = 'craftnet\\controllers';

        Event::on(Cp::class, Cp::EVENT_REGISTER_CP_NAV_ITEMS, function(RegisterCpNavItemsEvent $e) {
            $e->navItems[] = [
                'url' => 'cmslicenses',
                'label' => 'Craft Licenses',
            ];

            $e->navItems[] = [
                'url' => 'plugins',
                'label' => 'Plugins',
                'fontIcon' => 'plugin',
            ];

            $e->navItems[] = [
                'url' => 'partners',
                'label' => 'Partners',
                'icon' => __DIR__ . '/icons/partner.svg',
            ];
        });

        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $e) {
            $e->rules = array_merge($e->rules, [
                'cmslicenses' => 'craftnet/cms-licenses',
                'plugins' => ['template' => 'craftnet/plugins/_index'],
                'plugins/new' => 'craftnet/plugins/edit',
                'plugins/<pluginId:\d+><slug:(?:-[^\/]*)?>' => 'craftnet/plugins/edit',
                'partners' => ['template' => 'craftnet/partners/_index'],
                'partners/new' => 'craftnet/partners/edit',
                'partners/<partnerId:\d+><slug:(?:-[^\/]*)?>' => 'craftnet/partners/edit',
                'partners/foo' => 'craftnet/partners/foo',
                'GET partners/history/<partnerId:\d+>' => 'craftnet/partners/fetch-history',
                'POST partners/history' => 'craftnet/partners/save-history',
                'DELETE partners/history/<id:\d+>' => 'craftnet/partners/delete-history',
            ]);
        });

        Event::on(View::class, View::EVENT_REGISTER_CP_TEMPLATE_ROOTS, function(RegisterTemplateRootsEvent $e) {
            $e->roots['craftnet'] = __DIR__ . '/templates';
        });

        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, function(RegisterUserPermissionsEvent $e) {
            $e->permissions['Craftcom'] = [
                'craftnet:managePlugins' => [
                    'label' => 'Manage plugins',
                ],
            ];
        });
    }

    private function _initSiteRequest()
    {
        if (Craft::$app->getRequest()->getOrigin() === getenv('CRAFT_ID_ORIGIN')) {
            Craft::$app->getResponse()->getHeaders()->set('Access-Control-Allow-Origin', getenv('CRAFT_ID_ORIGIN'));
            Craft::$app->getResponse()->getHeaders()->set('Access-Control-Allow-Credentials', 'true');
        } else {
            Craft::$app->getResponse()->getHeaders()->set('Access-Control-Allow-Origin', '*');
        }
    }
}
