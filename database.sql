-- ========================
-- DATABASE SETUP
-- ========================
CREATE DATABASE IF NOT EXISTS lms
DEFAULT CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;

USE lms;

-- ========================
-- USERS
-- ========================
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(150) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','teacher','student') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================
-- STUDENTS
-- ========================
CREATE TABLE students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  course VARCHAR(50) DEFAULT 'BSIT',
  year_level VARCHAR(50) DEFAULT '1st Year',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================
-- TEACHERS
-- ========================
CREATE TABLE teachers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  department VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================
-- SUBJECTS
-- ========================
CREATE TABLE subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description TEXT,
  year_level VARCHAR(50) DEFAULT '1st Year',
  course VARCHAR(50) DEFAULT 'BSIT',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================
-- ENROLLMENTS
-- ========================
CREATE TABLE enrollments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  subject_id INT NOT NULL,
  enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  UNIQUE KEY unique_enrollment (student_id, subject_id)
);

-- ========================
-- LESSONS
-- ========================
CREATE TABLE lessons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  content TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- ========================
-- SEMESTERS
-- ========================
CREATE TABLE semesters (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  academic_year VARCHAR(20) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status ENUM('active','inactive','completed') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================
-- GRADING PERIODS
-- ========================
CREATE TABLE grading_periods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  semester_id INT NOT NULL,
  name ENUM('prelim','midterm','finals') NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  weight_percent DECIMAL(5,2) NOT NULL,
  status ENUM('active','inactive','completed','pending') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
  UNIQUE KEY unique_period (semester_id, name)
);

-- ========================
-- QUIZZES
-- ========================
CREATE TABLE quizzes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lesson_id INT NOT NULL,
  grading_period_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT,
  max_score INT NOT NULL,
  time_limit_minutes INT DEFAULT NULL,
  attempts_allowed INT DEFAULT 1,
  display_mode ENUM('single','per_page','all') DEFAULT 'all', -- single = 1 Q/page, per_page = 5/page
  open_at DATETIME NOT NULL,
  close_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
  FOREIGN KEY (grading_period_id) REFERENCES grading_periods(id) ON DELETE CASCADE
);

-- ========================
-- QUIZ QUESTIONS
-- ========================
CREATE TABLE quiz_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT NOT NULL,
  question_text TEXT NOT NULL,
  question_type ENUM('multiple_choice','checkbox','text') NOT NULL,
  score INT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

-- ========================
-- QUIZ CHOICES (for MCQ/Checkbox)
-- ========================
CREATE TABLE quiz_choices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_id INT NOT NULL,
  choice_text TEXT NOT NULL,
  is_correct TINYINT(1) DEFAULT 0,
  FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
);

-- ========================
-- QUIZ ATTEMPTS (per student per quiz)
-- ========================
CREATE TABLE quiz_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT NOT NULL,
  student_id INT NOT NULL,
  attempt_number INT NOT NULL,
  started_at DATETIME NOT NULL,
  finished_at DATETIME DEFAULT NULL,
  score INT DEFAULT 0,
  max_score INT DEFAULT 0,
  status ENUM('in_progress','submitted','timeout') DEFAULT 'in_progress',
  FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  UNIQUE KEY unique_attempt (quiz_id, student_id, attempt_number)
);

-- ========================
-- QUIZ ANSWERS (per student per question)
-- ========================
CREATE TABLE quiz_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT NOT NULL,
  question_id INT NOT NULL,
  choice_id INT DEFAULT NULL,      -- for MCQ/Checkbox
  answer_text TEXT DEFAULT NULL,   -- for text answers
  is_correct TINYINT(1) DEFAULT 0, -- auto-graded if possible
  FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
  FOREIGN KEY (choice_id) REFERENCES quiz_choices(id) ON DELETE SET NULL
);

-- ========================
-- QUIZ RESULTS
-- ========================
CREATE TABLE quiz_results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT NOT NULL,
  student_id INT NOT NULL,
  score INT NOT NULL,
  taken_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  UNIQUE KEY unique_quiz_result (quiz_id, student_id)
);

