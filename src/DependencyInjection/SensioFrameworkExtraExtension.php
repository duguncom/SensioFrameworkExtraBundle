<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sensio\Bundle\FrameworkExtraBundle\DependencyInjection;

use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\ClassExistenceResource;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SensioFrameworkExtraExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $annotationsToLoad = [];
        $definitionsToRemove = [];

        if ($config['request']['converters']) {
            $annotationsToLoad[] = 'converters.xml';

            $container->registerForAutoconfiguration(ParamConverterInterface::class)
                ->addTag('request.param_converter');

            $container->setParameter('sensio_framework_extra.disabled_converters', \is_string($config['request']['disable']) ? implode(',', $config['request']['disable']) : $config['request']['disable']);

            $container->addResource(new ClassExistenceResource(ExpressionLanguage::class));
            if (class_exists(ExpressionLanguage::class)) {
                $container->setAlias('sensio_framework_extra.converter.doctrine.orm.expression_language', new Alias('sensio_framework_extra.converter.doctrine.orm.expression_language.default', false));
            } else {
                $definitionsToRemove[] = 'sensio_framework_extra.converter.doctrine.orm.expression_language.default';
            }
        }

        if ($annotationsToLoad) {
            // must be first
            foreach ($annotationsToLoad as $configFile) {
                $loader->load($configFile);
            }

            if ($config['request']['converters']) {
                $container->getDefinition('sensio_framework_extra.converter.listener')->replaceArgument(1, $config['request']['auto_convert']);
            }
        }

        foreach ($definitionsToRemove as $definition) {
            $container->removeDefinition($definition);
        }
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath(): string
    {
        return __DIR__.'/../Resources/config/schema';
    }

    public function getNamespace(): string
    {
        return 'http://symfony.com/schema/dic/symfony_extra';
    }
}
