-- VIDEW Database Migration 1.0.3 -> 1.0.4
-- Modernization of Administrative UI and Platform Stabilization

INSERT INTO schema_migrations (version, filename, notes, applied_at) 
VALUES ('1.0.4', '003-admin-ui-modernization.sql', 'Modernization of all 14 administrative screens, Tactical Amber HUD enhancements, and core UI stabilization.', NOW());
