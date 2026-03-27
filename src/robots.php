<?php
header("Content-Type: text/plain");
require_once __DIR__ . '/includes/config.php';
?>
User-agent: *
Allow: /
Allow: /index.php
Disallow: /admin/
Disallow: /includes/
Disallow: /layout/
Disallow: /admin/login.php
Disallow: /admin/logout.php

Sitemap: <?= APP_URL ?>/sitemap.xml
