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

use Klipper\Bundle\ApiBundle\Action\Upsert;
use Klipper\Bundle\ApiBundle\Controller\ControllerHelper;
use Klipper\Component\Content\ContentManagerInterface;
use Klipper\Component\Import\ImportManagerInterface;
use Klipper\Component\Import\Message\ImportRunMessage;
use Klipper\Component\Import\Model\ImportInterface;
use Klipper\Component\SecurityOauth\Scope\ScopeVote;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class ImportController
{
    /**
     * @Entity(
     *     "id",
     *     class="App:Import"
     * )
     * @Route("/imports/{id}/retry", methods={"PUT"})
     * @Security("is_granted('perm:update', 'App\\Entity\\Import')")
     * @Security("is_granted('perm:import')")
     */
    public function retry(
        ControllerHelper $helper,
        MessageBusInterface $messageBus,
        ImportManagerInterface $importManager,
        ImportInterface $id
    ): Response {
        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote(['meta/import', 'meta/import.readonly'], false));
        }

        if (!$importManager->reset($id)) {
            return $helper->view($id);
        }

        $action = Upsert::build('', $id)->setProcessForm(false);
        $res = $helper->upsert($action);

        $messageBus->dispatch(new ImportRunMessage($id->getId()));

        return $res;
    }

    /**
     * @Entity(
     *     "id",
     *     class="App:Import"
     * )
     * @Route("/imports/{id}/original", methods={"GET"})
     * @Security("is_granted('perm:view', 'App\\Entity\\Import')")
     * @Security("is_granted('perm:import')")
     */
    public function downloadOriginal(
        ControllerHelper $helper,
        ContentManagerInterface $contentManager,
        ImportInterface $id
    ): Response {
        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote(['meta/import', 'meta/import.readonly'], false));
        }

        return $contentManager->download(
            'import',
            $id->getFilePath(),
            'Import '.$id->getId().'.'.$id->getFileExtension()
        );
    }

    /**
     * @Entity(
     *     "id",
     *     class="App:Import"
     * )
     * @Route("/imports/{id}/result", methods={"GET"})
     * @Security("is_granted('perm:view', 'App\\Entity\\Import')")
     * @Security("is_granted('perm:import')")
     */
    public function downloadResult(
        ControllerHelper $helper,
        ContentManagerInterface $contentManager,
        ImportInterface $id
    ): Response {
        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote(['meta/import', 'meta/import.readonly'], false));
        }

        if (null === $id->getResultFilePath()) {
            throw $helper->createNotFoundException();
        }

        return $contentManager->download(
            'import',
            $id->getResultFilePath(),
            'Import '.$id->getId().'.'.$id->getResultFileExtension()
        );
    }
}
