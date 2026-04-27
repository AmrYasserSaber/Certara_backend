-- DEV 4 workflow demo seed.
-- Load after roles.sql and test_users.sql on a clean database.
-- Assumes demo users exist with ids:
-- 1=admin, 2=manager, 3=sample size officer, 4=reviewer, 5=student, 6=pending student.
START TRANSACTION;
-- Remove any previous demo rows so the script can be rerun safely.
DELETE FROM activity_logs
WHERE target_type = 'research'
    AND target_id IN (101, 102, 103, 104, 105, 106, 107);
DELETE FROM notifications
WHERE research_id IN (101, 102, 103, 104, 105, 106, 107);
DELETE FROM research
WHERE id IN (101, 102, 103, 104, 105, 106, 107);
INSERT INTO `research` (
        `id`,
        `student_id`,
        `title`,
        `principal_investigator`,
        `co_investigators`,
        `department`,
        `faculty`,
        `specialization`,
        `serial_number`,
        `status`
    )
VALUES (
        101,
        5,
        'Assessment of Telemedicine Satisfaction in Rural Clinics',
        'Dr. Ahmed Ali',
        'Dr. Mona Said',
        'Family Medicine',
        'Medicine',
        'Primary Care',
        NULL,
        'pending_activation'
    ),
    (
        102,
        5,
        'Impact of Sleep Quality on Academic Performance',
        'Dr. Rana Fouad',
        'Dr. Omar Nabil',
        'Psychiatry',
        'Medicine',
        'Behavioral Health',
        'IRB-2026-0102',
        'in_review'
    ),
    (
        103,
        5,
        'Clinical Outcomes of Lifestyle Intervention in Prediabetes',
        'Dr. Sherif Kamal',
        'Dr. Yasmine Hassan',
        'Endocrinology',
        'Medicine',
        'Endocrinology',
        'IRB-2026-0103',
        'manager_reviewing'
    ),
    (
        104,
        5,
        'Pilot Study of Postoperative Pain Control Protocols',
        'Dr. Salma Hussein',
        'Dr. Mahmoud Farid',
        'Surgery',
        'Medicine',
        'General Surgery',
        'IRB-2026-0104',
        'approved'
    ),
    (
        105,
        5,
        'Evaluation of Vitamin Supplement Use Among Students',
        'Dr. Sara Adel',
        'Dr. Tarek Amin',
        'Public Health',
        'Medicine',
        'Epidemiology',
        'IRB-2026-0105',
        'rejected'
    ),
    (
        106,
        5,
        'Prospective Cohort on Emergency Triage Quality Indicators',
        'Dr. Khaled Fawzy',
        'Dr. Nada Mostafa',
        'Emergency Medicine',
        'Medicine',
        'Emergency Care',
        NULL,
        'pending_activation'
    ),
    (
        107,
        5,
        'Longitudinal Analysis of Hypertension Control in Primary Care',
        'Dr. Rania El-Sayed',
        'Dr. Hossam Nader',
        'Internal Medicine',
        'Medicine',
        'Cardiovascular Epidemiology',
        'IRB-2026-0107',
        'reviewer_approved'
    );
INSERT INTO `documents` (
        `id`,
        `research_id`,
        `type`,
        `file_path`,
        `original_name`,
        `size_bytes`
    )
VALUES (
        1001,
        101,
        'protocol',
        'uploads/documents/101-protocol.pdf',
        'telemedicine-protocol.pdf',
        124800
    ),
    (
        1002,
        102,
        'protocol',
        'uploads/documents/102-protocol.pdf',
        'sleep-quality-protocol.pdf',
        132400
    ),
    (
        1003,
        103,
        'protocol',
        'uploads/documents/103-protocol.pdf',
        'prediabetes-protocol.pdf',
        119500
    ),
    (
        1004,
        104,
        'protocol',
        'uploads/documents/104-protocol.pdf',
        'postop-pain-protocol.pdf',
        141200
    ),
    (
        1005,
        105,
        'protocol',
        'uploads/documents/105-protocol.pdf',
        'vitamin-supplement-protocol.pdf',
        118900
    ),
    (
        1006,
        106,
        'protocol',
        'uploads/documents/106-protocol.pdf',
        'emergency-triage-protocol.pdf',
        126300
    ),
    (
        1007,
        107,
        'protocol',
        'uploads/documents/107-protocol.pdf',
        'hypertension-control-protocol.pdf',
        136450
    );
INSERT INTO `payments` (
        `id`,
        `research_id`,
        `amount`,
        `currency`,
        `type`,
        `status`,
        `gateway`,
        `gateway_ref`,
        `paid_at`
    )
VALUES (
        2001,
        102,
        500.00,
        'EGP',
        'first',
        'paid',
        'cashair',
        'CASHAIR-DEMO-0102-1',
        NOW()
    ),
    (
        2002,
        102,
        500.00,
        'EGP',
        'second',
        'pending',
        'cashair',
        'CASHAIR-DEMO-0102-2',
        NULL
    ),
    (
        2003,
        103,
        500.00,
        'EGP',
        'first',
        'paid',
        'cashair',
        'CASHAIR-DEMO-0103-1',
        NOW()
    ),
    (
        2004,
        103,
        500.00,
        'EGP',
        'second',
        'paid',
        'cashair',
        'CASHAIR-DEMO-0103-2',
        NOW()
    ),
    (
        2005,
        104,
        500.00,
        'EGP',
        'first',
        'paid',
        'cashair',
        'CASHAIR-DEMO-0104-1',
        NOW()
    ),
    (
        2006,
        104,
        500.00,
        'EGP',
        'second',
        'paid',
        'cashair',
        'CASHAIR-DEMO-0104-2',
        NOW()
    ),
    (
        2007,
        105,
        500.00,
        'EGP',
        'first',
        'failed',
        'cashair',
        'CASHAIR-DEMO-0105-1',
        NULL
    ),
    (
        2008,
        107,
        500.00,
        'EGP',
        'first',
        'paid',
        'cashair',
        'CASHAIR-DEMO-0107-1',
        NOW()
    ),
    (
        2009,
        107,
        500.00,
        'EGP',
        'second',
        'paid',
        'cashair',
        'CASHAIR-DEMO-0107-2',
        NOW()
    );
