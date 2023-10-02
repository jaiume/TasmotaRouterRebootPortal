<?php
// fetch_files_with_checkboxes_v4.php

error_reporting(E_ALL);

// Get the current directory path
$directory = __DIR__;

// Check for the existence of the config.php file
$config_file = 'config.php';
$db_schema_checkbox = false;
if (file_exists($directory . '/' . $config_file)) {
    require $config_file;
	
    if (isset($servername) && isset($username) && isset($password) && isset($dbname)) {
        $db_schema_checkbox = true;	
    }
}


// Read the directory and get the file names
$dir_list = scandir($directory);

$files = [];

// Iterate through the files
$allowed_extensions = ['php', 'css', 'html', 'js'];

foreach ($dir_list as $file) {
    if ($file !== '.' && $file !== '..' && $file !== basename(__FILE__)) {
        $file_info = pathinfo($file);

        if (isset($file_info['extension']) && in_array($file_info['extension'], $allowed_extensions)) {
            $files[] = $file;
        }
    }
}

// Sort the files alphabetically
sort($files);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['files'])) {
    $selected_files = $_POST['files'];
    $file_contents = [];

    foreach ($selected_files as $file) {
        if ($file === 'database_schema') {
            $file_contents['database_schema'] = get_database_schema($servername, $username, $password, $dbname);
            continue;
        }

        $file_content = file_get_contents($directory . '/' . $file);

        if ($file_content === false) {
            $file_contents[$file] = 'Error: Failed to get file content';
        } else {
            $file_contents[$file] = $file_content;
        }
    }
}


function get_database_schema($host, $user, $password, $database) {
    // Connect to the database
    $mysqli = new mysqli($host, $user, $password, $database);

    if ($mysqli->connect_errno) {
        return 'Error: Failed to connect to the database';
    }

    // Get the list of tables
    $tables_result = $mysqli->query("SHOW TABLES");
    $schema = '';

    while ($table = $tables_result->fetch_array(MYSQLI_NUM)) {
        $schema .= "Table: {$table[0]}\n";

        // Get the table schema
        $columns_result = $mysqli->query("SHOW COLUMNS FROM {$table[0]}");

        while ($column = $columns_result->fetch_assoc()) {
            $schema .= "{$column['Field']} - {$column['Type']} - {$column['Null']} - {$column['Key']} - {$column['Extra']}\n";
        }

        $schema .= "\n";
    }

    $mysqli->close();

    return $schema;
}
?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SFTP Files</title>
    <style>
        #check-all-container {
            margin-bottom: 1em;
        }

        #output {
            border: 1px solid #ccc;
            padding: 1em;
            margin-top: 1em;
            position: relative;
            background-color: #f8f8f8;
            font-family: monospace;
        }

        #output pre {
            margin: 0;
        }

        #copy-code-button {
            position: absolute;
            top: 5px;
            right: 5px;
            background-color: #ccc;
            border: none;
            border-radius: 3px;
            padding: 5px 10px;
            cursor: pointer;
            font-size: 14px;
        }
    </style>

</head>
<body>
    <form method="post">
        <div id="check-all-container">
    <input type="checkbox" id="check-all" onclick="toggleCheckboxes()">
    <label for="check-all">Check All</label>
</div>
<?php if ($db_schema_checkbox): ?>
    <div>
        <input type="checkbox" name="files[]" value="database_schema">
        <label>Database Schema</label>
    </div>
<?php endif; ?>
<ul>
    <?php foreach ($files as $file): ?>
        <li>
            <input type="checkbox" name="files[]" value="<?php echo htmlspecialchars($file); ?>">
            <label><?php echo htmlspecialchars($file); ?></label>
        </li>
    <?php endforeach; ?>
</ul>
        <button type="submit">Submit</button>
    </form>
    <?php if (!empty($file_contents)): ?>
    <div id="output">
        <button id="copy-code-button" onclick="copyToClipboard(document.getElementById('output').innerText)">Copy Code</button>
        <?php foreach ($file_contents as $file => $content): ?>
            <pre><?php echo htmlspecialchars($file . "\n\n" . $content); ?></pre>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</body>
    <script>
        function toggleCheckboxes() {
            const checkAllBox = document.getElementById('check-all');
            const fileCheckboxes = document.getElementsByName('files[]');
            for (const checkbox of fileCheckboxes) {
                checkbox.checked = checkAllBox.checked;
            }
        }

        function copyToClipboard(text) {
            const textarea = document.createElement('textarea');
            textarea.textContent = text;
            textarea.style.position = 'fixed'; // Prevent scrolling to the bottom of the page
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        }
    </script>
</html>


