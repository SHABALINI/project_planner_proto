<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
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

    #[Route('', name: 'app_profile', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $profile = $user->getProfile();
        $totalProjects = $user->getProjects()->count();

        return $this->render('profile/index.html.twig', [
            'profile' => $profile,
            'totalProjects' => $totalProjects,
            'user' => $user,
            'isOwnProfile' => true
        ]);
    }

    #[Route('/{id}', name: 'app_profile_view', methods: ['GET'])]
    public function view(int $id, UserRepository $userRepository): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->redirectToRoute('app_login');
        }

        $user = $userRepository->find($id);
        if (!$user) {
            throw new NotFoundHttpException('Пользователь не найден');
        }

        $profile = $user->getProfile();
        $totalProjects = $user->getProjects()->count();

        $isOwnProfile = ($user->getId() === $currentUser->getId());

        return $this->render('profile/view.html.twig', [
            'user' => $user,
            'profile' => $profile,
            'totalProjects' => $totalProjects,
            'isOwnProfile' => $isOwnProfile
        ]);
    }

    #[Route('/update', name: 'api_profile_update', methods: ['POST'])]
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
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
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $file = $request->files->get('avatar');
        if (!$file) {
            return new JsonResponse(['success' => false, 'error' => 'No file uploaded'], 400);
        }

        // Проверка размера файла
        if ($file->getSize() > 5 * 1024 * 1024) {
            return new JsonResponse(['success' => false, 'error' => 'File too large. Max size: 5MB'], 400);
        }

        // Проверка типа файла
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedTypes)) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid file type. Allowed: JPEG, PNG, GIF, WEBP'], 400);
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