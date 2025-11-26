⛈️ GIS-WAC Weather Email Alerts Management PlatformThe GIS-WAC Weather Email Alerts Management Platform is a self-hosted PHP application running on PostgreSQL, designed to provide granular, customizable weather and operational alerting services via email. It allows organizations to define specific, actionable alert criteria tailored to their unique safety and operational needs, targeting users based on their physical location assignments.

🎯 Target AudienceThis platform is crucial for organizations requiring timely, targeted communication of hazards to specific user groups. It is perfectly suited for:Small Businesses & Retail: Protecting assets and managing staff scheduling during adverse weather.Construction Companies: Alerting crews to site-specific conditions (e.g., high winds, temperature extremes) that necessitate work stoppages.Independent & Rural Schools: Notifying staff, parents, and transport personnel of dangerous conditions (e.g., fog, ice) affecting routes and activities.Small Towns & Rural Municipalities: Informing public works teams and residents about localized weather threats.Any Entity: That needs to link specific weather metrics to customized alert criteria for specific locations and notify a predetermined list of recipients.

🚀 Installation & Setup GuideThis guide assumes a clean Ubuntu server running Apache2.1. Environment RequirementsComponentVersionNotesOperating SystemUbuntu (22.04 LTS or newer)Base OSWeb ServerApache2Standard PHP hostingProgramming LanguagePHP 8.4+Requires php-pgsql, php-curl, and php-cli modules.DatabasePostgreSQLPrimary data store for rules and alerts.Email ServicePostfixConfigured as a Relay for reliable email delivery.2. Code DeploymentClone the repository into the standard Apache web root and set permissions.Bash# Clone the project into the web root

sudo git clone [YOUR_GITHUB_REPO_URL]/gis-wac-project /var/www/html/gis-wac-project

# Change directory

cd /var/www/html/gis-wac-project

# Set ownership to the web server user (www-data on Ubuntu)

sudo chown -R www-data:www-data .

# Set appropriate permissions

sudo chmod -R 755 .

3. PostgreSQL Database SetupRun the provided SQL scripts to create the required database and user structure.Bash# Log in as the PostgreSQL superuser

sudo -u postgres psql

# Execute the database and user creation script (create_db_user.sql)

\i create_db_user.sql

# Exit psql

\q

Now, create the application tables within the new database.Bash# Log in to the new database (e.g., using the new user if defined in the script)

# Example: sudo -u postgres psql -d [your_db_name]

\i gis_wac_db-schema.sql

# Exit psql

\q

4. Application ConfigurationUpdate the config/database.php file with the credentials created in step 3.PHP// Example structure to update in config/database.php
$this->host = 'localhost';

$this->db_name = '[YOUR_DATABASE_NAME]'; // e.g., 'gis_wac'

$this->username = '[YOUR_DB_USER]'; // e.g., 'gis_wac_app'

$this->password = '[YOUR_DB_PASSWORD]'; // The password defined in create_db_user.sql

⚙️ Core Functionality

A. How It Works: The Alert CycleData Ingestion: The system retrieves current weather data for all managed locations (scheduled via CLI/Cron).Rule Evaluation (AlertRule Logic): The data is processed against rules defined by AlertRule.php. Rules check specific metrics (wind_speed, temperature, rain_1h) against operators (>, <) and a threshold_value.Alert Generation & Dispatch: If a rule is triggered, an entry is created in the alert_queue, and the system identifies all users assigned to the triggering location_id.Targeted Email Alerting: The customized message is delivered to the targeted recipients.

B. Sending of Email Alerts (via send_alert_email.php)This process handles the initial dispatch of a hazard notification:Rule Lookup: The system uses the selected rule_id and location_id to retrieve the associated message_template, custom_subject, and severity_level from the alert_rules table.Recipient Identification: It queries the database to find all users linked to the specified location_id (via the user_locations table).Email Construction: The alert content is wrapped in a consistent HTML template, ensuring a professional and clear presentation of the alert.Delivery: The email is sent using the configured Postfix Relay, maximizing deliverability and minimizing spam filtering issues.

C. Sending Deactivate Email Alerts (Clearance)The Alert Clearance System is vital for completing the alert lifecycle and preventing user fatigue. It is handled by the logic in send_deactive_alert.php.Identifying the Parent Alert: The system queries the alert_queue to find the most recent active/unresolved alert instance (status NOT IN ('RESOLVED', 'CLEARED', 'CANCELLED', 'EXPIRED')) for the selected Rule/Location.Transactional Resolution: The database operation is wrapped in a PDO Transaction to ensure absolute data integrity:A new CLEARED event is inserted into the alert_queue.The original (Parent) active alert is updated to status = 'RESOLVED'.Clearance Notification: A final email is constructed with a distinctive subject line (e.g., "✅ CLEARED:") and sent to all subscribers, confirming the threat has passed and the system is operating normally.

🔧 System Configuration Extensions Setting up PostgreSQL Ensure PostgreSQL is listening on the correct interface and that your connection limits are appropriate for the expected load.Bash# Check PostgreSQL status

sudo systemctl status postgresql

Configuring Postfix as a RelayThe system relies on Postfix to deliver high-volume, time-sensitive alerts. It must be configured as a relay to a professional email service (e.g., SendGrid, AWS SES, or a local corporate mail server) to bypass local spam filters and ensure delivery reliability.

Install Postfix: sudo apt install postfix mailutilsConfigure Relay: Edit /etc/postfix/main.cf to set relayhost = [smtp.server.com]:587 and configure SASL authentication credentials if needed.Testing: 

Test email delivery from the command line: echo "Test body" | mail -s "Test Subject" user@example.com




