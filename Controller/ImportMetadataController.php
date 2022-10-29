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
use Klipper\Component\Export\Exception\InvalidFormatException;
use Klipper\Component\Metadata\AssociationMetadataInterface;
use Klipper\Component\Metadata\ChildMetadataInterface;
use Klipper\Component\Metadata\MetadataManagerInterface;
use Klipper\Component\Security\Permission\PermVote;
use Klipper\Component\SecurityOauth\Scope\ScopeVote;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Ramsey\Uuid\Uuid;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class ImportMetadataController
{
    /**
     * @Route("/metadatas/{name}/import-template-file.{ext}", methods={"GET"}, requirements={"ext": "csv|ods|xls|xlsx"})
     * @Security("is_granted('perm:import')")
     */
    public function downloadImportTemplateAction(
        ControllerHelper $helper,
        TranslatorInterface $translator,
        MetadataManagerInterface $metadataManager,
        string $name,
        string $ext,
    ): Response {
        if (class_exists(ScopeVote::class)) {
            $helper->denyAccessUnlessGranted(new ScopeVote(['meta/import', 'meta/import.readonly'], false));
        }

        if (!$metadataManager->hasByName($name)) {
            throw $helper->createNotFoundException();
        }

        $meta = $metadataManager->getByName($name);
        $class = $meta->getClass();
        $fieldIdentifier = $meta->getFieldIdentifier();
        $filename = $translator->trans('metadata.import.template_file.filename', [
            '{metadata}' => $translator->trans($meta->getLabel(), [], $meta->getTranslationDomain()),
            '{ext}' => $ext,
        ], 'messages');

        if (!$helper->isGranted(new PermVote('create'), $class)
            || !$helper->isGranted(new PermVote('update'), $class)
            || !$helper->isGranted(new PermVote('import'))
        ) {
            throw $helper->createAccessDeniedException();
        }

        try {
            $spreadsheet = new Spreadsheet();
            $writer = IOFactory::createWriter($spreadsheet, ucfirst($ext));
            $sheet = $spreadsheet->getActiveSheet();
            $columnIndex = 1;

            /** @var ChildMetadataInterface[] $children */
            $children = [
                ...$meta->getFields(),
                ...$meta->getAssociations(),
            ];

            usort($children, fn (ChildMetadataInterface $a, ChildMetadataInterface $b) => strcmp($a->getName(), $b->getName()));

            foreach ($children as $child) {
                if (!$child->isReadOnly() || $fieldIdentifier === $child->getName()) {
                    $exampleMeta = $child;

                    if ($child instanceof AssociationMetadataInterface) {
                        $targetMeta = $metadataManager->getByName($child->getTarget());
                        $exampleMeta = $targetMeta->getFieldByName($targetMeta->getFieldIdentifier());
                    }

                    $sheet->setCellValueByColumnAndRow($columnIndex, 1, $child->getName());
                    $sheet->getCommentByColumnAndRow($columnIndex, 1)->getText()->createTextRun(sprintf(
                        '%s%s[%s Type: %s]',
                        $translator->trans($child->getLabel(), [], $child->getTranslationDomain()),
                        PHP_EOL,
                        $child instanceof AssociationMetadataInterface ? 'Association' : 'Field',
                        $child->getType(),
                    ));
                    $sheet->setCellValueByColumnAndRow($columnIndex, 3, 'DATA EXAMPLE');
                    $sheet->setCellValueByColumnAndRow($columnIndex, 4, $this->getExampleValue($exampleMeta));
                    $sheet->setCellValueByColumnAndRow($columnIndex, 5, $this->getExampleValue($exampleMeta));
                    $sheet->setCellValueByColumnAndRow($columnIndex, 6, $this->getExampleValue($exampleMeta));

                    ++$columnIndex;
                }
            }

            for ($i = 1; $i <= $columnIndex; ++$i) {
                $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
            }

            $response = new StreamedResponse(
                static function () use ($writer): void {
                    $writer->save('php://output');
                }
            );

            $response->setPrivate();
            $response->headers->addCacheControlDirective('no-cache');
            $response->headers->addCacheControlDirective('must-revalidate');
            $response->headers->set('Content-Type', MimeTypes::getDefault()->getMimeTypes($ext));
            $response->headers->set('Content-Disposition', 'attachment;filename="'.utf8_decode(htmlspecialchars_decode($filename)).'"');

            return $response;
        } catch (InvalidFormatException $e) {
            throw new BadRequestHttpException($translator->trans('klipper_api_export.invalid_format', [
                'format' => $ext,
            ], 'exceptions'), $e);
        } catch (\Throwable $e) {
            throw new BadRequestHttpException($translator->trans('klipper_api_export.error', [], 'exceptions'), $e);
        }
    }

    private function getExampleValue(ChildMetadataInterface $child)
    {
        switch ($child->getType()) {
            case 'guid':
            case 'uuid':
                $exampleValue = Uuid::uuid4()->toString();

                break;

            case 'string':
                $exampleValue = 'Text '.$this->generateRandomString(random_int(10, 20));

                break;

            case 'boolean':
                $exampleValue = 1 === random_int(0, 1) ? 'TRUE' : 'FALSE';

                break;

            case 'integer':
                $exampleValue = random_int(1, 10000);

                break;

            case 'float':
                $exampleValue = (float) random_int(0, 10000) / 100;

                break;

            case 'datetime':
                $exampleValue = (new \DateTime())->format('Y-m-d');

                break;

            case 'date':
                $exampleValue = (new \DateTime())->format(\DateTime::ATOM);

                break;

            case 'time':
                $exampleValue = (new \DateTime())->format('H:i:s');

                break;

            default:
                $exampleValue = '';

                break;
        }

        return $exampleValue;
    }

    private function generateRandomString($length = 10): string
    {
        return substr(
            str_shuffle(
                str_repeat(
                    $x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
                    ceil($length / \strlen($x))
                )
            ),
            1,
            $length
        );
    }
}
