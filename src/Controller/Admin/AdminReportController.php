<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/reports')]
class AdminReportController extends AbstractController
{
    #[Route('', name: 'app_admin_reports', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/reports.html.twig', [
            'controller_name' => 'AdminReportController',
        ]);
    }
}
