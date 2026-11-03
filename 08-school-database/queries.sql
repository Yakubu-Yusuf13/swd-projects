USE swd_school;

-- 1. Business question: Which students are in JSS1 A, and who is their form teacher?
-- Technique: JOINs connect class to enrollment/student and class to its form teacher.
SELECT
    sc.class_name,
    s.admission_number,
    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
    CONCAT_WS(' ', ft.first_name, ft.middle_name, ft.last_name) AS form_teacher_name
FROM school_classes AS sc
JOIN enrollments AS e ON e.class_id = sc.class_id
JOIN students AS s ON s.student_id = e.student_id
JOIN teachers AS ft ON ft.teacher_id = sc.form_teacher_id
JOIN terms AS t ON t.term_id = e.term_id
WHERE sc.class_name = 'JSS1 A'
  AND t.term_name = '2026/2027 First Term'
ORDER BY s.last_name, s.first_name, s.admission_number;

-- 2. Business question: What is each student's total and letter grade per subject this term?
-- Technique: CASE maps the calculated CA + exam total to the sample A-F grade bands.
-- Grade bands are illustrative and should be adjusted to the school's grading policy.
SELECT
    t.term_name,
    sc.class_name,
    s.admission_number,
    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
    sub.subject_code,
    sub.subject_name,
    r.ca_score,
    r.exam_score,
    r.ca_score + r.exam_score AS total_score,
    CASE
        WHEN r.ca_score + r.exam_score >= 80 THEN 'A'
        WHEN r.ca_score + r.exam_score >= 70 THEN 'B'
        WHEN r.ca_score + r.exam_score >= 60 THEN 'C'
        WHEN r.ca_score + r.exam_score >= 50 THEN 'D'
        WHEN r.ca_score + r.exam_score >= 40 THEN 'E'
        ELSE 'F'
    END AS grade
FROM results AS r
JOIN enrollments AS e ON e.enrollment_id = r.enrollment_id
JOIN students AS s ON s.student_id = e.student_id
JOIN school_classes AS sc ON sc.class_id = e.class_id
JOIN terms AS t ON t.term_id = e.term_id
JOIN class_subject_offerings AS o ON o.offering_id = r.offering_id
JOIN subjects AS sub ON sub.subject_id = o.subject_id
WHERE t.term_name = '2026/2027 First Term'
ORDER BY sc.class_name, s.last_name, s.first_name, sub.subject_name;

-- 3. Business question: What is each student's average and rank in their class this term?
-- Technique: Aggregate subject totals per student, then use RANK() partitioned by class and term.
-- Tied averages share a rank, and the next rank is skipped, matching SQL RANK semantics.
SELECT
    class_name,
    term_name,
    admission_number,
    student_name,
    average_score,
    RANK() OVER (
        PARTITION BY class_id, term_id
        ORDER BY average_score DESC
    ) AS class_position
FROM (
    SELECT
        sc.class_id,
        e.term_id,
        sc.class_name,
        t.term_name,
        s.admission_number,
        CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
        AVG(r.ca_score + r.exam_score) AS average_score
    FROM enrollments AS e
    JOIN school_classes AS sc ON sc.class_id = e.class_id
    JOIN terms AS t ON t.term_id = e.term_id
    JOIN students AS s ON s.student_id = e.student_id
    JOIN results AS r ON r.enrollment_id = e.enrollment_id
    WHERE t.term_name = '2026/2027 First Term'
    GROUP BY
        sc.class_id, e.term_id, sc.class_name, t.term_name,
        s.student_id, s.admission_number, s.first_name, s.middle_name, s.last_name
) AS student_averages
ORDER BY class_name, class_position, admission_number;

-- Query 3 fallback for MySQL versions without window functions (for example MySQL 5.7).
-- Technique: For each student, count distinct higher class averages and add 1.
-- This preserves RANK tie behavior. Run this alternative instead of Query 3.
SELECT
    ranked.class_name,
    ranked.term_name,
    ranked.admission_number,
    ranked.student_name,
    ranked.average_score,
    1 + (
        SELECT COUNT(DISTINCT comparison.average_score)
        FROM (
            SELECT
                e2.class_id,
                e2.term_id,
                e2.student_id,
                AVG(r2.ca_score + r2.exam_score) AS average_score
            FROM enrollments AS e2
            JOIN results AS r2 ON r2.enrollment_id = e2.enrollment_id
            GROUP BY e2.class_id, e2.term_id, e2.student_id
        ) AS comparison
        WHERE comparison.class_id = ranked.class_id
          AND comparison.term_id = ranked.term_id
          AND comparison.average_score > ranked.average_score
    ) AS class_position
