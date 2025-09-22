<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include required classes
require_once __DIR__ . '/../Db.php';
require_once __DIR__ . '/../Permissions.php';
require_once __DIR__ . '/../Exams.php';

// Check permissions - only students can take exams
if (!Permission::canTakeQuizzes() || Permission::canManageQuizzes()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Get action from request
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $pdo = Db::getConnection();
    $exams = new Exams();
    $user = $_SESSION['user'];

    switch ($action) {
        case 'get_exam_data':
            handleGetExamData($exams, $pdo, $user);
            break;
            
        case 'save_answer':
            handleSaveAnswer($pdo, $user);
            break;
            
        case 'submit_exam':
            handleSubmitExam($pdo, $user);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log("API Take Exam Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

/**
 * Get exam data and questions for student
 */
function handleGetExamData($exams, $pdo, $user) {
    $examId = $_GET['exam_id'] ?? null;
    if (!$examId) {
        echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
        return;
    }

    try {
        // Check if exam is available for student
        $availabilityCheck = $exams->isExamAvailable($examId, $user['id']);
        if (!$availabilityCheck['success']) {
            echo json_encode($availabilityCheck);
            return;
        }

        // Get exam details with student info
        $examResult = $exams->getExamForStudent($examId, $user['id']);
        if (!$examResult['success']) {
            echo json_encode($examResult);
            return;
        }

        $exam = $examResult['data'];

        // Get exam questions and choices
        $questionsSQL = "SELECT eq.*, 
                         GROUP_CONCAT(
                             JSON_OBJECT(
                                 'id', ec.id,
                                 'choice_text', ec.choice_text,
                                 'order_number', ec.order_number
                             ) ORDER BY ec.order_number SEPARATOR '|||'
                         ) as choices_json
                         FROM exam_questions eq
                         LEFT JOIN exam_choices ec ON eq.id = ec.question_id
                         WHERE eq.exam_id = :exam_id
                         GROUP BY eq.id
                         ORDER BY eq.order_number";
        
        $stmt = $pdo->prepare($questionsSQL);
        $stmt->execute(['exam_id' => $examId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process questions and choices
        foreach ($questions as &$question) {
            $question['choices'] = [];
            if ($question['choices_json']) {
                $choicesArray = explode('|||', $question['choices_json']);
                foreach ($choicesArray as $choiceJson) {
                    if ($choiceJson && $choiceJson !== 'null') {
                        $choice = json_decode($choiceJson, true);
                        if ($choice) {
                            $question['choices'][] = $choice;
                        }
                    }
                }
            }
            unset($question['choices_json']);
        }

        // Check if there are questions
        if (empty($questions)) {
            echo json_encode(['success' => false, 'message' => 'This exam has no questions yet. Please contact your teacher.']);
            return;
        }

        echo json_encode([
            'success' => true,
            'exam' => $exam,
            'questions' => $questions
        ]);

    } catch (PDOException $e) {
        error_log("Get exam data error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}

/**
 * Save student's answer (auto-save functionality)
 */
function handleSaveAnswer($pdo, $user) {
    $examId = $_POST['exam_id'] ?? null;
    $questionId = $_POST['question_id'] ?? null;
    $answer = $_POST['answer'] ?? null;

    if (!$examId || !$questionId) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        return;
    }

    try {
        // Check if there's an active attempt
        $attemptSQL = "SELECT id FROM exam_attempts 
                       WHERE exam_id = :exam_id AND student_id = :student_id AND completed_at IS NULL
                       ORDER BY started_at DESC LIMIT 1";
        $stmt = $pdo->prepare($attemptSQL);
        $stmt->execute([
            'exam_id' => $examId,
            'student_id' => $user['id']
        ]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attempt) {
            // Create new attempt
            $createAttemptSQL = "INSERT INTO exam_attempts (exam_id, student_id, max_score, started_at, answers) 
                                 SELECT :exam_id, :student_id, max_score, NOW(), JSON_OBJECT()
                                 FROM exams WHERE id = :exam_id";
            $stmt = $pdo->prepare($createAttemptSQL);
            $stmt->execute([
                'exam_id' => $examId,
                'student_id' => $user['id']
            ]);
            $attemptId = $pdo->lastInsertId();
        } else {
            $attemptId = $attempt['id'];
        }

        // Update answers in the attempt
        $updateSQL = "UPDATE exam_attempts 
                      SET answers = JSON_SET(COALESCE(answers, JSON_OBJECT()), CONCAT('$.', :question_id), :answer)
                      WHERE id = :attempt_id";
        $stmt = $pdo->prepare($updateSQL);
        $stmt->execute([
            'question_id' => $questionId,
            'answer' => $answer,
            'attempt_id' => $attemptId
        ]);

        echo json_encode(['success' => true, 'message' => 'Answer saved']);

    } catch (PDOException $e) {
        error_log("Save answer error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to save answer']);
    }
}

/**
 * Submit exam and calculate score
 */
function handleSubmitExam($pdo, $user) {
    $examId = $_POST['exam_id'] ?? null;
    $answers = $_POST['answers'] ?? '{}';
    $timeTaken = intval($_POST['time_taken'] ?? 0);

    if (!$examId) {
        echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
        return;
    }

    try {
        $pdo->beginTransaction();

        // Get or create the attempt
        $attemptSQL = "SELECT id FROM exam_attempts 
                       WHERE exam_id = :exam_id AND student_id = :student_id AND completed_at IS NULL
                       ORDER BY started_at DESC LIMIT 1";
        $stmt = $pdo->prepare($attemptSQL);
        $stmt->execute([
            'exam_id' => $examId,
            'student_id' => $user['id']
        ]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attempt) {
            // Create new attempt
            $createAttemptSQL = "INSERT INTO exam_attempts (exam_id, student_id, max_score, started_at, answers) 
                                 SELECT :exam_id, :student_id, max_score, NOW(), :answers
                                 FROM exams WHERE id = :exam_id";
            $stmt = $pdo->prepare($createAttemptSQL);
            $stmt->execute([
                'exam_id' => $examId,
                'student_id' => $user['id'],
                'answers' => $answers
            ]);
            $attemptId = $pdo->lastInsertId();
        } else {
            $attemptId = $attempt['id'];
        }

        // Calculate score
        $score = calculateExamScore($pdo, $examId, json_decode($answers, true));

        // Update attempt with final score and completion
        $updateAttemptSQL = "UPDATE exam_attempts 
                             SET score = :score, completed_at = NOW(), time_taken = :time_taken, answers = :answers
                             WHERE id = :attempt_id";
        $stmt = $pdo->prepare($updateAttemptSQL);
        $stmt->execute([
            'score' => $score,
            'time_taken' => $timeTaken,
            'answers' => $answers,
            'attempt_id' => $attemptId
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Exam submitted successfully',
            'score' => $score,
            'attempt_id' => $attemptId
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Submit exam error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to submit exam']);
    }
}

/**
 * Calculate exam score based on answers
 */
function calculateExamScore($pdo, $examId, $studentAnswers) {
    $totalScore = 0;

    try {
        // Get all questions with correct answers
        $questionsSQL = "SELECT eq.id, eq.question_type, eq.score,
                         GROUP_CONCAT(
                             CASE WHEN ec.is_correct = 1 THEN ec.id END
                         ) as correct_choice_ids
                         FROM exam_questions eq
                         LEFT JOIN exam_choices ec ON eq.id = ec.question_id
                         WHERE eq.exam_id = :exam_id
                         GROUP BY eq.id";
        
        $stmt = $pdo->prepare($questionsSQL);
        $stmt->execute(['exam_id' => $examId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($questions as $question) {
            $questionId = $question['id'];
            $questionType = $question['question_type'];
            $questionScore = floatval($question['score']);
            $correctChoiceIds = $question['correct_choice_ids'] ? explode(',', $question['correct_choice_ids']) : [];

            if (!isset($studentAnswers[$questionId])) {
                continue; // No answer provided
            }

            $studentAnswer = $studentAnswers[$questionId];

            if ($questionType === 'text') {
                // Text questions need manual grading, but give full points if answered
                if (!empty($studentAnswer)) {
                    $totalScore += $questionScore;
                }
            } elseif ($questionType === 'multiple_choice') {
                // Single correct answer
                if (in_array($studentAnswer, $correctChoiceIds)) {
                    $totalScore += $questionScore;
                }
            } elseif ($questionType === 'checkbox') {
                // Multiple correct answers - all must be selected, none incorrect
                if (is_array($studentAnswer) && !empty($correctChoiceIds)) {
                    $correctSelected = array_intersect($studentAnswer, $correctChoiceIds);
                    $incorrectSelected = array_diff($studentAnswer, $correctChoiceIds);
                    
                    // Award points if all correct answers selected and no incorrect ones
                    if (count($correctSelected) === count($correctChoiceIds) && empty($incorrectSelected)) {
                        $totalScore += $questionScore;
                    }
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Calculate score error: " . $e->getMessage());
    }

    return $totalScore;
}
?>
