<?php

namespace App\Controller;

use App\Entity\Profile;
use App\Entity\User;
use App\Service\ProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Route('/dashboard/profile')]
class ProfileController extends AbstractController
{
    public function __construct(private ProfileService $profileService) {}

    #[Route('', name: 'app_profile')]
    public function index(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $profile = $user->getProfile();
        $totalProjects = $user->getProjects()->count();

        return $this->render('project/profile.html.twig', [
            'profile' => $profile,
            'totalProjects' => $totalProjects,
            'user' => $user
        ]);
    }

    #[Route('/update', name: 'api_profile_update', methods: ['POST'])]
    public function update(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        
        try {
            $this->profileService->updateProfile($user, $data);
            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/upload-avatar', name: 'api_profile_upload_avatar', methods: ['POST'])]
    public function uploadAvatar(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $file = $request->files->get('avatar');
        if (!$file) {
            return new JsonResponse(['success' => false, 'error' => 'No file uploaded'], 400);
        }

        try {
            $avatarPath = $this->profileService->uploadAvatar($user, $file);
            return new JsonResponse([
                'success' => true,
                'avatar' => $avatarPath
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}