FROM (
    SELECT
        e.class_id,
        e.term_id,
        sc.class_name,
        t.term_name,
        s.admission_number,
        CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
        AVG(r.ca_score + r.exam_score) AS average_score
    FROM enrollments AS e
    JOIN school_classes AS sc ON sc.class_id = e.class_id
    JOIN terms AS t ON t.term_id = e.term_id
    JOIN students AS s ON s.student_id = e.student_id
    JOIN results AS r ON r.enrollment_id = e.enrollment_id
    WHERE t.term_name = '2026/2027 First Term'
    GROUP BY
        e.class_id, e.term_id, sc.class_name, t.term_name,
        s.student_id, s.admission_number, s.first_name, s.middle_name, s.last_name
) AS ranked
ORDER BY ranked.class_name, class_position, ranked.admission_number;

-- 4. Business question: What are the average, highest, and lowest total scores per subject and class?
-- Technique: GROUP BY forms one group per class, term, and subject; aggregates summarize its results.
SELECT
    sc.class_name,
    t.term_name,
    sub.subject_code,
    sub.subject_name,
    COUNT(r.enrollment_id) AS students_with_results,
    ROUND(AVG(r.ca_score + r.exam_score), 2) AS average_total_score,
    MAX(r.ca_score + r.exam_score) AS highest_total_score,
    MIN(r.ca_score + r.exam_score) AS lowest_total_score
FROM class_subject_offerings AS o
JOIN school_classes AS sc ON sc.class_id = o.class_id
JOIN terms AS t ON t.term_id = o.term_id
JOIN subjects AS sub ON sub.subject_id = o.subject_id
LEFT JOIN results AS r ON r.offering_id = o.offering_id
WHERE t.term_name = '2026/2027 First Term'
GROUP BY sc.class_id, sc.class_name, t.term_id, t.term_name, sub.subject_id, sub.subject_code, sub.subject_name
ORDER BY sc.class_name, sub.subject_name;

-- 5. Business question: Which students failed (scored below 40) in at least two subjects this term?
-- Technique: GROUP BY collects results per student; HAVING filters on an aggregate failure count.
SELECT
    t.term_name,
    sc.class_name,
    s.admission_number,
    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
    SUM(CASE WHEN r.ca_score + r.exam_score < 40 THEN 1 ELSE 0 END) AS failed_subject_count
FROM enrollments AS e
JOIN students AS s ON s.student_id = e.student_id
JOIN school_classes AS sc ON sc.class_id = e.class_id
JOIN terms AS t ON t.term_id = e.term_id
JOIN results AS r ON r.enrollment_id = e.enrollment_id
WHERE t.term_name = '2026/2027 First Term'
GROUP BY
    t.term_id, t.term_name, sc.class_id, sc.class_name,
    s.student_id, s.admission_number, s.first_name, s.middle_name, s.last_name
HAVING SUM(CASE WHEN r.ca_score + r.exam_score < 40 THEN 1 ELSE 0 END) >= 2
ORDER BY failed_subject_count DESC, sc.class_name, s.last_name, s.first_name;

-- 6. Business question: How many distinct subjects and classes does each teacher teach?
-- Technique: LEFT JOIN retains teachers with no offerings; DISTINCT avoids counting
-- repeated subject/class assignments more than once. Form-teacher roles are not counted here.
SELECT
    t.teacher_id,
    t.staff_number,
    CONCAT_WS(' ', t.first_name, t.middle_name, t.last_name) AS teacher_name,
    COUNT(DISTINCT o.subject_id) AS subjects_taught,
    COUNT(DISTINCT o.class_id) AS classes_taught
FROM teachers AS t
LEFT JOIN class_subject_offerings AS o ON o.teacher_id = t.teacher_id
GROUP BY t.teacher_id, t.staff_number, t.first_name, t.middle_name, t.last_name
ORDER BY teacher_name;

-- 7. Business question: What percentage of recorded school days did each student attend,
-- and who is below 80% for this term?
-- Technique: A derived table aggregates attendance per student; the outer query filters it.
-- Present and late count as attended. The denominator is days with an attendance row,
-- because this schema has no separate school-calendar table for unrecorded days.
-- Enrolled students with no attendance rows are included at 0%.
SELECT
    attendance_rates.term_name,
    attendance_rates.class_name,
    attendance_rates.admission_number,
    attendance_rates.student_name,
    attendance_rates.recorded_days,
    attendance_rates.attended_days,
    attendance_rates.attendance_percentage
