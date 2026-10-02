<?php

declare(strict_types=1);

/*
 * Connects the Facebook Page to this bot:
 *   php bin/connect-page.php
 *
 * 1. Takes a short-lived User token from the Graph API Explorer.
 * 2. Exchanges it for a long-lived token and fetches the Page token
 *    (a Page token from a long-lived User token does not expire).
 * 3. Saves PAGE_ID, PAGE_NAME and PAGE_ACCESS_TOKEN into .env.
 * 4. Subscribes the Page to the app's "feed" webhook (comments).
 *
 * Run again any time the token stops working.
 */

if (PHP_SAPI !== 'cli') {
    exit("Run this from the terminal.\n");
}

require dirname(__DIR__) . '/bootstrap.php';

use App\Cli;
use App\Env;
use App\EnvFile;
use App\Graph;
use App\GraphException;

foreach (['APP_ID', 'APP_SECRET'] as $key) {
    if (!Env::has($key)) {
        exit("$key is empty in .env. Copy it from Meta dashboard → App settings → Basic.\n");
    }
}

try {
    $userToken = Cli::askHidden('Paste the User access token from Graph API Explorer: ');
    if ($userToken === '') {
        exit("No token given.\n");
    }

    echo "Exchanging for a long-lived token...\n";
    $exchange = Graph::get('oauth/access_token', [
        'grant_type' => 'fb_exchange_token',
        'client_id' => Env::get('APP_ID'),
        'client_secret' => Env::get('APP_SECRET'),
        'fb_exchange_token' => $userToken,
    ], '');
    $longUserToken = (string) ($exchange['access_token'] ?? '');
    if ($longUserToken === '') {
        exit("Facebook did not return a long-lived token.\n");
    }

    $pages = Graph::get('me/accounts', ['fields' => 'id,name,access_token,tasks', 'limit' => 100], $longUserToken)['data'] ?? [];
    if ($pages === []) {
        exit("No Pages found. In Graph API Explorer, select your Page when asked and grant the permissions.\n");
    }

    $page = null;
    if (Env::has('PAGE_ID')) {
        foreach ($pages as $p) {
            if ((string) $p['id'] === Env::get('PAGE_ID')) {
                $page = $p;
            }
        }
        if ($page === null) {
            echo "PAGE_ID in .env (" . Env::get('PAGE_ID') . ") is not in the list below.\n";
        }
    }
    if ($page === null) {
        foreach ($pages as $i => $p) {
            printf("  [%d] %s (%s)\n", $i + 1, $p['name'], $p['id']);
        }
        $choice = (int) Cli::ask('Which Page? Enter the number: ');
        $page = $pages[$choice - 1] ?? exit("Invalid choice.\n");
    }

    $pageToken = (string) ($page['access_token'] ?? '');
    if ($pageToken === '') {
        exit("No Page token returned. Make sure you are an admin of the Page.\n");
    }

    EnvFile::set('PAGE_ID', (string) $page['id']);
    EnvFile::set('PAGE_NAME', (string) $page['name']);
    EnvFile::set('PAGE_ACCESS_TOKEN', $pageToken);
    echo "Saved Page \"{$page['name']}\" ({$page['id']}) and its token to .env.\n";

    echo "Subscribing the Page to comment webhooks (feed)...\n";
    Graph::post($page['id'] . '/subscribed_apps', ['subscribed_fields' => 'feed'], $pageToken);

    $apps = Graph::get($page['id'] . '/subscribed_apps', [], $pageToken)['data'] ?? [];
    foreach ($apps as $app) {
        if ((string) ($app['id'] ?? '') === Env::get('APP_ID')) {
            $fields = implode(', ', array_map(
                static fn ($f) => is_array($f) ? (string) ($f['name'] ?? '') : (string) $f,
                (array) ($app['subscribed_fields'] ?? [])
            ));
            echo "Done. Page is subscribed to: $fields\n";
            exit(0);
        }
    }
    echo "Subscribe call succeeded, but the app is not listed yet. Check again in a minute.\n";
} catch (GraphException $e) {
    echo "Facebook error: " . App\Logger::scrubString($e->getMessage()) . "\n";
    echo "Check the permissions you selected in Graph API Explorer and try again with a fresh token.\n";
    exit(1);
}
