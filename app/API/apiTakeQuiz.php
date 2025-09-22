<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if logged in
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../Db.php';
require_once __DIR__ . '/../Students.php';
require_once __DIR__ . '/../Permissions.php';

// Check if user can take quizzes (students only)
if (!Permission::canTakeQuizzes()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied - Students only']);
    exit();
}

$userId = $_SESSION['user']['id'];

header('Content-Type: application/json');

try {
    $pdo = Db::getConnection();
    $studentsModel = new Students($pdo);
    
    // Get student ID from user ID
    $studentId = $studentsModel->getStudentIdByUserId($userId);
    
    if (!$studentId) {
        // Create student record if it doesn't exist
        try {
            $stmt = $pdo->prepare("INSERT INTO students (user_id, course, year_level) VALUES (?, 'BSIT', '1st Year')");
            $stmt->execute([$userId]);
            $studentId = $pdo->lastInsertId();
        } catch (Exception $e) {
            throw new Exception('Failed to create student record: ' . $e->getMessage());
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'get_quiz_data':
                $quizId = (int)($_GET['quiz_id'] ?? 0);
                error_log("Attempting to load quiz data for quiz ID: " . $quizId);
                
                if ($quizId <= 0) {
                    error_log("Invalid quiz ID provided: " . $_GET['quiz_id']);
                    throw new Exception('Invalid quiz ID');
                }
                
                // Get quiz information
                $stmt = $pdo->prepare("
                    SELECT 
                        q.*,
                        l.title as lesson_title,
                        gp.name as grading_period_name
                    FROM quizzes q
                    LEFT JOIN lessons l ON q.lesson_id = l.id
                    LEFT JOIN grading_periods gp ON q.grading_period_id = gp.id
                    WHERE q.id = ? AND l.subject_id = 1
                ");
                $stmt->execute([$quizId]);
                $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
                error_log("Quiz query result: " . json_encode($quiz));
                
                if (!$quiz) {
                    error_log("Quiz not found for ID: " . $quizId);
                    throw new Exception('Quiz not found');
                }
                
                // Check if quiz is available
                try {
                    $now = new DateTime();
                    $openAt = new DateTime($quiz['open_at']);
                    $closeAt = new DateTime($quiz['close_at']);
                    
                    error_log("Quiz availability check - Now: " . $now->format('Y-m-d H:i:s') . 
                             ", Open: " . $openAt->format('Y-m-d H:i:s') . 
                             ", Close: " . $closeAt->format('Y-m-d H:i:s'));
                    
                    if ($now < $openAt) {
                        throw new Exception('Quiz is not yet open. Opens on ' . $openAt->format('Y-m-d H:i:s'));
                    }
                    
                    if ($now > $closeAt) {
                        throw new Exception('Quiz has closed. Closed on ' . $closeAt->format('Y-m-d H:i:s'));
                    }
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'Quiz') === 0) {
                        throw $e; // Re-throw our custom messages
                    }
                    error_log("Date parsing error: " . $e->getMessage());
                    throw new Exception('Invalid quiz dates');
                }
                
                // Check if student has already taken the quiz
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as attempt_count
                    FROM quiz_attempts 
                    WHERE quiz_id = ? AND student_id = ? AND status = 'submitted'
                ");
                $stmt->execute([$quizId, $studentId]);
                $attemptCount = $stmt->fetchColumn();
                
                if ($attemptCount >= $quiz['attempts_allowed']) {
                    throw new Exception('You have already used all your attempts for this quiz');
                }
                
                // Get quiz questions with choices
                $stmt = $pdo->prepare("
                    SELECT 
                        qq.id,
                        qq.question_text,
                        qq.question_type,
                        qq.score
                    FROM quiz_questions qq
                    WHERE qq.quiz_id = ?
                    ORDER BY qq.id ASC
                ");
                $stmt->execute([$quizId]);
                $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("Found " . count($questions) . " questions for quiz ID: " . $quizId);
                
                if (empty($questions)) {
                    throw new Exception('This quiz has no questions yet. Please contact your teacher.');
                }
                
                // Get choices for each question
                foreach ($questions as &$question) {
                    if ($question['question_type'] !== 'text') {
                        $stmt = $pdo->prepare("
                            SELECT id, choice_text
                            FROM quiz_choices
                            WHERE question_id = ?
                            ORDER BY id ASC
                        ");
                        $stmt->execute([$question['id']]);
                        $question['choices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (empty($question['choices'])) {
                            error_log("No choices found for question ID: " . $question['id']);
                        }
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'quiz' => $quiz,
                    'questions' => $questions
                ]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'start_attempt':
                $quizId = (int)($_POST['quiz_id'] ?? 0);
                
                if ($quizId <= 0) {
                    throw new Exception('Invalid quiz ID');
                }
                
                // Check if there's an existing in-progress attempt
                $stmt = $pdo->prepare("
                    SELECT id FROM quiz_attempts 
                    WHERE quiz_id = ? AND student_id = ? AND status = 'in_progress'
                ");
                $stmt->execute([$quizId, $studentId]);
                $existingAttempt = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingAttempt) {
                    // Return existing attempt
                    echo json_encode([
                        'success' => true,
                        'attempt_id' => $existingAttempt['id'],
                        'message' => 'Continuing existing attempt'
                    ]);
                } else {
                    // Create new attempt
                    $attemptNumber = 1;
                    $stmt = $pdo->prepare("
                        SELECT MAX(attempt_number) as max_attempt
                        FROM quiz_attempts 
                        WHERE quiz_id = ? AND student_id = ?
                    ");
                    $stmt->execute([$quizId, $studentId]);
                    $maxAttempt = $stmt->fetchColumn();
                    if ($maxAttempt) {
                        $attemptNumber = $maxAttempt + 1;
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO quiz_attempts (quiz_id, student_id, attempt_number, started_at, status)
                        VALUES (?, ?, ?, NOW(), 'in_progress')
                    ");
                    $stmt->execute([$quizId, $studentId, $attemptNumber]);
                    $attemptId = $pdo->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'attempt_id' => $attemptId,
                        'message' => 'Quiz attempt started'
                    ]);
                }
                break;
                
            case 'save_answer':
                $attemptId = (int)($_POST['attempt_id'] ?? 0);
                $questionId = (int)($_POST['question_id'] ?? 0);
                $answer = $_POST['answer'] ?? '';
                
                if ($attemptId <= 0 || $questionId <= 0) {
                    throw new Exception('Invalid attempt or question ID');
                }
                
                // Get question type to determine how to save answer
                $stmt = $pdo->prepare("SELECT question_type FROM quiz_questions WHERE id = ?");
                $stmt->execute([$questionId]);
                $questionType = $stmt->fetchColumn();
                
                if ($questionType === 'text') {
                    // Save text answer
                    $stmt = $pdo->prepare("
                        INSERT INTO quiz_answers (attempt_id, question_id, answer_text)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE answer_text = VALUES(answer_text)
                    ");
                    $stmt->execute([$attemptId, $questionId, $answer]);
                } else {
                    // Save choice answer(s)
                    // First delete existing answers for this question
                    $stmt = $pdo->prepare("DELETE FROM quiz_answers WHERE attempt_id = ? AND question_id = ?");
                    $stmt->execute([$attemptId, $questionId]);
                    
                    if ($questionType === 'multiple_choice') {
                        // Single choice
                        if (!empty($answer)) {
                            $stmt = $pdo->prepare("
                                INSERT INTO quiz_answers (attempt_id, question_id, choice_id)
                                VALUES (?, ?, ?)
                            ");
                            $stmt->execute([$attemptId, $questionId, $answer]);
                        }
                    } else if ($questionType === 'checkbox') {
                        // Multiple choices
                        $choices = json_decode($answer, true);
                        if (is_array($choices)) {
                            foreach ($choices as $choiceId) {
                                $stmt = $pdo->prepare("
                                    INSERT INTO quiz_answers (attempt_id, question_id, choice_id)
                                    VALUES (?, ?, ?)
                                ");
                                $stmt->execute([$attemptId, $questionId, $choiceId]);
                            }
                        }
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Answer saved']);
                break;
                
            case 'submit_quiz':
                $attemptId = (int)($_POST['attempt_id'] ?? 0);
                
                if ($attemptId <= 0) {
                    throw new Exception('Invalid attempt ID');
                }
                
                // Calculate score
                $score = calculateQuizScore($pdo, $attemptId);
                
                // Update attempt status
                $stmt = $pdo->prepare("
                    UPDATE quiz_attempts 
                    SET finished_at = NOW(), score = ?, max_score = (
                        SELECT SUM(qq.score) 
                        FROM quiz_questions qq 
                        WHERE qq.quiz_id = (
                            SELECT quiz_id FROM quiz_attempts WHERE id = ?
                        )
                    ), status = 'submitted'
                    WHERE id = ?
                ");
                $stmt->execute([$score, $attemptId, $attemptId]);
                
                // Get final result
                $stmt = $pdo->prepare("
                    SELECT score, max_score FROM quiz_attempts WHERE id = ?
                ");
                $stmt->execute([$attemptId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Quiz submitted successfully',
                    'result' => $result
                ]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } else {
        throw new Exception('Invalid request method');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Calculate Quiz Score
 */
function calculateQuizScore($pdo, $attemptId) {
    $totalScore = 0;
    
    // Get all questions for this quiz attempt
    $stmt = $pdo->prepare("
        SELECT 
            qq.id as question_id,
            qq.question_type,
            qq.score as max_points
        FROM quiz_questions qq
        JOIN quiz_attempts qa ON qq.quiz_id = qa.quiz_id
        WHERE qa.id = ?
    ");
    $stmt->execute([$attemptId]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($questions as $question) {
        if ($question['question_type'] === 'text') {
            // Text questions need manual grading, skip for now
            continue;
        }
        
        // Get student's answers
        $stmt = $pdo->prepare("
            SELECT choice_id FROM quiz_answers 
            WHERE attempt_id = ? AND question_id = ?
        ");
        $stmt->execute([$attemptId, $question['question_id']]);
        $studentChoices = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get correct answers
        $stmt = $pdo->prepare("
            SELECT id FROM quiz_choices 
            WHERE question_id = ? AND is_correct = 1
        ");
        $stmt->execute([$question['question_id']]);
        $correctChoices = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Check if answer is correct
        if ($question['question_type'] === 'multiple_choice') {
            // Single correct answer
            if (count($studentChoices) === 1 && in_array($studentChoices[0], $correctChoices)) {
                $totalScore += $question['max_points'];
            }
        } else if ($question['question_type'] === 'checkbox') {
            // Multiple correct answers - all must be selected, no extra selections
            sort($studentChoices);
            sort($correctChoices);
            if ($studentChoices === $correctChoices) {
                $totalScore += $question['max_points'];
            }
        }
    }
    
    return $totalScore;
}
?>
