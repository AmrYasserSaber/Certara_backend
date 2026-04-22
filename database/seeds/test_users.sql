-- Sample research + notifications. Requires roles.sql first.
-- Assumes clean DB where ids: 1=admin, 2=manager, 3=sample_size_officer,
-- 4=reviewer, 5=student, 6=pending student.

INSERT INTO `research`
    (`id`, `student_id`, `title`, `principal_investigator`, `co_investigators`, `department`, `faculty`, `specialization`, `serial_number`, `status`)
VALUES
    (1, 5, 'Effect of Vitamin D on COVID-19 Recovery Rates', 'Dr. Ahmed Ali',   'Dr. Mona Said',             'Internal Medicine', 'Medicine', 'Clinical Research', 'IRB-2026-0001', 'in_review'),
    (2, 5, 'Cardiac Biomarkers in Young Athletes',           'Dr. Sherif Kamal','Dr. Yasmine Hassan',        'Cardiology',        'Medicine', 'Cardiology',        'IRB-2026-0002', 'awaiting_sample_size'),
    (3, 5, 'Pilot Study on Pediatric Diabetes Education',    'Dr. Rana Fouad',  'Dr. Omar Nabil',            'Pediatrics',        'Medicine', 'Pediatrics',        NULL,            'draft');

INSERT INTO `payments`
    (`research_id`, `amount`, `currency`, `type`, `status`, `gateway`, `gateway_ref`, `paid_at`)
VALUES
    (1, 500.00, 'EGP', 'first', 'paid',    'cashair', 'CASHAIR-DEMO-0001', NOW()),
    (2, 500.00, 'EGP', 'first', 'paid',    'cashair', 'CASHAIR-DEMO-0002', NOW());

INSERT INTO `reviews`
    (`research_id`, `reviewer_id`, `status`)
VALUES
    (1, 4, 'in_progress');

INSERT INTO `notifications`
    (`user_id`, `type`, `title`, `message`, `is_read`, `research_id`)
VALUES
    (5, 'payment_confirmed',  'تم استلام الدفع',              'تم تأكيد دفع رسوم التقديم للبحث IRB-2026-0001.', 0, 1),
    (5, 'review_requested',   'بحثك قيد المراجعة',            'تم إسناد بحثك إلى مراجع. سيتم إخطارك عند اتخاذ القرار.', 0, 1),
    (4, 'review_requested',   'تم إسناد بحث جديد للمراجعة', 'يوجد بحث جديد في قائمة المراجعة الخاصة بك.',     0, 1);
