<?php
/**
 * PHP Extensions Check
 * Quick diagnostic to verify required extensions are loaded
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Extensions Check - LILAC Awards</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #137fec;
            padding-bottom: 10px;
        }
        .check-item {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        .icon {
            font-size: 24px;
            font-weight: bold;
        }
        .info {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            color: #0c5460;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .code {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
        }
        .instructions {
            margin-top: 30px;
            padding: 20px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 5px;
        }
        .instructions h3 {
            margin-top: 0;
            color: #856404;
        }
        .instructions ol {
            margin-left: 20px;
        }
        .instructions li {
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 PHP Extensions Check</h1>
        <p>Checking required PHP extensions for LILAC Awards System...</p>

        <?php
        $extensions = [
            'zip' => [
                'name' => 'ZIP Archive',
                'required' => true,
                'description' => 'Required for processing .docx files (Word documents)'
            ],
            'pdo_mysql' => [
                'name' => 'PDO MySQL',
                'required' => true,
                'description' => 'Required for database connectivity'
            ],
            'gd' => [
                'name' => 'GD Library',
                'required' => false,
                'description' => 'Recommended for image processing and manipulation'
            ],
            'mbstring' => [
                'name' => 'Multibyte String',
                'required' => true,
                'description' => 'Required for handling Unicode text'
            ],
            'fileinfo' => [
                'name' => 'File Info',
                'required' => true,
                'description' => 'Required for detecting file types'
            ]
        ];

        $allRequired = true;

        foreach ($extensions as $ext => $info) {
            $loaded = extension_loaded($ext);
            $isRequired = $info['required'];

            if ($isRequired && !$loaded) {
                $allRequired = false;
            }

            $class = $loaded ? 'success' : 'error';
            $icon = $loaded ? '✓' : '✗';
            $status = $loaded ? 'Loaded' : ($isRequired ? 'MISSING (Required)' : 'Not loaded (Optional)');

            echo '<div class="check-item ' . $class . '">';
            echo '<span class="icon">' . $icon . '</span>';
            echo '<div>';
            echo '<strong>' . $info['name'] . ' (' . $ext . ')</strong><br>';
            echo '<small>' . $info['description'] . '</small><br>';
            echo '<small><em>Status: ' . $status . '</em></small>';
            echo '</div>';
            echo '</div>';
        }
        ?>

        <div class="info">
            <strong>📋 PHP Version:</strong> <?php echo phpversion(); ?><br>
            <strong>📁 php.ini Location:</strong> <?php echo php_ini_loaded_file(); ?>
        </div>

        <?php if (!extension_loaded('zip')): ?>
        <div class="instructions">
            <h3>🔧 How to Enable ZIP Extension</h3>
            <ol>
                <li><strong>Open XAMPP Control Panel</strong></li>
                <li>Click <strong>Config</strong> button next to Apache</li>
                <li>Select <strong>PHP (php.ini)</strong></li>
                <li>Find this line:
                    <div class="code">;extension=zip</div>
                    or
                    <div class="code">;extension=php_zip.dll</div>
                </li>
                <li>Remove the semicolon (;) to uncomment it:
                    <div class="code">extension=zip</div>
                </li>
                <li><strong>Save</strong> the php.ini file</li>
                <li>Go back to XAMPP Control Panel and <strong>Restart Apache</strong></li>
                <li>Refresh this page to verify the extension is loaded</li>
            </ol>
        </div>
        <?php endif; ?>

        <?php if ($allRequired): ?>
        <div class="check-item success">
            <span class="icon">🎉</span>
            <div>
                <strong>All required extensions are loaded!</strong><br>
                <small>Your PHP environment is properly configured for LILAC Awards System.</small>
            </div>
        </div>
        <?php else: ?>
        <div class="check-item error">
            <span class="icon">⚠️</span>
            <div>
                <strong>Missing required extensions!</strong><br>
                <small>Please enable the missing extensions and restart Apache.</small>
            </div>
        </div>
        <?php endif; ?>

        <div style="margin-top: 30px; text-align: center; color: #666;">
            <a href="user-awards.php" style="color: #137fec; text-decoration: none;">← Back to Awards</a> |
            <a href="javascript:location.reload()" style="color: #137fec; text-decoration: none;">🔄 Refresh Check</a>
        </div>
    </div>
</body>
</html>
