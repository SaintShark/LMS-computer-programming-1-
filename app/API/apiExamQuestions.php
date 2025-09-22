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

// Check permissions - only teachers/admins can manage questions
if (!Permission::canManageQuizzes()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Get action from request
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $pdo = Db::getConnection();
    
    switch ($action) {
        case 'get_questions':
            handleGetQuestions($pdo);
            break;
            
        case 'get_question':
            handleGetQuestion($pdo);
            break;
            
        case 'create_question':
            handleCreateQuestion($pdo);
            break;
            
        case 'update_question':
            handleUpdateQuestion($pdo);
            break;
            
        case 'delete_question':
            handleDeleteQuestion($pdo);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log("API Exam Questions Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

/**
 * Get all questions for an exam
 */
function handleGetQuestions($pdo) {
    $examId = $_GET['exam_id'] ?? null;
    if (!$examId) {
        echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
        return;
    }

    try {
        $sql = "SELECT eq.*, 
                GROUP_CONCAT(
                    CONCAT(ec.id, ':', ec.choice_text, ':', ec.is_correct) 
                    ORDER BY ec.order_number SEPARATOR '|'
                ) as choices
                FROM exam_questions eq
                LEFT JOIN exam_choices ec ON eq.id = ec.question_id
                WHERE eq.exam_id = :exam_id
                GROUP BY eq.id
                ORDER BY eq.order_number";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['exam_id' => $examId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process choices for each question
        foreach ($questions as &$question) {
            $question['choices_array'] = [];
            if ($question['choices']) {
                $choicesData = explode('|', $question['choices']);
                foreach ($choicesData as $choice) {
                    $parts = explode(':', $choice, 3);
                    if (count($parts) >= 3) {
                        $question['choices_array'][] = [
                            'id' => $parts[0],
                            'text' => $parts[2],
                            'is_correct' => $parts[1] === '1'
                        ];
                    }
                }
            }
            unset($question['choices']); // Remove the concatenated string
        }
        
        if ($questions) {
            echo json_encode(['success' => true, 'data' => $questions]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No questions found']);
        }
    } catch (PDOException $e) {
        error_log("Get exam questions error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}

/**
 * Get single question details
 */
function handleGetQuestion($pdo) {
    $questionId = $_GET['id'] ?? null;
    if (!$questionId) {
        echo json_encode(['success' => false, 'message' => 'Question ID is required']);
        return;
    }

    try {
        // Get question details
        $questionSQL = "SELECT * FROM exam_questions WHERE id = :id";
        $stmt = $pdo->prepare($questionSQL);
        $stmt->execute(['id' => $questionId]);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$question) {
            echo json_encode(['success' => false, 'message' => 'Question not found']);
            return;
        }
        
        // Get choices if question type requires them
        if (in_array($question['question_type'], ['multiple_choice', 'checkbox'])) {
            $choicesSQL = "SELECT * FROM exam_choices WHERE question_id = :question_id ORDER BY order_number";
            $stmt = $pdo->prepare($choicesSQL);
            $stmt->execute(['question_id' => $questionId]);
            $question['choices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $question['choices'] = [];
        }
        
        echo json_encode(['success' => true, 'data' => $question]);
    } catch (PDOException $e) {
        error_log("Get exam question error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}

/**
 * Create new question
 */
function handleCreateQuestion($pdo) {
    $examId = $_POST['quiz_id'] ?? $_POST['exam_id'] ?? null;
    $questionText = $_POST['question_text'] ?? '';
    $questionType = $_POST['question_type'] ?? '';
    $score = floatval($_POST['score'] ?? 1);
    
    if (!$examId || !$questionText || !$questionType) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        return;
    }

    try {
        $pdo->beginTransaction();
        
        // Get next order number
        $orderSQL = "SELECT COALESCE(MAX(order_number), 0) + 1 as next_order FROM exam_questions WHERE exam_id = :exam_id";
        $stmt = $pdo->prepare($orderSQL);
        $stmt->execute(['exam_id' => $examId]);
        $orderNumber = $stmt->fetch(PDO::FETCH_ASSOC)['next_order'];
        
        // Insert question
        $questionSQL = "INSERT INTO exam_questions (exam_id, question_text, question_type, score, order_number) 
                        VALUES (:exam_id, :question_text, :question_type, :score, :order_number)";
        $stmt = $pdo->prepare($questionSQL);
        $stmt->execute([
            'exam_id' => $examId,
            'question_text' => $questionText,
            'question_type' => $questionType,
            'score' => $score,
            'order_number' => $orderNumber
        ]);
        
        $questionId = $pdo->lastInsertId();
        
        // Handle choices for multiple choice and checkbox questions
        if (in_array($questionType, ['multiple_choice', 'checkbox'])) {
            $choices = $_POST['choices'] ?? [];
            $correctAnswers = $_POST['correct_answers'] ?? [];
            
            if (empty($choices)) {
                throw new Exception('Choices are required for this question type');
            }
            
            foreach ($choices as $index => $choiceText) {
                if (!empty(trim($choiceText))) {
                    $isCorrect = in_array($index, $correctAnswers) ? 1 : 0;
                    
                    $choiceSQL = "INSERT INTO exam_choices (question_id, choice_text, is_correct, order_number) 
                                  VALUES (:question_id, :choice_text, :is_correct, :order_number)";
                    $stmt = $pdo->prepare($choiceSQL);
                    $stmt->execute([
                        'question_id' => $questionId,
                        'choice_text' => trim($choiceText),
                        'is_correct' => $isCorrect,
                        'order_number' => $index + 1
                    ]);
                }
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Question created successfully', 'id' => $questionId]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Create exam question error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Update existing question
 */
function handleUpdateQuestion($pdo) {
    $questionId = $_POST['id'] ?? null;
    $questionText = $_POST['question_text'] ?? '';
    $questionType = $_POST['question_type'] ?? '';
    $score = floatval($_POST['score'] ?? 1);
    
    if (!$questionId || !$questionText || !$questionType) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        return;
    }

    try {
        $pdo->beginTransaction();
        
        // Update question
        $questionSQL = "UPDATE exam_questions 
                        SET question_text = :question_text, question_type = :question_type, score = :score, updated_at = NOW()
                        WHERE id = :id";
        $stmt = $pdo->prepare($questionSQL);
        $stmt->execute([
            'id' => $questionId,
            'question_text' => $questionText,
            'question_type' => $questionType,
            'score' => $score
        ]);
        
        // Delete existing choices
        $deleteChoicesSQL = "DELETE FROM exam_choices WHERE question_id = :question_id";
        $stmt = $pdo->prepare($deleteChoicesSQL);
        $stmt->execute(['question_id' => $questionId]);
        
        // Handle choices for multiple choice and checkbox questions
        if (in_array($questionType, ['multiple_choice', 'checkbox'])) {
            $choices = $_POST['choices'] ?? [];
            $correctAnswers = $_POST['correct_answers'] ?? [];
            
            if (empty($choices)) {
                throw new Exception('Choices are required for this question type');
            }
            
            foreach ($choices as $index => $choiceText) {
                if (!empty(trim($choiceText))) {
                    $isCorrect = in_array($index, $correctAnswers) ? 1 : 0;
                    
                    $choiceSQL = "INSERT INTO exam_choices (question_id, choice_text, is_correct, order_number) 
                                  VALUES (:question_id, :choice_text, :is_correct, :order_number)";
                    $stmt = $pdo->prepare($choiceSQL);
                    $stmt->execute([
                        'question_id' => $questionId,
                        'choice_text' => trim($choiceText),
                        'is_correct' => $isCorrect,
                        'order_number' => $index + 1
                    ]);
                }
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Question updated successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Update exam question error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Delete question
 */
function handleDeleteQuestion($pdo) {
    $questionId = $_POST['id'] ?? null;
    if (!$questionId) {
        echo json_encode(['success' => false, 'message' => 'Question ID is required']);
        return;
    }

    try {
        $pdo->beginTransaction();
        
        // Delete choices first (foreign key constraint)
        $deleteChoicesSQL = "DELETE FROM exam_choices WHERE question_id = :question_id";
        $stmt = $pdo->prepare($deleteChoicesSQL);
        $stmt->execute(['question_id' => $questionId]);
        
        // Delete question
        $deleteQuestionSQL = "DELETE FROM exam_questions WHERE id = :id";
        $stmt = $pdo->prepare($deleteQuestionSQL);
        $result = $stmt->execute(['id' => $questionId]);
        
        if ($stmt->rowCount() > 0) {
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Question deleted successfully']);
        } else {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Question not found']);
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Delete exam question error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}
?>
