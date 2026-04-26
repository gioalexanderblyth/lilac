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
function parseNullableFloat($v){$r=trim((string)$v); if($r==='') return null; if(!is_numeric($r)) return null; $n=(float)$r; return $n>=0?$n:null;}
function parseNullableInt($v){$r=trim((string)$v); if($r==='') return null; if(!preg_match('/^\d+$/',$r)) return null; return (int)$r;}
try {
    $pdo = getDatabaseConnection();
    if ($pdo instanceof FileBasedDatabase) throw new Exception('MySQL required');
    $pdo->exec("CREATE TABLE IF NOT EXISTS studyph_program_records (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        year INT(11) DEFAULT NULL,
        kra VARCHAR(255) DEFAULT NULL,
        project_title VARCHAR(255) NOT NULL,
        field_area VARCHAR(255) DEFAULT NULL,
        sdg_covered VARCHAR(255) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        amount DECIMAL(14,2) DEFAULT NULL,
        beneficiaries_qty INT(11) DEFAULT NULL,
        beneficiaries_type VARCHAR(255) DEFAULT NULL,
        kpi VARCHAR(255) DEFAULT NULL,
        kpi_value VARCHAR(255) DEFAULT NULL,
        deleted_at DATETIME NULL DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY idx_deleted_at (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
        if ($action === 'restore') {
            $id = (int)($_GET['id'] ?? 0); if ($id<=0) throw new Exception('ID is required');
            $st = $pdo->prepare("UPDATE studyph_program_records SET deleted_at=NULL WHERE id=? LIMIT 1"); $st->execute([$id]);
            if ($st->rowCount()<=0) throw new Exception('Record not found');
            echo json_encode(['success'=>true,'message'=>'Record restored']); exit();
        }
        $st = $pdo->query("SELECT id, year, kra, project_title, field_area, sdg_covered, description, amount, beneficiaries_qty, beneficiaries_type, kpi, kpi_value, created_at, updated_at FROM studyph_program_records WHERE deleted_at IS NULL ORDER BY created_at DESC");
        echo json_encode(['success'=>true,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)]); exit();
    }
    if ($method === 'POST') {
        $action = $_GET['action'] ?? '';
        $year = parseNullableYear($_POST['year'] ?? null);
        $kra = trim((string)($_POST['kra'] ?? ''));
        $title = trim((string)($_POST['project_title'] ?? ''));
        $field = trim((string)($_POST['field_area'] ?? ''));
        $sdg = trim((string)($_POST['sdg_covered'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $amount = parseNullableFloat($_POST['amount'] ?? null);
        $qty = parseNullableInt($_POST['beneficiaries_qty'] ?? null);
        $type = trim((string)($_POST['beneficiaries_type'] ?? ''));
        $kpi = trim((string)($_POST['kpi'] ?? ''));
        $kpiValue = trim((string)($_POST['kpi_value'] ?? ''));
        if ($year === null && trim((string)($_POST['year'] ?? '')) !== '') throw new Exception('Invalid year');
        if ($title === '') throw new Exception('Project title is required');
        if ($amount === null && trim((string)($_POST['amount'] ?? '')) !== '') throw new Exception('Invalid amount');
        if ($qty === null && trim((string)($_POST['beneficiaries_qty'] ?? '')) !== '') throw new Exception('Invalid beneficiaries quantity');
        if ($action === 'update') {
            $id = (int)($_GET['id'] ?? 0); if ($id<=0) throw new Exception('ID is required');
            $st = $pdo->prepare("UPDATE studyph_program_records SET year=?, kra=?, project_title=?, field_area=?, sdg_covered=?, description=?, amount=?, beneficiaries_qty=?, beneficiaries_type=?, kpi=?, kpi_value=?, updated_at=NOW() WHERE id=? LIMIT 1");
            $st->execute([$year, $kra===''?null:$kra, $title, $field===''?null:$field, $sdg===''?null:$sdg, $desc===''?null:$desc, $amount, $qty, $type===''?null:$type, $kpi===''?null:$kpi, $kpiValue===''?null:$kpiValue, $id]);
            if ($st->rowCount()===0){$chk=$pdo->prepare("SELECT id FROM studyph_program_records WHERE id=? LIMIT 1");$chk->execute([$id]);if(!$chk->fetchColumn()) throw new Exception('Record not found');}
            echo json_encode(['success'=>true,'message'=>'Record updated']); exit();
        }
        $st = $pdo->prepare("INSERT INTO studyph_program_records (user_id, year, kra, project_title, field_area, sdg_covered, description, amount, beneficiaries_qty, beneficiaries_type, kpi, kpi_value) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $st->execute([$userId, $year, $kra===''?null:$kra, $title, $field===''?null:$field, $sdg===''?null:$sdg, $desc===''?null:$desc, $amount, $qty, $type===''?null:$type, $kpi===''?null:$kpi, $kpiValue===''?null:$kpiValue]);
        echo json_encode(['success'=>true,'id'=>$pdo->lastInsertId(),'message'=>'Record added']); exit();
    }
    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0); if ($id<=0) throw new Exception('ID is required');
        $permanent = isset($_GET['permanent']) && $_GET['permanent']==='true';
        if ($permanent) { $st=$pdo->prepare("DELETE FROM studyph_program_records WHERE id=? LIMIT 1"); $st->execute([$id]); echo json_encode(['success'=>true,'message'=>'Deleted']); exit(); }
        $st = $pdo->prepare("UPDATE studyph_program_records SET deleted_at=NOW() WHERE id=? AND deleted_at IS NULL LIMIT 1"); $st->execute([$id]);
        if ($st->rowCount()<=0) throw new Exception('Record not found');
        echo json_encode(['success'=>true,'message'=>'Moved to trash']); exit();
    }
    http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method not allowed']);
} catch (Exception $e) {
    http_response_code(400); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}

