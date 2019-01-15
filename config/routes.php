<?php
/**
 * Site URL Rules
 *
 * You can define custom site URL rules here, which Craft will check in addition
 * to any routes you’ve defined in Settings → Routes.
 *
 * See http://www.yiiframework.com/doc-2.0/guide-runtime-routing.html for more
 * info about URL rules.
 *
 * In addition to Yii’s supported syntaxes, Craft supports a shortcut syntax for
 * defining template routes:
 *
 *     'blog/archive/<year:\d{4}>' => ['template' => 'blog/_archive'],
 *
 * That example would match URIs such as `/blog/archive/2012`, and pass the
 * request along to the `blog/_archive` template, providing it a `year` variable
 * set to the value `2012`.
 */

return [
    'api' => [
        'OPTIONS <uri:.*>' => 'craftnet/api/options',
        'GET     v1/account' => 'craftnet/api/v1/account',
        'POST    v1/available-plugins' => 'craftnet/api/v1/available-plugins',
        'POST    v1/carts' => 'craftnet/api/v1/carts/create',
        'GET     v1/carts/<orderNumber:.*>' => 'craftnet/api/v1/carts/get',
        'POST    v1/carts/<orderNumber:.*>' => 'craftnet/api/v1/carts/update',
        'DELETE  v1/carts/<orderNumber:.*>' => 'craftnet/api/v1/carts/delete',
        'POST    v1/checkout' => 'craftnet/api/v1/checkout',
        'GET     v1/cms-editions' => 'craftnet/api/v1/cms-editions/get',
        'GET     v1/cms-licenses' => 'craftnet/api/v1/cms-licenses/get',
        'POST    v1/cms-licenses' => 'craftnet/api/v1/cms-licenses/create',
        'GET     v1/countries' => 'craftnet/api/v1/countries',
        'GET     v1/developer/<userId:\d+>' => 'craftnet/api/v1/developer',
        'POST    v1/optimize-composer-reqs' => 'craftnet/api/v1/optimize-composer-reqs',
        'POST    v1/composer-whitelist' => 'craftnet/api/v1/composer-whitelist',
        'POST    v1/partners' => 'craftnet/api/v1/partners/list',
        'POST    v1/partners/<id:\d+>' => 'craftnet/api/v1/partners/get',
        'POST    v1/payments' => 'craftnet/api/v1/payments/pay',
        'GET     v1/plugin-licenses' => 'craftnet/api/v1/plugin-licenses/list',
        'POST    v1/plugin-licenses' => 'craftnet/api/v1/plugin-licenses/create',
        'GET     v1/plugin-licenses/<key:.*>' => 'craftnet/api/v1/plugin-licenses/get',
        'GET     v1/plugin-store' => 'craftnet/api/v1/plugin-store',
        'GET     v1/plugin/<pluginId:\d+>' => 'craftnet/api/v1/plugin',
        'GET     v1/plugin/<pluginId:\d+>/changelog' => 'craftnet/api/v1/plugin/changelog',
        'GET     v1/updates' => 'craftnet/api/v1/updates',
        'POST    v1/updates' => 'craftnet/api/v1/updates/old',
        'POST    v1/utils/releases-2-changelog' => 'craftnet/api/v1/utils/releases-2-changelog',
        'POST    webhook/github' => 'craftnet/api/webhook/github',
        'update-deps' => 'craftnet/jobs/update-deps',
    ],
    'craftId' => [
        'POST    queue/handle-message' => 'queue/handle-message',

        'GET     v1/id' => 'craftnet/id/v1/id',
        'GET     craft-id' => 'craftnet/id/craft-id',
        'GET     apps/connect/<appTypeHandle:{handle}>' => 'craftnet/id/apps/connect',
        'GET     apps/callback' => 'craftnet/id/apps/callback',
        'GET     apps/disconnect/<appTypeHandle:{handle}>' => 'craftnet/id/apps/disconnect',

        'GET     stripe/connect' => 'craftnet/id/stripe/connect',
        'GET     stripe/account' => 'craftnet/id/stripe/account',
        'POST    stripe/disconnect' => 'craftnet/id/stripe/disconnect',
        'GET     stripe/customer' => 'craftnet/id/stripe/customer',
        'POST    stripe/save-card' => 'craftnet/id/stripe/save-card',
        'POST    stripe/remove-card' => 'craftnet/id/stripe/remove-card',

        'oauth/login' => 'oauth-server/oauth/login',
        'oauth/authorize' => 'oauth-server/oauth/authorize',
        'oauth/access-token' => 'oauth-server/oauth/access-token',
        'oauth/revoke' => 'oauth-server/oauth/revoke',

        'sync-staging' => 'craftnet/jobs/sync-staging',

        // Catch-all route for Vue when people reload the page.
        'login'=> ['template' => 'login'],
        'register'=> ['template' => 'register'],
        'register/success'=> ['template' => 'register/success'],
        'forgotpassword'=> ['template' => 'forgotpassword'],
        '<url:(.*)>'=> 'craftnet/id/account',
    ],
    'plugins' => [
        '/' => 'craftnet/plugins/index/index',
        '<url:(.*)>'=> 'craftnet/plugins/index/index',
    ],
];
