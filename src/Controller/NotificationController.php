<?php
// src/Controller/NotificationController.php
namespace App\Controller;

use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard/notifications')]
class NotificationController extends AbstractController
{
    #[Route('', name: 'app_notifications_center')]
    public function index(NotificationRepository $repo): Response
    {
        $user = $this->getUser();
        
        // 1. Все непрочитанные для счетчиков
        $allUnread = $repo->findBy(['user' => $user, 'isRead' => false], ['createdAt' => 'DESC']);
        $totalUnreadCount = count($allUnread);

        // 2. Все уведомления пользователя (новые сверху)
        $allNotifications = $repo->findBy(['user' => $user], ['createdAt' => 'DESC']);

        // 3. Группировка уведомлений по проектам с сортировкой проектов по дате свежего уведомления
        $grouped = [];
        foreach ($allNotifications as $n) {
            $pId = $n->getProject()->getId();
            if (!isset($grouped[$pId])) {
                $grouped[$pId] = [
                    'project' => $n->getProject(),
                    'items' => [],
                    'unreadCount' => 0,
                    'latestTimestamp' => $n->getCreatedAt()->getTimestamp()
                ];
            }
            $grouped[$pId]['items'][] = $n;
            if (!$n->isRead()) {
                $grouped[$pId]['unreadCount']++;
            }
        }

        // Сортируем группы проектов так, чтобы проект с самым свежим изменением вылетал наверх
        uasort($grouped, function($a, $b) {
            return $b['latestTimestamp'] <=> $a['latestTimestamp'];
        });

        return $this->render('project/notifications.html.twig', [
            'allNotifications' => $allNotifications,
            'totalUnreadCount' => $totalUnreadCount,
            'groupedProjects' => $grouped
        ]);
    }

    #[Route('/read/{id}', name: 'api_notification_read', methods: ['POST'])]
    public function markAsRead(int $id, NotificationRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $notification = $repo->findOneBy(['id' => $id, 'user' => $this->getUser()]);
        if (!$notification) {
            return new JsonResponse(['success' => false, 'error' => 'Не найдено'], 404);
        }

        $notification->setIsRead(true);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/read-all/{projectId}', name: 'api_notification_read_all', methods: ['POST'])]
    public function markAllAsRead(?int $projectId, NotificationRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $criteria = ['user' => $user, 'isRead' => false];
        if ($projectId && $projectId > 0) {
            $criteria['project'] = $projectId;
        }

        $unread = $repo->findBy($criteria);
        foreach ($unread as $n) {
            $n->setIsRead(true);
        }
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/unread-count-api', name: 'api_notifications_count', methods: ['GET'])]
    public function getUnreadCount(NotificationRepository $repo): JsonResponse
    {
        $count = count($repo->findBy(['user' => $this->getUser(), 'isRead' => false]));
        return new JsonResponse(['count' => $count]);
    }
}