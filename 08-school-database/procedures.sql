USE swd_school;

DROP PROCEDURE IF EXISTS sp_record_result;

DELIMITER $$

CREATE PROCEDURE sp_record_result(
    IN p_student_id INT UNSIGNED,
    IN p_subject_id INT UNSIGNED,
    IN p_term_id INT UNSIGNED,
    IN p_ca_score DECIMAL(5,2),
    IN p_exam_score DECIMAL(5,2)
)
BEGIN
    DECLARE v_enrollment_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_offering_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_class_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_match_count INT UNSIGNED DEFAULT 0;

    IF p_student_id IS NULL OR p_subject_id IS NULL OR p_term_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Student, subject, and term IDs are required';
    END IF;

    IF p_ca_score IS NULL OR p_ca_score < 0 OR p_ca_score > 40 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'CA score must be between 0 and 40';
    END IF;

    IF p_exam_score IS NULL OR p_exam_score < 0 OR p_exam_score > 60 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Exam score must be between 0 and 60';
    END IF;

    SELECT COUNT(*), MAX(enrollment_id), MAX(class_id)
      INTO v_match_count, v_enrollment_id, v_class_id
      FROM enrollments
     WHERE student_id = p_student_id
       AND term_id = p_term_id;

    IF v_match_count = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Student has no enrollment for the requested term';
    END IF;

    SELECT COUNT(*), MAX(offering_id)
      INTO v_match_count, v_offering_id
      FROM class_subject_offerings
     WHERE class_id = v_class_id
       AND subject_id = p_subject_id
       AND term_id = p_term_id;

    IF v_match_count = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Subject is not offered to the student class for the requested term';
    END IF;

    INSERT INTO results (enrollment_id, offering_id, ca_score, exam_score)
    VALUES (v_enrollment_id, v_offering_id, p_ca_score, p_exam_score)
    ON DUPLICATE KEY UPDATE
        ca_score = VALUES(ca_score),
        exam_score = VALUES(exam_score);
END$$

DELIMITER ;

-- Example: IDs must identify an enrolled student, subject, and term.
-- Repeating the same student/subject/term call updates the existing scores.
-- CALL sp_record_result(1, 1, 1, 35.00, 48.00);
