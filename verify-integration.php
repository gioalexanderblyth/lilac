<?php
/**
 * Comprehensive integration verification
 */

echo "=== Department & Assignee Integration Verification ===\n\n";

require 'api/config.php';
$pdo = getDatabaseConnection();

$allGood = true;

// Test 1: Verify departments table exists and has data
echo "✓ Step 1: Departments table\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM departments WHERE status = 'active'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  • Active departments: {$result['count']}\n";
    if ($result['count'] == 0) {
        echo "  ⚠ Warning: No active departments found\n";
        $allGood = false;
    }
} catch (Exception $e) {
    echo "  ✗ Error: {$e->getMessage()}\n";
    $allGood = false;
}

// Test 2: Verify users have department column
echo "\n✓ Step 2: Users department column\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE department IS NOT NULL");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  • Users with departments: {$result['count']}\n";
} catch (Exception $e) {
    echo "  ✗ Error: {$e->getMessage()}\n";
    $allGood = false;
}

// Test 3: Verify award_criteria has new columns
echo "\n✓ Step 3: Award criteria schema\n";
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM award_criteria WHERE Field IN ('department', 'assignee_id')");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hasDept = false;
    $hasAssignee = false;

    foreach ($columns as $col) {
        if ($col['Field'] === 'department') {
            echo "  • department column: {$col['Type']}\n";
            $hasDept = true;
        }
        if ($col['Field'] === 'assignee_id') {
            echo "  • assignee_id column: {$col['Type']}\n";
            $hasAssignee = true;
        }
    }

    if (!$hasDept || !$hasAssignee) {
        echo "  ✗ Missing required columns\n";
        $allGood = false;
    }
} catch (Exception $e) {
    echo "  ✗ Error: {$e->getMessage()}\n";
    $allGood = false;
}

// Test 4: Verify API files exist
echo "\n✓ Step 4: API files\n";
$apiFiles = [
    'api/departments.php' => 'Departments API',
    'api/users.php' => 'Users API',
    'api/award-criteria.php' => 'Award Criteria API'
];

foreach ($apiFiles as $file => $name) {
    if (file_exists($file)) {
        echo "  • {$name}: Found\n";
    } else {
        echo "  ✗ {$name}: Missing\n";
        $allGood = false;
    }
}

// Test 5: Check admin-awards.php for form fields
echo "\n✓ Step 5: Admin UI form fields\n";
$adminContent = file_get_contents('admin-awards.php');
if (strpos($adminContent, 'criteria-department') !== false) {
    echo "  • Department dropdown: Found in form\n";
} else {
    echo "  ✗ Department dropdown: Missing\n";
    $allGood = false;
}

if (strpos($adminContent, 'criteria-assignee') !== false) {
    echo "  • Assignee dropdown: Found in form\n";
} else {
    echo "  ✗ Assignee dropdown: Missing\n";
    $allGood = false;
}

// Test 6: Check form submission includes new fields
echo "\n✓ Step 6: Form submission handler\n";
if (strpos($adminContent, 'department: formData.get') !== false) {
    echo "  • Department field submission: Configured\n";
} else {
    echo "  ✗ Department field submission: Missing\n";
    $allGood = false;
}

if (strpos($adminContent, 'assignee_id: formData.get') !== false) {
    echo "  • Assignee field submission: Configured\n";
} else {
    echo "  ✗ Assignee field submission: Missing\n";
    $allGood = false;
}

// Test 7: Check loadDepartmentsAndUsers function
echo "\n✓ Step 7: Dynamic dropdown loading\n";
if (strpos($adminContent, 'loadDepartmentsAndUsers') !== false) {
    echo "  • Dynamic loader function: Found\n";
} else {
    echo "  ✗ Dynamic loader function: Missing\n";
    $allGood = false;
}

// Test 8: Verify analytics displays eligible departments
echo "\n✓ Step 8: Analytics integration\n";
$analyticsContent = file_get_contents('api/award-analytics.php');
if (strpos($analyticsContent, 'eligible_departments') !== false) {
    echo "  • Eligible departments query: Found in analytics\n";
} else {
    echo "  ✗ Eligible departments query: Missing\n";
    $allGood = false;
}

if (strpos($adminContent, 'kpi-eligible-departments') !== false) {
    echo "  • KPI display element: Found in admin UI\n";
} else {
    echo "  ✗ KPI display element: Missing\n";
    $allGood = false;
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
if ($allGood) {
    echo "✓ ALL CHECKS PASSED - Integration Complete!\n";
    echo "\nNext steps:\n";
    echo "1. Open admin-awards.php in your browser\n";
    echo "2. Go to Award Criteria Management section\n";
    echo "3. Click 'Add New Criteria'\n";
    echo "4. Verify department and assignee dropdowns appear\n";
    echo "5. Select values and submit to test the full flow\n";
} else {
    echo "⚠ SOME CHECKS FAILED - Review errors above\n";
}
echo str_repeat("=", 50) . "\n";
