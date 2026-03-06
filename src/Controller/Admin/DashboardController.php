<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/applications', name: 'app_admin_applications')]
    public function applications(): Response
    {
        return $this->render('admin/applications.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/courses', name: 'app_admin_courses')]
    public function courses(): Response
    {
        return $this->render('admin/courses.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/users', name: 'app_admin_users')]
    public function users(): Response
    {
        return $this->render('admin/users.html.twig', [
            'user' => $this->getUser(),
        ]);
    }
}
