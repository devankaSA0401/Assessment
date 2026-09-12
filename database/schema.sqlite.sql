PRAGMA foreign_keys=ON;
CREATE TABLE IF NOT EXISTS users (
 id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE, password TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'admin', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS parameters (
 id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, weight REAL NOT NULL, sort_order INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS controls (
 code TEXT PRIMARY KEY, parameter_id INTEGER NOT NULL REFERENCES parameters(id) ON DELETE CASCADE, question TEXT NOT NULL, objective TEXT, evidence TEXT, critical INTEGER NOT NULL DEFAULT 0, recommendation TEXT, sort_order INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS assessments (
 id INTEGER PRIMARY KEY AUTOINCREMENT, vendor_name TEXT NOT NULL, assessor_name TEXT NOT NULL, assessment_date TEXT NOT NULL, reference TEXT, notes TEXT, status TEXT NOT NULL DEFAULT 'In Progress', created_by INTEGER REFERENCES users(id), created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS assessment_answers (
 id INTEGER PRIMARY KEY AUTOINCREMENT, assessment_id INTEGER NOT NULL REFERENCES assessments(id) ON DELETE CASCADE, control_id TEXT NOT NULL REFERENCES controls(code) ON DELETE CASCADE, status TEXT, evidence_path TEXT, evidence_original_name TEXT, finding TEXT, recommendation TEXT, updated_by INTEGER REFERENCES users(id), created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(assessment_id, control_id)
);
CREATE TABLE IF NOT EXISTS audit_logs (
 id INTEGER PRIMARY KEY AUTOINCREMENT, assessment_id INTEGER REFERENCES assessments(id) ON DELETE CASCADE, user_id INTEGER REFERENCES users(id), action TEXT NOT NULL, entity_type TEXT, entity_id TEXT, details TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_answers_assessment ON assessment_answers(assessment_id);
CREATE INDEX IF NOT EXISTS idx_controls_parameter ON controls(parameter_id);
CREATE INDEX IF NOT EXISTS idx_audit_assessment ON audit_logs(assessment_id);
