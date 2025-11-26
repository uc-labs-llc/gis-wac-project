-- =============================================
-- STEP 1: Create ONLY Database and User
-- =============================================
\c postgres

-- Create the database
CREATE DATABASE gis_wac_db;

-- Create the application user
CREATE USER gis_wac_user WITH PASSWORD 'securepassword123';

-- Connect to the new database
\c gis_wac_db

-- Grant ALL permissions
GRANT ALL PRIVILEGES ON DATABASE gis_wac_db TO gis_wac_user;
GRANT ALL PRIVILEGES ON SCHEMA public TO gis_wac_user;
GRANT ALL PRIVILEGES ON ALL TABLES
