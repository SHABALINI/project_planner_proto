<?php

namespace App\Controller;

use App\Service\ProjectMemberService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[Route('/dashboard')]
class ProjectMemberController extends AbstractController
{
    public function __construct(private ProjectMemberService $memberService) {}

    #[Route('/project/{id}/members', name: 'api_project_members', methods: ['GET'])]
    public function getProjectMembers(int $id): JsonResponse
    {
        try {
            $result = $this->memberService->getProjectMembersList($id);
            return new JsonResponse($result);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 404);
        }
    }

    #[Route('/project/{id}/member/save', name: 'api_project_member_save', methods: ['POST'])]
    public function saveProjectMember(int $id, Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $this->memberService->saveProjectMember($currentUser, $id, $data);
            return new JsonResponse(['success' => true]);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(['success' => false], 404);
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (AccessDeniedHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 403);
        }
    }

    #[Route('/project/{projectId}/member/remove/{userId}', name: 'api_project_member_remove', methods: ['POST'])]
    public function removeProjectMember(int $projectId, int $userId): JsonResponse
    {
        $currentUser = $this->getUser();

        try {
            $this->memberService->removeProjectMember($currentUser, $projectId, $userId);
            return new JsonResponse(['success' => true]);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 404);
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (AccessDeniedHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 403);
        }
    }

    #[Route('/project/{id}/members-panel', name: 'api_project_members_panel', methods: ['GET'])]
    public function getMembersPanel(int $id): JsonResponse
    {
        try {
            $membersData = $this->memberService->getMembersPanelData($id);
            return new JsonResponse(['success' => true, 'members' => $membersData, 'total' => count($membersData)]);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 404);
        }
    }
}