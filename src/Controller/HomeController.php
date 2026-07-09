<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    // Задаем роут для самого корня сайта — '/'
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        // Перенаправляем пользователя на дашборд проектов
        return $this->redirectToRoute('app_dashboard');
    }
}