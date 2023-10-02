<?php
	require_once 'config.php';
	
	
	
	// Check if action parameter is set
	if(!isset($_POST['action'])) {
		echo json_encode(['status' => 'error', 'message' => 'Action parameter is missing']);
		exit;
	}
	
	$action = $_POST['action'];
	
	    switch($action) {
        case 'status':
            // Execute the command to check the service status
            exec("systemctl is-active $service_name.service", $output, $return_var);
            
            // Initialize status as "Not Installed" by default
            $status = "Not Installed";
            
            // Check if the command was successful
            if($return_var == 0) {
                switch($output[0]) {
                    case "active":
                        $status = "Active";
                        break;
                    case "inactive":
                        $status = "InActive";
                        break;
                }
            }
            echo json_encode(['status' => $status]);
            exit;
		
		case 'generate':
		// Define the path to the service definition text file
		$serviceDefinitionFilePath = __DIR__ . "/$service_definition_template";
		
		// Read the service definition from the text file
		$serviceFileContent = file_get_contents($serviceDefinitionFilePath);
		
		// Replace placeholder with the actual directory path
		$serviceFileContent = str_replace('/path/to/your', __DIR__, $serviceFileContent);
		
		// Define the path to the output service definition file
		$outputFilePath = __DIR__ . "/$service_definition_file";
		
		// Write the service file
		file_put_contents($outputFilePath, $serviceFileContent);
		
		// Check if the file was written successfully
		$message = file_exists($outputFilePath) ? 'The Service file generated successfully, the files is $outputFilePath. /nPlease create a link to this file in your service definition configuration directory (e.g. /etc/systemd/system) and then enable the service' : 'Failed to generate service file';
		break;
        
		default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action parameter']);
        exit;
	}
	
	echo json_encode(['status' => $return_var == 0 ? 'success' : 'error', 'message' => $message]);
	
	
