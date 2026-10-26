<?php

declare(strict_types=1);

namespace Setono\ClientBundle\Tests\Functional\App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
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

        $container->extension('doctrine', [
            'dbal' => ['url' => '%env(resolve:SETONO_CLIENT_BUNDLE_DATABASE_URL)%'],
            'orm' => [],
        ]);

        $services = $container->services();

        // Logs warnings and errors to a file instead of the test output. It's in the cache dir, so it's removed with the cache
        $services->set('logger', Logger::class)->args(['warning', '%kernel.cache_dir%/log.txt']);

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
