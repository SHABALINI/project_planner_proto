<?php

namespace App\Service;

use App\Entity\Comment;
use App\Entity\ProjectMember;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CommentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationService $notificationService,
        private LoggerInterface $logger,
        private string $kernelProjectDir
    ) {}

    public function createComment(User $user, int $taskId, string $text, ?UploadedFile $file): array
    {
        $task = $this->entityManager->getRepository(Task::class)->find($taskId);
        if (!$task) {
            throw new NotFoundHttpException('Задача не найдена');
        }

        $project = $task->getArea()->getProject();
        $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
        if ($project->getOwner() !== $user && !$member) {
            throw new AccessDeniedHttpException('Forbidden');
        }

        if (empty(trim($text)) && !$file) {
            throw new BadRequestHttpException('Комментарий не может быть пустым');
        }

        $comment = new Comment();
        $comment->setText($text);
        $comment->setTask($task);
        $comment->setAuthor($user);

        $filePath = null;
        $fileName = null;

        if ($file) {
            $mimeType = $file->getMimeType();
            $isImage = str_starts_with($mimeType, 'image/');

            $comment->setIsImage($isImage);
            $uploadsDirectory = $this->kernelProjectDir . '/public/uploads/comments';
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = mb_strtolower($originalFilename);
            $safeFilename = preg_replace('/[^a-z0-9_]+/u', '-', $safeFilename);
            $safeFilename = trim($safeFilename, '-');
            if (empty($safeFilename)) {
                $safeFilename = 'attachment';
            }
            $finalFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

            try {
                $file->move($uploadsDirectory, $finalFilename);
                $filePath = '/uploads/comments/' . $finalFilename;
                $fileName = $file->getClientOriginalName();
                $comment->setFilePath($filePath);
                $comment->setFileName($fileName);
            } catch (\Exception $e) {
                throw new \RuntimeException('Ошибка загрузки файла');
            }
        }

        try {
            $this->notificationService->notifyNewComment($comment, $user);
        } catch (\Exception $e) {
            $this->logger->error('Notification error: ' . $e->getMessage());
        }

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return [
            'comment' => $comment,
            'project' => $project,
            'filePath' => $filePath,
            'fileName' => $fileName
        ];
    }

    public function deleteComment(User $user, int $commentId): void
    {
        $comment = $this->entityManager->getRepository(Comment::class)->find($commentId);
        if (!$comment) {
            throw new NotFoundHttpException('Комментарий не найден');
        }

        $task = $comment->getTask();
        $area = $task->getArea();
        $project = $area->getProject();

        $member = $this->entityManager->getRepository(ProjectMember::class)->findOneBy(['project' => $project, 'user' => $user]);
        $role = $project->getOwner() === $user ? 'admin' : ($member ? $member->getRole() : 'viewer');

        $isAuthor = ($comment->getAuthor() === $user);
        $isAdmin = ($role === 'admin');
        $isAreaManager = ($role === 'manager' && $member && $member->getAreas()->contains($area));

        if (!$isAuthor && !$isAdmin && !$isAreaManager) {
            throw new AccessDeniedHttpException('Forbidden. Вы не можете удалить этот комментарий');
        }

        $this->entityManager->remove($comment);
        $this->entityManager->flush();
    }
}