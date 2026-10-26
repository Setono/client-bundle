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
        return $this->getVarDir() . '/cache/' . $this->getEnvironment();
    }

    public function getLogDir(): string
    {
        return $this->getVarDir() . '/log';
    }

    /**
     * The application is configured in this class, so the config dir only receives the config reference
     * that Symfony 7.4+ dumps in debug mode
     */
    protected function getConfigDir(): string
    {
        return $this->getVarDir() . '/config';
    }

    /**
     * Infection runs the tests in parallel processes and gives each a token, so each process gets its own database
     */
    private function getVarDir(): string
    {
        return sprintf('%s/setono-client-bundle%s', sys_get_temp_dir(), (string) getenv('TEST_TOKEN'));
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

        // Set the DATABASE_URL environment variable to run the tests against another database
        $container->parameters()->set('env(DATABASE_URL)', 'sqlite:///%kernel.cache_dir%/test.db');

        $container->extension('doctrine', [
            'dbal' => ['url' => '%env(resolve:DATABASE_URL)%'],
            'orm' => [],
        ]);

        $services = $container->services();

        // Logs to a file instead of the test output
        $services->set('logger', Logger::class)->args(['debug', '%kernel.cache_dir%/log.txt']);

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
