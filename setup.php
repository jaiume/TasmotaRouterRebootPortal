<?php
    require_once 'config.php';
    
    // Define the path to the service definition text file
    $serviceDefinitionFilePath = __DIR__ . "/$service_definition_template";
	$outputFilePath = __DIR__ . "/$service_definition_file";
	
    $checkIcon = '&#x2713;'; // Unicode check mark
    $crossIcon = '&#x2717;'; // Unicode multiplication mark
	
    // Initialize checklist variables
    $isServiceFileGenerated = false;
    $isServiceInstalledAndEnabled = false;
    $isServiceRunning = false;
	
	$isServiceFileGenerated = file_exists($outputFilePath);
	
	
	// Check service status
	if($isServiceFileGenerated) {
		exec("systemctl is-enabled $service_name.service", $output, $return_var);		
		$isServiceInstalledAndEnabled = $output[0] === "enabled";
		
		exec("systemctl is-active $service_name.service", $output, $return_var);
		$isServiceRunning = $output[0] === "enabled";
		
		} else {
		// Generate service file
        $serviceFileContent = file_get_contents($serviceDefinitionFilePath);
        $serviceFileContent = str_replace('/path/to/your', __DIR__, $serviceFileContent);
        
        file_put_contents($outputFilePath, $serviceFileContent);
		
	}
	
	$isServiceFileGenerated = file_exists($outputFilePath);
	
	// Check if form is submitted
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		// Handle logo upload
		if (isset($_FILES['logo'])) {
			$logo = $_FILES['logo'];
			$uploadDir = __DIR__;
			$uploadFile = $uploadDir . '/' . basename($logo['name']);
			
			if (move_uploaded_file($logo['tmp_name'], $uploadFile)) {
				$message = 'Logo uploaded successfully';
				} else {
				$message = 'Failed to upload logo';
			}
		}
	}
	
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<title>Setup</title>
		<link rel="stylesheet" href="styles.css">
	</head>
	<body class="admin-login">
		<div class="login-container">
		    <h1>Setup Tasmota Power Cycle Portal</h1>
			<ul>
				<li>Service File Generated: <?= $isServiceFileGenerated ? $checkIcon : $crossIcon ?></li>
				<?php
				if ($isServiceFileGenerated) { ?>
					<ol><li>The generated service file is <b><?= $service_definition_file ?></b></li>
					<li>Please copy to (or create a link from) your service definition directory, and enable and start the service.</li></ol>
				<?php } ?>
				<li>Service Installed and Enabled: <?= $isServiceInstalledAndEnabled ? $checkIcon : $crossIcon ?></li>
				<li>Service Running: <?= $isServiceRunning ? $checkIcon : $crossIcon ?></li>
			</ul>
				
			<a href="setup.php" class="button-style">Check Again</a>
			<a href="index.php" class="button-style">Close</a>
		</div>
	</body>
</html>



