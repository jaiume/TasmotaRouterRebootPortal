<!DOCTYPE html>
<html>
	<head>
		<title>Power Cycle Dashboard</title>
		<link rel="stylesheet" href="styles.css">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
		<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
	</head>
	<body>
		<header>
			<div class="logo">
				<a href="/"> <!-- Added link to index.html -->
					<img src="<?php echo $logo_filename; ?>" alt="Logo" class="logo">
				</a> <!-- End of link -->
			</div>
			<nav>
				<ul>
					<li id="serviceIndicator">
						<span id="serviceIcon"><!-- Icon will go here --></span>
						<span id="serviceText"><!-- Text will go here --></span>
						<a href="#" id="serviceButton" style="display:none;">
							<!-- This will be displayed only when service is not installed -->
						</a>
					</li>
				</ul>
			</nav>
		</header>
		
		<script>
			$(document).ready(function() {
				function updateServiceStatus() {
					$.post('manage_service.php', { action: 'status' }, function(data) {
						let status = data.status;
						let $serviceButton = $('#serviceButton');
						let $serviceIcon = $('#serviceIcon');
						let $serviceText = $('#serviceText');
						
						if (status === 'Not Installed') {
							$serviceIcon.html('<i class="fas fa-ban"></i>');
							$serviceText.text('Service Not Installed | Generate');
							$serviceButton.show().off('click').on('click', function() {
								$.post('manage_service.php', {action: 'generate'}, function(data) {
									alert(data.message);
									updateServiceStatus();
								}, 'json');
							});
						} else {
							$serviceButton.hide();
							if (status === 'Active') {
								$serviceIcon.html('<i class="fas fa-lightbulb"></i>');
								$serviceText.text('Service is Running');
							} else {
								$serviceIcon.html('<i class="far fa-lightbulb"></i>');
								$serviceText.text('Service is Not Running');
							}
						}
					}, 'json');
				}
				
				updateServiceStatus();
			});
		</script>
	</body>
</html>

