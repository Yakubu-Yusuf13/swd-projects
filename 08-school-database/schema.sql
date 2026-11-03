-- School database schema for MySQL/MariaDB with enforced CHECK constraints.
-- Run against MySQL 8.0.16+ or a MariaDB release that enforces CHECK.
CREATE DATABASE IF NOT EXISTS swd_school
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE swd_school;

-- Remove guards before their referenced tables so this file can be re-run.
DROP TRIGGER IF EXISTS trg_results_validate_match_before_insert;
DROP TRIGGER IF EXISTS trg_results_validate_match_before_update;

-- Drop child tables before parents to respect foreign-key dependencies.
DROP TABLE IF EXISTS results;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS class_subject_offerings;
DROP TABLE IF EXISTS enrollments;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS subjects;
DROP TABLE IF EXISTS school_classes;
DROP TABLE IF EXISTS terms;
DROP TABLE IF EXISTS teachers;

CREATE TABLE teachers (
    teacher_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    staff_number VARCHAR(20) NOT NULL,
    first_name VARCHAR(60) NOT NULL,
    middle_name VARCHAR(60) NULL,
    last_name VARCHAR(60) NOT NULL,
    email VARCHAR(254) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    hire_date DATE NOT NULL,
    PRIMARY KEY (teacher_id),
    UNIQUE KEY uq_teachers_staff_number (staff_number),
    UNIQUE KEY uq_teachers_email (email)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE terms (
    term_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    term_name VARCHAR(80) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    PRIMARY KEY (term_id),
    UNIQUE KEY uq_terms_term_name (term_name),
    CONSTRAINT chk_terms_date_order CHECK (end_date >= start_date)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE school_classes (
    class_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    class_name VARCHAR(30) NOT NULL,
    form_teacher_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (class_id),
    UNIQUE KEY uq_school_classes_name (class_name),
    -- RESTRICT preserves class ownership if a teacher has history; CASCADE
    -- keeps the reference valid if the referenced teacher ID is changed.
    CONSTRAINT fk_school_classes_form_teacher
        FOREIGN KEY (form_teacher_id) REFERENCES teachers (teacher_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subjects (
    subject_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject_code VARCHAR(15) NOT NULL,
    subject_name VARCHAR(80) NOT NULL,
    PRIMARY KEY (subject_id),
    UNIQUE KEY uq_subjects_code (subject_code),
    UNIQUE KEY uq_subjects_name (subject_name)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE students (
    student_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    admission_number VARCHAR(24) NOT NULL,
    first_name VARCHAR(60) NOT NULL,
    middle_name VARCHAR(60) NULL,
    last_name VARCHAR(60) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('female', 'male', 'other', 'prefer_not_to_say') NOT NULL,
    guardian_name VARCHAR(150) NOT NULL,
    guardian_phone VARCHAR(20) NOT NULL,
    PRIMARY KEY (student_id),
    UNIQUE KEY uq_students_admission_number (admission_number)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE enrollments (
    enrollment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    term_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (enrollment_id),
    UNIQUE KEY uq_enrollments_student_term (student_id, term_id),
    -- RESTRICT prevents deleting a student whose enrollment is school history;
    -- CASCADE propagates a changed parent ID without orphaning the enrollment.
    CONSTRAINT fk_enrollments_student
        FOREIGN KEY (student_id) REFERENCES students (student_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    -- RESTRICT protects term history; CASCADE maintains the FK if its ID changes.
    CONSTRAINT fk_enrollments_term
        FOREIGN KEY (term_id) REFERENCES terms (term_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    -- RESTRICT preserves the class assignment recorded for the enrollment;
    -- CASCADE keeps it attached if the class ID changes.
    CONSTRAINT fk_enrollments_class
        FOREIGN KEY (class_id) REFERENCES school_classes (class_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE class_subject_offerings (
    offering_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    class_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    term_id INT UNSIGNED NOT NULL,
    teacher_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (offering_id),
    UNIQUE KEY uq_offerings_class_subject_term (class_id, subject_id, term_id),
    -- RESTRICT preserves offerings when their class has been used; CASCADE
    -- updates the reference if the class ID is changed.
    CONSTRAINT fk_offerings_class
        FOREIGN KEY (class_id) REFERENCES school_classes (class_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    -- RESTRICT protects academic records that use this subject; CASCADE
    -- maintains the reference if the subject ID is changed.
    CONSTRAINT fk_offerings_subject
        FOREIGN KEY (subject_id) REFERENCES subjects (subject_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    -- RESTRICT preserves which term an offering belongs to; CASCADE updates
    -- the reference if the term ID is changed.
    CONSTRAINT fk_offerings_term
        FOREIGN KEY (term_id) REFERENCES terms (term_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    -- RESTRICT protects teaching assignments used by results; CASCADE
    -- preserves the assignment if the teacher ID is changed.
    CONSTRAINT fk_offerings_teacher
        FOREIGN KEY (teacher_id) REFERENCES teachers (teacher_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE results (
    enrollment_id INT UNSIGNED NOT NULL,
    offering_id INT UNSIGNED NOT NULL,
    ca_score DECIMAL(5,2) NOT NULL,
    exam_score DECIMAL(5,2) NOT NULL,
    -- This composite primary key is also the required uniqueness rule:
    -- an enrollment (one student in one term) gets at most one result per offering.
    PRIMARY KEY (enrollment_id, offering_id),
    CONSTRAINT chk_results_ca_score CHECK (ca_score BETWEEN 0.00 AND 40.00),
    CONSTRAINT chk_results_exam_score CHECK (exam_score BETWEEN 0.00 AND 60.00),
    -- RESTRICT prevents removing an enrollment that has scores; CASCADE
    -- preserves the score's reference if the enrollment ID changes.
    CONSTRAINT fk_results_enrollment
        FOREIGN KEY (enrollment_id) REFERENCES enrollments (enrollment_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    -- RESTRICT preserves scores for an offering; CASCADE keeps them attached
    -- if the offering ID changes.
    CONSTRAINT fk_results_offering
        FOREIGN KEY (offering_id) REFERENCES class_subject_offerings (offering_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance (
    attendance_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present', 'absent', 'late') NOT NULL,
    PRIMARY KEY (attendance_id),
    UNIQUE KEY uq_attendance_student_day (student_id, attendance_date),
    -- RESTRICT protects attendance history from student deletion; CASCADE
    -- maintains the reference if the student ID changes.
    CONSTRAINT fk_attendance_student
        FOREIGN KEY (student_id) REFERENCES students (student_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Keep results normalized (no duplicated class or term columns) while ensuring
-- the student's enrollment and the subject offering are for the same class/term.
DELIMITER $$

CREATE TRIGGER trg_results_validate_match_before_insert
BEFORE INSERT ON results
FOR EACH ROW
BEGIN
    DECLARE v_enrollment_class_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_enrollment_term_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_offering_class_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_offering_term_id INT UNSIGNED DEFAULT NULL;

    SELECT MAX(e.class_id), MAX(e.term_id), MAX(o.class_id), MAX(o.term_id)
      INTO v_enrollment_class_id, v_enrollment_term_id,
           v_offering_class_id, v_offering_term_id
      FROM enrollments AS e
      JOIN class_subject_offerings AS o
        ON o.offering_id = NEW.offering_id
     WHERE e.enrollment_id = NEW.enrollment_id;

    IF v_enrollment_class_id IS NULL
       OR v_offering_class_id IS NULL
       OR v_enrollment_class_id <> v_offering_class_id
       OR v_enrollment_term_id <> v_offering_term_id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Result enrollment and offering must match class and term';
    END IF;
END$$

CREATE TRIGGER trg_results_validate_match_before_update
BEFORE UPDATE ON results
FOR EACH ROW
BEGIN
    DECLARE v_enrollment_class_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_enrollment_term_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_offering_class_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_offering_term_id INT UNSIGNED DEFAULT NULL;

    SELECT MAX(e.class_id), MAX(e.term_id), MAX(o.class_id), MAX(o.term_id)
      INTO v_enrollment_class_id, v_enrollment_term_id,
           v_offering_class_id, v_offering_term_id
      FROM enrollments AS e
      JOIN class_subject_offerings AS o
        ON o.offering_id = NEW.offering_id
     WHERE e.enrollment_id = NEW.enrollment_id;

    IF v_enrollment_class_id IS NULL
       OR v_offering_class_id IS NULL
       OR v_enrollment_class_id <> v_offering_class_id
       OR v_enrollment_term_id <> v_offering_term_id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Result enrollment and offering must match class and term';
    END IF;
END$$

DELIMITER ;