FROM (
    SELECT
        t.term_name,
        sc.class_name,
        s.admission_number,
        CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
        COUNT(a.attendance_id) AS recorded_days,
        SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END) AS attended_days,
        COALESCE(ROUND(
            100.0 * SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END)
            / NULLIF(COUNT(a.attendance_id), 0),
            2
        ), 0.00) AS attendance_percentage
    FROM enrollments AS e
    JOIN students AS s ON s.student_id = e.student_id
    JOIN school_classes AS sc ON sc.class_id = e.class_id
    JOIN terms AS t ON t.term_id = e.term_id
    LEFT JOIN attendance AS a
      ON a.student_id = s.student_id
     AND a.attendance_date BETWEEN t.start_date AND t.end_date
    WHERE t.term_name = '2026/2027 First Term'
    GROUP BY
        t.term_id, t.term_name, sc.class_id, sc.class_name,
        s.student_id, s.admission_number, s.first_name, s.middle_name, s.last_name
) AS attendance_rates
WHERE attendance_rates.attendance_percentage < 80.00
ORDER BY attendance_rates.attendance_percentage, attendance_rates.class_name,
         attendance_rates.admission_number;

-- 8. Business question: Who is the top-scoring student or students in each class this term?
-- Technique: RANK() partitions student averages by class and term; rank 1 includes ties.
SELECT
    class_name,
    term_name,
    admission_number,
    student_name,
    average_score
FROM (
    SELECT
        sc.class_name,
        t.term_name,
        e.class_id,
        e.term_id,
        s.admission_number,
        CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
        AVG(r.ca_score + r.exam_score) AS average_score,
        RANK() OVER (
            PARTITION BY e.class_id, e.term_id
            ORDER BY AVG(r.ca_score + r.exam_score) DESC
        ) AS class_rank
    FROM enrollments AS e
    JOIN school_classes AS sc ON sc.class_id = e.class_id
    JOIN terms AS t ON t.term_id = e.term_id
    JOIN students AS s ON s.student_id = e.student_id
    JOIN results AS r ON r.enrollment_id = e.enrollment_id
    WHERE t.term_name = '2026/2027 First Term'
    GROUP BY
        sc.class_id, sc.class_name, e.class_id, e.term_id, t.term_name,
        s.student_id, s.admission_number, s.first_name, s.middle_name, s.last_name
) AS class_rankings
WHERE class_rank = 1
ORDER BY class_name, admission_number;

-- Views: drop and recreate so this section can be rerun safely.
DROP VIEW IF EXISTS v_class_summary;
DROP VIEW IF EXISTS v_student_results;

-- View: one row per student and subject offering, with score total and sample grade.
-- The grade cutoffs match Query 2 and are illustrative school-policy defaults.
CREATE VIEW v_student_results AS
SELECT
    e.student_id,
    s.admission_number,
    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
    e.class_id,
    sc.class_name,
    e.term_id,
    t.term_name,
    o.offering_id,
    sub.subject_id,
    sub.subject_code,
    sub.subject_name,
    r.ca_score,
    r.exam_score,
    r.ca_score + r.exam_score AS total_score,
    CASE
        WHEN r.ca_score + r.exam_score >= 80 THEN 'A'
        WHEN r.ca_score + r.exam_score >= 70 THEN 'B'
        WHEN r.ca_score + r.exam_score >= 60 THEN 'C'
        WHEN r.ca_score + r.exam_score >= 50 THEN 'D'
        WHEN r.ca_score + r.exam_score >= 40 THEN 'E'
        ELSE 'F'
    END AS grade
FROM results AS r
JOIN enrollments AS e ON e.enrollment_id = r.enrollment_id
JOIN students AS s ON s.student_id = e.student_id
JOIN school_classes AS sc ON sc.class_id = e.class_id
JOIN terms AS t ON t.term_id = e.term_id
JOIN class_subject_offerings AS o ON o.offering_id = r.offering_id
JOIN subjects AS sub ON sub.subject_id = o.subject_id;

-- View: one row per class and term, summarizing enrolled students and recorded results.
CREATE VIEW v_class_summary AS
SELECT
    sc.class_id,
    sc.class_name,
    t.term_id,
    t.term_name,
    COUNT(DISTINCT e.enrollment_id) AS enrolled_students,
    COUNT(r.enrollment_id) AS result_count,
    ROUND(AVG(r.ca_score + r.exam_score), 2) AS average_total_score,
    MAX(r.ca_score + r.exam_score) AS highest_total_score,
    MIN(r.ca_score + r.exam_score) AS lowest_total_score
FROM school_classes AS sc
CROSS JOIN terms AS t
LEFT JOIN enrollments AS e
  ON e.class_id = sc.class_id
 AND e.term_id = t.term_id
LEFT JOIN results AS r ON r.enrollment_id = e.enrollment_id
GROUP BY sc.class_id, sc.class_name, t.term_id, t.term_name;