-- ========================
-- EXAMS
-- ========================
CREATE TABLE exams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lesson_id INT NOT NULL,
    grading_period_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    max_score DECIMAL(5,2) NOT NULL DEFAULT 100,
    time_limit_minutes INT NULL, -- NULL means no time limit
    attempts_allowed INT DEFAULT 1,
    display_mode ENUM('all', 'per_page', 'single') DEFAULT 'all',
    open_at DATETIME NOT NULL,
    close_at DATETIME NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    FOREIGN KEY (grading_period_id) REFERENCES grading_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================
-- EXAM QUESTIONS
-- ========================
CREATE TABLE exam_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'checkbox', 'text') NOT NULL,
    score DECIMAL(5,2) NOT NULL DEFAULT 1,
    order_number INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
);

-- ========================
-- EXAM QUESTION CHOICES
-- ========================
CREATE TABLE exam_choices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question_id INT NOT NULL,
    choice_text TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    order_number INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES exam_questions(id) ON DELETE CASCADE
);

-- ========================
-- EXAM ATTEMPTS
-- ========================
CREATE TABLE exam_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    score DECIMAL(5,2) DEFAULT 0,
    max_score DECIMAL(5,2) NOT NULL,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    time_taken INT DEFAULT 0, -- in seconds
    answers JSON,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================
-- ACTIVITIES
-- ========================
CREATE TABLE activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject_id INT NOT NULL,
  grading_period_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT,
  activity_file VARCHAR(255),
  allow_from DATE,
  due_date DATE,
  cutoff_date DATE,
  reminder_date DATE,
  deduction_percent DECIMAL(5,2) DEFAULT 0,
  status ENUM('active','inactive','missed','completed','pending') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (grading_period_id) REFERENCES grading_periods(id) ON DELETE CASCADE
);

-- ========================
-- ACTIVITY SUBMISSIONS
-- ========================
CREATE TABLE activity_submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  activity_id INT NOT NULL,
  student_id INT NOT NULL,
  file_path VARCHAR(255),
  submission_link TEXT,
  submission_text TEXT,
  status ENUM('submitted', 'unsubmitted') DEFAULT 'submitted',
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  UNIQUE KEY unique_submission (activity_id, student_id)
);

-- ========================
-- ATTENDANCE
-- ========================
CREATE TABLE attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  subject_id INT NOT NULL,
  attendance_date DATE NOT NULL,
  status ENUM('present','absent','late','excused') DEFAULT 'present',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  UNIQUE KEY unique_attendance (student_id, subject_id, attendance_date)
);

-- ========================
-- INTERVENTIONS
-- ========================
CREATE TABLE interventions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  subject_id INT NOT NULL,
  notes TEXT,
  notify_teacher TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- ========================
-- ANNOUNCEMENTS
-- ========================
CREATE TABLE announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ========================
-- GRADES
-- ========================
CREATE TABLE grades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  subject_id INT NOT NULL,
  semester_id INT NOT NULL,
  grading_period_id INT NOT NULL,
  activity_score DECIMAL(5,2) DEFAULT 0,
  quiz_score DECIMAL(5,2) DEFAULT 0,
  exam_score DECIMAL(5,2) DEFAULT 0,
  period_grade DECIMAL(5,2) DEFAULT 0,
  status ENUM('pass','fail','pending') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
  FOREIGN KEY (grading_period_id) REFERENCES grading_periods(id) ON DELETE CASCADE,
  UNIQUE KEY unique_grade (student_id, subject_id, semester_id, grading_period_id)
);

