<?php

namespace craftnet\console\controllers;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use craftnet\Module;
use craftnet\plugins\Plugin;
use DateTime;
use DateTimeZone;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\ArrayHelper;

/**
 * Manages plugins.
 *
 * @property Module $module
 */
class PluginsController extends Controller
{
    /**
     * Displays info about all plugins
     *
     * @return int
     */
    public function actionInfo(): int
    {
        $formatter = Craft::$app->getFormatter();
        $total = $formatter->asDecimal(Plugin::find()->count(), 0);
        $totalAbandoned = $formatter->asDecimal(Plugin::find()->status(Plugin::STATUS_ABANDONED)->count(), 0);
        $totalPending = $formatter->asDecimal(Plugin::find()->status(Plugin::STATUS_PENDING)->count(), 0);

        $output = <<<OUTPUT
Total approved:  $total
Total abandoned: $totalAbandoned
Total pending:   $totalPending

OUTPUT;

        if ($totalPending) {
            $output .= "\nPending plugins:\n\n";
            $pending = Plugin::find()->status(Plugin::STATUS_PENDING)->all();
            $maxLength = max(array_map('mb_strlen', ArrayHelper::getColumn($pending, 'name'))) + 2;
            foreach ($pending as $plugin) {
                $output .= str_pad($plugin->name, $maxLength) . $plugin->getCpEditUrl() . "\n";
            }
        }

        $this->stdout($output);
        return ExitCode::OK;
    }

    /**
     * Updates plugin install counts
     *
     * @return int
     */
    public function actionUpdateInstallCounts(): int
    {
        $db = Craft::$app->getDb();

        $db
            ->createCommand('update craftnet_plugins as p set "activeInstalls" = (
    select count(*) from craftnet_cmslicense_plugins as lp
    where lp."pluginId" = p.id
    and lp.timestamp > :date
)', [
                'date' => Db::prepareDateForDb(new \DateTime('1 year ago')),
            ])
            ->execute();

        // Make sure we haven't inserted any historical data for today yet
        $timestamp = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d');
        $exists = (new Query())
            ->from('craftnet_plugin_installs')
            ->where(['date' => $timestamp])
            ->exists();

        if (!$exists) {
            $db
                ->createCommand('insert into craftnet_plugin_installs("pluginId", "activeInstalls", date) ' .
                    'select id, "activeInstalls", :date from craftnet_plugins', [
                    'date' => $timestamp,
                ])
                ->execute();
        }

        return ExitCode::OK;
    }
}
