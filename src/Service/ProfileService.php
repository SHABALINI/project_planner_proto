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
        // Получаем или создаем профиль
        $profile = $user->getProfile();
        if (!$profile) {
            // Создаем профиль, если его нет
            $profile = new Profile();
            $profile->setUser($user);
            $this->entityManager->persist($profile);
            $this->entityManager->flush();
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
            if (!mkdir($uploadsDirectory, 0777, true)) {
                throw new \RuntimeException('Failed to create upload directory');
            }
        }

        // Проверяем, что директория доступна для записи
        if (!is_writable($uploadsDirectory)) {
            throw new \RuntimeException('Upload directory is not writable');
        }

        // Генерируем имя файла
        $extension = $file->guessExtension() ?: 'jpg';
        $filename = 'avatar_' . $user->getId() . '_' . uniqid() . '.' . $extension;
        $avatarPath = '/uploads/avatars/' . $filename;
        $fullPath = $uploadsDirectory . '/' . $filename;
        
        try {
            // Перемещаем файл
            $file->move($uploadsDirectory, $filename);

            // Оптимизируем изображение (уменьшаем размер до 300px)
            $this->optimizeImage($fullPath);

            // Удаляем старый аватар если есть
            if ($profile->getAvatar()) {
                $oldAvatarPath = $this->kernelProjectDir . '/public' . $profile->getAvatar();
                if (file_exists($oldAvatarPath) && is_file($oldAvatarPath)) {
                    unlink($oldAvatarPath);
                }
            }

            $profile->setAvatar($avatarPath);
            $this->entityManager->flush();

            $this->logger->info('Avatar uploaded for user ' . $user->getId() . ': ' . $avatarPath);

            return $avatarPath;
        } catch (\Exception $e) {
            $this->logger->error('Avatar upload failed: ' . $e->getMessage(), [
                'user_id' => $user->getId(),
                'file' => $file->getClientOriginalName()
            ]);
            throw new \RuntimeException('Failed to upload avatar: ' . $e->getMessage());
        }
    }

    private function optimizeImage(string $path): void
    {
        // Проверяем, что GD расширение установлено
        if (!extension_loaded('gd')) {
            $this->logger->warning('GD extension not loaded, skipping image optimization');
            return;
        }

        try {
            $imageInfo = getimagesize($path);
            if (!$imageInfo) {
                return;
            }

            $maxSize = 300;
            $width = $imageInfo[0];
            $height = $imageInfo[1];

            // Если изображение уже меньше максимального размера, ничего не делаем
            if ($width <= $maxSize && $height <= $maxSize) {
                return;
            }

            // Создаем изображение в зависимости от типа
            $image = null;
            switch ($imageInfo[2]) {
                case IMAGETYPE_JPEG:
                    $image = imagecreatefromjpeg($path);
                    break;
                case IMAGETYPE_PNG:
                    $image = imagecreatefrompng($path);
                    break;
                case IMAGETYPE_GIF:
                    $image = imagecreatefromgif($path);
                    break;
                case IMAGETYPE_WEBP:
                    if (function_exists('imagecreatefromwebp')) {
                        $image = imagecreatefromwebp($path);
                    }
                    break;
                default:
                    return;
            }

            if (!$image) {
                return;
            }

            // Вычисляем новые размеры
            $ratio = min($maxSize / $width, $maxSize / $height);
            $newWidth = max(1, round($width * $ratio));
            $newHeight = max(1, round($height * $ratio));

            // Создаем оптимизированное изображение
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            
            // Сохраняем прозрачность для PNG
            if ($imageInfo[2] === IMAGETYPE_PNG) {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
            }

            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            
            // Сохраняем оптимизированное изображение
            switch ($imageInfo[2]) {
                case IMAGETYPE_JPEG:
                    imagejpeg($resized, $path, 85);
                    break;
                case IMAGETYPE_PNG:
                    imagepng($resized, $path, 8);
                    break;
                case IMAGETYPE_GIF:
                    imagegif($resized, $path);
                    break;
                case IMAGETYPE_WEBP:
                    if (function_exists('imagewebp')) {
                        imagewebp($resized, $path, 85);
                    }
                    break;
            }

            imagedestroy($resized);
            imagedestroy($image);

        } catch (\Exception $e) {
            $this->logger->warning('Image optimization failed: ' . $e->getMessage());
            // Не критично, продолжаем без оптимизации
        }
    }
}