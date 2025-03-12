<?php

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->ignoreErrorsOnPackage('symfony/uid', [ErrorType::UNUSED_DEPENDENCY]) // When initializing the default client context, the Uuid class from this component is used
;
