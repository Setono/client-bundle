<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional\App;

use DAMA\DoctrineTestBundle\DAMADoctrineTestBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\DBAL\Connection;
use Setono\ClientBundle\SetonoClientBundle;
use Setono\ClientBundle\Tests\Functional\App\Controller\ClientController;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\HttpKernel\Log\Logger;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new DAMADoctrineTestBundle();
        yield new SetonoClientBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return self::getVarDir() . '/cache/' . $this->getEnvironment();
    }

    public function getLogDir(): string
    {
        return self::getVarDir() . '/log';
    }

    /**
     * The application is configured in this class, so the config dir only receives the config reference
     * that Symfony 7.4+ dumps in debug mode
     */
    protected function getConfigDir(): string
    {
        return self::getVarDir() . '/config';
    }

    /**
     * Each checkout gets its own directory, and so does each of the parallel processes that Infection runs and gives a token.
     * That way, no two test runs share a container or a database
     */
    public static function getVarDir(): string
    {
        return sprintf('%s/setono-client-bundle-%s%s', sys_get_temp_dir(), hash('xxh3', __DIR__), (string) getenv('TEST_TOKEN'));
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'secret',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);

        // Set the SETONO_CLIENT_BUNDLE_DATABASE_URL environment variable to run the tests against another database. The tests drop
        // the bundle's tables in it. The variable isn't DATABASE_URL, so the tests can't pick up the database of another application
        $container->parameters()->set('env(SETONO_CLIENT_BUNDLE_DATABASE_URL)', 'sqlite:///%kernel.cache_dir%/test.db');

        $dbal = ['url' => '%env(resolve:SETONO_CLIENT_BUNDLE_DATABASE_URL)%'];

        // DAMA DoctrineTestBundle runs each test in a transaction, so the transactions of the bundle need savepoints.
        // DBAL 4 always uses them and deprecates the option. Like DoctrineBundle, this tells DBAL 3 by its getEventManager()
        if (method_exists(Connection::class, 'getEventManager')) {
            $dbal['use_savepoints'] = true;
        }

        $container->extension('doctrine', [
            'dbal' => $dbal,
            'orm' => [],
        ]);

        $services = $container->services();

        // Logs warnings and errors to a file instead of the test output. It's in the cache dir, so it's removed with the cache
        $services->set('logger', Logger::class)->args(['warning', '%kernel.cache_dir%/log.txt']);

        // Lets the tests read the queries that a request executed
        $services->alias('test.doctrine.debug_data_holder', 'doctrine.debug_data_holder')->public();

        $services
            ->defaults()->autowire()->autoconfigure()
            ->load(__NAMESPACE__ . '\\Controller\\', __DIR__ . '/Controller')->tag('controller.service_arguments')
            ->load(__NAMESPACE__ . '\\EventSubscriber\\', __DIR__ . '/EventSubscriber')
        ;
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('client_id', '/')->controller([ClientController::class, 'id']);
        $routes->add('metadata_show', '/metadata')->controller([ClientController::class, 'showMetadata']);
        $routes->add('metadata_set', '/metadata/set')->controller([ClientController::class, 'setMetadata']);
        $routes->add('metadata_remove', '/metadata/remove')->controller([ClientController::class, 'removeMetadata']);
        $routes->add('metadata_set_concurrently', '/metadata/set-concurrently')->controller([ClientController::class, 'setMetadataConcurrently']);
    }
}
