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
}
