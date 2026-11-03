USE swd_school;

-- Query 3 and Query 5 traverse enrollments by class, then restrict rows to a
-- term and read the student ID. This composite index supports that access path
-- and covers those enrollment columns.
SET @index_sql = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'enrollments'
          AND index_name = 'idx_enrollments_class_term_student'
    ),
    'SELECT ''idx_enrollments_class_term_student already exists'' AS status',
    'CREATE INDEX idx_enrollments_class_term_student ON enrollments (class_id, term_id, student_id)'
);
PREPARE index_statement FROM @index_sql;
EXECUTE index_statement;
DEALLOCATE PREPARE index_statement;

-- Query 7 looks up attendance per student over a date range and reads status.
-- The existing unique (student_id, attendance_date) index already supports
-- filtering; this covering index also supplies status without a table lookup.
SET @index_sql = IF(
    EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'attendance'
          AND index_name = 'idx_attendance_student_date_status'
    ),
    'SELECT ''idx_attendance_student_date_status already exists'' AS status',
    'CREATE INDEX idx_attendance_student_date_status ON attendance (student_id, attendance_date, status)'
);
PREPARE index_statement FROM @index_sql;
EXECUTE index_statement;
DEALLOCATE PREPARE index_statement;
