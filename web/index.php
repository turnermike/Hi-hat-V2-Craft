<?php

/**
 * Craft web bootstrap file
 */

// Load shared bootstrap
require dirname(__DIR__) . '/bootstrap.php';

// Render a simple status page for the direct site root so the Craft DDEV URL is usable.
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';
if ($path === '/' || $path === '') {
  header('Content-Type: text/html; charset=UTF-8');
  echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Craft DDEV Status</title><style>body{font-family:system-ui,sans-serif;background:#f7f8fb;color:#111827;margin:0;padding:40px;} .container{max-width:720px;margin:0 auto;background:#fff;border-radius:18px;box-shadow:0 24px 64px rgba(15,23,42,.08);padding:36px;} h1{margin-top:0;font-size:2.25rem;} p{line-height:1.8;} a{color:#2563eb;} .links{margin-top:24px;} .links a{display:inline-block;margin-bottom:8px;}</style></head><body><div class="container"><h1>Craft DDEV is running</h1><p>The Craft project is active and reachable on this DDEV host.</p><div class="links"><p><strong>GraphQL endpoint:</strong> <a href="/actions/graphql/api">/actions/graphql/api</a></p><p><strong>Admin login:</strong> <a href="/admin">/admin</a></p></div><p>If you want the root path to render a real Craft homepage, we can wire the Craft single section or add a front-end route.</p></div></body></html>';
  return;
}

// Load and run Craft
/** @var craft\web\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/web.php';
$app->run();
