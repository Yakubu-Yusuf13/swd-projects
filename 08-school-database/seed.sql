-- Synthetic Nigerian school sample data. Names and phone numbers are fictional.
USE swd_school;

INSERT INTO teachers
    (staff_number, first_name, middle_name, last_name, email, phone, hire_date)
VALUES
    ('STF-001', 'Adaeze', NULL, 'Okafor', 'adaeze.okafor@school.example', '0800-000-0101', '2018-09-01'),
    ('STF-002', 'Babatunde', 'Ayo', 'Adeyemi', 'babatunde.adeyemi@school.example', '0800-000-0102', '2019-01-15'),
    ('STF-003', 'Chinwe', NULL, 'Eze', 'chinwe.eze@school.example', '0800-000-0103', '2020-09-07'),
    ('STF-004', 'David', 'Emeka', 'Nwosu', 'david.nwosu@school.example', '0800-000-0104', '2017-04-03'),
    ('STF-005', 'Fatima', 'Zainab', 'Bello', 'fatima.bello@school.example', '0800-000-0105', '2021-02-08'),
    ('STF-006', 'Ifeanyi', NULL, 'Okonkwo', 'ifeanyi.okonkwo@school.example', '0800-000-0106', '2016-10-10');

INSERT INTO terms (term_name, start_date, end_date)
VALUES ('2026/2027 First Term', '2026-09-14', '2026-12-18');

INSERT INTO school_classes (class_name, form_teacher_id)
SELECT assignments.class_name, teachers.teacher_id
FROM (
    SELECT 'JSS1 A' AS class_name, 'STF-001' AS staff_number
    UNION ALL SELECT 'JSS1 B', 'STF-002'
    UNION ALL SELECT 'JSS2 A', 'STF-003'
) AS assignments
JOIN teachers ON teachers.staff_number = assignments.staff_number;

INSERT INTO subjects (subject_code, subject_name)
VALUES
    ('ENG', 'English Language'),
    ('MTH', 'Mathematics'),
    ('BSC', 'Basic Science'),
    ('BST', 'Basic Technology'),
    ('SOC', 'Social Studies'),
    ('CIV', 'Civic Education'),
    ('AGR', 'Agricultural Science'),
    ('CRS', 'Christian Religious Studies');

INSERT INTO students
    (admission_number, first_name, middle_name, last_name, date_of_birth,
     gender, guardian_name, guardian_phone)
VALUES
    ('SWD/2026/001', 'Amina', 'Yusuf', 'Abubakar', '2013-04-12', 'female', 'Musa Abubakar', '0800-000-1001'),
    ('SWD/2026/002', 'Chinedu', NULL, 'Okafor', '2012-11-03', 'male', 'Grace Okafor', '0800-000-1002'),
    ('SWD/2026/003', 'Tomiwa', 'Bola', 'Adeleke', '2013-07-26', 'male', 'Kemi Adeleke', '0800-000-1003'),
    ('SWD/2026/004', 'Zainab', 'Hauwa', 'Ibrahim', '2012-02-18', 'female', 'Sani Ibrahim', '0800-000-1004'),
    ('SWD/2026/005', 'Emeka', NULL, 'Nwankwo', '2013-09-09', 'male', 'Ifeoma Nwankwo', '0800-000-1005'),
    ('SWD/2026/006', 'Ngozi', 'Chika', 'Eze', '2012-06-14', 'female', 'Peter Eze', '0800-000-1006'),
    ('SWD/2026/007', 'Seyi', NULL, 'Akinola', '2013-01-30', 'male', 'Bisi Akinola', '0800-000-1007'),
    ('SWD/2026/008', 'Maryam', 'Binta', 'Garba', '2012-12-21', 'female', 'Aminu Garba', '0800-000-1008'),
    ('SWD/2026/009', 'Kelechi', NULL, 'Obi', '2013-05-05', 'male', 'Nneka Obi', '0800-000-1009'),
    ('SWD/2026/010', 'Funke', 'Yetunde', 'Ojo', '2012-08-16', 'female', 'Tunde Ojo', '0800-000-1010'),
    ('SWD/2026/011', 'Ibrahim', 'Musa', 'Sule', '2012-03-11', 'male', 'Aisha Sule', '0800-000-1011'),
    ('SWD/2026/012', 'Ada', NULL, 'Okeke', '2011-10-27', 'female', 'Chuka Okeke', '0800-000-1012'),
    ('SWD/2026/013', 'Oluwaseun', 'David', 'Bakare', '2012-07-02', 'male', 'Ronke Bakare', '0800-000-1013'),
    ('SWD/2026/014', 'Safiya', NULL, 'Lawal', '2011-12-09', 'female', 'Kabir Lawal', '0800-000-1014'),
    ('SWD/2026/015', 'Obinna', 'Chukwu', 'Umeh', '2012-04-23', 'male', 'Ijeoma Umeh', '0800-000-1015'),
    ('SWD/2026/016', 'Halima', 'Sadiya', 'Bello', '2011-06-19', 'female', 'Usman Bello', '0800-000-1016'),
    ('SWD/2026/017', 'Damilola', NULL, 'Ogunleye', '2012-09-28', 'male', 'Morenike Ogunleye', '0800-000-1017'),
    ('SWD/2026/018', 'Chisom', 'Adaora', 'Madu', '2011-02-07', 'female', 'Emeka Madu', '0800-000-1018'),
    ('SWD/2026/019', 'Bashir', NULL, 'Abdullahi', '2012-01-15', 'male', 'Hadiza Abdullahi', '0800-000-1019'),
    ('SWD/2026/020', 'Temitope', 'Grace', 'Fashola', '2011-11-30', 'female', 'Wale Fashola', '0800-000-1020'),
    ('SWD/2026/021', 'Nnamdi', NULL, 'Ibe', '2011-05-12', 'male', 'Ngozi Ibe', '0800-000-1021'),
    ('SWD/2026/022', 'Aisha', 'Mariam', 'Yaro', '2010-12-04', 'female', 'Garba Yaro', '0800-000-1022'),
    ('SWD/2026/023', 'Tobiloba', NULL, 'Olatunji', '2011-08-25', 'male', 'Bola Olatunji', '0800-000-1023'),
    ('SWD/2026/024', 'Amarachi', 'Joy', 'Onyeka', '2010-10-17', 'female', 'Chinedu Onyeka', '0800-000-1024'),
    ('SWD/2026/025', 'Yusuf', 'Sadiq', 'Bello', '2011-03-08', 'male', 'Rabi Bello', '0800-000-1025'),
    ('SWD/2026/026', 'Bisola', NULL, 'Olowu', '2010-07-21', 'female', 'Femi Olowu', '0800-000-1026'),
    ('SWD/2026/027', 'Chukwudi', 'Paul', 'Anyanwu', '2011-09-14', 'male', 'Nkiru Anyanwu', '0800-000-1027'),
    ('SWD/2026/028', 'Maimuna', NULL, 'Shehu', '2010-04-29', 'female', 'Bello Shehu', '0800-000-1028'),
    ('SWD/2026/029', 'Ayomide', 'Tunde', 'Olawale', '2011-01-06', 'male', 'Sade Olawale', '0800-000-1029'),
    ('SWD/2026/030', 'Chiamaka', NULL, 'Ugo', '2010-06-11', 'female', 'Ikenna Ugo', '0800-000-1030');

