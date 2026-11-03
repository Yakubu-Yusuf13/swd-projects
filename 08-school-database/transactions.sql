USE swd_school;

-- The audit row references the enrollment and both classes. RESTRICT keeps
-- transfer history intact; ON UPDATE CASCADE preserves it if a referenced ID changes.
CREATE TABLE IF NOT EXISTS class_transfers (
    transfer_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    enrollment_id INT UNSIGNED NOT NULL,
    term_id INT UNSIGNED NOT NULL,
    from_class_id INT UNSIGNED NOT NULL,
    to_class_id INT UNSIGNED NOT NULL,
    transferred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (transfer_id),
    CONSTRAINT chk_class_transfers_different_classes
        CHECK (from_class_id <> to_class_id),
    CONSTRAINT fk_class_transfers_enrollment
        FOREIGN KEY (enrollment_id) REFERENCES enrollments (enrollment_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_class_transfers_term
        FOREIGN KEY (term_id) REFERENCES terms (term_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_class_transfers_from_class
        FOREIGN KEY (from_class_id) REFERENCES school_classes (class_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_class_transfers_to_class
        FOREIGN KEY (to_class_id) REFERENCES school_classes (class_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Example 1: simple transaction for a student with no results in this term.
-- Change these values before running. The target class and enrollment must exist.
-- Do not run this after the student's results exist: changing the enrollment's
-- class would make those results refer to offerings from the old class.
-- The sample is commented out so loading this file does not move sample data.
-- Uncomment the following block and set the variables deliberately to execute it:
-- SET @admission_number = 'SWD/2026/001';
-- SET @term_name = '2026/2027 First Term';
-- SET @target_class_name = 'JSS1 B';
--
-- START TRANSACTION;
--
-- INSERT INTO class_transfers
--     (enrollment_id, term_id, from_class_id, to_class_id)
-- SELECT e.enrollment_id, e.term_id, e.class_id, target.class_id
-- FROM enrollments AS e
-- JOIN students AS s ON s.student_id = e.student_id
-- JOIN terms AS t ON t.term_id = e.term_id
-- JOIN school_classes AS target ON target.class_name = @target_class_name
-- WHERE s.admission_number = @admission_number
--   AND t.term_name = @term_name
--   AND e.class_id <> target.class_id;
--
-- UPDATE enrollments AS e
-- JOIN students AS s ON s.student_id = e.student_id
-- JOIN terms AS t ON t.term_id = e.term_id
-- JOIN school_classes AS target ON target.class_name = @target_class_name
-- SET e.class_id = target.class_id
-- WHERE s.admission_number = @admission_number
--   AND t.term_name = @term_name
--   AND e.class_id <> target.class_id;
--
-- COMMIT;

-- Example 2: guarded transaction. A missing target, missing enrollment,
-- existing results, or any SQL error signals an error and rolls back both DML
-- statements. Call this procedure outside any existing transaction.
DROP PROCEDURE IF EXISTS sp_transfer_student_checked;

DELIMITER $$

CREATE PROCEDURE sp_transfer_student_checked(
    IN p_admission_number VARCHAR(24),
    IN p_term_name VARCHAR(80),
    IN p_target_class_name VARCHAR(30)
)
BEGIN
    DECLARE v_target_class_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_enrollment_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_current_class_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_term_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_match_count INT UNSIGNED DEFAULT 0;
    DECLARE v_result_count INT UNSIGNED DEFAULT 0;

    DECLARE EXIT HANDLER FOR NOT FOUND
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT COUNT(*), MAX(class_id)
      INTO v_match_count, v_target_class_id
      FROM school_classes
     WHERE class_name = p_target_class_name;

    IF v_match_count = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Target class does not exist; transfer rolled back';
    END IF;

    SELECT COUNT(*)
      INTO v_match_count
      FROM enrollments AS e
      JOIN students AS s ON s.student_id = e.student_id
      JOIN terms AS t ON t.term_id = e.term_id
     WHERE s.admission_number = p_admission_number
       AND t.term_name = p_term_name;

    IF v_match_count = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Student has no enrollment for the requested term; transfer rolled back';
    END IF;

    -- Lock the enrollment so a concurrent result insert cannot race the
    -- existing-results check and leave a score tied to the old class offering.
    SELECT e.enrollment_id, e.class_id, e.term_id
      INTO v_enrollment_id, v_current_class_id, v_term_id
      FROM enrollments AS e
      JOIN students AS s ON s.student_id = e.student_id
      JOIN terms AS t ON t.term_id = e.term_id
     WHERE s.admission_number = p_admission_number
       AND t.term_name = p_term_name
     FOR UPDATE;

    IF v_current_class_id = v_target_class_id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Student is already in the target class; transfer rolled back';
    END IF;

    SELECT COUNT(*)
      INTO v_result_count
      FROM results
     WHERE enrollment_id = v_enrollment_id;

    IF v_result_count > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot transfer after results exist; transfer rolled back';
    END IF;

    INSERT INTO class_transfers
        (enrollment_id, term_id, from_class_id, to_class_id)
    VALUES
        (v_enrollment_id, v_term_id, v_current_class_id, v_target_class_id);

    UPDATE enrollments
       SET class_id = v_target_class_id
     WHERE enrollment_id = v_enrollment_id;

    COMMIT;
END$$

DELIMITER ;

-- Example call. This intentionally fails and rolls back because the target does not exist:
-- CALL sp_transfer_student_checked('SWD/2026/001', '2026/2027 First Term', 'JSS9 Z');
