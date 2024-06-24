<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sensio\Bundle\FrameworkExtraBundle\EventListener;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The ParamConverterListener handles the ParamConverter annotation.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ParamConverterListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly ParamConverterManager $manager,
        private $autoConvert = true,
    ) {
    }

    /**
     * Modifies the ParamConverterManager instance.
     */
    public function onKernelController(KernelEvent $event): void
    {
        $controller = $event->getController();
        $request = $event->getRequest();
        $configurations = [];

        $object = new \ReflectionObject($controller[0]);
        $method = $object->getMethod($controller[1]);

        $entityAttributes = $method->getAttributes(ParamConverter::class);
        if ([] === $entityAttributes) {
            return;
        }

        foreach ($entityAttributes as $entityAttribute) {
            /** @var ParamConverter $entityAttribute */
            $entityAttribute = $entityAttribute->newInstance();
            $configurations[$entityAttribute->getName()] = $entityAttribute;
        }

        // automatically apply conversion for non-configured objects
        if ($this->autoConvert) {
            if (\is_array($controller)) {
                $r = new \ReflectionMethod($controller[0], $controller[1]);
            } elseif (\is_object($controller) && \is_callable([$controller, '__invoke'])) {
                $r = new \ReflectionMethod($controller, '__invoke');
            } else {
                $r = new \ReflectionFunction($controller);
            }

            $configurations = $this->autoConfigure($r, $request, $configurations);
        }

        $this->manager->apply($request, $configurations);
    }

    private function autoConfigure(\ReflectionFunctionAbstract $r, Request $request, $configurations)
    {
        foreach ($r->getParameters() as $param) {
            $type = $param->getType();
            $class = $this->getParamClassByType($type);
            if (null !== $class && $request instanceof $class) {
                continue;
            }

            $name = $param->getName();

            if ($type) {
                if (!isset($configurations[$name])) {
                    $configuration = new ParamConverter([]);
                    $configuration->setName($name);

                    $configurations[$name] = $configuration;
                }

                if (null !== $class && null === $configurations[$name]->getClass()) {
                    $configurations[$name]->setClass($class);
                }
            }

            if (isset($configurations[$name])) {
                $configurations[$name]->setIsOptional($param->isOptional() || $param->isDefaultValueAvailable() || ($type && $type->allowsNull()));
            }
        }

        return $configurations;
    }

    private function getParamClassByType(?\ReflectionType $type): ?string
    {
        if (null === $type) {
            return null;
        }

        foreach ($type instanceof \ReflectionUnionType ? $type->getTypes() : [$type] as $type) {
            if (!$type->isBuiltin()) {
                return $type->getName();
            }
        }

        return null;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}
