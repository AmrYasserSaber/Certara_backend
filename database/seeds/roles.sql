-- Test accounts — password for ALL users below is "password".
-- Replace hashes before deploying to any shared environment.

INSERT INTO `users`
    (`name`, `email`, `password_hash`, `phone`, `national_id`, `department`, `faculty`, `specialization`, `role`, `status`)
VALUES
    ('Admin Demo',           'admin@irb.local',    '$2a$10$UGA7KQ7wkdtPxVp5kfl8N.ISlrmYunI988M7zof6TMgqM4NWzqB8S', '01000000001', '20000000000001', 'IRB Office',    'Medicine', 'Administration',          'admin',               'active'),
    ('Manager Demo',         'manager@irb.local',  '$2a$10$UGA7KQ7wkdtPxVp5kfl8N.ISlrmYunI988M7zof6TMgqM4NWzqB8S', '01000000002', '20000000000002', 'IRB Committee', 'Medicine', 'Committee Management',    'manager',             'active'),
    ('Sample Size Officer',  'sample@irb.local',   '$2a$10$UGA7KQ7wkdtPxVp5kfl8N.ISlrmYunI988M7zof6TMgqM4NWzqB8S', '01000000003', '20000000000003', 'Biostatistics', 'Medicine', 'Biostatistics',           'sample_size_officer', 'active'),
    ('Reviewer Demo',        'reviewer@irb.local', '$2a$10$UGA7KQ7wkdtPxVp5kfl8N.ISlrmYunI988M7zof6TMgqM4NWzqB8S', '01000000004', '20000000000004', 'Internal Medicine', 'Medicine', 'Clinical Research',  'reviewer',            'active'),
    ('Student Demo',         'student@irb.local',  '$2a$10$UGA7KQ7wkdtPxVp5kfl8N.ISlrmYunI988M7zof6TMgqM4NWzqB8S', '01000000005', '20000000000005', 'Surgery',       'Medicine', 'General Surgery',         'student',             'active'),
    ('Pending Student',      'pending@irb.local',  '$2a$10$UGA7KQ7wkdtPxVp5kfl8N.ISlrmYunI988M7zof6TMgqM4NWzqB8S', '01000000006', '20000000000006', 'Pediatrics',    'Medicine', 'Pediatrics',              'student',             'pending');
