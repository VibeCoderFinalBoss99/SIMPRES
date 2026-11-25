-- Database SIMPRES (Sistem Presensi Simbang)
-- Sesuai docs/database/ERD_SIMPRES.drawio
-- MySQL 8 / MariaDB 10.6+, engine InnoDB, charset utf8mb4

CREATE DATABASE IF NOT EXISTS simpres
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE simpres;

-- 1. Lokasi kerja (titik Puskesmas dan radius absensi)
CREATE TABLE work_locations (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100)    NOT NULL,
  address     VARCHAR(255)    NULL,
  latitude    DECIMAL(10,7)   NOT NULL,
  longitude   DECIMAL(10,7)   NOT NULL,
  radius_m    SMALLINT UNSIGNED NOT NULL DEFAULT 150,
  is_active   TINYINT(1)      NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Shift kerja
CREATE TABLE shifts (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name                VARCHAR(50)     NOT NULL,
  start_time          TIME            NOT NULL,
  end_time            TIME            NOT NULL,
  late_tolerance_min  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at          TIMESTAMP NULL,
  updated_at          TIMESTAMP NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Pengguna (pegawai dan admin)
CREATE TABLE users (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  work_location_id   BIGINT UNSIGNED NULL,
  nip                VARCHAR(30)     NOT NULL,
  name               VARCHAR(100)    NOT NULL,
  email              VARCHAR(150)    NOT NULL,
  password           VARCHAR(255)    NOT NULL,
  phone              VARCHAR(20)     NULL,
  position           VARCHAR(100)    NOT NULL,
  unit               VARCHAR(100)    NULL,
  role               ENUM('pegawai','admin') NOT NULL DEFAULT 'pegawai',
  -- Jenis kerja: non_shift dan petugas_lapangan memakai shift tetap (default_shift_id), shift diatur lewat roster.
  work_type          ENUM('non_shift','shift','petugas_lapangan') NOT NULL DEFAULT 'non_shift',
  -- Shift tetap: dipakai otomatis Senin-Sabtu (Minggu libur) pada tanggal yang tidak punya baris di schedules.
  default_shift_id   BIGINT UNSIGNED NULL,
  photo_path         VARCHAR(255)    NULL,
  is_active          TINYINT(1)      NOT NULL DEFAULT 1,
  email_verified_at  TIMESTAMP NULL,
  remember_token     VARCHAR(100)    NULL,
  created_at         TIMESTAMP NULL,
  updated_at         TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY users_nip_unique (nip),
  UNIQUE KEY users_email_unique (email),
  CONSTRAINT users_work_location_id_foreign FOREIGN KEY (work_location_id)
    REFERENCES work_locations (id) ON DELETE SET NULL,
  CONSTRAINT users_default_shift_id_foreign FOREIGN KEY (default_shift_id)
    REFERENCES shifts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Jadwal kerja per pegawai per tanggal
CREATE TABLE schedules (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  shift_id    BIGINT UNSIGNED NULL,
  date        DATE            NOT NULL,
  is_day_off  TINYINT(1)      NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY schedules_user_id_date_unique (user_id, date),
  CONSTRAINT schedules_user_id_foreign FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT schedules_shift_id_foreign FOREIGN KEY (shift_id)
    REFERENCES shifts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Absensi
CREATE TABLE attendances (
  id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id               BIGINT UNSIGNED NOT NULL,
  schedule_id           BIGINT UNSIGNED NULL,
  date                  DATE            NOT NULL,
  check_in_at           DATETIME        NULL,
  check_in_lat          DECIMAL(10,7)   NULL,
  check_in_lng          DECIMAL(10,7)   NULL,
  check_in_accuracy_m   DECIMAL(6,1)    NULL,
  check_in_distance_m   DECIMAL(8,1)    NULL,
  check_out_at          DATETIME        NULL,
  check_out_lat         DECIMAL(10,7)   NULL,
  check_out_lng         DECIMAL(10,7)   NULL,
  check_out_accuracy_m  DECIMAL(6,1)    NULL,
  check_out_distance_m  DECIMAL(8,1)    NULL,
  status                ENUM('hadir','terlambat','izin','alpa') NOT NULL,
  late_minutes          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  source                ENUM('online','offline') NOT NULL DEFAULT 'online',
  client_uuid           CHAR(36)        NULL,
  device_info           VARCHAR(255)    NULL,
  created_at            TIMESTAMP NULL,
  updated_at            TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY attendances_user_id_date_unique (user_id, date),
  UNIQUE KEY attendances_client_uuid_unique (client_uuid),
  CONSTRAINT attendances_user_id_foreign FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT attendances_schedule_id_foreign FOREIGN KEY (schedule_id)
    REFERENCES schedules (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Izin dan cuti
CREATE TABLE leave_requests (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id          BIGINT UNSIGNED NOT NULL,
  type             ENUM('izin_keluar','sakit','cuti','lainnya') NOT NULL,
  start_date       DATE            NOT NULL,
  end_date         DATE            NOT NULL,
  start_time       TIME            NULL,
  end_time         TIME            NULL,
  reason           TEXT            NOT NULL,
  attachment_path  VARCHAR(255)    NULL,
  status           ENUM('menunggu','diterima','ditolak') NOT NULL DEFAULT 'menunggu',
  reviewed_by      BIGINT UNSIGNED NULL,
  reviewed_at      TIMESTAMP NULL,
  admin_note       VARCHAR(255)    NULL,
  created_at       TIMESTAMP NULL,
  updated_at       TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY leave_requests_user_id_status_index (user_id, status),
  CONSTRAINT leave_requests_user_id_foreign FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT leave_requests_reviewed_by_foreign FOREIGN KEY (reviewed_by)
    REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Koreksi absensi
CREATE TABLE attendance_corrections (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id              BIGINT UNSIGNED NOT NULL,
  attendance_id        BIGINT UNSIGNED NULL,
  date                 DATE            NOT NULL,
  corrected_check_in   TIME            NULL,
  corrected_check_out  TIME            NULL,
  reason               TEXT            NOT NULL,
  attachment_path      VARCHAR(255)    NULL,
  status               ENUM('menunggu','diterima','ditolak') NOT NULL DEFAULT 'menunggu',
  reviewed_by          BIGINT UNSIGNED NULL,
  reviewed_at          TIMESTAMP NULL,
  admin_note           VARCHAR(255)    NULL,
  created_at           TIMESTAMP NULL,
  updated_at           TIMESTAMP NULL,
  PRIMARY KEY (id),
  CONSTRAINT attendance_corrections_user_id_foreign FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT attendance_corrections_attendance_id_foreign FOREIGN KEY (attendance_id)
    REFERENCES attendances (id) ON DELETE SET NULL,
  CONSTRAINT attendance_corrections_reviewed_by_foreign FOREIGN KEY (reviewed_by)
    REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Tugas lapangan
CREATE TABLE field_duties (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        BIGINT UNSIGNED NOT NULL,
  date           DATE            NOT NULL,
  location_name  VARCHAR(150)    NOT NULL,
  description    TEXT            NOT NULL,
  latitude       DECIMAL(10,7)   NULL,
  longitude      DECIMAL(10,7)   NULL,
  photo_path     VARCHAR(255)    NOT NULL,
  status         ENUM('menunggu','diterima','ditolak') NOT NULL DEFAULT 'menunggu',
  reviewed_by    BIGINT UNSIGNED NULL,
  reviewed_at    TIMESTAMP NULL,
  admin_note     VARCHAR(255)    NULL,
  created_at     TIMESTAMP NULL,
  updated_at     TIMESTAMP NULL,
  PRIMARY KEY (id),
  CONSTRAINT field_duties_user_id_foreign FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT field_duties_reviewed_by_foreign FOREIGN KEY (reviewed_by)
    REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Notifikasi di aplikasi
CREATE TABLE user_notifications (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  type        VARCHAR(50)     NOT NULL,
  title       VARCHAR(150)    NOT NULL,
  body        TEXT            NOT NULL,
  data        JSON            NULL,
  read_at     TIMESTAMP NULL,
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY user_notifications_user_id_read_at_index (user_id, read_at),
  CONSTRAINT user_notifications_user_id_foreign FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Token HP untuk notifikasi push (FCM)
CREATE TABLE device_tokens (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       BIGINT UNSIGNED NOT NULL,
  fcm_token     VARCHAR(255)    NOT NULL,
  platform      VARCHAR(20)     NOT NULL DEFAULT 'android',
  last_used_at  TIMESTAMP NULL,
  created_at    TIMESTAMP NULL,
  updated_at    TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY device_tokens_fcm_token_unique (fcm_token),
  CONSTRAINT device_tokens_user_id_foreign FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Data awal untuk mencoba (password semua akun: simpres123)
-- Koordinat Puskesmas dari Google Maps: -5.022256521324268, 119.66536817954076
-- (dibulatkan ke 7 angka desimal sesuai kolom DECIMAL(10,7)).
-- ---------------------------------------------------------------
INSERT INTO work_locations (name, address, latitude, longitude, radius_m, created_at, updated_at) VALUES
('UPTD Puskesmas Simbang', 'Simbang', -5.0222565, 119.6653682, 150, NOW(), NOW());

INSERT INTO shifts (name, start_time, end_time, late_tolerance_min, created_at, updated_at) VALUES
('Pagi', '07:30:00', '14:00:00', 10, NOW(), NOW()),
('Siang', '14:00:00', '21:00:00', 10, NOW(), NOW());

INSERT INTO users (work_location_id, nip, name, email, password, position, unit, role, work_type, default_shift_id, created_at, updated_at) VALUES
(1, '000000000000000001', 'Admin Puskesmas', 'admin@simpres.test',
 '$2y$10$OBV2hA/spmu1cLrcznUVY.ZviIQPdkBW63UoJziTYCbmLc3VeH8pW', 'Admin Kepegawaian', 'Tata Usaha', 'admin', 'non_shift', NULL, NOW(), NOW()),
(1, '198501012010012001', 'Siti Aminah', 'siti@simpres.test',
 '$2y$10$OBV2hA/spmu1cLrcznUVY.ZviIQPdkBW63UoJziTYCbmLc3VeH8pW', 'Perawat', 'Rawat Jalan', 'pegawai', 'shift', NULL, NOW(), NOW()),
(1, '199002022015021002', 'Budi Santoso', 'budi@simpres.test',
 '$2y$10$OBV2hA/spmu1cLrcznUVY.ZviIQPdkBW63UoJziTYCbmLc3VeH8pW', 'Bidan', 'KIA', 'pegawai', 'non_shift', 1, NOW(), NOW());

INSERT INTO schedules (user_id, shift_id, date, created_at, updated_at) VALUES
(2, 1, CURDATE(), NOW(), NOW()),
(3, 1, CURDATE(), NOW(), NOW());
