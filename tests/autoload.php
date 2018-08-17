<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

require_once __DIR__ . '/../vendor/autoload.php';

$classLoader = new ClassLoader();
$classLoader->add('SOFe\AwaitGenerator', __DIR__, true);
$classLoader->register();
