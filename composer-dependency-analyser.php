<?php

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    // setono/client generates the client ids with symfony/uid when the default client context creates a client, so src/ doesn't reference it, only the tests
    ->ignoreErrorsOnPackage('symfony/uid', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])
;
