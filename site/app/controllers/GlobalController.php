<?php

namespace app\controllers;

use app\libraries\FileUtils;
use app\models\Button;
use app\models\NavButton;
use app\models\User;
use app\libraries\DateUtils;
use Symfony\Component\Routing\Annotation\Route;

class GlobalController extends AbstractController {
    public function header() {
        $this->core->getOutput()->addServiceWorker();
        $wrapper_files = $this->core->getConfig()->getWrapperFiles();
        $wrapper_urls = array_map(function ($file) {
            return $this->core->buildCourseUrl(['read_file']) . '?' . http_build_query([
                'dir' => 'site',
                'path' => $file,
                'file' => pathinfo($file, PATHINFO_FILENAME),
                'csrf_token' => $this->core->getCsrfToken()
            ]);
        }, $wrapper_files);

        $breadcrumbs = $this->core->getOutput()->getBreadcrumbs();
        $page_name = $this->core->getOutput()->getPageName();
        $css = $this->core->getOutput()->getCss();
        $js = $this->core->getOutput()->getJs();
        $content_only = $this->core->getOutput()->isContentOnly();

        if (array_key_exists('override.css', $wrapper_urls)) {
            $css->add($wrapper_urls['override.css']);
        }

        $unread_notifications_count = null;
        if ($this->core->getUser() && $this->core->getConfig()->isCourseLoaded()) {
            $unread_notifications_count = $this->core->getQueries()->getUnreadNotificationsCount($this->core->getUser()->getId(), null);
        }

        $sidebar_buttons = [];

        $this->prep_sidebar($sidebar_buttons, $unread_notifications_count);

        $current_route = $_SERVER["REQUEST_URI"];
        foreach ($sidebar_buttons as $button) {
            /* @var Button $button */
            $href = $button->getHref();
            if ($href !== null) {
                $parse = parse_url($href);
                $path = isset($parse['path']) ? $parse['path'] : '';
                $query = isset($parse['query']) ? '?' . $parse['query'] : '';
                $fragment = isset($parse['fragment']) ? '#' . $parse['fragment'] : '';
                $route = $path . $query . $fragment;

                if ($this->routeEquals($route, $current_route)) {
                    $class = $button->getClass() ?? "";
                    $class = ($class === "" ? "selected" : $class . " selected");
                    $button->setClass($class);
                }
            }
        }

        $now = $this->core->getDateTimeNow();
        $duck_img = $this->getDuckImage($now);

        return $this->core->getOutput()->renderTemplate('Global', 'header', $breadcrumbs, $wrapper_urls, $sidebar_buttons, $unread_notifications_count, $css->toArray(), $js->toArray(), $duck_img, $page_name, $content_only);
    }

    // ==========================================================================================
    // ==========================================================================================
    public function prep_sidebar(&$sidebar_buttons, $unread_notifications_count) {

        if (!$this->core->userLoaded()) {
            return;
        }
        if ($this->core->getConfig()->isCourseLoaded()) {
            $this->prep_course_sidebar($sidebar_buttons, $unread_notifications_count);
        }
        $this->prep_user_sidebar($sidebar_buttons);

        // $sidebar_buttons[] = new NavButton($this->core, [
        //     "href" => "javascript: toggleSidebar();",
        //     "title" => "Collapse Sidebar",
        //     "icon" => "fa-bars"
        // ]);

        $sidebar_buttons[] = new NavButton($this->core, [
            "href" => $this->core->buildUrl(['authentication', 'logout']),
            "title" => "Logout " . $this->core->getUser()->getDisplayedGivenName(),
            "id" => "logout",
            "icon" => "fa-power-off"
        ]);
    }

