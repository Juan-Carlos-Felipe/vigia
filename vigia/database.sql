CREATE DATABASE IF NOT EXISTS vigia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vigia;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    role ENUM('admin', 'inspector') NOT NULL DEFAULT 'inspector',
    password_hash VARCHAR(255) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_seen_alert_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS people (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(160) NOT NULL,
    course VARCHAR(80) NULL,
    guardian_phone VARCHAR(40) NULL,
    photo_path VARCHAR(255) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS cameras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    source VARCHAR(255) NOT NULL,
    location VARCHAR(160) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_heartbeat DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cameras_name (name)
);

CREATE TABLE IF NOT EXISTS incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camera_id INT NULL,
    camera_name VARCHAR(120) NOT NULL,
    type ENUM('fall', 'fight') NOT NULL,
    severity ENUM('medium', 'high', 'critical') NOT NULL DEFAULT 'high',
    message VARCHAR(255) NOT NULL,
    snapshot_path VARCHAR(255) NULL,
    confidence DECIMAL(5, 2) NOT NULL DEFAULT 0,
    status ENUM('open', 'resolved') NOT NULL DEFAULT 'open',
    resolved_by INT NULL,
    resolved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_incidents_status_created (status, created_at),
    CONSTRAINT fk_incidents_camera FOREIGN KEY (camera_id) REFERENCES cameras(id) ON DELETE SET NULL,
    CONSTRAINT fk_incidents_user FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS sightings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    person_id INT NOT NULL,
    camera_id INT NULL,
    camera_name VARCHAR(120) NOT NULL,
    confidence DECIMAL(5, 2) NOT NULL DEFAULT 0,
    snapshot_path VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sightings_created (created_at),
    CONSTRAINT fk_sightings_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE,
    CONSTRAINT fk_sightings_camera FOREIGN KEY (camera_id) REFERENCES cameras(id) ON DELETE SET NULL
);

INSERT INTO users (name, email, role, password_hash)
VALUES
    ('Administrador VigIA', 'admin@vigia.local', 'admin', '$2y$10$QeIdg8bpOCBJlaJiRILS8ezPLRnp4n8/kP9aSG3FRozThw0gkPB2O'),
    ('Inspector General', 'inspector@vigia.local', 'inspector', '$2y$10$kHWguIhcfX9tLJ8fluB0Au6ZGVwyZI2F/LTl./Hmt5nkwWWwwbbGi')
ON DUPLICATE KEY UPDATE email = VALUES(email);

INSERT INTO cameras (name, source, location)
VALUES ('Cámara Patio Central', '0', 'Patio central')
ON DUPLICATE KEY UPDATE source = VALUES(source);
