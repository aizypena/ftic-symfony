<?php

namespace App\Controller\Student;

use App\Repository\CourseRepository;
use App\Repository\CourseWeekRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/student')]
#[IsGranted('ROLE_STUDENT')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_student_dashboard')]
    public function index(CourseRepository $courseRepo): Response
    {
        /** @var \App\Entity\User $me */
        $me = $this->getUser();

        $myCourses = $courseRepo->createQueryBuilder('c')
            ->join('c.students', 's')
            ->where('s.id = :id')
            ->andWhere('c.status = :status')
            ->setParameter('id', $me->getId())
            ->setParameter('status', 'active')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('student/dashboard.html.twig', [
            'user'      => $me,
            'myCourses' => $myCourses,
        ]);
    }

    #[Route('/courses', name: 'app_student_courses')]
    public function courses(CourseRepository $courseRepo): Response
    {
        /** @var \App\Entity\User $me */
        $me = $this->getUser();

        $myCourses = $courseRepo->createQueryBuilder('c')
            ->join('c.students', 's')
            ->where('s.id = :id')
            ->andWhere('c.status = :status')
            ->setParameter('id', $me->getId())
            ->setParameter('status', 'active')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('student/courses.html.twig', [
            'user'      => $me,
            'myCourses' => $myCourses,
        ]);
    }

    #[Route('/courses/{id}', name: 'app_student_course_view')]
    public function courseView(int $id, CourseRepository $courseRepo, CourseWeekRepository $weekRepo): Response
    {
        /** @var \App\Entity\User $me */
        $me     = $this->getUser();
        $course = $courseRepo->find($id);

        if (!$course || !$course->hasStudent($me)) {
            $this->addFlash('error', 'You are not enrolled in that course.');
            return $this->redirectToRoute('app_student_courses');
        }

        if ($course->getStatus() !== 'active') {
            $this->addFlash('error', 'This course is currently unavailable.');
            return $this->redirectToRoute('app_student_courses');
        }

        // Build weeks 1-14 map (same pattern as trainer controller)
        $weeks = [];
        for ($w = 1; $w <= 14; $w++) {
            $week = $weekRepo->findOneBy(['course' => $course, 'weekNumber' => $w]);
            if ($week) {
                $weeks[$w] = $week;
            }
        }

        return $this->render('student/course_view.html.twig', [
            'user'   => $me,
            'course' => $course,
            'weeks'  => $weeks,
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
