<?php

namespace App\Controller\Student;

use App\Repository\AnnouncementRepository;
use App\Repository\CourseRepository;
use App\Repository\CourseWeekRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/student')]
#[IsGranted('ROLE_STUDENT')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_student_dashboard')]
    public function index(CourseRepository $courseRepo, AnnouncementRepository $annRepo): Response
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

        $announcements = $annRepo->createQueryBuilder('a')
            ->where('a.isDeleted = false')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('student/dashboard.html.twig', [
            'user'          => $me,
            'myCourses'     => $myCourses,
            'announcements' => $announcements,
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
    public function courseView(int $id, CourseRepository $courseRepo, CourseWeekRepository $weekRepo, \Doctrine\ORM\EntityManagerInterface $em): Response
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

        // Build weeks 1-14 map
        $weeks = [];
        for ($w = 1; $w <= 14; $w++) {
            $week = $weekRepo->findOneBy(['course' => $course, 'weekNumber' => $w]);
            if ($week) {
                $weeks[$w] = $week;
            }
        }

        $submissionsRaw = $em->getRepository(\App\Entity\StudentSubmission::class)
            ->findBy(['student' => $me]);
        
        $submissionsByWeek = [];
        foreach ($submissionsRaw as $sub) {
            if ($sub->getCourseWeek()->getCourse() === $course) {
                $submissionsByWeek[$sub->getCourseWeek()->getId()][] = $sub;
            }
        }

        return $this->render('student/course_view.html.twig', [
            'user'              => $me,
            'course'            => $course,
            'weeks'             => $weeks,
            'submissionsByWeek' => $submissionsByWeek,
        ]);
    }

    #[Route('/courses/{courseId}/weeks/{weekId}/upload', name: 'app_student_submission_upload', methods: ['POST'])]
    public function uploadSubmission(
        int $courseId,
        int $weekId,
        CourseRepository $courseRepo,
        CourseWeekRepository $weekRepo,
        Request $request,
        \Doctrine\ORM\EntityManagerInterface $em,
        \Symfony\Component\String\Slugger\SluggerInterface $slugger
    ): Response {
        /** @var \App\Entity\User $me */
        $me = $this->getUser();
        $course = $courseRepo->find($courseId);
        $week = $weekRepo->find($weekId);

        if (!$course || !$course->hasStudent($me) || !$week || $week->getCourse() !== $course) {
            throw $this->createAccessDeniedException('Invalid course or week.');
        }

        if (!$week->isSubmissionRequired()) {
            $this->addFlash('error', 'Submissions are not accepted for this week.');
            return $this->redirectToRoute('app_student_course_view', ['id' => $courseId]);
        }

        $files = $request->files->get('submission_files');
        if (!$files || empty($files[0])) {
            $this->addFlash('error', 'Please select at least one file.');
            return $this->redirectToRoute('app_student_course_view', ['id' => $courseId, '_fragment' => 'week-' . $week->getWeekNumber()]);
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/submissions';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $count = current($em->createQuery(
            'SELECT COUNT(s.id) FROM App\Entity\StudentSubmission s WHERE s.student = :student AND s.courseWeek = :week'
        )->setParameters(['student' => $me, 'week' => $week])->getResult())[1] ?? 0;

        if ($count + count($files) > $week->getMaxFiles()) {
            $this->addFlash('error', 'You cannot upload more than ' . $week->getMaxFiles() . ' file(s) for this week.');
            return $this->redirectToRoute('app_student_course_view', ['id' => $courseId, '_fragment' => 'week-' . $week->getWeekNumber()]);
        }

        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $safeBase = $slugger->slug(pathinfo($originalName, PATHINFO_FILENAME));
            $storedName = $safeBase . '-' . uniqid() . '.' . $file->guessExtension();

            $file->move($uploadDir, $storedName);

            $submission = new \App\Entity\StudentSubmission();
            $submission->setStudent($me)
                       ->setCourseWeek($week)
                       ->setOriginalName($originalName)
                       ->setFilename($storedName)
                       ->setUploadedAt(new \DateTimeImmutable());

            $em->persist($submission);
        }
        
        $em->flush();
        $this->addFlash('success', 'Submission uploaded successfully!');

        return $this->redirectToRoute('app_student_course_view', ['id' => $courseId, '_fragment' => 'week-' . $week->getWeekNumber()]);
    }

    #[Route('/calendar', name: 'app_student_calendar')]
    public function calendar(Request $request, \App\Repository\TrainerEventRepository $eventRepository): Response
    {
        /** @var \App\Entity\User $student */
        $student = $this->getUser();

        $year = max(2000, min(2100, (int) $request->query->get('year', (int) (new \DateTimeImmutable('now'))->format('Y'))));
        $month = max(1, min(12, (int) $request->query->get('month', (int) (new \DateTimeImmutable('now'))->format('n'))));
        $monthStart = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
        $calendarStart = $monthStart->modify('-' . $monthStart->format('w') . ' days');
        $calendarEnd = $calendarStart->modify('+41 days')->setTime(23, 59, 59);

        // Fetch events for trainers whose courses this student is taking
        $events = $eventRepository->findForStudentRange($student, $calendarStart, $calendarEnd);

        $eventsByDate = [];
        foreach ($events as $event) {
            $startsAt = $event->getStartsAt();
            if (!$startsAt) {
                continue;
            }
            $eventsByDate[$startsAt->format('Y-m-d')][] = $event;
        }

        $days = [];
        $todayKey = (new \DateTimeImmutable('today'))->format('Y-m-d');
        for ($i = 0; $i < 42; $i++) {
            $date = $calendarStart->modify('+' . $i . ' days');
            $dateKey = $date->format('Y-m-d');
            $days[] = [
                'date' => $date,
                'dateKey' => $dateKey,
                'inMonth' => $date->format('n') === $monthStart->format('n'),
                'isToday' => $dateKey === $todayKey,
                'events' => $eventsByDate[$dateKey] ?? [],
            ];
        }

        $prevMonth = $monthStart->modify('-1 month');
        $nextMonth = $monthStart->modify('+1 month');

        return $this->render('student/calendar.html.twig', [
            'user' => $student,
            'monthLabel' => $monthStart->format('F Y'),
            'currentYear' => (int) $monthStart->format('Y'),
            'currentMonth' => (int) $monthStart->format('n'),
            'prevYear' => (int) $prevMonth->format('Y'),
            'prevMonth' => (int) $prevMonth->format('n'),
            'nextYear' => (int) $nextMonth->format('Y'),
            'nextMonth' => (int) $nextMonth->format('n'),
            'days' => $days,
            'monthEvents' => $events,
        ]);
    }
}
