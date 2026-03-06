<?php

namespace App\Controller\Student;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/student')]
#[IsGranted('ROLE_STUDENT')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_student_dashboard')]
    public function index(): Response
    {
        return $this->render('student/dashboard.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/calendar', name: 'app_student_calendar')]
    public function calendar(): Response
    {
        return $this->render('student/calendar.html.twig', [
            'user' => $this->getUser(),
        ]);
    }
}