-- ========================
-- FINAL GRADES
-- ========================
CREATE TABLE final_grades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  subject_id INT NOT NULL,
  semester_id INT NOT NULL,
  prelim_grade DECIMAL(5,2) DEFAULT 0,
  midterm_grade DECIMAL(5,2) DEFAULT 0,
  finals_grade DECIMAL(5,2) DEFAULT 0,
  final_grade DECIMAL(5,2) DEFAULT 0,
  status ENUM('pass','fail','pending') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
  UNIQUE KEY unique_final_grade (student_id, subject_id, semester_id)
);

-- ========================
-- ACTIVITY GRADES
-- ========================
CREATE TABLE activity_grades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  submission_id INT NOT NULL,
  score DECIMAL(5,2) NOT NULL,
  max_score DECIMAL(5,2) NOT NULL,
  comments TEXT,
  graded_by INT NULL,
  graded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (submission_id) REFERENCES activity_submissions(id) ON DELETE CASCADE,
  FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ========================
-- SAMPLE DATA
-- ========================

-- USERS (3 students, 1 teacher, 1 admin)
INSERT INTO users (first_name, last_name, email, password, role) VALUES
('Juan', 'Dela Cruz', 'juan@student.com', MD5('student1'), 'student'),
('Maria', 'Santos', 'maria@student.com', MD5('student2'), 'student'),
('Pedro', 'Reyes', 'pedro@student.com', MD5('student3'), 'student'),
('Ana', 'Lopez', 'ana@teacher.com', MD5('teacher1'), 'teacher'),
('Admin', 'One', 'admin@lms.com', MD5('admin1'), 'admin');

-- STUDENTS
INSERT INTO students (user_id) VALUES
(1), (2), (3);

-- TEACHER
INSERT INTO teachers (user_id, department) VALUES
(4, 'Computer Science');

-- SUBJECT (only Computer Programming 1 for BSIT 1st Year)
INSERT INTO subjects (name, description) VALUES
('Computer Programming 1', 'Introduction to programming concepts');

-- SEMESTER (1st Semester 2025-2026)
INSERT INTO semesters (name, academic_year, start_date, end_date, status) VALUES
('1st Semester', '2025-2026', '2025-08-01', '2025-12-15', 'active');

-- GRADING PERIODS (Prelim, Midterm, Finals)
INSERT INTO grading_periods (semester_id, name, start_date, end_date, weight_percent, status) VALUES
(1, 'prelim', '2025-08-01', '2025-09-30', 30.00, 'completed'),
(1, 'midterm', '2025-10-01', '2025-11-15', 30.00, 'active'),
(1, 'finals', '2025-11-16', '2025-12-15', 40.00, 'pending');

-- ENROLLMENTS (all students enrolled in Computer Programming 1)
INSERT INTO enrollments (student_id, subject_id) VALUES
(1, 1), (2, 1), (3, 1);

-- LESSONS
INSERT INTO lessons (subject_id, title, content) VALUES
(1, 'Intro to C Programming', 'Variables, loops, and conditionals'),
(1, 'Functions in C', 'Defining and calling functions'),
(1, 'Arrays in C', 'Basics of arrays and indexing'),
(1, 'Pointers in C', 'Understanding pointers and memory management'),
(1, 'File Handling in C', 'Reading and writing files'),
(1, 'Data Structures', 'Linked lists, stacks, and queues');

-- QUIZZES (Prelim Period)
INSERT INTO quizzes (lesson_id, grading_period_id, title, description, max_score, time_limit_minutes, attempts_allowed, display_mode, open_at, close_at) VALUES
(1, 1, 'Prelim Quiz 1: Variables and Data Types', 'Test your understanding of C variables and data types', 100, 30, 1, 'all', '2024-08-20 08:00:00', '2025-12-25 23:59:59'),
(2, 1, 'Prelim Quiz 2: Functions', 'Quiz on C functions and parameters', 100, 20, 1, 'single', '2024-09-01 08:00:00', '2025-12-25 23:59:59');

