<?php
namespace App\Model;

use PDO;

class StaffModel {
    public static function getByEmail(PDO $db, string $email): ?array {
        $stmt = $db->prepare("SELECT * FROM staff WHERE email = :email");
        $stmt->execute(['email'=>$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function getModulesForStaff(PDO $db, int $staffId): array {
        $sql = <<<SQL
SELECT
    m.modules_id,
    m.name,
    m.year
FROM modules AS m
INNER JOIN module_leaders AS ml
    ON m.modules_id = ml.modules_id
WHERE ml.staff_id = :staffId
ORDER BY m.year, m.name
SQL;

        $stmt = $db->prepare($sql);
        $stmt->execute(['staffId' => $staffId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function getProgrammesForStaff(PDO $db, int $staffId): array {
        $sql = <<<SQL
SELECT DISTINCT
    p.programmes_id,
    p.name,
    p.level
FROM programmes AS p
INNER JOIN programme_modules AS pm
    ON p.programmes_id = pm.programme_id
INNER JOIN module_leaders AS ml
    ON pm.module_id = ml.modules_id
WHERE ml.staff_id = :staffId
ORDER BY p.name
SQL;

        $stmt = $db->prepare($sql);
        $stmt->execute(['staffId' => $staffId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    public static function getModuleById(PDO $db, int $moduleId): ?array {
        $stmt = $db->prepare("SELECT * FROM modules WHERE modules_id = :id");
        $stmt->execute(['id' => $moduleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function getStudentsForModule(PDO $db, int $moduleId): array {

        $stmt = $db->prepare("
        SELECT s.*
        FROM students AS s
        INNER JOIN module_students AS ms ON s.student_id = ms.student_id
        WHERE ms.module_id = :moduleId
    ");
        $stmt->execute(['moduleId' => $moduleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    public static function getProgrammeById(PDO $db, int $programmeId): ?array {
        $stmt = $db->prepare("SELECT * FROM programmes WHERE programmes_id = :id");
        $stmt->execute(['id' => $programmeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function getModulesForProgramme(PDO $db, int $programmeId): array {
        $stmt = $db->prepare("
        SELECT m.*
        FROM modules m
        INNER JOIN programme_modules pm ON m.modules_id = pm.module_id
        WHERE pm.programme_id = :programmeId
        ORDER BY m.year, m.name
    ");
        $stmt->execute(['programmeId' => $programmeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    public static function getById(\PDO $db, int $staffId): ?array
    {
        $stmt = $db->prepare('SELECT * FROM staff WHERE staff_id = :staff_id');
        $stmt->bindValue(':staff_id', $staffId, \PDO::PARAM_INT);
        $stmt->execute();

        $staff = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $staff ?: null;
    }
    public static function updateProfile(PDO $db, int $staffId, array $data): void {
        $sql = "UPDATE staff SET name = :name, email = :email, bio = :bio";
        $params = [
            'name' => $data['name'],
            'email' => $data['email'],
            'bio' => $data['bio'],
            'staff_id' => $staffId
        ];

        if (!empty($data['password'])) {
            $sql .= ", password_hash = :password_hash";
            $params['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $sql .= " WHERE staff_id = :staff_id";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }



}
