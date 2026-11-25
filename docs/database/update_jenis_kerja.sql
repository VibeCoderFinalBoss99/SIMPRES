-- Pembaruan database SIMPRES yang SUDAH berjalan (lokal atau hosting): jenis kerja dan shift tetap pegawai.
-- Database baru tidak perlu file ini, karena simpres.sql sudah berisi kolom yang sama.
-- Jalankan sekali saja, misalnya lewat phpMyAdmin (tab SQL) pada database simpres / tkjh1984_kel4.

ALTER TABLE users
  ADD COLUMN work_type ENUM('non_shift','shift','petugas_lapangan') NOT NULL DEFAULT 'non_shift' AFTER role,
  ADD COLUMN default_shift_id BIGINT UNSIGNED NULL AFTER work_type,
  ADD CONSTRAINT users_default_shift_id_foreign FOREIGN KEY (default_shift_id)
    REFERENCES shifts (id) ON DELETE SET NULL;

-- Data contoh: Siti pegawai shift (roster manual), Rina petugas lapangan, pegawai lain non-shift.
-- Non-shift dan petugas lapangan memakai shift Pagi sebagai shift tetap.
UPDATE users SET work_type = 'shift', default_shift_id = NULL WHERE email = 'siti@simpres.test';
UPDATE users SET work_type = 'petugas_lapangan', default_shift_id = (SELECT id FROM shifts WHERE name = 'Pagi' LIMIT 1)
  WHERE email = 'rina@simpres.test';
UPDATE users u
  JOIN (SELECT id FROM shifts WHERE name = 'Pagi' LIMIT 1) pagi
  SET u.work_type = 'non_shift', u.default_shift_id = pagi.id
  WHERE u.role = 'pegawai' AND u.email NOT IN ('siti@simpres.test', 'rina@simpres.test');