-- QUIZZES (Midterm Period)
INSERT INTO quizzes (lesson_id, grading_period_id, title, description, max_score, time_limit_minutes, attempts_allowed, display_mode, open_at, close_at) VALUES
(3, 2, 'Midterm Quiz 1: Arrays', 'Test your knowledge of arrays and indexing', 100, 25, 1, 'per_page', '2024-10-20 08:00:00', '2025-12-25 23:59:59'),
(4, 2, 'Midterm Quiz 2: Pointers', 'Quiz on pointers and memory management', 100, 30, 1, 'all', '2024-11-01 08:00:00', '2025-12-25 23:59:59');

-- QUIZZES (Finals Period)
INSERT INTO quizzes (lesson_id, grading_period_id, title, description, max_score, time_limit_minutes, attempts_allowed, display_mode, open_at, close_at) VALUES
(5, 3, 'Finals Quiz 1: File Handling', 'Test your understanding of file I/O operations', 100, 25, 1, 'all', '2025-12-01 08:00:00', '2025-12-05 23:59:59'),
(6, 3, 'Finals Quiz 2: Data Structures', 'Comprehensive quiz on data structures', 100, 35, 1, 'single', '2025-12-08 08:00:00', '2025-12-12 23:59:59');

-- QUIZ QUESTIONS
INSERT INTO quiz_questions (quiz_id, question_text, question_type, score) VALUES
-- Prelim Quiz 1: Variables and Data Types
(1, 'What is the correct way to declare an integer variable in C?', 'multiple_choice', 10),
(1, 'Which of the following are valid data types in C? (Select all that apply)', 'checkbox', 15),
(1, 'Explain the difference between int and float data types in C.', 'text', 25),

-- Prelim Quiz 2: Functions
(2, 'What is the return type of the main() function in C?', 'multiple_choice', 10),
(2, 'Write a simple function that adds two numbers.', 'text', 30),

-- Midterm Quiz 1: Arrays
(3, 'How do you declare an array of 10 integers in C?', 'multiple_choice', 10),
(3, 'What is the index of the first element in a C array?', 'multiple_choice', 10),

-- Midterm Quiz 2: Pointers  
(4, 'What does the & operator do in C?', 'multiple_choice', 15),
(4, 'Explain how pointers work in C with an example.', 'text', 25);

-- QUIZ CHOICES
INSERT INTO quiz_choices (question_id, choice_text, is_correct) VALUES
-- Question 1 choices (Prelim Quiz 1)
(1, 'int x;', 1),
(1, 'integer x;', 0),
(1, 'Int x;', 0),
(1, 'int x = ;', 0),

-- Question 2 choices (Prelim Quiz 1) 
(2, 'int', 1),
(2, 'float', 1),
(2, 'char', 1),
(2, 'string', 0),

-- Question 4 choices (Prelim Quiz 2)
(4, 'int', 1),
(4, 'void', 0),
(4, 'char', 0),
(4, 'float', 0),

-- Question 6 choices (Midterm Quiz 1)
(6, 'int arr[10];', 1),
(6, 'int arr(10);', 0),
(6, 'array int[10];', 0),
(6, 'int[10] arr;', 0),

-- Question 7 choices (Midterm Quiz 1)
(7, '0', 1),
(7, '1', 0),
(7, '-1', 0),
(7, 'Depends on compiler', 0),

-- Question 8 choices (Midterm Quiz 2)
(8, 'Returns the address of a variable', 1),
(8, 'Returns the value of a variable', 0),
(8, 'Declares a pointer', 0),
(8, 'Dereferences a pointer', 0);

-- QUIZ ATTEMPTS
INSERT INTO quiz_attempts (quiz_id, student_id, attempt_number, started_at, finished_at, score, max_score, status) VALUES
(1, 1, 1, '2025-08-22 10:00:00', '2025-08-22 10:25:00', 85, 100, 'submitted'),
(2, 2, 1, '2025-09-02 14:00:00', '2025-09-02 14:18:00', 78, 100, 'submitted'),
(3, 3, 1, '2025-10-22 09:30:00', '2025-10-22 09:50:00', 92, 100, 'submitted');

