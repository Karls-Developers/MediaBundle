<?php
namespace Karls\MediaBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use UniteCMS\CoreBundle\Entity\ContentType;
use UniteCMS\CoreBundle\Entity\SettingType;
use Karls\MediaBundle\Form\PreSignFormType;

class SelectController extends Controller
{
    /**
     * @Route("/content/{content_type}/files", methods={"GET"})
     * @Entity("contentType", expr="repository.findByIdentifiers(organization, domain, content_type)")
     * @Security("is_granted(constant('UniteCMS\\CoreBundle\\Security\\Voter\\ContentVoter::CREATE'), contentType)")
     *
     * @param ContentType $contentType
     * @param Request $request
     *
     * @return Response
     */
    public function selectFilesContentTypeAction(ContentType $contentType, Request $request)
    {
        try {
            $objects = $this->container->get(
                'karls.media.service'
            )->listObjects($contentType, $request->query->get('field'));

            return new JsonResponse($objects);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * @Route("/setting/{setting_type}/files", methods={"GET"})
     * @Entity("settingType", expr="repository.findByIdentifiers(organization, domain, setting_type)")
     * @Security("is_granted(constant('UniteCMS\\CoreBundle\\Security\\Voter\\SettingVoter::UPDATE'), settingType)")
     *
     * @param SettingType $settingType
     * @param Request $request
     *
     * @return Response
     */
    public function selectFilesSettingTypeAction(SettingType $settingType, Request $request)
    {
        try {
            $objects = $this->container->get(
                'karls.media.service'
            )->listObjects($settingType, $request->query->get('field'));

            return new JsonResponse($objects);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }
}
