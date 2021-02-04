<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Bundle\ApiImportBundle\Metadata\Guess;

use Klipper\Component\Metadata\ActionMetadataBuilderInterface;
use Klipper\Component\Metadata\Guess\GuessActionConfigInterface;
use Klipper\Component\Metadata\ObjectMetadataBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class GuessActionMetadata implements GuessActionConfigInterface
{
    public const ACTIONS = [
        'import' => 'guessImport',
    ];

    public function guessActionConfig(ActionMetadataBuilderInterface $builder): void
    {
        $actionName = $builder->getName();

        if (isset(static::ACTIONS[$actionName])) {
            $this->{static::ACTIONS[$actionName]}($builder, $builder->getParent());
        }
    }

    /**
     * Guess the import action.
     *
     * @param ActionMetadataBuilderInterface $builder The action metadata builder
     */
    public function guessImport(ActionMetadataBuilderInterface $builder): void
    {
        $builder->addDefaults([
            '_action' => 'import',
            '_action_class' => $builder->getParent()->getClass(),
            '_priority' => -50,
        ]);

        if (empty($builder->getMethods())) {
            $builder->setMethods([Request::METHOD_POST]);
        }

        if (null === $builder->getPath()) {
            $builder->setPath($this->getBasePath($builder->getParent()));
        }

        if (null === $builder->getController()) {
            $builder->setController('Klipper\Bundle\ApiImportBundle\Controller\StandardController::importAction');
        }
    }

    /**
     * Get the base path of route.
     *
     * @param ObjectMetadataBuilderInterface $builder The object metadata builder
     * @param string                         $prefix  The path prefix
     */
    private function getBasePath(ObjectMetadataBuilderInterface $builder, string $prefix = ''): string
    {
        return '/{organization}/import/'.('' !== $prefix ? $prefix.'/' : '').$builder->getPluralName();
    }
}
