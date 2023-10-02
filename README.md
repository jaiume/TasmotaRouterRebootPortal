# TasmotaRouterRebootPortal
A PHP based portal that will register, provision/convert and manage Tasmota based Automatic Router Restarting Smartplugs

# Usage
Setup a MQTT server or get an account on a MQTT server
Setup Some PHP hosting space
Copy Code to PHP Space
Create a mySQL database
Execute CreateDatabase.sql on the database to setup the tables
rename config.php.example to config.php and change the necessary details
open the base URL
A setup page will be displayed
Copy the generated service file to your service definition directory
Enable and Start the service
Close the setup page, the portal should appear, it will have no devices
Log into your Tasmota smartplug
Set it up with your Wifi and MQTT details
Look for the device in the portal
Initialize the device
Enjoy