-- Admission numbers 001-010, 011-020, and 021-030 belong to the three classes.
INSERT INTO enrollments (student_id, term_id, class_id)
SELECT students.student_id, terms.term_id, school_classes.class_id
FROM students
JOIN terms ON terms.term_name = '2026/2027 First Term'
JOIN school_classes
  ON school_classes.class_name = CASE
       WHEN CAST(SUBSTRING(students.admission_number, 10) AS UNSIGNED) <= 10 THEN 'JSS1 A'
       WHEN CAST(SUBSTRING(students.admission_number, 10) AS UNSIGNED) <= 20 THEN 'JSS1 B'
       ELSE 'JSS2 A'
     END
WHERE students.admission_number LIKE 'SWD/2026/%';

-- All three classes take all eight subjects in the term. Each subject has one
-- assigned teacher across these sample classes.
INSERT INTO class_subject_offerings (class_id, subject_id, term_id, teacher_id)
SELECT school_classes.class_id, subjects.subject_id, terms.term_id, teachers.teacher_id
FROM school_classes
CROSS JOIN terms
CROSS JOIN subjects
JOIN (
    SELECT 'ENG' AS subject_code, 'STF-001' AS staff_number
    UNION ALL SELECT 'MTH', 'STF-002'
    UNION ALL SELECT 'BSC', 'STF-003'
    UNION ALL SELECT 'BST', 'STF-004'
    UNION ALL SELECT 'SOC', 'STF-005'
    UNION ALL SELECT 'CIV', 'STF-006'
    UNION ALL SELECT 'AGR', 'STF-003'
    UNION ALL SELECT 'CRS', 'STF-004'
) AS assignments ON assignments.subject_code = subjects.subject_code
JOIN teachers ON teachers.staff_number = assignments.staff_number
WHERE terms.term_name = '2026/2027 First Term';

-- Generate one result for every enrolled student and every offering in their
-- class and term: 30 students x 8 subjects = 240 results.
INSERT INTO results (enrollment_id, offering_id, ca_score, exam_score)
SELECT
    enrollments.enrollment_id,
    class_subject_offerings.offering_id,
    CAST(MOD(enrollments.enrollment_id * 37 + class_subject_offerings.subject_id * 13, 401) / 10 AS DECIMAL(5,2)),
    CAST(MOD(enrollments.enrollment_id * 53 + class_subject_offerings.subject_id * 17, 601) / 10 AS DECIMAL(5,2))
FROM enrollments
JOIN class_subject_offerings
  ON class_subject_offerings.class_id = enrollments.class_id
 AND class_subject_offerings.term_id = enrollments.term_id;

-- Generate five school-day attendance records per student.
INSERT INTO attendance (student_id, attendance_date, status)
SELECT students.student_id, school_days.attendance_date,
       CASE MOD(students.student_id + DAYOFMONTH(school_days.attendance_date), 15)
           WHEN 0 THEN 'absent'
           WHEN 1 THEN 'late'
           ELSE 'present'
       END
FROM students
CROSS JOIN (
    SELECT DATE '2026-11-02' AS attendance_date
    UNION ALL SELECT DATE '2026-11-03'
    UNION ALL SELECT DATE '2026-11-04'
    UNION ALL SELECT DATE '2026-11-05'
    UNION ALL SELECT DATE '2026-11-06'
) AS school_days;