    // ==========================================================================================
    public function prep_course_sidebar(&$sidebar_buttons, $unread_notifications_count) {

        if ($this->core->getConfig()->getCourseHomeUrl() != "") {
            $sidebar_buttons[] = new NavButton($this->core, [
                "href" => $this->core->getConfig()->getCourseHomeUrl(),
                "title" => "Course Home",
                "icon" => "fa-home"
            ]);
        }

        $sidebar_buttons[] = new NavButton($this->core, [
            "href" => $this->core->buildCourseUrl(),
            "title" => "(AGA) Automated Grading Assistant",
            "id" => "nav-sidebar-submitty",
            "icon" => "fas fa-star"
         ]);
        $course_path = $this->core->getConfig()->getCoursePath();
        $course_materials_path = $course_path . "/uploads/course_materials";
        $empty = FileUtils::isEmptyDir($course_materials_path);
        if ($this->core->getUser()->accessAdmin() || !($empty)) {
        }
        // --------------------------------------------------------------------------

        $sidebar_buttons[] = new Button($this->core, [
            "class" => "nav-row short-line"
        ]);

        $sidebar_links = FileUtils::joinPaths(
            $this->core->getConfig()->getCoursePath(),
            'site',
            'sidebar.json'
        );
        if (file_exists($sidebar_links)) {
            $links = json_decode(file_get_contents($sidebar_links), true);
            if (is_array($links)) {
                foreach ($links as $link) {
                    if (is_array($link)) {
                        if (empty($link['title'])) {
                            continue;
                        }
                        if (empty($link['icon'])) {
                            $link['icon'] = "fa-question";
                        }
                        if (!str_starts_with($link['icon'], "fa-")) {
                            $link['icon'] = "fa-" . $link['icon'];
                        }
                        $sidebar_buttons[] = new NavButton($this->core, [
                            "href" => $link['link'] ?? null,
                            "title" => $link['title'],
                            "icon" => $link['icon']
                        ]);
                    }
                }
                if (count($links) > 0) {
                    $sidebar_buttons[] = new Button($this->core, [
                        "class" => "nav-row short-line"
                    ]);
                }
            }
        }

        // --------------------------------------------------------------------------

        if ($this->core->getUser()->accessAdmin()) {
            // $sidebar_buttons[] = new NavButton($this->core, [
            //    "href" => $this->core->buildCourseUrl(['users']),
            //    "title" => "Manage Students",
            //    "id" => "nav-sidebar-students",
            //    "icon" => "fa-user-graduate"
            // ]);
            // $sidebar_buttons[] = new NavButton($this->core, [
            //     "href" => $this->core->buildCourseUrl(['graders']),
            //     "title" => "Manage Graders",
            //     "id" => "nav-sidebar-graders",
            //     "icon" => "fa-address-book"
            // ]);
            $sidebar_buttons[] = new NavButton($this->core, [
                "title" => "(AQG)Generate Question sets",
                "href" => $this->core->buildCourseUrl(["generateSet"]),
                "icon" => "fa-file"
            ]);
        }

        // --------------------------------------------------------------------------

        if ($this->core->getUser()->accessGrading()) {
            $sidebar_buttons[] = new Button($this->core, [
                "class" => "nav-row short-line"
            ]);
        }

        if ($this->core->getUser()->accessAdmin()) {
            $sidebar_buttons[] = new NavButton($this->core, [
                "href" => $this->core->buildCourseUrl(['grade_override']),
                "title" => "Grade Override",
                "icon" => "fa-eraser"
            ]);
            $sidebar_buttons[] = new NavButton($this->core, [
                "href" => $this->core->buildCourseUrl(['reports']),
                "title" => "Grade Reports",
                "id" => "nav-sidebar-reports",
                "icon" => "fa-chart-bar"
            ]);
            
        if ($this->core->getUser()->accessAdmin()) {
            $sidebar_buttons[] = new NavButton($this->core, [
                "href" => $this->core->buildCourseUrl(['gradeable']),
                "title" => "New Gradeable",
                "icon" => "fa-plus-square"
            ]);
        }
        }

        // --------------------------------------------------------------------------

        $display_rainbow_grades_summary = $this->core->getConfig()->displayRainbowGradesSummary();

        // --------------------------------------------------------------------------
        $sidebar_buttons[] = new Button($this->core, [
            "class" => "nav-row short-line",
        ]);
    }