-- EXAMS (Prelim Period)
INSERT INTO exams (lesson_id, grading_period_id, title, description, max_score, time_limit_minutes, attempts_allowed, display_mode, open_at, close_at, created_by) VALUES
(1, 1, 'Prelim Exam: C Programming Fundamentals', 'Comprehensive exam covering variables, data types, and basic functions', 150, 120, 1, 'all', '2024-08-25 08:00:00', '2025-12-25 23:59:59', 4),
(2, 1, 'Prelim Practical Exam: Programming Implementation', 'Hands-on programming exam for practical skills assessment', 100, 90, 1, 'single', '2024-09-05 08:00:00', '2025-12-25 23:59:59', 4);

-- EXAMS (Midterm Period)
INSERT INTO exams (lesson_id, grading_period_id, title, description, max_score, time_limit_minutes, attempts_allowed, display_mode, open_at, close_at, created_by) VALUES
(3, 2, 'Midterm Exam: Arrays and Pointers', 'Comprehensive exam on array operations and pointer concepts', 150, 120, 1, 'per_page', '2024-10-25 08:00:00', '2025-12-25 23:59:59', 4),
(4, 2, 'Midterm Practical Exam: Advanced Programming', 'Advanced programming techniques and memory management', 100, 90, 1, 'single', '2024-11-05 08:00:00', '2025-12-25 23:59:59', 4);

-- EXAMS (Finals Period)
INSERT INTO exams (lesson_id, grading_period_id, title, description, max_score, time_limit_minutes, attempts_allowed, display_mode, open_at, close_at, created_by) VALUES
(5, 3, 'Final Exam: Comprehensive C Programming', 'Final comprehensive exam covering all semester topics', 200, 150, 1, 'all', '2025-12-10 08:00:00', '2025-12-15 23:59:59', 4),
(6, 3, 'Final Project Defense Exam', 'Oral and practical exam for final project defense', 100, 60, 1, 'single', '2025-12-16 08:00:00', '2025-12-20 23:59:59', 4);

-- EXAM QUESTIONS (Prelim Exam 1)
INSERT INTO exam_questions (exam_id, question_text, question_type, score, order_number) VALUES
(1, 'What is the correct way to declare an integer variable in C?', 'multiple_choice', 10, 1),
(1, 'Which of the following are valid data types in C? (Select all that apply)', 'checkbox', 15, 2),
(1, 'Explain the difference between a variable and a constant in C programming.', 'text', 25, 3),
(1, 'What is the output of the following code?\n\n```c\nint x = 5;\nint y = ++x;\nprintf("%d %d", x, y);\n```', 'multiple_choice', 10, 4),
(1, 'Write a simple C function that takes two integers as parameters and returns their sum.', 'text', 30, 5);

-- EXAM QUESTIONS (Prelim Practical Exam)
INSERT INTO exam_questions (exam_id, question_text, question_type, score, order_number) VALUES
(2, 'Write a complete C program that asks the user for their name and age, then displays a greeting message.', 'text', 50, 1),
(2, 'Create a function that calculates the factorial of a given number.', 'text', 50, 2);

-- EXAM CHOICES (Prelim Exam 1 - Question 1)
INSERT INTO exam_choices (question_id, choice_text, is_correct, order_number) VALUES
(1, 'int x;', 1, 1),
(1, 'integer x;', 0, 2),
(1, 'declare int x;', 0, 3),
(1, 'var int x;', 0, 4);

-- EXAM CHOICES (Prelim Exam 1 - Question 2)
INSERT INTO exam_choices (question_id, choice_text, is_correct, order_number) VALUES
(2, 'int', 1, 1),
(2, 'float', 1, 2),
(2, 'char', 1, 3),
(2, 'string', 0, 4),
(2, 'boolean', 0, 5);

