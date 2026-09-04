#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Mcp\Server;
use Mcp\Server\Transport\StdioTransport;

exit(
    Server::builder()
        ->setServerInfo('README Generator', '1.0.0')
        
        // Aquí es donde se "descubre" la clase ReadmeGenerator
        ->setDiscovery(
            basePath: __DIR__,
            scanDirs: ['.'],             // Escanea la raíz, incluida src/
            excludeDirs: ['vendor']      // No escanea vendor (importante)
        )
        
        ->build()
        ->run(new StdioTransport())
);