    // ==========================================================================================
    public function prep_user_sidebar(&$sidebar_buttons) {

        // --------------------------------------------------------------------------
        // ALL USERS
        $sidebar_buttons[] = new NavButton($this->core, [
            "href" => $this->core->buildUrl(['home']),
            "title" => "My Courses",
            "icon" => "fa-book-reader"
        ]);

        // $sidebar_buttons[] = new NavButton($this->core, [
        //     "href" => $this->core->buildUrl(['user_profile']),
        //     "title" => "Profile",
        //     "icon" => "fa-user"
        // ]);

        $is_instructor = !empty($this->core->getQueries()->getInstructorLevelAccessCourse($this->core->getUser()->getId()));
        // Create the line for all faculties, superusers, and instructors
        if (
            $this->core->getUser()->accessFaculty()
            || $is_instructor
        ) {
            $sidebar_buttons[] = new Button($this->core, [
                "class" => "nav-row short-line",
            ]);
        }
        // --------------------------------------------------------------------------
        // FACULTY & SUPERUSERS ONLY
        if ($this->core->getUser()->accessFaculty()) {
            $sidebar_buttons[] = new NavButton($this->core, [
                "href" => $this->core->buildUrl(['home', 'courses', 'new']),
                "title" => "New Course",
                "icon" => "fa-plus-square"
            ]);
        }

        // --------------------------------------------------------------------------
        // SUPERUSERS ONLY
        if ($this->core->getUser()->getAccessLevel() === User::LEVEL_SUPERUSER) {
            $sidebar_buttons[] = new NavButton($this->core, [
                "href" => $this->core->buildUrl(['superuser', 'gradeables']),
                "title" => "Pending Gradeables",
                "id" => "nav-sidebar-submitty",
                "icon" => "fas fa-clock"
            ]);

            $sidebar_buttons[] = new NavButton($this->core, [
                "href" => $this->core->buildUrl(['update']),
                "title" => "System Update",
                "id" => "nav-sidebar-update",
                "icon" => "fas fa-sync"
            ]);

            $sidebar_buttons[] = new NavButton($this->core, [
                "href" => $this->core->buildUrl(['superuser', 'email']),
                "title" => "Email All",
                "icon" => "fas fa-paper-plane"
            ]);

            $sidebar_buttons[] = new NavButton($this->core, [
                "href" => $this->core->buildUrl(['superuser', 'email_status']),
                "title" => "Email Status",
                "icon" => "fas fa-mail-bulk"
            ]);

            $sidebar_buttons[] = new NavButton($this->core, [
                "href" => $this->core->buildUrl(['superuser', 'saml']),
                "title" => "SAML Management",
                "icon" => "fas fa-user-lock"
            ]);
        }

        // --------------------------------------------------------------------------
        // INSTRUCTOR IN ANY COURSE
        if ($is_instructor) {
        }

        $sidebar_buttons[] = new Button($this->core, [
            "class" => "nav-row short-line",
        ]);
    }

    public function calculateHanukkahDate(int $year): \DateTime {
        // This is the Hanukkah in civil year
        $startdate = jewishtojd(3, 24, $year + 3761);
        $gregorianDate = \DateTime::createFromFormat('m/d/Y', jdtogregorian($startdate));

        // Set the time to 4:00 PM for approx sunfall
        $gregorianDate->setTime(16, 0, 0);

        return $gregorianDate;
    }



    // ==========================================================================================
    private function getDuckImage(\DateTime $now): string {
        $duck_img = 'moorthy_duck/00-original.svg';
        $day = (int) $now->format('j');
        $month = (int) $now->format('n');
        $year = $now->format('Y');
        $yearint = (int) $now->format('Y');
        $hour = (int) $now->format('G');
        $minute = (int) $now->format('i');
        $second = (int) $now->format('s');
        switch ($month) {
            case 12:
                //December (Christmas, Hanukkah)
                $hanukkahStartDateTime = $this->calculateHanukkahDate($yearint);
                $hanukkahEndDateTime = $this->calculateHanukkahDate($yearint);
                $hanukkahEndDateTime->modify('+8 days');

                if ($now >= $hanukkahStartDateTime && $now <= $hanukkahEndDateTime) {
                    // Select the menorah duck image based on the day of Hanukkah
                    $datecounter = (int) $now->diff($hanukkahStartDateTime)->format('%a') + 1;
                    // Ensure datecounter is between 1 and 8
                    $datecounter = max(1, min(8, $datecounter));
                    $menorah_duck = 'moorthy_duck/menorah-duck/' . $datecounter . '.svg';
                    $decemberImages = ['moorthy_duck/12-December.svg', $menorah_duck];
                }
                else {
                    $decemberImages = ['moorthy_duck/12-December.svg'];
                }
                $duck_img = $decemberImages[array_rand($decemberImages)];
                break;
            case 11:
                //November (Thanksgiving)
                //last week of November
                $tgt_date = date('Y-W-n', strtotime("fourth Thursday of November $year"));
                if ($tgt_date === $now->format('Y-W-n')) {
                    $duck_img = 'moorthy_duck/11-november.svg';
                }
                break;
            case 10:
                //October (Halloween)
                if ($day >= 25) {
                    $duck_img = 'moorthy_duck/halloween.png';
                }
                break;
            case 9:
                //September (leaf)
                $duck_img = 'moorthy_duck/09-september.svg';
                break;
            case 8:
                break;
            case 7:
                //July (Independence)
                if ($day <= 7) {
                    $duck_img = 'moorthy_duck/07-july.svg';
                }
                break;
            case 6:
                //June (Pride)
                $duck_img = 'moorthy_duck/06-june.svg';
                break;
            case 5:
                //May (Graduation)
                $duck_img = 'moorthy_duck/05-may.svg';
                break;
            case 4:
                //April (Flowers)
                $duck_img = 'moorthy_duck/04-april.svg';
                break;
            case 3:
                //Saint Patrick's Day (Shamrock)
                if ($day >= 14 && $day <= 20) {
                    $duck_img = 'moorthy_duck/03-march.svg';
                }
                break;
            case 2:
                $februaryImages = ['moorthy_duck/black-history-duck.svg'];
                if ($day <= 3) {
                    $februaryImages[] = 'moorthy_duck/party-duck/party-duck-10th.svg';
                }
                //Valentines (Hearts)
                if ($day >= 11 && $day <= 17) {
                    $februaryImages[] = 'moorthy_duck/02-february.svg';
                }
                $duck_img = $februaryImages[array_rand($februaryImages)];
                break;
            case 1:
                //January (Snowflakes)
                $duck_img = 'moorthy_duck/01-january.svg';

                if ($day >= 28) {
                    $duck_img = 'moorthy_duck/party-duck/party-duck-10th.svg';
                }
                break;
            default:
                break;
        }

        return $duck_img;
    }


