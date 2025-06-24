<?php

namespace App\Model;

use PDO;
use PDOException;

class AdminModel
{
    /**
     * Get admin user by username
     */
    public static function getAdminByUsername(PDO $db, string $username): ?array
    {
        try {
            $stmt = $db->prepare("SELECT id, username, password_hash, role FROM admin_users WHERE username = ?");
            $stmt->execute([$username]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Error getting admin by username: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get total number of students who have expressed interest (student_interest table)
     */
    public static function getTotalInterestedStudents(PDO $db): int
    {
        try {
            $stmt = $db->query("SELECT COUNT(DISTINCT student_id) FROM student_interest");
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting total interested students: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get total number of programmes
     */
    public static function getTotalProgrammes(PDO $db): int
    {
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM programmes");
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting total programmes: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get total number of interests (not registrations!)
     */
    public static function getTotalInterests(PDO $db): int
    {
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM student_interest");
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting total interests: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get list of all programmes (id and name)
     */
    public static function getProgrammes(PDO $db): array
    {
        try {
            // Add 'level' to the SELECT statement
            $stmt = $db->query("SELECT programmes_id, name, level, description, image_url, published FROM programmes ORDER BY name");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting programmes: " . $e->getMessage());
            return [];
        }
    }


    /**
     * Get interested students for a given programme
     */


    // Fetch a single module by ID
    public static function getModuleById(PDO $db, int $id): ?object
    {
        $stmt = $db->prepare("SELECT * FROM modules WHERE modules_id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    // Fetch a single programme by ID
    public static function getProgrammeById(PDO $db, int $id): ?object
    {
        $stmt = $db->prepare("SELECT * FROM programmes WHERE programmes_id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    // Fetch modules by programme ID


    // Add a new programme
    public static function addProgram(PDO $db, string $name, string $description, string $level, ?string $imageUrl): bool
    {
        try {
            $stmt = $db->prepare("INSERT INTO programmes (name, description, level, image_url) VALUES (?, ?, ?, ?)");
            return $stmt->execute([$name, $description, $level, $imageUrl]);
        } catch (PDOException $e) {
            error_log("❌ DB error: " . $e->getMessage());
            return false;
        }
    }




    // Update existing programme
    public static function updateProgram(PDO $db, int $id, string $name, string $description, string $level, ?string $imageUrl): bool
    {
        try {
            $stmt = $db->prepare("UPDATE programmes SET name = ?, description = ?, level = ?, image_url = ? WHERE programmes_id = ?");
            return $stmt->execute([$name, $description, $level, $imageUrl, $id]);
        } catch (PDOException $e) {
            error_log("Error updating programme: " . $e->getMessage());
            return false;
        }
    }


    // Delete a programme
    public static function deleteProgram(PDO $db, int $id): bool
    {
        try {
            $stmt = $db->prepare("DELETE FROM programmes WHERE programmes_id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Error deleting programme: " . $e->getMessage());
            return false;
        }
    }

    // Get programme by id (alternative)
    public static function getProgramById(PDO $db, int $id): ?array
    {
        try {
            $stmt = $db->prepare("SELECT * FROM programmes WHERE programmes_id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Error fetching programme: " . $e->getMessage());
            return null;
        }
    }

    public static function addModule($db, $name, $description, $year)
    {
        $stmt = $db->prepare("INSERT INTO modules (name, description, year) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description, $year]);
        return $db->lastInsertId(); // Return the last inserted module_id
    }

    // Method to link a module to a programme
    public static function linkModuleToProgramme($db, $moduleId, $programmeId)
    {
        $stmt = $db->prepare("INSERT INTO programme_modules (programme_id, module_id) VALUES (?, ?)");
        $stmt->execute([$programmeId, $moduleId]);
    }

    // Method to assign a module leader
    public static function assignModuleLeader($db, $moduleId, $staffId)
    {
        $stmt = $db->prepare("INSERT INTO module_leaders (modules_id, staff_id) VALUES (?, ?)");
        $stmt->execute([$moduleId, $staffId]);
    }


    // Method to edit an existing module
    public static function editModule($db, $moduleId, $name, $description, $year)
    {
        $stmt = $db->prepare("UPDATE modules SET name = ?, description = ?, year = ? WHERE modules_id = ?");
        $stmt->execute([$name, $description, $year, $moduleId]);
    }
    public static function updateModule(PDO $db, int $moduleId, string $name, string $description, int $year, ?int $leaderId): bool
    {
        try {
            // Update module info
            $stmt = $db->prepare("UPDATE modules SET name = ?, description = ?, year = ? WHERE modules_id = ?");
            $stmt->execute([$name, $description, $year, $moduleId]);

            // Update or insert module leader
            if ($leaderId) {
                // Check if leader already assigned
                $checkStmt = $db->prepare("SELECT * FROM module_leaders WHERE modules_id = ?");
                $checkStmt->execute([$moduleId]);

                if ($checkStmt->fetch()) {
                    // Update leader
                    $updateStmt = $db->prepare("UPDATE module_leaders SET staff_id = ? WHERE modules_id = ?");
                    $updateStmt->execute([$leaderId, $moduleId]);
                } else {
                    // Insert new leader
                    $insertStmt = $db->prepare("INSERT INTO module_leaders (modules_id, staff_id) VALUES (?, ?)");
                    $insertStmt->execute([$moduleId, $leaderId]);
                }
            }

            return true;
        } catch (PDOException $e) {
            error_log("Error updating module: " . $e->getMessage());
            return false;
        }
    }


    // Method to delete a module
    public static function deleteModule($db, $moduleId): bool
    {
        try {
            $stmt = $db->prepare("DELETE FROM modules WHERE modules_id = ?");
            return $stmt->execute([$moduleId]);
        } catch (PDOException $e) {
            error_log("Error deleting module: " . $e->getMessage());
            return false;
        }
    }



    public static function fetchAllStaff($db)
    {
        $stmt = $db->query("SELECT * FROM staff");
        return $stmt->fetchAll();
    }



    public static function addStaff(PDO $db, string $name, string $email, string $role, string $password): bool
    {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO staff (name, email, role, password_hash) VALUES (?, ?, ?, ?)");
            return $stmt->execute([$name, $email, $role, $hashedPassword]);
        } catch (PDOException $e) {
            error_log("Error adding staff: " . $e->getMessage());
            return false;
        }
    }


    public static function getStaffById(PDO $db, int $staff_id): ?array
    {
        $stmt = $db->prepare("SELECT * FROM staff WHERE staff_id = ?");
        $stmt->execute([$staff_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function updateStaff($db, $id, $name, $email, $role)
    {
        $query = "UPDATE staff SET name = :name, email = :email, role = :role WHERE staff_id = :id";
        $stmt = $db->prepare($query);
        return $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':role' => $role,
            ':id' => $id
        ]);
    }


    public static function deleteStaff(PDO $db, int $staff_id): bool
    {
        try {
            $stmt = $db->prepare("DELETE FROM staff WHERE staff_id = ?");
            return $stmt->execute([$staff_id]);
        } catch (PDOException $e) {
            error_log("Error deleting staff: " . $e->getMessage());
            return false;
        }
    }

    public static function getInterestedStudents(PDO $db): array
    {
        $stmt = $db->prepare("
        SELECT 
            si.student_interest_id AS id,
            s.name AS student_name,
            s.email,
            p.name AS program_name,
            si.created_at
        FROM student_interest si
        JOIN students s ON si.students_id = s.students_id
        JOIN programmes p ON si.programmes_id = p.programmes_id
        ORDER BY si.created_at DESC
    ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // In AdminModel.php
    public static function getModulesByProgrammeId($db, $programmeId)
    {
        $stmt = $db->prepare("
        SELECT m.modules_id, m.name, m.description, m.year, 
               COALESCE(s.name, 'Not Assigned') AS module_leader_name
        FROM modules m
        LEFT JOIN module_leaders ml ON ml.modules_id = m.modules_id
        LEFT JOIN staff s ON s.staff_id = ml.staff_id
        JOIN programme_modules pm ON pm.module_id = m.modules_id  -- Join the programme_modules table
        WHERE pm.programme_id = ?  -- Use programme_id from programme_modules table
    ");
        $stmt->execute([$programmeId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public static function deleteStudentInterestById(PDO $db, int $id): void
    {
        $stmt = $db->prepare("DELETE FROM student_interest WHERE student_interest_id = :id");
        $stmt->execute(['id' => $id]);
    }

    public static function getStudentEmailsForProgramme(PDO $db, int $programmeId): array
    {
        $stmt = $db->prepare("
        SELECT CONCAT(s.name) AS student_name, s.email, si.created_at
        FROM student_interest si
        JOIN students s ON si.students_id = s.students_id
        WHERE si.programmes_id = :programmeId
    ");
        $stmt->execute(['programmeId' => $programmeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function getInterestCounts(PDO $db): array
    {
        $stmt = $db->query("
        SELECT p.name AS program_name, COUNT(*) as total
        FROM student_interest si
        JOIN programmes p ON si.programmes_id = p.programmes_id
        GROUP BY si.programmes_id
    ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function getInterestedStudentsByProgramme(PDO $db, int $programmeId): array
    {
        $stmt = $db->prepare("
        SELECT si.student_interest_id as id,
               s.name AS student_name,
               s.email,
               p.name AS program_name,
               si.created_at
        FROM student_interest si
        JOIN students s ON si.students_id = s.students_id
        JOIN programmes p ON si.programmes_id = p.programmes_id
        WHERE si.programmes_id = :programmeId
        ORDER BY si.created_at DESC
    ");
        $stmt->execute(['programmeId' => $programmeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function getAllProgrammes(PDO $db): array
    {
        $stmt = $db->query("SELECT programmes_id AS id, name FROM programmes ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function setProgrammePublished(PDO $db, int $programmeId, bool $published): bool
    {
        $stmt = $db->prepare("UPDATE programmes SET published = ? WHERE programmes_id = ?");
        return $stmt->execute([$published ? 1 : 0, $programmeId]);
    }










}