<?php

namespace App\Controller\Student;

use App\Repository\AnnouncementRepository;
use App\Repository\CourseRepository;
use App\Repository\CourseWeekRepository;
use App\Entity\Course;
use App\Entity\CourseMaterial;
use App\Entity\StudentMaterialView;
use App\Entity\User;
use App\Repository\StudentMaterialViewRepository;
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

        $myCourses = $courseRepo->createEnrolledInActiveTermQueryBuilder($me)
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

        $myCourses = $courseRepo->createEnrolledInActiveTermQueryBuilder($me)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('student/courses.html.twig', [
            'user'      => $me,
            'myCourses' => $myCourses,
        ]);
    }

    #[Route('/courses/{id}', name: 'app_student_course_view')]
    public function courseView(
        int $id,
        CourseRepository $courseRepo,
        CourseWeekRepository $weekRepo,
        \Doctrine\ORM\EntityManagerInterface $em,
        StudentMaterialViewRepository $materialViewRepository
    ): Response
    {
        /** @var \App\Entity\User $me */
        $me     = $this->getUser();
        $course = $courseRepo->findEnrolledInActiveTermCourseForStudent($me, $id);

        if (!$course) {
            $this->addFlash('error', 'You are not enrolled in that course.');
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

        $orderedWeeksRaw = $weekRepo->findBy(
            ['course' => $course],
            ['displayOrder' => 'ASC', 'weekNumber' => 'ASC']
        );

        $orderedWeekNumbers = [];
        foreach ($orderedWeeksRaw as $week) {
            $weekNumber = $week->getWeekNumber();
            $orderedWeekNumbers[] = $weekNumber;
        }

        // Keep placeholders for missing weeks while preserving professor-defined order first.
        for ($w = 1; $w <= 14; $w++) {
            if (!\in_array($w, $orderedWeekNumbers, true)) {
                $orderedWeekNumbers[] = $w;
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

        $viewedMaterialIds = $materialViewRepository->findViewedMaterialIdsForStudentInCourse($me, $course);
        $viewedMaterialSet = array_fill_keys($viewedMaterialIds, true);
        $submittedWeekIds = $this->getSubmittedWeekIdsForCourse($em, $me, $course);
        [$unlockedMaterialIds, $materialLockReasons] = $this->buildMaterialAccessMap(
            $course,
            $viewedMaterialSet,
            $submittedWeekIds
        );

        return $this->render('student/course_view.html.twig', [
            'user'              => $me,
            'course'            => $course,
            'weeks'             => $weeks,
            'orderedWeekNumbers' => $orderedWeekNumbers,
            'submissionsByWeek' => $submissionsByWeek,
            'viewedMaterialIds' => $viewedMaterialSet,
            'unlockedMaterialIds' => $unlockedMaterialIds,
            'materialLockReasons' => $materialLockReasons,
        ]);
    }

    #[Route('/materials/{id}/mark-viewed', name: 'app_student_material_mark_viewed', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function markMaterialViewed(
        int $id,
        Request $request,
        \Doctrine\ORM\EntityManagerInterface $em,
        CourseRepository $courseRepository,
        StudentMaterialViewRepository $materialViewRepository
    ): \Symfony\Component\HttpFoundation\JsonResponse {
        /** @var User $student */
        $student = $this->getUser();

        if (!$this->isCsrfTokenValid('student-mark-material-view', (string) $request->request->get('token'))) {
            return $this->json(['success' => false, 'message' => 'Invalid request token.'], Response::HTTP_FORBIDDEN);
        }

        $material = $em->find(CourseMaterial::class, $id);
        if (!$material || !$material->getWeek()) {
            return $this->json(['success' => false, 'message' => 'Module not found.'], Response::HTTP_NOT_FOUND);
        }

        $course = $material->getCourse();
        $enrolledCourse = $courseRepository->findEnrolledInActiveTermCourseForStudent($student, (int) $course->getId());
        if (!$enrolledCourse) {
            return $this->json(['success' => false, 'message' => 'You are not enrolled in this course.'], Response::HTTP_FORBIDDEN);
        }

        $viewedMaterialIds = $materialViewRepository->findViewedMaterialIdsForStudentInCourse($student, $course);
        $viewedMaterialSet = array_fill_keys($viewedMaterialIds, true);
        $submittedWeekIds = $this->getSubmittedWeekIdsForCourse($em, $student, $course);
        [$unlockedMaterialIds, $materialLockReasons] = $this->buildMaterialAccessMap($course, $viewedMaterialSet, $submittedWeekIds);

        $materialId = (int) $material->getId();
        if (!(bool) ($unlockedMaterialIds[$materialId] ?? false)) {
            return $this->json([
                'success' => false,
                'message' => $materialLockReasons[$materialId] ?? 'This module is locked until earlier requirements are completed.',
            ], Response::HTTP_FORBIDDEN);
        }

        $existing = $materialViewRepository->findOneBy([
            'student' => $student,
            'material' => $material,
        ]);

        if (!$existing) {
            $view = new StudentMaterialView();
            $view->setStudent($student)
                ->setMaterial($material)
                ->setViewedAt(new \DateTimeImmutable());
            $em->persist($view);
            $em->flush();
        }

        return $this->json(['success' => true]);
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
        $course = $courseRepo->findEnrolledInActiveTermCourseForStudent($me, $courseId);
        $week = $weekRepo->find($weekId);

        if (!$course || !$week || $week->getCourse() !== $course) {
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
    public function calendar(Request $request, \App\Repository\ProfessorEventRepository $eventRepository): Response
    {
        /** @var \App\Entity\User $student */
        $student = $this->getUser();

        $year = max(2000, min(2100, (int) $request->query->get('year', (int) (new \DateTimeImmutable('now'))->format('Y'))));
        $month = max(1, min(12, (int) $request->query->get('month', (int) (new \DateTimeImmutable('now'))->format('n'))));
        $monthStart = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
        $calendarStart = $monthStart->modify('-' . $monthStart->format('w') . ' days');
        $calendarEnd = $calendarStart->modify('+41 days')->setTime(23, 59, 59);

        // Fetch events for professors whose courses this student is taking
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

    /**
     * @param array<int, bool> $viewedMaterialSet
     * @param int[] $submittedWeekIds
     * @return array{0: array<int, bool>, 1: array<int, string>}
     */
    private function buildMaterialAccessMap(Course $course, array $viewedMaterialSet, array $submittedWeekIds): array
    {
        $submittedWeekSet = array_fill_keys($submittedWeekIds, true);
        $unlockedMaterialIds = [];
        $materialLockReasons = [];
        $previousWeeksComplete = true;

        foreach ($course->getWeeks() as $week) {
            $weekMaterials = $week->getMaterials()->toArray();
            $allWeekMaterialsViewed = true;

            foreach ($weekMaterials as $material) {
                if (!$material instanceof CourseMaterial || !$material->getId()) {
                    continue;
                }

                $materialId = (int) $material->getId();
                $isUnlocked = $previousWeeksComplete;

                if ($isUnlocked) {
                    $prerequisite = $material->getPrerequisiteMaterial();
                    if ($prerequisite instanceof CourseMaterial && $prerequisite->getCourse() === $course && $prerequisite->getId()) {
                        $prerequisiteId = (int) $prerequisite->getId();
                        $isUnlocked = isset($viewedMaterialSet[$prerequisiteId]);

                        if (!$isUnlocked) {
                            $materialLockReasons[$materialId] = sprintf('Open "%s" first before this module.', $prerequisite->getOriginalName());
                        }
                    }
                } else {
                    $materialLockReasons[$materialId] = 'Complete all modules and required submissions from previous weeks first.';
                }

                $unlockedMaterialIds[$materialId] = $isUnlocked;
                if (!isset($viewedMaterialSet[$materialId])) {
                    $allWeekMaterialsViewed = false;
                }
            }

            $submissionCompleted = !$week->isSubmissionRequired() || isset($submittedWeekSet[(int) $week->getId()]);
            $isWeekComplete = $allWeekMaterialsViewed && $submissionCompleted;
            $previousWeeksComplete = $previousWeeksComplete && $isWeekComplete;
        }

        return [$unlockedMaterialIds, $materialLockReasons];
    }

    /**
     * @return int[]
     */
    private function getSubmittedWeekIdsForCourse(
        \Doctrine\ORM\EntityManagerInterface $em,
        User $student,
        Course $course
    ): array {
        $submittedWeekIds = $em->createQuery(
            'SELECT DISTINCT IDENTITY(s.courseWeek) AS weekId
            FROM App\\Entity\\StudentSubmission s
            JOIN s.courseWeek w
            WHERE s.student = :student
            AND w.course = :course'
        )
            ->setParameter('student', $student)
            ->setParameter('course', $course)
            ->getScalarResult();

        return array_values(array_map(static fn (array $row): int => (int) $row['weekId'], $submittedWeekIds));
    }
}
