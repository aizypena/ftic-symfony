<?php

namespace App\Controller\Admin;

use App\Entity\AcademicTerm;
use App\Form\AcademicTermType;
use App\Repository\AcademicTermRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/terms')]
#[IsGranted('ROLE_ADMIN')]
class TermController extends AbstractController
{
    #[Route('', name: 'app_admin_terms', methods: ['GET'])]
    public function index(AcademicTermRepository $termRepository): Response
    {
        $terms = $termRepository->findBy([], ['startDate' => 'DESC']);

        return $this->render('admin/terms.html.twig', [
            'user'  => $this->getUser(),
            'terms' => $terms,
        ]);
    }

    #[Route('/new', name: 'app_admin_terms_new')]
    public function new(Request $request, EntityManagerInterface $em, AcademicTermRepository $termRepository): Response
    {
        $term = new AcademicTerm();
        $form = $this->createForm(AcademicTermType::class, $term);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($term->isActive()) {
                $termRepository->deactivateAllExcept();
            }

            $em->persist($term);
            $em->flush();

            $this->addFlash('success', 'Academic term created successfully.');
            return $this->redirectToRoute('app_admin_terms');
        }

        return $this->render('admin/term_form.html.twig', [
            'user'  => $this->getUser(),
            'form'  => $form,
            'title' => 'Create Academic Term',
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_terms_edit')]
    public function edit(AcademicTerm $term, Request $request, EntityManagerInterface $em, AcademicTermRepository $termRepository): Response
    {
        $form = $this->createForm(AcademicTermType::class, $term);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($term->isActive()) {
                $termRepository->deactivateAllExcept($term);
            }

            $em->flush();

            $this->addFlash('success', 'Academic term updated successfully.');
            return $this->redirectToRoute('app_admin_terms');
        }

        return $this->render('admin/term_form.html.twig', [
            'user'  => $this->getUser(),
            'form'  => $form,
            'title' => 'Edit Academic Term',
            'term'  => $term,
        ]);
    }

    #[Route('/{id}/activate', name: 'app_admin_terms_activate', methods: ['POST'])]
    public function activate(AcademicTerm $term, Request $request, AcademicTermRepository $termRepository, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('activate-term-' . $term->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $termRepository->deactivateAllExcept($term);
        $term->setIsActive(true);
        $em->flush();

        $this->addFlash('success', sprintf('%s is now the active academic term.', $term->getDisplayLabel() ?: 'Selected term'));

        return $this->redirectToRoute('app_admin_terms');
    }
}
