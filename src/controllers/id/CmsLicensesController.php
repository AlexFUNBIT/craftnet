<?php

namespace craftnet\controllers\id;

use Craft;
use craft\errors\UploadFailedException;
use craft\web\Controller;
use craft\web\UploadedFile;
use craftnet\errors\LicenseNotFoundException;
use craftnet\Module;
use Exception;
use Throwable;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Class CmsLicensesController
 *
 * @property Module $module
 */
class CmsLicensesController extends BaseController
{
    // Public Methods
    // =========================================================================

    /**
     * Claims a license.
     *
     * @return Response
     */
    public function actionClaim(): Response
    {
        $key = Craft::$app->getRequest()->getBodyParam('key');
        $licenseFile = UploadedFile::getInstanceByName('licenseFile');

        try {
            $user = Craft::$app->getUser()->getIdentity();

            if ($licenseFile) {
                if ($licenseFile->getHasError()) {
                    throw new UploadFailedException($licenseFile->error);
                }

                $licenseFilePath = $licenseFile->tempName;

                $key = file_get_contents($licenseFilePath);
            }

            if ($key) {
                $this->module->getCmsLicenseManager()->claimLicense($user, $key);
                return $this->asJson(['success' => true]);
            }

            throw new Exception("No license key provided.");
        } catch (Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * Download license file.
     *
     * @return Response
     * @throws ForbiddenHttpException
     * @throws \yii\web\HttpException
     */
    public function actionDownload(): Response
    {
        $user = Craft::$app->getUser()->getIdentity();
        $licenseId = Craft::$app->getRequest()->getParam('id');
        $license = $this->module->getCmsLicenseManager()->getLicenseById($licenseId);

        if ($license->ownerId === $user->id) {
            return Craft::$app->getResponse()->sendContentAsFile(chunk_split($license->key, 50), 'license.key');
        }

        throw new ForbiddenHttpException('User is not authorized to perform this action');
    }

    /**
     * Get the number of expiring licenses.
     *
     * @return Response
     */
    public function actionGetExpiringLicensesTotal(): Response
    {
        $user = Craft::$app->getUser()->getIdentity();

        try {
            $total = Module::getInstance()->getCmsLicenseManager()->getExpiringLicensesTotal($user);

            return $this->asJson($total);
        } catch (Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * Get license by ID.
     *
     * @return Response
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionGetLicenseById(): Response
    {
        $user = Craft::$app->getUser()->getIdentity();
        $id = Craft::$app->getRequest()->getRequiredParam('id');

        try {
            $license = Module::getInstance()->getCmsLicenseManager()->getLicenseById($id);

            if ($license->ownerId !== $user->id) {
                throw new UnauthorizedHttpException('Not Authorized');
            }

            $licenseArray = Module::getInstance()->getCmsLicenseManager()->transformLicenseForOwner($license, $user, ['pluginLicenses']);

            return $this->asJson($licenseArray);
        } catch (Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * Returns the current user’s licenses for `vue-tables-2`.
     *
     * @return Response
     */
    public function actionGetLicenses(): Response
    {
        $user = Craft::$app->getUser()->getIdentity();

        $filter = Craft::$app->getRequest()->getParam('filter');
        $limit = Craft::$app->getRequest()->getParam('limit', 10);
        $page = (int) Craft::$app->getRequest()->getParam('page', 1);
        $orderBy = Craft::$app->getRequest()->getParam('orderBy');
        $ascending = Craft::$app->getRequest()->getParam('ascending');
        $byColumn = Craft::$app->getRequest()->getParam('byColumn');

        try {
            $licenses = Module::getInstance()->getCmsLicenseManager()->getLicensesByOwner($user, $filter, $limit, $page, $orderBy, $ascending, $byColumn);
            $totalLicenses = Module::getInstance()->getCmsLicenseManager()->getTotalLicensesByOwner($user, $filter);

            $last_page = ceil($totalLicenses / $limit);
            $next_page_url = '?next';
            $prev_page_url = '?prev';
            $from = ($page - 1) * $limit;
            $to = ($page * $limit) - 1;

            return $this->asJson([
                'total' => $totalLicenses,
                'per_page' => $limit,
                'current_page' => $page,
                'last_page' => $last_page,
                'next_page_url' => $next_page_url,
                'prev_page_url' => $prev_page_url,
                'from' => $from,
                'to' => $to,
                'data' => $licenses,
            ]);
        } catch (Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * Releases a license.
     *
     * @return Response
     * @throws LicenseNotFoundException
     */
    public function actionRelease(): Response
    {
        $key = Craft::$app->getRequest()->getParam('key');
        $user = Craft::$app->getUser()->getIdentity();
        $manager = $this->module->getCmsLicenseManager();
        $license = $manager->getLicenseByKey($key);

        try {
            if ($user && $license->ownerId === $user->id) {
                $license->ownerId = null;

                if ($manager->saveLicense($license)) {
                    $manager->addHistory($license->id, "released by {$user->email}");
                    return $this->asJson(['success' => true]);
                }

                throw new Exception("Couldn't save license.");
            }

            throw new LicenseNotFoundException($key);
        } catch (Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * Saves a license.
     *
     * @return Response
     * @throws LicenseNotFoundException
     */
    public function actionSave(): Response
    {
        $key = Craft::$app->getRequest()->getParam('key');
        $user = Craft::$app->getUser()->getIdentity();
        $manager = $this->module->getCmsLicenseManager();
        $license = $manager->getLicenseByKey($key);

        try {
            if ($user && $license->ownerId === $user->id) {
                $domain = Craft::$app->getRequest()->getParam('domain');
                $notes = Craft::$app->getRequest()->getParam('notes');
                $autoRenew = Craft::$app->getRequest()->getParam('autoRenew');

                if ($domain !== null) {
                    $oldDomain = $license->domain;
                    $license->domain = $domain ?: null;
                }

                if ($notes !== null) {
                    $license->notes = $notes;
                }

                if ($autoRenew !== null) {
                    $license->autoRenew = Craft::$app->getRequest()->getParam('autoRenew');
                }

                if (!$manager->saveLicense($license)) {
                    throw new Exception("Couldn't save license.");
                }

                if ($domain !== null && $license->domain !== $oldDomain) {
                    $note = $license->domain ? "tied to domain {$license->domain}" : "untied from domain {$oldDomain}";
                    $manager->addHistory($license->id, "{$note} by {$user->email}");
                }

                return $this->asJson([
                    'success' => true,
                    'license' => $manager->transformLicenseForOwner($license, $user),
                ]);
            }

            throw new LicenseNotFoundException($key);
        } catch (Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }
}
