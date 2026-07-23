<?php

namespace App\Controller;

use App\Service\CommentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[Route('/dashboard')]
class CommentController extends AbstractController
{
    public function __construct(private CommentService $commentService) {}

    #[Route('/comment/create', name: 'api_comment_create', methods: ['POST'])]
    public function createComment(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $taskId = (int)$request->request->get('task_id');
        $text = $request->request->get('text') ?? '';
        $file = $request->files->get('file');

        try {
            $result = $this->commentService->createComment($user, $taskId, $text, $file);
            $comment = $result['comment'];

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'id' => $comment->getId(),
                    'text' => $comment->getText(),
                    'filePath' => $result['filePath'],
                    'fileName' => $result['fileName'],
                    'author' => $user->getEmail(),
                    'isImage' => $comment->isImage() ?? false,
                ]);
            }

            return $this->redirectToRoute('app_project_view', ['id' => $result['project']->getId()]);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 404);
        } catch (AccessDeniedHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 403);
        } catch (BadRequestHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/comment/delete/{id}', name: 'api_comment_delete', methods: ['POST'])]
    public function deleteComment(int $id): JsonResponse
    {
        $user = $this->getUser();
        try {
            $this->commentService->deleteComment($user, $id);
            return new JsonResponse(['success' => true]);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 404);
        } catch (AccessDeniedHttpException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 403);
        }
    }
}