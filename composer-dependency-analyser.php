<?php

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    // setono/client generates the client ids with symfony/uid when the default client context creates a client, so src/ doesn't reference it, only the tests
    ->ignoreErrorsOnPackage('symfony/uid', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])

    // The bundle maps its Metadata with the ORM's XML mapping, which DoctrineOrmMappingsPass reads with the ORM's XmlDriver,
    // and stores it with the ORM's entity manager through setono/doctrine-orm-trait. So src/ doesn't reference the ORM's classes
    ->ignoreErrorsOnPackage('doctrine/orm', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])
;