-- EXAM CHOICES (Prelim Exam 1 - Question 4)
INSERT INTO exam_choices (question_id, choice_text, is_correct, order_number) VALUES
(4, '6 6', 1, 1),
(4, '5 6', 0, 2),
(4, '6 5', 0, 3),
(4, '5 5', 0, 4);

-- EXAM ATTEMPTS (Sample student attempts)
INSERT INTO exam_attempts (exam_id, student_id, score, max_score, started_at, completed_at, time_taken, answers) VALUES
(1, 1, 125.50, 150, '2024-08-26 09:00:00', '2024-08-26 10:45:00', 6300, '{}'),
(1, 2, 98.75, 150, '2024-08-26 10:00:00', '2024-08-26 11:30:00', 5400, '{}'),
(2, 3, 85.00, 100, '2024-09-06 14:00:00', '2024-09-06 15:15:00', 4500, '{}');

-- -- ACTIVITIES (Prelim Period)
INSERT INTO activities (subject_id, grading_period_id, title, description, activity_file, allow_from, due_date, cutoff_date, reminder_date, deduction_percent, status) VALUES
(1, 1, 'Prelim Activity 1: Hello World Program', 'Write your first C program that displays "Hello World"', 'hello_world_template.c', '2025-08-05', '2025-08-12', '2025-08-14', '2025-08-10', 5.00, 'completed'),
(1, 1, 'Prelim Activity 2: Variables and Input', 'Create a program that accepts user input and displays it', 'variables_input_template.c', '2025-08-15', '2025-08-22', '2025-08-24', '2025-08-20', 5.00, 'completed'),
(1, 1, 'Prelim Activity 3: Basic Functions', 'Write a function to calculate the area of a circle', 'circle_area_template.c', '2025-08-25', '2025-09-02', '2025-09-04', '2025-08-30', 5.00, 'completed');

-- ACTIVITIES (Midterm Period)
INSERT INTO activities (subject_id, grading_period_id, title, description, activity_file, allow_from, due_date, cutoff_date, reminder_date, deduction_percent, status) VALUES
(1, 2, 'Midterm Activity 1: Array Operations', 'Create a program that demonstrates array operations', 'array_operations_template.c', '2025-10-05', '2025-10-12', '2025-10-14', '2025-10-10', 5.00, 'active'),
(1, 2, 'Midterm Activity 2: Pointer Basics', 'Write a program using pointers to swap two numbers', 'pointer_swap_template.c', '2025-10-15', '2025-10-22', '2025-10-24', '2025-10-20', 5.00, 'active'),
(1, 2, 'Midterm Activity 3: String Manipulation', 'Create a program that manipulates strings using pointers', 'string_manipulation_template.c', '2025-10-25', '2025-11-02', '2025-11-04', '2025-10-30', 5.00, 'pending');

-- ACTIVITIES (Finals Period)
INSERT INTO activities (subject_id, grading_period_id, title, description, activity_file, allow_from, due_date, cutoff_date, reminder_date, deduction_percent, status) VALUES
(1, 3, 'Finals Activity 1: File I/O', 'Create a program that reads from and writes to files', 'file_io_template.c', '2025-11-20', '2025-11-27', '2025-11-29', '2025-11-25', 5.00, 'pending'),
(1, 3, 'Finals Activity 2: Linked List Implementation', 'Implement a basic linked list with insert and delete operations', 'linked_list_template.c', '2025-11-30', '2025-12-07', '2025-12-09', '2025-12-05', 5.00, 'pending'),
(1, 3, 'Finals Activity 3: Final Project', 'Create a comprehensive C program demonstrating all concepts learned', 'final_project_template.c', '2025-12-01', '2025-12-12', '2025-12-14', '2025-12-10', 10.00, 'pending');

