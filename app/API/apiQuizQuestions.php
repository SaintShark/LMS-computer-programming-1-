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
require_once __DIR__ . '/../Permissions.php';

// Check if user can manage quizzes
if (!Permission::canManageQuizzes()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied - Teachers/Admins only']);
    exit();
}

$userId = $_SESSION['user']['id'];

header('Content-Type: application/json');

try {
    $pdo = Db::getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'get_questions':
                $quizId = (int)($_GET['quiz_id'] ?? 0);
                
                if ($quizId <= 0) {
                    throw new Exception('Invalid quiz ID');
                }
                
                // Get questions with their choices
                $stmt = $pdo->prepare("
                    SELECT 
                        qq.id,
                        qq.quiz_id,
                        qq.question_text,
                        qq.question_type,
                        qq.score
                    FROM quiz_questions qq
                    WHERE qq.quiz_id = ?
                    ORDER BY qq.id ASC
                ");
                $stmt->execute([$quizId]);
                $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get choices for each question
                foreach ($questions as &$question) {
                    $stmt = $pdo->prepare("
                        SELECT id, choice_text, is_correct
                        FROM quiz_choices
                        WHERE question_id = ?
                        ORDER BY id ASC
                    ");
                    $stmt->execute([$question['id']]);
                    $question['choices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                
                echo json_encode(['success' => true, 'data' => $questions]);
                break;
                
            case 'get_question':
                $id = (int)($_GET['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid question ID');
                }
                
                $stmt = $pdo->prepare("
                    SELECT * FROM quiz_questions WHERE id = ?
                ");
                $stmt->execute([$id]);
                $question = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$question) {
                    throw new Exception('Question not found');
                }
                
                // Get choices
                $stmt = $pdo->prepare("
                    SELECT * FROM quiz_choices WHERE question_id = ? ORDER BY id ASC
                ");
                $stmt->execute([$id]);
                $question['choices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $question]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create_question':
                // Validate required fields
                $requiredFields = ['quiz_id', 'question_text', 'question_type', 'score'];
                foreach ($requiredFields as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception("Field '{$field}' is required");
                    }
                }
                
                $quizId = (int)$_POST['quiz_id'];
                $questionText = trim($_POST['question_text']);
                $questionType = $_POST['question_type'];
                $score = (int)$_POST['score'];
                
                // Validate question type
                $validTypes = ['multiple_choice', 'checkbox', 'text'];
                if (!in_array($questionType, $validTypes)) {
                    throw new Exception('Invalid question type');
                }
                
                // Start transaction
                $pdo->beginTransaction();
                
                try {
                    // Insert question
                    $stmt = $pdo->prepare("
                        INSERT INTO quiz_questions (quiz_id, question_text, question_type, score)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$quizId, $questionText, $questionType, $score]);
                    $questionId = $pdo->lastInsertId();
                    
                    // Insert choices if applicable
                    if ($questionType === 'multiple_choice' || $questionType === 'checkbox') {
                        $choices = $_POST['choices'] ?? [];
                        
                        // Handle correct choices for both question types
                        $correctChoices = [];
                        if ($questionType === 'multiple_choice') {
                            $correctChoices = isset($_POST['correct_choice']) ? [$_POST['correct_choice']] : [];
                        } else {
                            $correctChoices = $_POST['correct_choice'] ?? [];
                        }
                        
                        if (empty($choices)) {
                            throw new Exception('Choices are required for this question type');
                        }
                        
                        foreach ($choices as $index => $choiceText) {
                            if (!empty(trim($choiceText))) {
                                $isCorrect = in_array((string)$index, $correctChoices) ? 1 : 0;
                                
                                $stmt = $pdo->prepare("
                                    INSERT INTO quiz_choices (question_id, choice_text, is_correct)
                                    VALUES (?, ?, ?)
                                ");
                                $stmt->execute([$questionId, trim($choiceText), $isCorrect]);
                            }
                        }
                    }
                    
                    $pdo->commit();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Question created successfully',
                        'question_id' => $questionId
                    ]);
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break;
                
            case 'update_question':
                $id = (int)($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid question ID');
                }
                
                // Validate required fields
                $requiredFields = ['question_text', 'question_type', 'score'];
                foreach ($requiredFields as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception("Field '{$field}' is required");
                    }
                }
                
                $questionText = trim($_POST['question_text']);
                $questionType = $_POST['question_type'];
                $score = (int)$_POST['score'];
                
                // Validate question type
                $validTypes = ['multiple_choice', 'checkbox', 'text'];
                if (!in_array($questionType, $validTypes)) {
                    throw new Exception('Invalid question type');
                }
                
                // Start transaction
                $pdo->beginTransaction();
                
                try {
                    // Update question
                    $stmt = $pdo->prepare("
                        UPDATE quiz_questions 
                        SET question_text = ?, question_type = ?, score = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$questionText, $questionType, $score, $id]);
                    
                    // Delete existing choices
                    $stmt = $pdo->prepare("DELETE FROM quiz_choices WHERE question_id = ?");
                    $stmt->execute([$id]);
                    
                    // Insert new choices if applicable
                    if ($questionType === 'multiple_choice' || $questionType === 'checkbox') {
                        $choices = $_POST['choices'] ?? [];
                        
                        // Handle correct choices for both question types
                        $correctChoices = [];
                        if ($questionType === 'multiple_choice') {
                            $correctChoices = isset($_POST['correct_choice']) ? [$_POST['correct_choice']] : [];
                        } else {
                            $correctChoices = $_POST['correct_choice'] ?? [];
                        }
                        
                        if (!empty($choices)) {
                            foreach ($choices as $index => $choiceText) {
                                if (!empty(trim($choiceText))) {
                                    $isCorrect = in_array((string)$index, $correctChoices) ? 1 : 0;
                                    
                                    $stmt = $pdo->prepare("
                                        INSERT INTO quiz_choices (question_id, choice_text, is_correct)
                                        VALUES (?, ?, ?)
                                    ");
                                    $stmt->execute([$id, trim($choiceText), $isCorrect]);
                                }
                            }
                        }
                    }
                    
                    $pdo->commit();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Question updated successfully'
                    ]);
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break;
                
            case 'delete_question':
                $id = (int)($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception('Invalid question ID');
                }
                
                // Start transaction
                $pdo->beginTransaction();
                
                try {
                    // Delete choices first (foreign key constraint)
                    $stmt = $pdo->prepare("DELETE FROM quiz_choices WHERE question_id = ?");
                    $stmt->execute([$id]);
                    
                    // Delete question
                    $stmt = $pdo->prepare("DELETE FROM quiz_questions WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    if ($stmt->rowCount() === 0) {
                        throw new Exception('Question not found');
                    }
                    
                    $pdo->commit();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Question deleted successfully'
                    ]);
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
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
?>
