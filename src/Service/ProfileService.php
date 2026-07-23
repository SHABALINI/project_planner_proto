<?php

namespace App\Service;

use App\Entity\Profile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ProfileService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private string $kernelProjectDir
    ) {}

    public function updateProfile(User $user, array $data): Profile
    {
        $profile = $user->getProfile();
        
        if (!$profile) {
            $profile = new Profile();
            $profile->setUser($user);
            $this->entityManager->persist($profile);
        }

        // Обновляем поля
        if (isset($data['fullName'])) {
            $profile->setFullName($data['fullName']);
        }
        if (isset($data['company'])) {
            $profile->setCompany($data['company']);
        }
        if (isset($data['position'])) {
            $profile->setPosition($data['position']);
        }
        if (isset($data['university'])) {
            $profile->setUniversity($data['university']);
        }
        if (isset($data['specialty'])) {
            $profile->setSpecialty($data['specialty']);
        }
        if (isset($data['educationLevel'])) {
            $profile->setEducationLevel($data['educationLevel']);
        }
        if (isset($data['bio'])) {
            $profile->setBio($data['bio']);
        }
        if (isset($data['telegram'])) {
            $profile->setTelegram($data['telegram']);
        }
        if (isset($data['github'])) {
            $profile->setGithub($data['github']);
        }
        if (isset($data['linkedin'])) {
            $profile->setLinkedin($data['linkedin']);
        }
        if (isset($data['website'])) {
            $profile->setWebsite($data['website']);
        }

        $profile->setUpdatedAtValue();

        $this->entityManager->flush();

        return $profile;
    }

    public function uploadAvatar(User $user, UploadedFile $file): string
    {
        $profile = $user->getProfile();
        if (!$profile) {
            throw new BadRequestHttpException('Profile not found');
        }

        // Проверяем тип файла
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            throw new BadRequestHttpException('Invalid file type. Allowed: JPEG, PNG, GIF, WEBP');
        }

        // Проверяем размер (макс 5MB)
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new BadRequestHttpException('File too large. Max size: 5MB');
        }

        $uploadsDirectory = $this->kernelProjectDir . '/public/uploads/avatars';
        
        // Создаем директорию если не существует
        if (!is_dir($uploadsDirectory)) {
            mkdir($uploadsDirectory, 0777, true);
        }

        // Генерируем имя файла
        $filename = 'avatar_' . $user->getId() . '_' . uniqid() . '.' . $file->guessExtension();
        
        try {
            $file->move($uploadsDirectory, $filename);
            $avatarPath = '/uploads/avatars/' . $filename;
            
            // Удаляем старый аватар если есть
            if ($profile->getAvatar()) {
                $oldAvatarPath = $this->kernelProjectDir . '/public' . $profile->getAvatar();
                if (file_exists($oldAvatarPath)) {
                    unlink($oldAvatarPath);
                }
            }

            $profile->setAvatar($avatarPath);
            $this->entityManager->flush();

            return $avatarPath;
        } catch (\Exception $e) {
            $this->logger->error('Avatar upload failed: ' . $e->getMessage());
            throw new \RuntimeException('Failed to upload avatar');
        }
    }
}