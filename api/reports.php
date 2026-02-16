<?php
/**
 * Advanced Reporting API
 * Generates comprehensive reports for all entities
 */

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];
$isAdmin = (($_SESSION['user'] ?? [])['role'] ?? '') === 'admin';

// Get database connection
try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database required for reporting']);
        exit();
    }

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $reportType = $input['report_type'] ?? '';
        $format = $input['format'] ?? 'json'; // json, csv, pdf
        $dateFrom = $input['date_from'] ?? null;
        $dateTo = $input['date_to'] ?? null;
        $filters = $input['filters'] ?? [];

        $report = [];

        switch ($reportType) {
            case 'mou_renewals':
                // MOU/MOA Renewal Report
                $sql = "SELECT m.*, u.username as created_by, 
                       DATEDIFF(m.end_date, CURDATE()) as days_remaining
                       FROM mou_moa m
                       LEFT JOIN users u ON m.user_id = u.id
                       WHERE m.end_date IS NOT NULL";
                
                if ($dateFrom) {
                    $sql .= " AND m.end_date >= ?";
                    $params[] = $dateFrom;
                }
                if ($dateTo) {
                    $sql .= " AND m.end_date <= ?";
                    $params[] = $dateTo;
                }
                if (!empty($filters['status'])) {
                    $sql .= " AND m.status = ?";
                    $params[] = $filters['status'];
                }
                
                $sql .= " ORDER BY m.end_date ASC";
                
                $stmt = $pdo->prepare($sql);
                if (isset($params)) {
                    $stmt->execute($params);
                } else {
                    $stmt->execute();
                }
                $report = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'awards_summary':
                // Awards Summary Report
                $sql = "SELECT 
                        COUNT(*) as total_awards,
                        COUNT(DISTINCT a.user_id) as unique_users,
                        COUNT(CASE WHEN a.status = 'completed' THEN 1 END) as completed,
                        COUNT(CASE WHEN a.status = 'pending' THEN 1 END) as pending,
                        AVG(CAST(a.match_percentage AS DECIMAL(5,2))) as avg_match
                        FROM awards a";
                
                $whereConditions = [];
                $params = [];
                
                if ($dateFrom) {
                    $whereConditions[] = "a.created_at >= ?";
                    $params[] = $dateFrom;
                }
                if ($dateTo) {
                    $whereConditions[] = "a.created_at <= ?";
                    $params[] = $dateTo;
                }
                
                if (!empty($whereConditions)) {
                    $sql .= " WHERE " . implode(" AND ", $whereConditions);
                }
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $report = $stmt->fetch(PDO::FETCH_ASSOC);
                break;

            case 'events_summary':
                // Events Summary Report
                $sql = "SELECT 
                        COUNT(*) as total_events,
                        COUNT(DISTINCT e.user_id) as unique_creators,
                        COUNT(CASE WHEN e.status = 'confirmed' THEN 1 END) as confirmed,
                        COUNT(CASE WHEN e.status = 'planned' THEN 1 END) as planned,
                        COUNT(CASE WHEN e.event_date >= CURDATE() THEN 1 END) as upcoming
                        FROM events e";
                
                $whereConditions = [];
                $params = [];
                
                if ($dateFrom) {
                    $whereConditions[] = "e.event_date >= ?";
                    $params[] = $dateFrom;
                }
                if ($dateTo) {
                    $whereConditions[] = "e.event_date <= ?";
                    $params[] = $dateTo;
                }
                
                if (!empty($whereConditions)) {
                    $sql .= " WHERE " . implode(" AND ", $whereConditions);
                }
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $report = $stmt->fetch(PDO::FETCH_ASSOC);
                break;

            case 'user_activity':
                // User Activity Report
                if (!$isAdmin) {
                    throw new Exception('Admin access required');
                }
                
                $sql = "SELECT 
                        u.id, u.username, u.full_name, u.email,
                        COUNT(DISTINCT a.id) as awards_count,
                        COUNT(DISTINCT e.id) as events_count,
                        COUNT(DISTINCT m.id) as mou_count,
                        MAX(GREATEST(COALESCE(a.created_at, '1970-01-01'), 
                                     COALESCE(e.created_at, '1970-01-01'),
                                     COALESCE(m.created_at, '1970-01-01'))) as last_activity
                        FROM users u
                        LEFT JOIN awards a ON u.id = a.user_id
                        LEFT JOIN events e ON u.id = e.user_id
                        LEFT JOIN mou_moa m ON u.id = m.user_id
                        GROUP BY u.id
                        ORDER BY last_activity DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $report = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            default:
                throw new Exception('Invalid report type');
        }

        // Generate report in requested format
        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            if (is_array($report) && !empty($report)) {
                // Write headers
                fputcsv($output, array_keys($report[0]));
                
                // Write data
                foreach ($report as $row) {
                    fputcsv($output, $row);
                }
            }
            
            fclose($output);
            exit();
        } elseif ($format === 'json') {
            echo json_encode([
                'success' => true,
                'report_type' => $reportType,
                'data' => $report,
                'generated_at' => date('Y-m-d H:i:s'),
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]);
        } else {
            throw new Exception('Unsupported format. Use json or csv');
        }

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

