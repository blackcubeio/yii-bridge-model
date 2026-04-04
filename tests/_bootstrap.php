<?php

declare(strict_types=1);

date_default_timezone_set('Europe/Paris');

defined('STDIN') or define('STDIN', fopen('php://stdin', 'r'));
defined('STDOUT') or define('STDOUT', fopen('php://stdout', 'w'));

require dirname(__DIR__) . '/vendor/autoload.php';

ini_set('display_errors', '1');
error_reporting(E_ALL);

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();
