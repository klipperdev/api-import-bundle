<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Bundle\ApiImportBundle\Listener;

use Klipper\Component\Content\Uploader\Event\UploadFileCompletedEvent;
use Klipper\Component\Import\Message\ImportRunMessage;
use Klipper\Component\Import\Model\ImportInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class ImportUploadCompletedMessageSubscriber implements EventSubscriberInterface
{
    private MessageBusInterface  $messageBus;

    public function __construct(
        MessageBusInterface $messageBus
    ) {
        $this->messageBus = $messageBus;
    }

    public static function getSubscribedEvents(): iterable
    {
        return [
            UploadFileCompletedEvent::class => [
                ['onUploadRequest', 0],
            ],
        ];
    }

    /**
     * @throws
     */
    public function onUploadRequest(UploadFileCompletedEvent $event): void
    {
        $payload = $event->getPayload();

        if (\is_object($payload) && $payload instanceof ImportInterface && null !== $payload->getId()) {
            $this->messageBus->dispatch(new ImportRunMessage($payload->getId()));
        }
    }
}