INSERT INTO `sample_sizes` (
        `id`,
        `research_id`,
        `officer_id`,
        `calculated_size`,
        `notes`,
        `fee_amount`
    )
VALUES (
        3001,
        102,
        3,
        120,
        'Calculated from a 95% confidence level and 5% margin of error.',
        500.00
    ),
    (
        3002,
        103,
        3,
        85,
        'Adjusted for a smaller pilot population.',
        350.00
    ),
    (
        3003,
        104,
        3,
        140,
        'Full clinical cohort estimate.',
        600.00
    ),
    (
        3004,
        107,
        3,
        110,
        'Calculated with design effect and 10% dropout contingency.',
        450.00
    );
INSERT INTO `reviews` (
        `id`,
        `research_id`,
        `reviewer_id`,
        `round_number`,
        `previous_review_id`,
        `status`,
        `decision`,
        `decided_at`
    )
VALUES (4001, 102, 4, 1, NULL, 'in_progress', NULL, NULL),
    (4002, 103, 4, 1, NULL, 'decided', 'approved', NOW()),
    (4003, 104, 4, 1, NULL, 'decided', 'approved', NOW()),
    (4004, 105, 4, 1, NULL, 'decided', 'rejected', NOW()),
    (4005, 107, 4, 1, NULL, 'decided', 'approved', NOW());
INSERT INTO `review_comments` (`id`, `review_id`, `reviewer_id`, `comment_text`)
VALUES (
        5001,
        4002,
        4,
        'The methodology is acceptable and the risk profile is minimal.'
    ),
    (
        5002,
        4003,
        4,
        'Approved with no additional revisions required.'
    ),
    (
        5003,
        4004,
        4,
        'The protocol requires substantial revision before reconsideration.'
    ),
    (
        5004,
        4005,
        4,
        'Statistical plan is adequate and risk mitigation is clearly documented. Recommended for final manager approval.'
    );
INSERT INTO `certificates` (
        `id`,
        `research_id`,
        `issued_by`,
        `certificate_number`,
        `file_path`,
        `issued_at`
    )
VALUES (
        6001,
        104,
        2,
        'CERT-2026-0104',
        'uploads/certificates/seed-certificate-104.pdf',
        NOW()
    );
INSERT INTO `notifications` (
        `id`,
        `user_id`,
        `type`,
        `title`,
        `message`,
        `is_read`,
        `research_id`
    )
VALUES (
        7001,
        4,
        'review_requested',
        'تم إسناد بحث جديد للمراجعة',
        'يوجد بحث جديد في قائمة المراجعة الخاصة بك.',
        0,
        102
    ),
    (
        7002,
        5,
        'research_approved',
        'تمت الموافقة النهائية على البحث',
        'تمت الموافقة النهائية على بحثك رقم IRB-2026-0103.',
        0,
        103
    ),
    (
        7003,
        5,
        'certificate_ready',
        'الشهادة جاهزة',
        'تم إصدار شهادة الموافقة الخاصة ببحثك رقم IRB-2026-0104.',
        0,
        104
    ),
    (
        7004,
        5,
        'research_rejected',
        'تم رفض البحث',
        'تم رفض بحثك رقم IRB-2026-0105 بعد المراجعة النهائية.',
        0,
        105
    ),
    (
        7005,
        2,
        'review_requested',
        'بحث بانتظار القرار النهائي',
        'البحث IRB-2026-0107 وصل إلى حالة reviewer_approved ويحتاج قرار المدير.',
        0,
        107
    ),
    (
        7006,
        5,
        'payment_confirmed',
        'تم تجهيز البحث للمراجعة الإدارية',
        'بحثك بعنوان Emergency Triage جاهز لإجراء توليد الرقم التسلسلي من الإدارة.',
        0,
        106
    );
INSERT INTO `activity_logs` (
        `id`,
        `actor_id`,
        `action`,
        `target_type`,
        `target_id`,
        `details`,
        `ip_address`,
        `user_agent`
    )
VALUES (
        8001,
        1,
        'admin.serial_generated',
        'research',
        101,
        '{"serial_number":"IRB-2026-0005","status":"awaiting_payment_1"}',
        '127.0.0.1',
        'seed-data'
    ),
    (
        8002,
        1,
        'admin.reviewer_assigned',
        'research',
        102,
        '{"reviewer_id":4}',
        '127.0.0.1',
        'seed-data'
    ),
    (
        8003,
        2,
        'manager.decision_recorded',
        'research',
        103,
        '{"decision":"approved","note":"No additional concerns"}',
        '127.0.0.1',
        'seed-data'
    ),
    (
        8004,
        2,
        'manager.certificate_issued',
        'research',
        104,
        '{"certificate_number":"CERT-2026-0104","file_path":"uploads/certificates/seed-certificate-104.pdf"}',
        '127.0.0.1',
        'seed-data'
    ),
    (
        8005,
        4,
        'reviewer.decision_submitted',
        'research',
        107,
        '{"decision":"approved","review_id":4005}',
        '127.0.0.1',
        'seed-data'
    ),
    (
        8006,
        1,
        'admin.research_pending_activation',
        'research',
        106,
        '{"status":"pending_activation","note":"ready_for_serial_generation"}',
        '127.0.0.1',
        'seed-data'
    );
COMMIT;