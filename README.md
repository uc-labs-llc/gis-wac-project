![GIS WAC Dashboard Screenshot] (./gis-1.png)

⛈️ Weather Email Alerts Management Platform

The Weather Email Alerts Management Platform is a robust, self-hosted system designed to provide hyper-local, customizable weather and operational alerting services via email. It moves beyond generic public weather notifications by allowing organizations to define specific, actionable alert criteria tailored to their unique safety and operational needs.

🎯 Target Audience

This platform is ideal for any entity that requires timely, targeted communication of weather and operational hazards to specific groups of users. It is perfectly suited for:

Small Businesses & Retail: Protecting outdoor assets, scheduling outdoor staff, or preparing for severe conditions.

Construction Companies: Alerting crews to high winds, heavy rain, or temperature extremes that halt work or create unsafe conditions.

Independent & Rural Schools: Notifying staff, parents, and bus drivers of dangerous conditions like low visibility, ice, or severe storms that affect transport or outdoor activities.

Small Towns & Rural Municipalities: Informing public works teams or residents about specific localized weather threats.

Any Entity: That needs to link specific weather metrics to customized alert criteria for specific locations and notify a predetermined list of recipients.


⚙️ How It Works: The Alert Cycle

The platform operates through a continuous cycle of data evaluation and targeted notification:

Data Ingestion (Weather Retrieval): The system periodically retrieves current weather data (e.g., via the "Retrieve Weather Data" action) for all managed locations.

Rule Evaluation (AlertRule Logic): The system processes the incoming data against Alert Rules defined in the system.

Rules are checked against specific metrics (temperature, wind_speed, rain_1h, humidity, etc.) using operators (>, <, BETWEEN, etc.) and a specific threshold_value.

The rule also considers parameters like condition_type (e.g., INSTANT, DURATION, or TREND) and a cooldown_period_minutes to prevent alert spamming.

Alert Generation & Email Dispatch: If a rule is triggered (e.g., wind_speed > 20 m/s at Location X), a new alert instance is created in the database (alert_queue).

Targeted Email Alerting: The system identifies all users assigned to the triggering location_id and immediately dispatches a customized Email Alert.

Alert Clearance (Deactivation): Once the dangerous condition subsides or is manually resolved, a Deactivate Email Alert is dispatched, completing the cycle and resolving the original alert.


✨ Key Features

1. Custom Alert Rules Management
The heart of the system is the customizable alert definition, managed via the AlertRule.php model and the create_rule.php interface.

Metric-Driven Conditions: Define rules based on quantifiable metrics like:

Air Temperature (°C)

Wind Speed & Wind Gust (m/s)

Humidity (%)

Rainfall/Snowfall (1hr total, mm)

Atmospheric Pressure (hPa)

Flexible Logic: Utilize operators like Greater than (>), Less than (<), Equal to (=), and Between to create precise triggers.

Severity Levels: Assign a severity_level (LOW, MEDIUM, HIGH, CRITICAL) to prioritize and categorize notifications.

Custom Messaging: Each rule has a message_template and optional custom_subject to ensure every alert is relevant and clear to the recipient.


2. Detailed Email Alerts Dispatch

The send_alert_email.php process ensures that notifications are immediate and informative.

Alert Generation: When a rule is manually or automatically triggered, the system uses the Rule's parameters (UUID) to pull the defined message_template and severity_level.

User Targeting: The alert is sent only to the list of subscribers who are explicitly linked to the affected location_id in the database, ensuring zero unnecessary notifications.

Rich HTML Formatting: Emails are delivered in a consistent, branded HTML format (as seen in wrapInEmailTemplate), providing clarity and professionalism.


3. Comprehensive Alert Clearance System (Deactivation)

The Deactivate Email Alert feature, managed by the logic in send_deactive_alert.php, is essential for closing the alert loop and preventing complacency.

Function: This system resolves the most recent active alert instance for a specific Rule and Location pair.

Process:

It identifies the original, unresolved alert in the alert_queue (where status is not RESOLVED, CLEARED, etc.).

It logs a new event with the status CLEARED (the clearance event).

Crucially, it updates the original alert's status to RESOLVED and sets the resolved_at timestamp. This action removes the alert from the "active/pending" lists, such as the filter used to populate the dropdown in the clearance tool.

A final clearance email is sent with a distinct subject line (e.g., ✅ CLEARED: [Original Subject]) informing users that the threat has passed and the system is operating normally.

Integrity: The use of PDO Transactions ensures that the logging of the CLEARED event and the resolution of the original alert are an atomic operation—both succeed, or both fail—guaranteeing data integrity.


💻 Technical Overview

Component	Description	Files Involved

Database	PostgreSQL is the preferred database, leveraging features like UUIDs and transactional integrity.	database.php (config), All Model files

Models	PHP classes for interacting with database entities.	AlertRule.php, Location.php

User Interface	Provides a dashboard for viewing statistics and quick access to management pages.	dashboard.php, index.php, create_rule.php

Alert Dispatch	Handles the logic for sending initial and clearance emails, including user lookup and HTML template wrapping.	send_alert_email.php, send_deactive_alert.php

Core Tables	alert_rules (Rule definitions), locations (Managed locations), users (User list), user_locations (User-Location assignments), alert_queue (History of triggered alerts).	(Implied by table schema and model usage)