    public function footer() {
        $wrapper_files = $this->core->getConfig()->getWrapperFiles();
        $wrapper_urls = array_map(function ($file) {
            return $this->core->buildCourseUrl(['read_file']) . '?' . http_build_query([
                'dir' => 'site',
                'path' => $file,
                'file' => pathinfo($file, PATHINFO_FILENAME),
                'csrf_token' => $this->core->getCsrfToken()
            ]);
        }, $wrapper_files);
        $content_only = $this->core->getOutput()->isContentOnly();
        // Get additional links to display in the global footer.
        $footer_links = [];
        $footer_links_json_file = FileUtils::joinPaths($this->core->getConfig()->getConfigPath(), "footer_links.json");
        if (file_exists($footer_links_json_file)) {
            $footer_links_json_data = file_get_contents($footer_links_json_file);
            if ($footer_links_json_data !== false) {
                $footer_links_json_data = json_decode($footer_links_json_data, true);
                // Validate that every footer link ($row) has required columns: 'url' and 'title'.
                // $row can also have an 'icon' column, but it is optional.
                foreach ($footer_links_json_data as $row) {
                    switch (false) {
                        case array_key_exists('url', $row):
                        case array_key_exists('title', $row):
                            //Validation fail.  Exclude $row.
                            continue 2;
                        default:
                            //Validation OK.  Include $row.
                            if (isset($row['icon']) && !str_starts_with($row['icon'], "fa-")) {
                                $row['icon'] = "fa-" . $row['icon'];
                            }
                            $footer_links[] = $row;
                    }
                }
            }
        }
        // append the help links
        if ($this->core->getConfig()->getSysAdminUrl() !== '') {
            $footer_links[] =  ["title" => "Report Issues", "url" => $this->core->getConfig()->getSysAdminUrl()];
        }
        if ($this->core->getConfig()->getSysAdminEmail() !== '') {
            $footer_links[] =  ["title" => "Email Admin", "url" => $this->core->getConfig()->getSysAdminEmail(), "is_email" => true];
        }

        $performance_warning = $this->core->getConfig()->isDebug() && $this->core->hasDBPerformanceWarning();

        $runtime = $this->core->getOutput()->getRunTime();
        return $this->core->getOutput()->renderTemplate('Global', 'footer', $runtime, $wrapper_urls, $footer_links, $content_only, $performance_warning);
    }

    private function routeEquals(string $a, string $b) {
        //TODO: Have an actual router and use that instead of this string comparison

        $parse_a = parse_url($a);
        $parse_b = parse_url($b);

        $path_a = isset($parse_a['path']) ? $parse_a['path'] : '';
        $path_b = isset($parse_b['path']) ? $parse_b['path'] : '';
        $query_a = isset($parse_a['query']) ? $parse_a['query'] : '';
        $query_b = isset($parse_b['query']) ? $parse_b['query'] : '';

        //Different paths, different urls
        if ($path_a !== $path_b) {
            return false;
        }

        //Query parameters to discard when checking routes
        $ignored_params = [
            "success_login"
        ];

        //Query strings can be in (basically) arbitrary order. Make sure they at least
        // have the same parts though
        $query_a = array_filter(explode("&", $query_a));
        $query_b = array_filter(explode("&", $query_b));

        $query_a = array_filter($query_a, function ($param) use ($ignored_params) {
            return !in_array(explode("=", $param)[0], $ignored_params);
        });
        $query_b = array_filter($query_b, function ($param) use ($ignored_params) {
            return !in_array(explode("=", $param)[0], $ignored_params);
        });

        $diff_a = array_values(array_diff($query_a, $query_b));
        $diff_b = array_values(array_diff($query_b, $query_a));
        $diff = array_merge($diff_a, $diff_b);
        if (count($diff) > 0) {
            //Wacky checking because the navigation page is the default when there
            // is no route in the query
            if (count($diff) === 1 && $diff[0] === "component=navigation") {
                return true;
            }
            return false;
        }

        return true;
    }
}
