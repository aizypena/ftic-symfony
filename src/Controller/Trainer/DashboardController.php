<?php

namespace App\Controller\Trainer;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/trainer')]
#[IsGranted('ROLE_TRAINER')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_trainer_dashboard')]
    public function index(): Response
    {
        return $this->render('trainer/dashboard.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/courses', name: 'app_trainer_courses')]
    public function courses(): Response
    {
        return $this->render('trainer/courses.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/submissions', name: 'app_trainer_submissions')]
    public function submissions(): Response
    {
        return $this->render('trainer/submissions.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/calendar', name: 'app_trainer_calendar')]
    public function calendar(): Response
    {
        return $this->render('trainer/calendar.html.twig', [
            'user' => $this->getUser(),
        ]);
    }
}