-- ACTIVITY SUBMISSIONS
INSERT INTO activity_submissions (activity_id, student_id, submission_link, submission_text, file_path, status, submitted_at) VALUES
(1, 1, 'https://drive.google.com/file/d/sample1', 'Hello World program completed', 'DELACRUZ_2025-01-27_14-30-25.c', 'submitted', '2025-08-11 10:30:00'),
(2, 2, 'https://github.com/maria/variables-input', 'Variables and input assignment', 'SANTOS_2025-01-27_15-45-30.c', 'submitted', '2025-08-21 14:15:00'),
(4, 1, 'https://drive.google.com/file/d/sample4', 'Array operations completed', 'DELACRUZ_2025-01-27_16-20-15.c', 'submitted', '2025-10-11 09:45:00'),
(5, 3, NULL, 'Pointer basics assignment submitted', 'REYES_2025-01-27_11-10-45.c', 'submitted', '2025-10-20 16:30:00');

-- -- ATTENDANCE
-- INSERT INTO attendance (student_id, subject_id, attendance_date, status) VALUES
-- (1, 1, '2025-09-01', 'present'),
-- (2, 1, '2025-09-01', 'absent'),
-- (3, 1, '2025-09-01', 'late');

-- -- INTERVENTIONS
-- INSERT INTO interventions (student_id, subject_id, notes, notify_teacher) VALUES
-- (1, 1, 'Needs improvement in quizzes', 1),
-- (2, 1, 'Absent multiple times', 1),
-- (3, 1, 'Doing well, keep it up', 0);

-- -- ANNOUNCEMENTS
-- INSERT INTO announcements (title, message, created_by) VALUES
-- ('Welcome to Computer Programming 1', 'Welcome to our Computer Programming 1 class! This semester we will be learning the fundamentals of C programming. Please make sure to attend all classes and submit assignments on time.', 4),
-- ('Assignment 1 Due Date', 'Reminder: Assignment 1 (Hello World Program) is due on September 5th. Late submissions will have a 5% deduction per day.', 4),
-- ('Quiz Schedule Update', 'The first quiz on Variables and Data Types has been scheduled for next week. Please review your notes and practice exercises.', 4);

-- -- SAMPLE GRADES (Prelim Period - Completed)
-- INSERT INTO grades (student_id, subject_id, semester_id, grading_period_id, activity_score, quiz_score, exam_score, period_grade, status) VALUES
-- (1, 1, 1, 1, 85.50, 78.00, 82.00, 81.50, 'pass'),
-- (2, 1, 1, 1, 72.00, 65.00, 70.00, 69.00, 'fail'),
-- (3, 1, 1, 1, 92.00, 88.00, 90.00, 90.00, 'pass');

-- -- SAMPLE GRADES (Midterm Period - Active)
-- INSERT INTO grades (student_id, subject_id, semester_id, grading_period_id, activity_score, quiz_score, exam_score, period_grade, status) VALUES
-- (1, 1, 1, 2, 88.00, 82.00, 85.00, 85.00, 'pass'),
-- (2, 1, 1, 2, 75.00, 70.00, 72.00, 72.33, 'pass'),
-- (3, 1, 1, 2, 95.00, 92.00, 94.00, 93.67, 'pass');

-- -- SAMPLE GRADES (Finals Period - Pending)
-- INSERT INTO grades (student_id, subject_id, semester_id, grading_period_id, activity_score, quiz_score, exam_score, period_grade, status) VALUES
-- (1, 1, 1, 3, 0.00, 0.00, 0.00, 0.00, 'pending'),
-- (2, 1, 1, 3, 0.00, 0.00, 0.00, 0.00, 'pending'),
-- (3, 1, 1, 3, 0.00, 0.00, 0.00, 0.00, 'pending');

-- -- SAMPLE FINAL GRADES (Calculated from all periods)
-- INSERT INTO final_grades (student_id, subject_id, semester_id, prelim_grade, midterm_grade, finals_grade, final_grade, status) VALUES
-- (1, 1, 1, 81.50, 85.00, 0.00, 0.00, 'pending'),
-- (2, 1, 1, 69.00, 72.33, 0.00, 0.00, 'pending'),
-- (3, 1, 1, 90.00, 93.67, 0.00, 0.00, 'pending');