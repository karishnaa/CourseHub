<?php

use App\Controller\Admin\AdminController;
use App\Controller\Staff\StaffController;
use App\Controller\Student\ProgrammeController;
use App\Controller\Student\RegisterController;
use App\Controller\Student\CourseController;
use App\Controller\LogoutController;
use App\Controller\Student\LoginController;
use App\Controller\Student\StudentController;
use App\Controller\Student\ContactController;
use App\Controller\Student\HomeController;
use App\Middleware\StaffAuth;
use App\Middleware\AdminAuth;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function (App $app) {

    $app->get('/', [HomeController::class, 'show'])->setName('home');


    $app->get('/contact', [ContactController::class, 'showForm'])->setName('contact-form');
    $app->post('/contact', [ContactController::class, 'submit'])->setName('submit-contact');



    $app->map(['GET', 'POST'], '/login', [LoginController::class, 'login'])->setName('login');


    $app->group('/programmes', function (RouteCollectorProxy $group) {
        $group->get('', [ProgrammeController::class, 'showAllProgrammes'])->setName('programmes-list');
        $group->post('/search', [ProgrammeController::class, 'searchProgrammes']);


    });
    $app->get('/program-details/{id}', [ProgrammeController::class, 'show'])->setName('program-details');

    $app->group('/student', function (RouteCollectorProxy $group) {
        $group->get('/dashboard', [StudentController::class, 'dashboard'])->setName('student-dashboard');


        $group->get('/profile', [StudentController::class, 'showEditProfile'])->setName('student-profile');


        $group->post('/profile/update', [StudentController::class, 'updateProfile'])->setName('student-profile-update');
        $group->post('/withdraw-interest', [StudentController::class, 'withdrawInterest'])->setName('withdraw-interest');

    });





    $app->get('/registerinterest', [RegisterController::class, 'showForm'])->setName('register-form');
    $app->post('/registerinterest', [RegisterController::class, 'register'])->setName('register');

    $app->get('/logout', [LogoutController::class, 'logout'])
        ->setName('logout');


    $app->post('/search-courses', [CourseController::class, 'searchCourses'])->setName('search-courses');


    $app->get('/admin/login', [AdminController::class, 'loginPage'])->setName('admin.login');

    $app->post('/admin/login', [AdminController::class, 'handleLogin'])->setName('admin.login.post');


    $app->group('/admin', function(RouteCollectorProxy $group) {
        $group->get('/dashboard', [AdminController::class, 'dashboard'])->setName('admin.dashboard');

        // Programmes CRUD routes...
        $group->get('/programmes', [AdminController::class, 'managePrograms'])
            ->setName('admin.programmes.list');

        $group->get('/programmes/add', [AdminController::class, 'showAddProgramme'])
            ->setName('admin.programmes.add');

        $group->post('/programmes/add', [AdminController::class, 'processAddProgramme'])
            ->setName('admin.programmes.add.post');


        $group->get('/programmes/{id}/edit', [AdminController::class, 'showEditProgramme'])
            ->setName('admin.programmes.edit');

        $group->post('/programmes/{id}/edit', [AdminController::class, 'processEditProgramme'])
            ->setName('admin.programmes.edit.post');

        $group->post('/programmes/{id}/delete', [AdminController::class, 'deleteProgramme'])
            ->setName('admin.programmes.delete');





        $group->post('/programmes/{id:[0-9]+}/publish', AdminController::class . ':togglePublish')->setName('admin.programmes.publish');


        // Modules CRUD routes in AdminController
        $group->get('/modules', [AdminController::class, 'manageModules'])->setName('admin.modules.list');
        $group->get('/modules/add', [AdminController::class, 'showAddModule'])->setName('admin.modules.add');
        $group->post('/modules/add', [AdminController::class, 'processAddModule'])->setName('admin.modules.add.post');
        $group->get('/modules/{id}/edit', [AdminController::class, 'showEditModule'])->setName('admin.modules.edit');
        $group->post('/modules/{id}/edit', [AdminController::class, 'processEditModule'])->setName('admin.modules.edit.post');
        $group->post('/modules/{id}/delete', [AdminController::class, 'deleteModule'])->setName('admin.modules.delete');



        // Student Interests
        $group->get('/interests', [AdminController::class, 'viewStudentInterest'])->setName('admin.interests.list');
        $group->get('/interests/export/{programmeId}', [AdminController::class, 'exportMailingList'])->setName('admin.interests.export');
        $group->post('/interests/delete/{id}', [AdminController::class, 'deleteStudentInterest'])->setName('admin.interests.delete');
        $group->get('/interests/export-all', [AdminController::class, 'exportAllInterests'])->setName('admin.interests.export_all');

        // Staff CRUD routes in AdminController
        $group->get('/staff', [AdminController::class, 'showStaffList'])->setName('admin.staff.list');
        $group->get('/staff/add', [AdminController::class, 'showAddStaffForm'])->setName('admin.staff.add');
        $group->post('/staff/add', [AdminController::class, 'processAddStaff'])->setName('admin.staff.add.post');
        $group->post('/staff/{id}/delete', [AdminController::class, 'deleteStaff'])->setName('admin.staff.delete');


        $group->post('/staff/{id}/edit', [AdminController::class, 'processEditStaff'])->setName('admin.staff.edit.post');

    })->add(AdminAuth::class);



    $app->get('/staff/login',  [StaffController::class, 'showLoginForm'])
        ->setName('staff.login');
    $app->post('/staff/login', [StaffController::class, 'handleLogin'])
        ->setName('staff.login.post');

    $app->group('/staff', function($g) {
        $g->get('/dashboard', [StaffController::class, 'dashboard'])
            ->setName('staff.dashboard');


        $g->get('/edit-profile', [StaffController::class, 'editProfile'])
            ->setName('staff.profile.edit');
        $g->get('/profile', [StaffController::class, 'editProfile'])
            ->setName('staff.profile.edit');

        $g->post('/profile', [StaffController::class, 'updateProfile'])
            ->setName('staff.profile.update');
        $g->get('/programme/{id}', [StaffController::class, 'viewProgramme'])->setName('staff.programme.view');
        $g->get('/module/{id}', [StaffController::class, 'viewModule'])->setName('staff.module.view');


    })->add(StaffAuth::class);



    $app->map(['GET', 'POST'], '/{routes:.+}', function (Request $request, Response $response) {
        $requestedPath = $request->getUri()->getPath();
        error_log("Requested path: $requestedPath");
        $response->getBody()->write('Page not found');
        return $response->withStatus(404);
    });


};



