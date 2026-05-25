-- Seed kategori dan rule default untuk hosting.
-- Jalankan SETELAH database_migration_hosting.sql.
-- Aman dijalankan lebih dari sekali karena memakai pengecekan NOT EXISTS.

START TRANSACTION;

INSERT INTO `categories` (`user_id`, `pocket_id`, `name`, `type`, `budget_amount`, `budget_enabled`)
SELECT u.`id`, NULL, seed.`name`, 'expense', 0, 0
FROM `users` u
JOIN (
    SELECT 'Belanja' AS `name`
    UNION ALL SELECT 'Makanan'
    UNION ALL SELECT 'Transportasi'
    UNION ALL SELECT 'Utilitas'
    UNION ALL SELECT 'Kesehatan'
    UNION ALL SELECT 'Lainnya'
    UNION ALL SELECT 'Pemasukan'
) seed
WHERE NOT EXISTS (
    SELECT 1
    FROM `categories` c
    WHERE (c.`user_id` IS NULL OR c.`user_id` = u.`id`)
      AND c.`pocket_id` IS NULL
      AND c.`name` = seed.`name`
);

INSERT INTO `category_rules` (`user_id`, `pocket_id`, `category_id`, `keyword`, `priority`, `is_active`)
SELECT u.`id`, NULL, c.`id`, rules.`keyword`, rules.`priority`, 1
FROM `users` u
JOIN (
    SELECT 'Belanja' AS category_name, 'belanja' AS keyword, 10 AS priority
    UNION ALL SELECT 'Belanja', 'beras', 11
    UNION ALL SELECT 'Belanja', 'sayur', 12
    UNION ALL SELECT 'Belanja', 'telur', 13
    UNION ALL SELECT 'Belanja', 'telor', 14
    UNION ALL SELECT 'Belanja', 'ayam', 15
    UNION ALL SELECT 'Belanja', 'ikan', 16
    UNION ALL SELECT 'Belanja', 'daging', 17
    UNION ALL SELECT 'Belanja', 'buah', 18
    UNION ALL SELECT 'Belanja', 'susu', 19
    UNION ALL SELECT 'Belanja', 'roti', 20
    UNION ALL SELECT 'Belanja', 'sabun', 21
    UNION ALL SELECT 'Belanja', 'shampoo', 22
    UNION ALL SELECT 'Belanja', 'deterjen', 23
    UNION ALL SELECT 'Belanja', 'minyak', 24
    UNION ALL SELECT 'Belanja', 'gula', 25
    UNION ALL SELECT 'Belanja', 'tepung', 26
    UNION ALL SELECT 'Belanja', 'pasar', 27
    UNION ALL SELECT 'Belanja', 'mart', 28
    UNION ALL SELECT 'Belanja', 'indomaret', 29
    UNION ALL SELECT 'Belanja', 'alfamart', 30
    UNION ALL SELECT 'Belanja', 'mirota', 31
    UNION ALL SELECT 'Belanja', 'galon', 32

    UNION ALL SELECT 'Makanan', 'makan', 10
    UNION ALL SELECT 'Makanan', 'minum', 11
    UNION ALL SELECT 'Makanan', 'kopi', 12
    UNION ALL SELECT 'Makanan', 'warung', 13
    UNION ALL SELECT 'Makanan', 'sarapan', 14
    UNION ALL SELECT 'Makanan', 'siang', 15
    UNION ALL SELECT 'Makanan', 'malam', 16
    UNION ALL SELECT 'Makanan', 'nasi', 17
    UNION ALL SELECT 'Makanan', 'bakso', 18
    UNION ALL SELECT 'Makanan', 'mie', 19
    UNION ALL SELECT 'Makanan', 'ayam geprek', 20
    UNION ALL SELECT 'Makanan', 'gofood', 21
    UNION ALL SELECT 'Makanan', 'grabfood', 22
    UNION ALL SELECT 'Makanan', 'resto', 23
    UNION ALL SELECT 'Makanan', 'jajan', 24
    UNION ALL SELECT 'Makanan', 'seblak', 25
    UNION ALL SELECT 'Makanan', 'gorengan', 26
    UNION ALL SELECT 'Makanan', 'siomay', 27

    UNION ALL SELECT 'Transportasi', 'bensin', 10
    UNION ALL SELECT 'Transportasi', 'pertalite', 11
    UNION ALL SELECT 'Transportasi', 'pertamax', 12
    UNION ALL SELECT 'Transportasi', 'solar', 13
    UNION ALL SELECT 'Transportasi', 'parkir', 14
    UNION ALL SELECT 'Transportasi', 'tol', 15
    UNION ALL SELECT 'Transportasi', 'gojek', 16
    UNION ALL SELECT 'Transportasi', 'grab', 17
    UNION ALL SELECT 'Transportasi', 'ojek', 18
    UNION ALL SELECT 'Transportasi', 'taxi', 19
    UNION ALL SELECT 'Transportasi', 'angkot', 20
    UNION ALL SELECT 'Transportasi', 'kereta', 21
    UNION ALL SELECT 'Transportasi', 'bus', 22

    UNION ALL SELECT 'Utilitas', 'listrik', 10
    UNION ALL SELECT 'Utilitas', 'token', 11
    UNION ALL SELECT 'Utilitas', 'air', 12
    UNION ALL SELECT 'Utilitas', 'pdam', 13
    UNION ALL SELECT 'Utilitas', 'pulsa', 14
    UNION ALL SELECT 'Utilitas', 'kuota', 15
    UNION ALL SELECT 'Utilitas', 'internet', 16
    UNION ALL SELECT 'Utilitas', 'wifi', 17
    UNION ALL SELECT 'Utilitas', 'indihome', 18
    UNION ALL SELECT 'Utilitas', 'gas', 19
    UNION ALL SELECT 'Utilitas', 'elpiji', 20
    UNION ALL SELECT 'Utilitas', 'pln', 21
    UNION ALL SELECT 'Utilitas', 'bpjs', 22

    UNION ALL SELECT 'Kesehatan', 'obat', 10
    UNION ALL SELECT 'Kesehatan', 'dokter', 11
    UNION ALL SELECT 'Kesehatan', 'klinik', 12
    UNION ALL SELECT 'Kesehatan', 'rs', 13
    UNION ALL SELECT 'Kesehatan', 'rumah sakit', 14
    UNION ALL SELECT 'Kesehatan', 'vitamin', 15
    UNION ALL SELECT 'Kesehatan', 'apotek', 16
    UNION ALL SELECT 'Kesehatan', 'periksa', 17
    UNION ALL SELECT 'Kesehatan', 'terapi', 18

    UNION ALL SELECT 'Lainnya', 'lainnya', 10
    UNION ALL SELECT 'Lainnya', 'misc', 11
    UNION ALL SELECT 'Lainnya', 'tak terduga', 12
    UNION ALL SELECT 'Lainnya', 'biaya admin', 13
    UNION ALL SELECT 'Lainnya', 'admin', 14
    UNION ALL SELECT 'Lainnya', 'transfer', 15

    UNION ALL SELECT 'Pemasukan', 'gaji', 10
    UNION ALL SELECT 'Pemasukan', 'bonus', 11
    UNION ALL SELECT 'Pemasukan', 'transfer masuk', 12
    UNION ALL SELECT 'Pemasukan', 'refund', 13
    UNION ALL SELECT 'Pemasukan', 'cashback', 14
    UNION ALL SELECT 'Pemasukan', 'komisi', 15
    UNION ALL SELECT 'Pemasukan', 'honor', 16
    UNION ALL SELECT 'Pemasukan', 'pemasukan', 17
) rules
JOIN `categories` c ON c.`name` = rules.`category_name`
    AND (c.`user_id` IS NULL OR c.`user_id` = u.`id`)
    AND c.`pocket_id` IS NULL
WHERE NOT EXISTS (
    SELECT 1
    FROM `category_rules` existing
    WHERE existing.`user_id` = u.`id`
      AND existing.`pocket_id` IS NULL
      AND existing.`category_id` = c.`id`
      AND existing.`keyword` = rules.`keyword`
);

COMMIT;

-- Validasi:
-- SELECT c.name, COUNT(r.id) AS total_rule
-- FROM categories c
-- LEFT JOIN category_rules r ON r.category_id = c.id
-- WHERE c.user_id = 1 OR c.user_id IS NULL
-- GROUP BY c.id, c.name
-- ORDER BY c.name;
