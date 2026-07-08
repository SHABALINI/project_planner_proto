<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $userPasswordHasher, 
        EntityManagerInterface $em
    ): Response {
        $user = new User(); // Обрати внимание: проверь, как у тебя называется класс пользователя (User или AppUser)
        
        // Создаем форму регистрации встроенными средствами Symfony
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        // КРИТИЧЕСКИЙ МОМЕНТ: код ниже должен выполняться ТОЛЬКО если форма отправлена!
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getNormData();

            // Хешируем пароль
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Регистрация успешна! Войдите в аккаунт.');

            return $this->redirectToRoute('app_login');
        }

        // Если это обычный переход по ссылке (GET-запрос), просто показываем пустую форму
        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
