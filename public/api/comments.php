<?php
// JSON/form 댓글 API.
declare(strict_types=1);

require __DIR__ . '/../../src/comments.php';

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $pdo = db();

    if ($method === 'GET') {
        $id = $_GET['id'] ?? null;
        if ($id !== null && $id !== '') {
            $comment = comments_get($pdo, comments_positive_id($id));
            if ($comment === null) {
                throw new CommentsNotFoundException('comment not found');
            }
            json_response(['comment' => $comment]);
        }

        $limit = isset($_GET['limit']) ? comments_positive_id($_GET['limit']) : 30;
        $page = isset($_GET['page']) ? comments_positive_id($_GET['page']) : 1;
        $offset = ($page - 1) * $limit;
        json_response([
            'comments' => comments_list($pdo, $limit, $offset),
            'page' => $page,
            'limit' => min(100, $limit),
            'total' => comments_count($pdo),
        ]);
    }

    if ($method === 'POST') {
        $comment = comments_create($pdo, request_input());
        json_response(['comment' => $comment], 201);
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        $input = request_input();
        $id = comments_positive_id($input['id'] ?? ($_GET['id'] ?? null));
        $comment = comments_update($pdo, $id, $input);
        json_response(['comment' => $comment]);
    }

    if ($method === 'DELETE') {
        $input = request_input();
        $id = comments_positive_id($input['id'] ?? ($_GET['id'] ?? null));
        comments_delete($pdo, $id, (string)($input['password'] ?? ''));
        json_response(['deleted' => true]);
    }

    json_response(['error' => 'method not allowed'], 405);
} catch (CommentsNotFoundException $e) {
    json_response(['error' => $e->getMessage()], 404);
} catch (CommentsForbiddenException $e) {
    json_response(['error' => $e->getMessage()], 403);
} catch (InvalidArgumentException $e) {
    json_response(['error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log($e);
    json_response(['error' => 'internal server error'], 500);
}

function request_input(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $raw = file_get_contents('php://input') ?: '';

    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('invalid JSON body');
        }
        return $decoded;
    }

    if ($_POST !== []) {
        return $_POST;
    }

    parse_str($raw, $parsed);
    return is_array($parsed) ? $parsed : [];
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
