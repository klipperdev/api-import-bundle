<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Bundle\ApiImportBundle\Controller;

use Klipper\Bundle\ApiBundle\Controller\ControllerHelper;
use Klipper\Component\Content\ContentManagerInterface;
use Klipper\Component\Import\Choice\ImportStatus;
use Klipper\Component\Import\Model\ImportInterface;
use Klipper\Component\Metadata\MetadataManagerInterface;
use Klipper\Component\Resource\Object\ObjectFactoryInterface;
use Klipper\Component\Security\Permission\PermVote;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Standard controller for API Import.
 *
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class StandardController
{
    /**
     * Standard action to import the entities.
     */
    public function importAction(
        Request $request,
        ControllerHelper $helper,
        ContentManagerInterface $contentManager,
        MetadataManagerInterface $metadataManager,
        ObjectFactoryInterface $objectFactory
    ): Response {
        $class = $request->attributes->get('_action_class');
        $adapter = $request->attributes->get('_import_adapter');

        if (!$helper->isGranted(new PermVote('create'), $class)
            || !$helper->isGranted(new PermVote('update'), $class)
            || !$helper->isGranted(new PermVote('import'))
        ) {
            throw $helper->createAccessDeniedException();
        }

        /** @var ImportInterface $import */
        $import = $objectFactory->create('App:Import');
        $import->setStatus(current(ImportStatus::getValues()));
        $import->setAdapter($adapter);
        $import->setMetadata($metadataManager->get($class)->getName());

        $contentManager->upload('import', $import);

        return $helper->view($import);
    }
}
