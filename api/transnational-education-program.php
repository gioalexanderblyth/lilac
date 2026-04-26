<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit(); }
$userId = (int) $_SESSION['user_id'];
function parseNullableYear($v){$r=trim((string)$v); if($r==='') return null; if(!preg_match('/^\d{4}$/',$r)) return null; $y=(int)$r; return ($y>=1900&&$y<=2100)?$y:null;}
try {
    $pdo = getDatabaseConnection();
    if ($pdo instanceof FileBasedDatabase) throw new Exception('MySQL required');
    $pdo->exec("CREATE TABLE IF NOT EXISTS transnational_education_programs (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        partner_university VARCHAR(255) NOT NULL,
        country VARCHAR(120) NOT NULL,
        academic_program VARCHAR(255) NOT NULL,
        ched_permit VARCHAR(255) DEFAULT NULL,
        students_count INT(11) NOT NULL DEFAULT 0,
        nationalities TEXT DEFAULT NULL,
        year_started INT(11) DEFAULT NULL,
        program_status VARCHAR(50) DEFAULT NULL,
        deleted_at DATETIME NULL DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY idx_deleted_at (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
        if ($action === 'restore') {
            $id = (int) ($_GET['id'] ?? 0); if ($id <= 0) throw new Exception('ID is required');
            $st = $pdo->prepare("UPDATE transnational_education_programs SET deleted_at=NULL WHERE id=? LIMIT 1"); $st->execute([$id]);
            if ($st->rowCount()<=0) throw new Exception('Record not found');
            echo json_encode(['success'=>true,'message'=>'Record restored']); exit();
        }
        $st = $pdo->query("SELECT id, partner_university, country, academic_program, ched_permit, students_count, nationalities, year_started, program_status, created_at, updated_at FROM transnational_education_programs WHERE deleted_at IS NULL ORDER BY created_at DESC");
        echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit();
    }
    if ($method === 'POST') {
        $action = $_GET['action'] ?? '';
        $partner = trim((string)($_POST['partner_university'] ?? ''));
        $country = trim((string)($_POST['country'] ?? ''));
        $program = trim((string)($_POST['academic_program'] ?? ''));
        $permit = trim((string)($_POST['ched_permit'] ?? ''));
        $students = isset($_POST['students_count']) ? (int) $_POST['students_count'] : 0;
        $nationalities = trim((string)($_POST['nationalities'] ?? ''));
        $year = parseNullableYear($_POST['year_started'] ?? null);
        $status = trim((string)($_POST['program_status'] ?? ''));
        if ($partner==='' || $country==='' || $program==='') throw new Exception('Partner university, country, and academic program are required');
        if ($students < 0) throw new Exception('Student count must be 0 or greater');
        if ($year===null && trim((string)($_POST['year_started'] ?? ''))!=='') throw new Exception('Invalid year');
        if ($action === 'update') {
            $id = (int)($_GET['id'] ?? 0); if ($id<=0) throw new Exception('ID is required');
            $st = $pdo->prepare("UPDATE transnational_education_programs SET partner_university=?, country=?, academic_program=?, ched_permit=?, students_count=?, nationalities=?, year_started=?, program_status=?, updated_at=NOW() WHERE id=? LIMIT 1");
            $st->execute([$partner,$country,$program,$permit===''?null:$permit,$students,$nationalities===''?null:$nationalities,$year,$status===''?null:$status,$id]);
            if ($st->rowCount()===0){$chk=$pdo->prepare("SELECT id FROM transnational_education_programs WHERE id=? LIMIT 1");$chk->execute([$id]);if(!$chk->fetchColumn()) throw new Exception('Record not found');}
            echo json_encode(['success'=>true,'message'=>'Record updated']); exit();
        }
        $st = $pdo->prepare("INSERT INTO transnational_education_programs (user_id, partner_university, country, academic_program, ched_permit, students_count, nationalities, year_started, program_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $st->execute([$userId,$partner,$country,$program,$permit===''?null:$permit,$students,$nationalities===''?null:$nationalities,$year,$status===''?null:$status]);
        echo json_encode(['success'=>true,'id'=>$pdo->lastInsertId(),'message'=>'Record added']); exit();
    }
    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0); if ($id<=0) throw new Exception('ID is required');
        $permanent = isset($_GET['permanent']) && $_GET['permanent']==='true';
        if ($permanent) { $st=$pdo->prepare("DELETE FROM transnational_education_programs WHERE id=? LIMIT 1"); $st->execute([$id]); echo json_encode(['success'=>true,'message'=>'Deleted']); exit(); }
        $st=$pdo->prepare("UPDATE transnational_education_programs SET deleted_at=NOW() WHERE id=? AND deleted_at IS NULL LIMIT 1"); $st->execute([$id]);
        if ($st->rowCount()<=0) throw new Exception('Record not found');
        echo json_encode(['success'=>true,'message'=>'Moved to trash']); exit();
    }
    http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method not allowed']);
} catch (Exception $e) {
    http_response_code(400); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}

