<?php

namespace App\Controller\Admin;

use App\Entity\Announcement;
use App\Entity\Course;
use App\Entity\User;
use App\Form\CourseType;
use App\Form\UserType;
use App\Repository\AnnouncementRepository;
use App\Repository\CourseRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function index(UserRepository $userRepo, CourseRepository $courseRepo, AnnouncementRepository $announcementRepo): Response
    {
        $totalUsers    = $userRepo->count([]);
        $totalCourses  = $courseRepo->count([]);
        $pendingCount  = $userRepo->count(['role' => 'student', 'isConfirmed' => false]);
        $recentApps    = $userRepo->findBy(['role' => 'student', 'isConfirmed' => false], ['id' => 'DESC'], 5);
        $announcements = $announcementRepo->findBy(['isDeleted' => false], ['id' => 'DESC']);

        return $this->render('admin/dashboard.html.twig', [
            'user'          => $this->getUser(),
            'totalUsers'    => $totalUsers,
            'totalCourses'  => $totalCourses,
            'pendingCount'  => $pendingCount,
            'recentApps'    => $recentApps,
            'announcements' => $announcements,
        ]);
    }

    #[Route('/announcements/create', name: 'app_admin_announcement_create', methods: ['POST'])]
    public function announcementCreate(Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('announcement-create', $request->request->get('_token'))) {
            $title   = trim($request->request->get('title', ''));
            $content = trim($request->request->get('content', ''));
            if ($title && $content) {
                $announcement = (new Announcement())
                    ->setTitle($title)
                    ->setContent($content)
                    ->setPostedBy($this->getUser());

                $imageFile = $request->files->get('image');
                if ($imageFile) {
                    $uploadDir = $this->getParameter('announcements_upload_dir');
                    if (!is_dir($uploadDir)) { mkdir($uploadDir, 0775, true); }
                    $ext        = $imageFile->guessExtension() ?: 'jpg';
                    $filename   = uniqid('ann-') . '.' . $ext;
                    $imageFile->move($uploadDir, $filename);
                    $announcement->setImageFilename($filename);
                }

                $em->persist($announcement);
                $em->flush();
                $this->addFlash('success', 'Announcement posted.');
            }
        }
        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/announcements/{id}/delete', name: 'app_admin_announcement_delete', methods: ['POST'])]
    public function announcementDelete(int $id, AnnouncementRepository $repo, EntityManagerInterface $em, Request $request): Response
    {
        $announcement = $repo->find($id);
        if ($announcement && $this->isCsrfTokenValid('announcement-delete-' . $id, $request->request->get('_token'))) {
            $announcement->setIsDeleted(true);
            $em->flush();
            $this->addFlash('success', 'Announcement deleted.');
        }
        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/announcements/{id}/edit', name: 'app_admin_announcement_edit', methods: ['POST'])]
    public function announcementEdit(int $id, AnnouncementRepository $repo, EntityManagerInterface $em, Request $request): Response
    {
        $announcement = $repo->find($id);
        if ($announcement && $this->isCsrfTokenValid('announcement-edit-' . $id, $request->request->get('_token'))) {
            $title   = trim($request->request->get('title', ''));
            $content = trim($request->request->get('content', ''));
            if ($title && $content) {
                $announcement->setTitle($title)->setContent($content);

                // Remove existing image if requested
                if ($request->request->get('remove_image') === '1' && $announcement->getImageFilename()) {
                    $oldPath = $this->getParameter('announcements_upload_dir') . '/' . $announcement->getImageFilename();
                    if (file_exists($oldPath)) { unlink($oldPath); }
                    $announcement->setImageFilename(null);
                }

                // Upload new image if provided
                $imageFile = $request->files->get('image');
                if ($imageFile) {
                    // Delete old file first
                    if ($announcement->getImageFilename()) {
                        $oldPath = $this->getParameter('announcements_upload_dir') . '/' . $announcement->getImageFilename();
                        if (file_exists($oldPath)) { unlink($oldPath); }
                    }
                    $uploadDir = $this->getParameter('announcements_upload_dir');
                    if (!is_dir($uploadDir)) { mkdir($uploadDir, 0775, true); }
                    $ext      = $imageFile->guessExtension() ?: 'jpg';
                    $filename = uniqid('ann-') . '.' . $ext;
                    $imageFile->move($uploadDir, $filename);
                    $announcement->setImageFilename($filename);
                }

                $em->flush();
                $this->addFlash('success', 'Announcement updated.');
            }
        }
        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/applications', name: 'app_admin_applications')]
    public function applications(UserRepository $repo): Response
    {
        return $this->render('admin/applications.html.twig', [
            'user'     => $this->getUser(),
            'pending'  => $repo->findBy(['role' => 'student', 'isConfirmed' => false], ['id' => 'DESC']),
            'approved' => $repo->findBy(['role' => 'student', 'isConfirmed' => true],  ['id' => 'DESC']),
        ]);
    }

    // ── Courses CRUD ────────────────────────────────────────────────────────

    #[Route('/courses', name: 'app_admin_courses')]
    public function courses(CourseRepository $repo): Response
    {
        return $this->render('admin/courses.html.twig', [
            'user'    => $this->getUser(),
            'courses' => $repo->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/courses/new', name: 'app_admin_courses_new')]
    public function courseNew(Request $request, EntityManagerInterface $em): Response
    {
        $course = new Course();
        $form   = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($course);
            $em->flush();
            $this->addFlash('success', 'Course created successfully.');
            return $this->redirectToRoute('app_admin_courses');
        }

        return $this->render('admin/course_form.html.twig', [
            'user'  => $this->getUser(),
            'form'  => $form,
            'title' => 'New Course',
        ]);
    }

    #[Route('/courses/{id}/edit', name: 'app_admin_courses_edit')]
    public function courseEdit(Course $course, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Course updated successfully.');
            return $this->redirectToRoute('app_admin_courses');
        }

        return $this->render('admin/course_form.html.twig', [
            'user'   => $this->getUser(),
            'form'   => $form,
            'title'  => 'Edit Course',
            'course' => $course,
        ]);
    }

    #[Route('/courses/{id}/delete', name: 'app_admin_courses_delete', methods: ['POST'])]
    public function courseDelete(Course $course, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-course-' . $course->getId(), $request->request->get('_token'))) {
            $em->remove($course);
            $em->flush();
            $this->addFlash('success', 'Course deleted.');
        }
        return $this->redirectToRoute('app_admin_courses');
    }

    #[Route('/courses/{id}/students', name: 'app_admin_course_students')]
    public function courseStudents(Course $course, UserRepository $userRepo): Response
    {
        $enrolled = $course->getStudents();
        $enrolledIds = array_map(fn($s) => $s->getId(), $enrolled->toArray());

        $available = array_filter(
            $userRepo->findBy(['role' => 'student', 'isConfirmed' => true], ['lastName' => 'ASC']),
            fn($s) => !in_array($s->getId(), $enrolledIds)
        );

        return $this->render('admin/course_students.html.twig', [
            'user'      => $this->getUser(),
            'course'    => $course,
            'enrolled'  => $enrolled,
            'available' => array_values($available),
        ]);
    }

    #[Route('/courses/{id}/students/add', name: 'app_admin_course_student_add', methods: ['POST'])]
    public function courseStudentAdd(Course $course, Request $request, EntityManagerInterface $em, UserRepository $userRepo): Response
    {
        if ($this->isCsrfTokenValid('add-student-' . $course->getId(), $request->request->get('_token'))) {
            $studentIds = $request->request->all('student_ids');
            $enrolled   = 0;
            foreach ($studentIds as $studentId) {
                $student = $userRepo->find((int) $studentId);
                if ($student && $student->getRole() === 'student' && !$course->hasStudent($student)) {
                    $course->addStudent($student);
                    $enrolled++;
                }
            }
            if ($enrolled > 0) {
                $em->flush();
                $this->addFlash('success', $enrolled . ' student' . ($enrolled > 1 ? 's' : '') . ' enrolled in ' . $course->getName() . '.');
            }
        }
        return $this->redirectToRoute('app_admin_course_students', ['id' => $course->getId()]);
    }

    #[Route('/courses/{id}/students/{studentId}/remove', name: 'app_admin_course_student_remove', methods: ['POST'])]
    public function courseStudentRemove(Course $course, int $studentId, Request $request, EntityManagerInterface $em, UserRepository $userRepo): Response
    {
        if ($this->isCsrfTokenValid('remove-student-' . $course->getId() . '-' . $studentId, $request->request->get('_token'))) {
            $student = $userRepo->find($studentId);
            if ($student) {
                $course->removeStudent($student);
                $em->flush();
                $this->addFlash('success', $student->getDisplayName() . ' removed from ' . $course->getName() . '.');
            }
        }
        return $this->redirectToRoute('app_admin_course_students', ['id' => $course->getId()]);
    }

    // ── Users CRUD ───────────────────────────────────────────────────────────

    #[Route('/users', name: 'app_admin_users')]
    public function users(UserRepository $repo): Response
    {
        return $this->render('admin/users.html.twig', [
            'user'  => $this->getUser(),
            'users' => $repo->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/users/new', name: 'app_admin_users_new')]
    public function userNew(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $newUser = new User();
        $newUser->setIsConfirmed(true); // admin-created accounts are confirmed immediately
        $form    = $this->createForm(UserType::class, $newUser, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('plainPassword')->getData();
            $newUser->setPassword($hasher->hashPassword($newUser, $plain));
            $em->persist($newUser);
            $em->flush();
            $this->addFlash('success', 'User created successfully.');
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/user_form.html.twig', [
            'user'  => $this->getUser(),
            'form'  => $form,
            'title' => 'New User',
        ]);
    }

    #[Route('/users/{id}/edit', name: 'app_admin_users_edit')]
    public function userEdit(User $editUser, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $form = $this->createForm(UserType::class, $editUser, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('plainPassword')->getData();
            if ($plain) {
                $editUser->setPassword($hasher->hashPassword($editUser, $plain));
            }
            $em->flush();
            $this->addFlash('success', 'User updated successfully.');
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/user_form.html.twig', [
            'user'     => $this->getUser(),
            'form'     => $form,
            'title'    => 'Edit User',
            'editUser' => $editUser,
        ]);
    }

    #[Route('/users/{id}/delete', name: 'app_admin_users_delete', methods: ['POST'])]
    public function userDelete(User $editUser, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-user-' . $editUser->getId(), $request->request->get('_token'))) {
            $em->remove($editUser);
            $em->flush();
            $this->addFlash('success', 'User deleted.');
        }
        return $this->redirectToRoute('app_admin_users');
    }

    // ── Student Confirmation ─────────────────────────────────────────────────

    #[Route('/applications/{id}/confirm', name: 'app_admin_application_confirm', methods: ['POST'])]
    public function applicationConfirm(User $student, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('confirm-student-' . $student->getId(), $request->request->get('_token'))) {
            $student->setIsConfirmed(true);
            $em->flush();
            $this->addFlash('success', $student->getDisplayName() . '\'s application has been approved.');
        }
        return $this->redirectToRoute('app_admin_applications');
    }

    #[Route('/applications/{id}/reject', name: 'app_admin_application_reject', methods: ['POST'])]
    public function applicationReject(User $student, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('reject-student-' . $student->getId(), $request->request->get('_token'))) {
            $em->remove($student);
            $em->flush();
            $this->addFlash('success', 'Application rejected and account removed.');
        }
        return $this->redirectToRoute('app_admin_applications');
    }

    #[Route('/users/{id}/toggle-confirm', name: 'app_admin_user_toggle_confirm', methods: ['POST'])]
    public function userToggleConfirm(User $targetUser, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('toggle-confirm-' . $targetUser->getId(), $request->request->get('_token'))) {
            $targetUser->setIsConfirmed(!$targetUser->isConfirmed());
            $em->flush();
            $msg = $targetUser->isConfirmed() ? 'Account confirmed.' : 'Account approval revoked.';
            $this->addFlash('success', $msg);
        }
        return $this->redirectToRoute('app_admin_users');
    }
}

