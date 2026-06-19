-- Auto-run on first pgsql container init. Creates the dedicated testing DB so
-- Pest Feature tests (RefreshDatabase) run against PostgreSQL 18 — never SQLite.
CREATE DATABASE uniges_testing;
GRANT ALL PRIVILEGES ON DATABASE uniges_testing TO uniges;
